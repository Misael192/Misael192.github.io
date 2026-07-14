<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Estrutura organizacional: filiais, departamentos, cargos, centros de
 * custo e escalas. CRUD simples e uniforme — as 5 entidades compartilham
 * o mesmo padrão de listagem/criação por empresa.
 */
class Structure extends Model
{
    protected string $table = 'branches';

    public function forCompany(int $companyId): array
    {
        return [
            'branches' => $this->select('SELECT * FROM branches WHERE company_id = :c ORDER BY name', ['c' => $companyId]),
            'departments' => $this->select(
                'SELECT d.*, b.name AS branch FROM departments d
                 LEFT JOIN branches b ON b.id = d.branch_id
                 WHERE d.company_id = :c ORDER BY d.name', ['c' => $companyId]),
            'positions' => $this->select('SELECT * FROM positions WHERE company_id = :c ORDER BY title', ['c' => $companyId]),
            'cost_centers' => $this->select('SELECT * FROM cost_centers WHERE company_id = :c ORDER BY code', ['c' => $companyId]),
            'work_shifts' => $this->select('SELECT * FROM work_shifts WHERE company_id = :c ORDER BY name', ['c' => $companyId]),
        ];
    }

    public function addBranch(int $companyId, string $name, ?string $city, ?string $state): void
    {
        $this->execute('INSERT INTO branches (company_id, name, city, state) VALUES (:c, :n, :city, :uf)',
            ['c' => $companyId, 'n' => $name, 'city' => $city, 'uf' => $state]);
    }

    public function addDepartment(int $companyId, string $name, ?int $branchId): void
    {
        $this->execute('INSERT INTO departments (company_id, name, branch_id) VALUES (:c, :n, :b)',
            ['c' => $companyId, 'n' => $name, 'b' => $branchId]);
    }

    public function addPosition(int $companyId, string $title, ?int $salaryCents): void
    {
        $this->execute('INSERT INTO positions (company_id, title, base_salary_cents) VALUES (:c, :t, :s)',
            ['c' => $companyId, 't' => $title, 's' => $salaryCents]);
    }

    public function addCostCenter(int $companyId, string $code, string $name): void
    {
        $this->execute('INSERT INTO cost_centers (company_id, code, name) VALUES (:c, :code, :n)',
            ['c' => $companyId, 'code' => $code, 'n' => $name]);
    }

    public function addWorkShift(int $companyId, string $name, int $weeklyHours, float $dailyHours): void
    {
        $this->execute('INSERT INTO work_shifts (company_id, name, weekly_hours, daily_hours) VALUES (:c, :n, :w, :d)',
            ['c' => $companyId, 'n' => $name, 'w' => $weeklyHours, 'd' => $dailyHours]);
    }
}
