<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Auth;
use App\Models\Company;
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
        ]);
    }
}
