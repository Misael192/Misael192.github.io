<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Models\Employee;
use App\Services\AuditService;

/** Benefícios recorrentes por colaborador (VT, VA/VR, saúde, odonto…). */
class BenefitController
{
    private const TYPES = [
        'vt' => 'Vale Transporte', 'va' => 'Vale Alimentação', 'vr' => 'Vale Refeição',
        'saude' => 'Plano de Saúde', 'odonto' => 'Plano Odontológico',
        'seguro_vida' => 'Seguro de Vida', 'convenio' => 'Convênio',
    ];

    public function index(): void
    {
        Can::check('benefits:manage');
        $companyId = auth_user()['company_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            ($_POST['action'] ?? '') === 'deactivate' ? $this->deactivate($companyId) : $this->store($companyId);
        }

        $stmt = Database::connection()->prepare(
            'SELECT b.*, e.full_name FROM employee_benefits b
             JOIN employees e ON e.id = b.employee_id
             WHERE e.company_id = :c
             ORDER BY b.is_active DESC, e.full_name, b.type',
        );
        $stmt->execute(['c' => $companyId]);

        view('benefits_admin', [
            'benefits' => $stmt->fetchAll(),
            'employees' => (new Employee)->listForCompany($companyId),
            'types' => self::TYPES,
        ]);
    }

    private function store(int $companyId): void
    {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        $type = (string) ($_POST['type'] ?? '');
        $amount = (int) round(((float) str_replace(['.', ','], ['', '.'], (string) $_POST['amount'])) * 100);
        $sharePercent = $_POST['share_percent'] !== '' ? (float) $_POST['share_percent'] : null;
        $shareFixed = $_POST['share_fixed'] !== ''
            ? (int) round(((float) str_replace(['.', ','], ['', '.'], (string) $_POST['share_fixed'])) * 100)
            : null;

        if ($employeeId < 1 || ! isset(self::TYPES[$type]) || $amount <= 0) {
            flash('error', 'Informe colaborador, tipo e valor do benefício.');
            redirect('beneficios.php');
        }

        // Colaborador precisa ser da mesma empresa (isolamento multiempresa)
        $check = Database::connection()->prepare('SELECT 1 FROM employees WHERE id = :e AND company_id = :c');
        $check->execute(['e' => $employeeId, 'c' => $companyId]);
        if ($check->fetch() === false) {
            flash('error', 'Colaborador inválido.');
            redirect('beneficios.php');
        }

        Database::connection()->prepare(
            'INSERT INTO employee_benefits (employee_id, type, description, amount_cents,
                                            employee_share_percent, employee_share_cents)
             VALUES (:e, :t, :d, :a, :sp, :sf)',
        )->execute([
            'e' => $employeeId, 't' => $type, 'd' => self::TYPES[$type], 'a' => $amount,
            'sp' => $sharePercent, 'sf' => $shareFixed,
        ]);

        AuditService::log('benefit.assign', 'employee_benefit', $employeeId, null,
            ['type' => $type, 'amount_cents' => $amount]);
        flash('success', self::TYPES[$type].' atribuído — entra automaticamente na próxima folha.');
        redirect('beneficios.php');
    }

    private function deactivate(int $companyId): void
    {
        Database::connection()->prepare(
            'UPDATE employee_benefits SET is_active = FALSE, ends_on = CURRENT_DATE
             WHERE id = :id AND employee_id IN (SELECT id FROM employees WHERE company_id = :c)',
        )->execute(['id' => (int) ($_POST['benefit_id'] ?? 0), 'c' => $companyId]);

        AuditService::log('benefit.deactivate', 'employee_benefit', (int) $_POST['benefit_id']);
        flash('success', 'Benefício encerrado.');
        redirect('beneficios.php');
    }
}
