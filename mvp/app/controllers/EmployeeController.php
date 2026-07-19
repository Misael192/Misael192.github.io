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

    /** Ficha completa do colaborador (abas) + ações (checklist/reajuste/situação). */
    public function show(): void
    {
        Can::check('employees:read');
        $companyId = auth_user()['company_id'];
        $employeeId = (int) ($_GET['id'] ?? 0);

        $employee = $this->employees->findFull($employeeId, $companyId);
        if ($employee === null) {
            http_response_code(404);
            flash('error', 'Colaborador não encontrado.');
            redirect('colaboradores.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Can::check('employees:manage');
            Csrf::verify();
            match ($_POST['action'] ?? '') {
                'checklist' => $this->toggleChecklistItem($employee),
                'raise' => $this->applyRaise($employee),
                'status' => $this->changeStatus($employee),
                default => null,
            };
            redirect("colaborador.php?id={$employeeId}");
        }

        view('employee_show', ['emp' => $employee]);
    }

    /** Admissão digital: marca/desmarca item; tudo concluído → admissão completa. */
    private function toggleChecklistItem(array $employee): void
    {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $db = \App\Models\Database::connection();

        $db->prepare(
            'UPDATE admission_items SET is_done = NOT is_done, done_at = CASE WHEN is_done THEN NULL ELSE now() END
             WHERE id = :i AND admission_id = :a',
        )->execute(['i' => $itemId, 'a' => $employee['admission']['id']]);

        // Todos os itens concluídos → processo completo (+ ativa se em admissão)
        $stmt = $db->prepare('SELECT bool_and(is_done) FROM admission_items WHERE admission_id = :a');
        $stmt->execute(['a' => $employee['admission']['id']]);
        $allDone = $stmt->fetchColumn();

        // PDO serializa bool como '' no pgsql — o timestamp é decidido aqui, não no SQL
        $db->prepare('UPDATE admissions SET status = :s, completed_at = :done_at WHERE id = :a')
           ->execute(['s' => $allDone ? 'completed' : 'in_progress',
               'done_at' => $allDone ? date('Y-m-d H:i:sP') : null, 'a' => $employee['admission']['id']]);

        if ($allDone && $employee['status'] === 'admission') {
            $db->prepare("UPDATE employees SET status = 'active' WHERE id = :e")->execute(['e' => $employee['id']]);
            $db->prepare('INSERT INTO employee_status_history (employee_id, old_status, new_status, reason, changed_by)
                          VALUES (:e, :old, :new, :r, :by)')
               ->execute(['e' => $employee['id'], 'old' => 'admission', 'new' => 'active',
                   'r' => 'Admissão digital concluída', 'by' => auth_user()['id']]);
            flash('success', 'Checklist completo — colaborador ativado! 🎉');
        }

        AuditService::log('admission.checklist', 'admission_item', $itemId);
    }

    /** Reajuste salarial: atualiza salário + histórico imutável + auditoria. */
    private function applyRaise(array $employee): void
    {
        $newSalary = (int) round(((float) str_replace(['.', ','], ['', '.'], (string) $_POST['new_salary'])) * 100);
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: 'Reajuste';

        if ($newSalary <= 0) {
            flash('error', 'Informe o novo salário.');

            return;
        }

        $db = \App\Models\Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE employees SET salary_cents = :s WHERE id = :e')
               ->execute(['s' => $newSalary, 'e' => $employee['id']]);
            $db->prepare('INSERT INTO employee_salary_history (employee_id, old_salary_cents, new_salary_cents, reason, changed_by)
                          VALUES (:e, :old, :new, :r, :by)')
               ->execute(['e' => $employee['id'], 'old' => $employee['salary_cents'],
                   'new' => $newSalary, 'r' => $reason, 'by' => auth_user()['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        AuditService::log('employee.raise', 'employee', $employee['id'],
            ['salary_cents' => $employee['salary_cents']], ['salary_cents' => $newSalary, 'reason' => $reason]);
        flash('success', 'Reajuste aplicado e registrado no histórico.');
    }

    /** Mudança de situação com histórico + auditoria. */
    private function changeStatus(array $employee): void
    {
        $newStatus = (string) ($_POST['new_status'] ?? '');
        $reason = trim((string) ($_POST['reason'] ?? '')) ?: null;
        $allowed = ['active', 'vacation', 'on_leave', 'terminated', 'admission'];

        if (! in_array($newStatus, $allowed, true) || $newStatus === $employee['status']) {
            flash('error', 'Situação inválida ou inalterada.');

            return;
        }

        $db = \App\Models\Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE employees SET status = :s, terminated_at = :ended WHERE id = :e')
               ->execute(['s' => $newStatus, 'e' => $employee['id'],
                   'ended' => $newStatus === 'terminated' ? date('Y-m-d') : null]);
            $db->prepare('INSERT INTO employee_status_history (employee_id, old_status, new_status, reason, changed_by)
                          VALUES (:e, :old, :new, :r, :by)')
               ->execute(['e' => $employee['id'], 'old' => $employee['status'],
                   'new' => $newStatus, 'r' => $reason, 'by' => auth_user()['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        AuditService::log('employee.status', 'employee', $employee['id'],
            ['status' => $employee['status']], ['status' => $newStatus, 'reason' => $reason]);
        flash('success', 'Situação atualizada.');
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
        \App\Services\Api\WebhookService::dispatch($companyId, 'employee.created', [
            'employee_id' => $employeeId, 'full_name' => $core['full_name'],
            'registration' => $core['registration'], 'hired_at' => $core['hired_at'],
        ]);
        flash('success', "Colaborador \"{$core['full_name']}\" admitido — checklist de admissão criado.");
        redirect("colaborador.php?id={$employeeId}");
    }
}
