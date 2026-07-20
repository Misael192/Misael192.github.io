<?php

/**
 * PeopleFlow API pública v1 — front controller (roteia por PATH_INFO, sem
 * exigir mod_rewrite: /api/v1/index.php/employees funciona em Apache e php -S).
 *
 * Autenticação: Authorization: Bearer pfk_…  ·  Escopos: read | write
 * Envelope: {"data": …, "meta": …} | {"error": {"code", "message"}}
 */
require __DIR__.'/../../../app/bootstrap.php';

use App\Models\Database;
use App\Services\Api\ApiAuth;

$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_SERVER['PATH_INFO'] ?? '/', '/');
$segments = $path === '' ? [] : explode('/', $path);
$resource = $segments[0] ?? '';
$id = isset($segments[1]) ? (int) $segments[1] : null;

// Spec pública (sem dados) — não exige chave
if ($method === 'GET' && $resource === 'openapi') {
    ApiAuth::json([
        'openapi' => '3.0.3',
        'info' => ['title' => 'PeopleFlow API', 'version' => '1.0.0'],
        'servers' => [['url' => '/api/v1/index.php']],
        'security' => [['bearerAuth' => []]],
        'paths' => [
            '/me' => ['get' => ['summary' => 'Empresa da chave e escopos']],
            '/employees' => ['get' => ['summary' => 'Lista colaboradores', 'parameters' => [['name' => 'status', 'in' => 'query']]]],
            '/employees/{id}' => ['get' => ['summary' => 'Ficha resumida do colaborador']],
            '/payrolls' => ['get' => ['summary' => 'Folhas da competência', 'parameters' => [['name' => 'competency', 'in' => 'query', 'required' => true]]]],
            '/vacations' => ['get' => ['summary' => 'Solicitações de férias', 'parameters' => [['name' => 'status', 'in' => 'query']]]],
            '/payroll-events' => ['post' => ['summary' => 'Lança evento de folha (comissão/bônus/desconto) — escopo write']],
        ],
    ]);
}

$key = ApiAuth::authenticate();
$companyId = (int) $key['company_id'];
$db = Database::connection();

