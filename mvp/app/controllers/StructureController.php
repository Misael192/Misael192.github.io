<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Structure;
use App\Services\AuditService;

/** Estrutura organizacional: filiais, departamentos, cargos, CC e escalas. */
class StructureController
{
    public function __construct(private readonly Structure $structure = new Structure)
    {
    }

    public function index(): void
    {
        Can::check('structure:manage');
        $companyId = auth_user()['company_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $this->store($companyId);
        }

        view('structure', ['data' => $this->structure->forCompany($companyId)]);
    }

    private function store(int $companyId): void
    {
        $in = fn (string $k): ?string => trim((string) ($_POST[$k] ?? '')) ?: null;
        $type = $in('type');

        try {
            match ($type) {
                'branch' => $this->structure->addBranch($companyId, (string) $in('name'), $in('city'), $in('state')),
                'department' => $this->structure->addDepartment($companyId, (string) $in('name'), ((int) $_POST['branch_id']) ?: null),
                'position' => $this->structure->addPosition($companyId, (string) $in('name'),
                    $in('salary') ? (int) round(((float) str_replace(['.', ','], ['', '.'], $in('salary'))) * 100) : null),
                'cost_center' => $this->structure->addCostCenter($companyId, (string) $in('code'), (string) $in('name')),
                'work_shift' => $this->structure->addWorkShift($companyId, (string) $in('name'),
                    (int) ($_POST['weekly_hours'] ?? 44), (float) ($_POST['daily_hours'] ?? 8)),
                default => throw new \InvalidArgumentException('Tipo inválido'),
            };
            AuditService::log("structure.{$type}.create", $type, null, null, ['name' => $in('name') ?? $in('code')]);
            flash('success', 'Cadastro realizado.');
        } catch (\PDOException) {
            flash('error', 'Não foi possível salvar — verifique duplicidade.');
        }

        redirect('estrutura.php');
    }
}
