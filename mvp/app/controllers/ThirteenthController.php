<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Database;
use App\Services\AuditService;
use App\Services\Payroll\SpecialPayrollService;
use App\Services\Payroll\ThirteenthCalculator;
use App\Services\Payroll\TaxTableRepository;

/** 13º salário: 1ª parcela (até 30/11, sem descontos) e 2ª (até 20/12, tributada). */
class ThirteenthController
{
    public function __construct(private readonly SpecialPayrollService $special = new SpecialPayrollService)
    {
    }

    public function index(): void
    {
        Can::check('payroll:manage');
        $companyId = auth_user()['company_id'];
        $year = $this->year();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $installment = (int) ($_POST['installment'] ?? 0);
            if (in_array($installment, [1, 2], true)) {
                [$ok, $message] = $this->special->thirteenth($companyId, $year, $installment);
                AuditService::log('payroll.thirteenth', 'payroll_period', "{$year}/{$installment}", null, ['result' => $message]);
                flash($ok ? 'success' : 'error', $message);
            }
            redirect('decimo.php?year='.$year);
        }

        $db = Database::connection();

        // Elegíveis + avos previstos (≥15 dias no mês = 1 avo, referência 20/12)
        $tables = new TaxTableRepository;
        $t = $tables->forCompetency("{$year}-12");
        $calc = new ThirteenthCalculator($tables->buildInss($t), $tables->buildIrrf($t));

        $stmt = $db->prepare(
            "SELECT e.id, e.full_name, e.registration, e.hired_at, e.salary_cents, pos.title AS position_name
             FROM employees e LEFT JOIN positions pos ON pos.id = e.position_id
             WHERE e.company_id = :c AND e.status IN ('active', 'vacation') AND e.salary_cents IS NOT NULL
             ORDER BY e.full_name",
        );
        $stmt->execute(['c' => $companyId]);
        $eligible = array_map(function (array $e) use ($calc, $year): array {
            $e['months'] = $calc->months($e['hired_at'], "{$year}-12-20");

            return $e;
        }, $stmt->fetchAll());

        // Parcelas já calculadas no ano
        $stmt = $db->prepare(
            'SELECT t.employee_id, t.installment, t.months, t.payroll_id,
                    p.gross_cents, p.deductions_cents, p.net_cents, e.full_name
             FROM thirteenth_salary t
             JOIN payrolls p ON p.id = t.payroll_id
             JOIN employees e ON e.id = t.employee_id
             WHERE t.year = :y AND e.company_id = :c
             ORDER BY e.full_name, t.installment',
        );
        $stmt->execute(['y' => $year, 'c' => $companyId]);
        $calculated = ['1' => [], '2' => []];
        foreach ($stmt->fetchAll() as $row) {
            $calculated[(string) $row['installment']][] = $row;
        }

        view('thirteenth', [
            'year' => $year,
            'eligible' => $eligible,
            'calculated' => $calculated,
        ]);
    }

    private function year(): int
    {
        $year = (int) ($_GET['year'] ?? date('Y'));

        return ($year >= 2020 && $year <= 2100) ? $year : (int) date('Y');
    }
}
