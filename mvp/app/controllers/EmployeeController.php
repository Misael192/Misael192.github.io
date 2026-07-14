<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Employee;
use App\Models\Structure;
use App\Services\AuditService;

class EmployeeController
{
    public function __construct(private readonly Employee $employees = new Employee)
    {
    }

    /** GET: listagem · POST: cadastro completo. */
    public function index(): void
    {
        Can::check('employees:read');
        $companyId = auth_user()['company_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Can::check('employees:manage');
            Csrf::verify();
            $this->store($companyId);
        }

        view('employees', [
            'employees' => $this->employees->listForCompany($companyId),
            'structure' => (new Structure)->forCompany($companyId),
            'managers' => $this->employees->listForCompany($companyId),
        ]);
    }

    /** Ficha completa do colaborador (abas). */
    public function show(): void
    {
        Can::check('employees:read');
        $companyId = auth_user()['company_id'];

        $employee = $this->employees->findFull((int) ($_GET['id'] ?? 0), $companyId);
        if ($employee === null) {
            http_response_code(404);
            flash('error', 'Colaborador não encontrado.');
            redirect('colaboradores.php');
        }

        view('employee_show', ['emp' => $employee]);
    }

    private function store(int $companyId): void
    {
        $in = fn (string $k): ?string => trim((string) ($_POST[$k] ?? '')) ?: null;
        $id = fn (string $k): ?int => ((int) ($_POST[$k] ?? 0)) ?: null;

        if ($in('full_name') === null || $in('registration') === null || $in('hired_at') === null) {
            flash('error', 'Nome, matrícula e data de admissão são obrigatórios.');
            redirect('colaboradores.php');
        }
        if ($in('cpf') !== null && ! preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $in('cpf'))) {
            flash('error', 'CPF inválido — use o formato 000.000.000-00.');
            redirect('colaboradores.php');
        }
        if ($this->employees->registrationExists($companyId, $in('registration'))) {
            flash('error', 'Já existe colaborador com esta matrícula.');
            redirect('colaboradores.php');
        }

        // Foto (opcional): jpg/png até 2 MB, nome aleatório fora do docroot
        $photoPath = null;
        if (! empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $mime = mime_content_type($_FILES['photo']['tmp_name']);
            $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
            if ($ext === null || $_FILES['photo']['size'] > 2 * 1024 * 1024) {
                flash('error', 'Foto: apenas JPG/PNG até 2 MB.');
                redirect('colaboradores.php');
            }
            $photoPath = 'photos/'.bin2hex(random_bytes(16)).".{$ext}";
            @mkdir(STORAGE_PATH.'/uploads/photos', 0775, true);
            move_uploaded_file($_FILES['photo']['tmp_name'], STORAGE_PATH.'/uploads/'.$photoPath);
        }

        $salaryCents = $in('salary') !== null
            ? (int) round(((float) str_replace(['.', ','], ['', '.'], $in('salary'))) * 100)
            : null;

        $core = [
            'company_id' => $companyId,
            'registration' => $in('registration'),
            'full_name' => $in('full_name'),
            'cpf' => $in('cpf'), 'rg' => $in('rg'), 'rg_issuer' => $in('rg_issuer'),
            'birth_date' => $in('birth_date'), 'gender' => $in('gender'),
            'marital_status' => $in('marital_status'),
            'nationality' => $in('nationality') ?? 'Brasileira', 'birthplace' => $in('birthplace'),
            'pis' => $in('pis'), 'ctps' => $in('ctps'), 'voter_title' => $in('voter_title'),
            'reservist' => $in('reservist'), 'cnh' => $in('cnh'),
            'branch_id' => $id('branch_id'), 'department_id' => $id('department_id'),
            'position_id' => $id('position_id'), 'cost_center_id' => $id('cost_center_id'),
            'work_shift_id' => $id('work_shift_id'), 'manager_id' => $id('manager_id'),
            'contract_type' => $in('contract_type') ?? 'clt',
            'salary_cents' => $salaryCents,
            'hired_at' => $in('hired_at'),
            'status' => $in('status') ?? 'admission',
            'photo_path' => $photoPath,
            'position' => null, // legado da Fase 1; o vínculo agora é position_id
        ];

        $satellites = [
            'address' => ['zip_code' => $in('zip_code'), 'street' => $in('street'), 'number' => $in('number'),
                'district' => $in('district'), 'city' => $in('city'), 'state' => $in('state'), 'complement' => $in('complement')],
            'contact' => ['phone' => $in('phone'), 'mobile' => $in('mobile'), 'email' => $in('email')],
            'bank' => ['bank' => $in('bank'), 'agency' => $in('agency'), 'account' => $in('account'),
                'account_type' => $in('account_type') ?? 'corrente', 'pix_key' => $in('pix_key')],
            'dependent' => ['name' => $in('dep_name'), 'cpf' => $in('dep_cpf'),
                'relationship' => $in('dep_relationship') ?? 'filho(a)', 'birth_date' => $in('dep_birth_date')],
            'emergency' => ['name' => $in('emg_name'), 'relationship' => $in('emg_relationship'), 'phone' => $in('emg_phone')],
        ];

        $employeeId = $this->employees->createFull($core, $satellites, auth_user()['id']);

        AuditService::log('employee.create', 'employee', $employeeId, null, ['name' => $core['full_name'], 'registration' => $core['registration']]);
        flash('success', "Colaborador \"{$core['full_name']}\" admitido — checklist de admissão criado.");
        redirect("colaborador.php?id={$employeeId}");
    }
}
