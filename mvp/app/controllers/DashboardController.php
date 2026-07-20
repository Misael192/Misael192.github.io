<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Auth;
use App\Models\Company;
use App\Models\Database;
use App\Models\Employee;
use App\Models\User;

class DashboardController
{
    public function index(): void
    {
        Auth::check();

        $employees = new Employee;

        view('dashboard', [
            'totalCompanies' => (new Company)->count(),
            'totalUsers' => (new User)->count(),
            'statusCounts' => $employees->statusCounts(),
            'recentEmployees' => $employees->recent(),
            'pending' => $this->pending(auth_user()['company_id']),
        ]);
    }

    /** Pendências que exigem ação do RH/DP hoje — cada card vira link direto. */
    private function pending(int $companyId): array
    {
        $count = function (string $sql) use ($companyId): int {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute(['c' => $companyId]);

            return (int) $stmt->fetchColumn();
        };

        return [
            'vacations' => $count("SELECT count(*) FROM vacations WHERE company_id = :c AND status = 'requested'"),
            'timeclock' => $count("SELECT count(*) FROM time_clock_records WHERE company_id = :c AND status = 'recorded'"),
            'admissions' => $count("SELECT count(*) FROM admissions WHERE company_id = :c AND status = 'in_progress'"),
        ];
    }
}