match (true) {
    // ── GET /me ──────────────────────────────────────────────────────────────
    $method === 'GET' && $resource === 'me' => (function () use ($db, $companyId, $key) {
        $stmt = $db->prepare('SELECT id, name, trade_name, cnpj FROM companies WHERE id = :c');
        $stmt->execute(['c' => $companyId]);
        ApiAuth::json(['company' => $stmt->fetch(), 'key_name' => $key['name'],
            'scopes' => explode(',', $key['scopes'])]);
    })(),

    // ── GET /employees[/{id}] ────────────────────────────────────────────────
    $method === 'GET' && $resource === 'employees' && $id === null => (function () use ($db, $companyId) {
        $status = $_GET['status'] ?? null;
        $sql = 'SELECT e.id, e.registration, e.full_name, e.status, e.hired_at, e.terminated_at,
                       e.salary_cents, e.contract_type, pos.title AS position, d.name AS department
                FROM employees e
                LEFT JOIN positions pos ON pos.id = e.position_id
                LEFT JOIN departments d ON d.id = e.department_id
                WHERE e.company_id = :c'.($status !== null ? ' AND e.status = :s' : '').'
                ORDER BY e.full_name';
        $stmt = $db->prepare($sql);
        $stmt->execute(['c' => $companyId] + ($status !== null ? ['s' => $status] : []));
        $rows = $stmt->fetchAll();
        ApiAuth::json($rows, 200, ['count' => count($rows)]);
    })(),

    $method === 'GET' && $resource === 'employees' && $id !== null => (function () use ($db, $companyId, $id) {
        $stmt = $db->prepare(
            'SELECT e.id, e.registration, e.full_name, e.cpf, e.status, e.hired_at, e.terminated_at,
                    e.salary_cents, e.contract_type, pos.title AS position, d.name AS department,
                    (SELECT COUNT(*) FROM employee_dependents dep WHERE dep.employee_id = e.id) AS dependents
             FROM employees e
             LEFT JOIN positions pos ON pos.id = e.position_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE e.id = :id AND e.company_id = :c',
        );
        $stmt->execute(['id' => $id, 'c' => $companyId]);
        $employee = $stmt->fetch();
        $employee === false
            ? ApiAuth::error(404, 'not_found', 'Colaborador não encontrado.')
            : ApiAuth::json($employee);
    })(),

    // ── GET /payrolls?competency=YYYY-MM ─────────────────────────────────────
    $method === 'GET' && $resource === 'payrolls' => (function () use ($db, $companyId) {
        $competency = (string) ($_GET['competency'] ?? '');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competency)) {
            ApiAuth::error(422, 'invalid_competency', 'Informe ?competency=YYYY-MM.');
        }
        $stmt = $db->prepare(
            'SELECT p.id, p.employee_id, e.full_name, p.kind, pp.status AS period_status,
                    p.gross_cents, p.deductions_cents, p.net_cents
             FROM payrolls p
             JOIN payroll_periods pp ON pp.id = p.period_id
             JOIN employees e ON e.id = p.employee_id
             WHERE pp.company_id = :c AND pp.competency = :m
             ORDER BY e.full_name, p.kind',
        );
        $stmt->execute(['c' => $companyId, 'm' => $competency]);
        $rows = $stmt->fetchAll();
        ApiAuth::json($rows, 200, ['competency' => $competency, 'count' => count($rows)]);
    })(),

    // ── GET /vacations ───────────────────────────────────────────────────────
    $method === 'GET' && $resource === 'vacations' => (function () use ($db, $companyId) {
        $status = $_GET['status'] ?? null;
        $sql = 'SELECT v.id, v.employee_id, e.full_name, v.start_date, v.end_date, v.days,
                       v.sell_days, v.status, v.decided_at
                FROM vacations v JOIN employees e ON e.id = v.employee_id
                WHERE v.company_id = :c'.($status !== null ? ' AND v.status = :s' : '').'
                ORDER BY v.start_date DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute(['c' => $companyId] + ($status !== null ? ['s' => $status] : []));
        $rows = $stmt->fetchAll();
        ApiAuth::json($rows, 200, ['count' => count($rows)]);
    })(),

    // ── POST /payroll-events (write) — integrações lançam comissões/bônus ────
    $method === 'POST' && $resource === 'payroll-events' => (function () use ($db, $companyId, $key) {
        ApiAuth::requireScope($key, 'write');

        $body = json_decode(file_get_contents('php://input'), true);
        if (! is_array($body)) {
            ApiAuth::error(400, 'invalid_json', 'Corpo deve ser JSON.');
        }

        $employeeId = (int) ($body['employee_id'] ?? 0);
        $competency = (string) ($body['competency'] ?? '');
        $rubric = (string) ($body['rubric_code'] ?? '');
        $reference = isset($body['reference']) ? (float) $body['reference'] : null;
        $amount = isset($body['amount_cents']) ? (int) $body['amount_cents'] : null;

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $competency) || $employeeId < 1
            || $rubric === '' || ($reference === null && $amount === null)) {
            ApiAuth::error(422, 'validation', 'Campos: employee_id, competency (YYYY-MM), rubric_code e reference ou amount_cents.');
        }

        $check = $db->prepare('SELECT 1 FROM employees WHERE id = :e AND company_id = :c');
        $check->execute(['e' => $employeeId, 'c' => $companyId]);
        if ($check->fetch() === false) {
            ApiAuth::error(404, 'not_found', 'Colaborador não encontrado nesta empresa.');
        }
        $rubricCheck = $db->prepare('SELECT 1 FROM rubrics WHERE code = :r AND is_active');
        $rubricCheck->execute(['r' => $rubric]);
        if ($rubricCheck->fetch() === false) {
            ApiAuth::error(422, 'invalid_rubric', 'Rubrica inexistente ou inativa.');
        }
        $period = $db->prepare("SELECT 1 FROM payroll_periods WHERE company_id = :c AND competency = :m AND status = 'closed'");
        $period->execute(['c' => $companyId, 'm' => $competency]);
        if ($period->fetch() !== false) {
            ApiAuth::error(409, 'period_closed', "A competência {$competency} está fechada.");
        }

        $stmt = $db->prepare(
            'INSERT INTO payroll_events (company_id, employee_id, competency, rubric_code, reference, amount_cents, notes)
             VALUES (:c, :e, :m, :r, :ref, :a, :n) RETURNING id',
        );
        $stmt->execute(['c' => $companyId, 'e' => $employeeId, 'm' => $competency, 'r' => $rubric,
            'ref' => $reference, 'a' => $amount,
            'n' => 'API: '.substr((string) ($body['notes'] ?? $key['name']), 0, 150)]);

        ApiAuth::json(['id' => (int) $stmt->fetchColumn(), 'status' => 'created'], 201);
    })(),

    default => ApiAuth::error(404, 'route_not_found',
        'Rotas: GET /me, /employees[/{id}], /payrolls?competency=, /vacations, /openapi · POST /payroll-events.'),
};
