<?php

declare(strict_types=1);

/**
 * Teste do exporter do cutover — rode com: php tests/cutover_export_test.php
 *
 * Usa SQLite em memória com um esquema mínimo espelhando as tabelas reais do
 * MVP (employees/payroll_periods/payrolls/payroll_items/social_charges/
 * employee_dependents), prova que o exporter produz EXATAMENTE o formato que o
 * `cutover:import` da plataforma consome e que o dinheiro (centavos) é
 * preservado. Não toca no Postgres nem na base real.
 */

require __DIR__.'/../app/services/Cutover/CutoverExporter.php';

use App\Services\Cutover\CutoverExporter;

$passed = 0;
$failed = 0;

function check(string $name, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        echo "  ✓ {$name}\n";
    } else {
        $failed++;
        echo "  ✗ {$name}\n    esperado: ".var_export($expected, true)."\n    obtido:   ".var_export($actual, true)."\n";
    }
}

// ── Fixture: esquema mínimo do MVP em SQLite ─────────────────────────────────
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE companies (id INTEGER PRIMARY KEY, name TEXT, cnpj TEXT)');
$db->exec('CREATE TABLE employees (id INTEGER PRIMARY KEY, company_id INT, registration TEXT, full_name TEXT,
            cpf TEXT, birth_date TEXT, status TEXT, hired_at TEXT, salary_cents INT)');
$db->exec('CREATE TABLE employee_dependents (id INTEGER PRIMARY KEY, employee_id INT, name TEXT, cpf TEXT,
            relationship TEXT, birth_date TEXT)');
$db->exec('CREATE TABLE payroll_periods (id INTEGER PRIMARY KEY, company_id INT, competency TEXT, status TEXT)');
$db->exec('CREATE TABLE payrolls (id INTEGER PRIMARY KEY, period_id INT, employee_id INT, kind TEXT,
            gross_cents INT, deductions_cents INT, net_cents INT,
            inss_base_cents INT, irrf_base_cents INT, fgts_base_cents INT)');
$db->exec('CREATE TABLE payroll_items (id INTEGER PRIMARY KEY, payroll_id INT, rubric_code TEXT, description TEXT,
            reference REAL, amount_cents INT, type TEXT)');
$db->exec('CREATE TABLE social_charges (id INTEGER PRIMARY KEY, payroll_id INT, type TEXT, base_cents INT,
            rate REAL, amount_cents INT)');

$db->exec("INSERT INTO companies (id, name, cnpj) VALUES (1, 'Acme LTDA', '12.345.678/0001-99')");
$db->exec("INSERT INTO employees (id, company_id, registration, full_name, cpf, birth_date, status, hired_at, salary_cents)
           VALUES (1, 1, '0001', 'Maria da Silva', '123.456.789-01', '1990-05-10', 'active', '2024-01-01', 520000)");
$db->exec("INSERT INTO employee_dependents (employee_id, name, cpf, relationship, birth_date)
           VALUES (1, 'Filho', NULL, 'filho', '2015-01-01')");
$db->exec("INSERT INTO payroll_periods (id, company_id, competency, status) VALUES (1, 1, '2026-07', 'closed')");
$db->exec("INSERT INTO payrolls (id, period_id, employee_id, kind, gross_cents, deductions_cents, net_cents,
            inss_base_cents, irrf_base_cents, fgts_base_cents)
           VALUES (1, 1, 1, 'payslip', 520000, 89549, 430451, 520000, 520000, 520000)");
$db->exec("INSERT INTO payroll_items (payroll_id, rubric_code, description, reference, amount_cents, type)
           VALUES (1, '1000', 'Salário Base', NULL, 520000, 'earning')");
$db->exec("INSERT INTO social_charges (payroll_id, type, base_cents, rate, amount_cents)
           VALUES (1, 'fgts', 520000, 8.0, 41600)");

// ── Export ───────────────────────────────────────────────────────────────────
$export = (new CutoverExporter($db))->export(1, 'acme', 'Grupo Acme');

echo "Exporter do cutover (MVP → cutover:import)\n";

check('tenant.slug', 'acme', $export['tenant']['slug']);
check('company.name', 'Acme LTDA', $export['company']['name']);
check('1 colaborador', 1, count($export['employees']));

$emp = $export['employees'][0];
check('matrícula → registration_number', '0001', $emp['registration_number']);
check('salário vira contrato (centavos)', 520000, $emp['contracts'][0]['salary_cents']);
check('dependente conta p/ IRRF', true, $emp['dependents'][0]['counts_for_irrf']);

$period = $export['periods'][0];
check('competência fechada', 'closed', $period['status']);
$pay = $period['payrolls'][0];
check('líquido preservado (centavos)', 430451, $pay['net_cents']);
check('folha aponta a matrícula', '0001', $pay['registration_number']);
check('item da folha', '1000', $pay['items'][0]['rubric_code']);
check('encargo FGTS', 41600, $pay['charges'][0]['amount_cents']);

// O formato precisa sobreviver a um round-trip JSON (é assim que chega no import).
$roundTrip = json_decode((string) json_encode($export), true);
check('round-trip JSON preserva o líquido', 430451, $roundTrip['periods'][0]['payrolls'][0]['net_cents']);

echo "\n{$passed} passaram, {$failed} falharam.\n";
exit($failed === 0 ? 0 : 1);
