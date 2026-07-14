<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Colaborador: núcleo + satélites (endereço, contato, banco, dependentes,
 * emergência, contratos, históricos). O cadastro completo roda em UMA
 * transação — ou grava tudo, ou nada.
 */
class Employee extends Model
{
    protected string $table = 'employees';

    private const ADMISSION_CHECKLIST = [
        'CPF', 'RG', 'CTPS', 'PIS/PASEP', 'Comprovante de residência',
        'Exame admissional (ASO)', 'Contrato assinado', 'Foto 3x4',
    ];

    public function listForCompany(int $companyId): array
    {
        return $this->select(
            'SELECT e.id, e.registration, e.full_name, e.status, e.hired_at, e.photo_path,
                    p.title AS position_title, d.name AS department, b.name AS branch
             FROM employees e
             LEFT JOIN positions p ON p.id = e.position_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             WHERE e.company_id = :c
             ORDER BY e.created_at DESC',
            ['c' => $companyId],
        );
    }

    public function findFull(int $id, int $companyId): ?array
    {
        $employee = $this->selectOne(
            'SELECT e.*, p.title AS position_title, d.name AS department, b.name AS branch,
                    cc.name AS cost_center, ws.name AS shift_name, ws.daily_hours,
                    m.full_name AS manager_name
             FROM employees e
             LEFT JOIN positions p ON p.id = e.position_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN cost_centers cc ON cc.id = e.cost_center_id
             LEFT JOIN work_shifts ws ON ws.id = e.work_shift_id
             LEFT JOIN employees m ON m.id = e.manager_id
             WHERE e.id = :id AND e.company_id = :c',
            ['id' => $id, 'c' => $companyId],
        );
        if ($employee === null) {
            return null;
        }

        $employee['address'] = $this->selectOne('SELECT * FROM employee_addresses WHERE employee_id = :id LIMIT 1', ['id' => $id]);
        $employee['contact'] = $this->selectOne('SELECT * FROM employee_contacts WHERE employee_id = :id LIMIT 1', ['id' => $id]);
        $employee['bank'] = $this->selectOne('SELECT * FROM employee_bank_accounts WHERE employee_id = :id LIMIT 1', ['id' => $id]);
        $employee['dependents'] = $this->select('SELECT * FROM employee_dependents WHERE employee_id = :id ORDER BY name', ['id' => $id]);
        $employee['emergency'] = $this->select('SELECT * FROM employee_emergency_contacts WHERE employee_id = :id', ['id' => $id]);
        $employee['salary_history'] = $this->select(
            'SELECT h.*, u.name AS changed_by_name FROM employee_salary_history h
             LEFT JOIN users u ON u.id = h.changed_by WHERE h.employee_id = :id ORDER BY h.changed_at DESC', ['id' => $id]);
        $employee['status_history'] = $this->select(
            'SELECT h.*, u.name AS changed_by_name FROM employee_status_history h
             LEFT JOIN users u ON u.id = h.changed_by WHERE h.employee_id = :id ORDER BY h.changed_at DESC', ['id' => $id]);
        $employee['admission'] = $this->selectOne(
            'SELECT a.*,
                    (SELECT COUNT(*) FROM admission_items i WHERE i.admission_id = a.id AND i.is_done) AS done,
                    (SELECT COUNT(*) FROM admission_items i WHERE i.admission_id = a.id) AS total
             FROM admissions a WHERE a.employee_id = :id', ['id' => $id]);
        $employee['admission_items'] = $employee['admission']
            ? $this->select('SELECT * FROM admission_items WHERE admission_id = :a ORDER BY id', ['a' => $employee['admission']['id']])
            : [];

        return $employee;
    }

    /**
     * Cadastro completo em transação: employee + endereço + contato + banco +
     * dependentes + emergência + contrato + históricos + admissão (checklist)
     * + período aquisitivo de férias.
     *
     * @return int id do colaborador criado
     */
    public function createFull(array $core, array $satellites, int $actorUserId): int
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'INSERT INTO employees (company_id, registration, full_name, cpf, rg, rg_issuer,
                    birth_date, gender, marital_status, nationality, birthplace,
                    pis, ctps, voter_title, reservist, cnh,
                    branch_id, department_id, position_id, cost_center_id, work_shift_id, manager_id,
                    contract_type, salary_cents, hired_at, status, photo_path, position)
                 VALUES (:company_id, :registration, :full_name, :cpf, :rg, :rg_issuer,
                    :birth_date, :gender, :marital_status, :nationality, :birthplace,
                    :pis, :ctps, :voter_title, :reservist, :cnh,
                    :branch_id, :department_id, :position_id, :cost_center_id, :work_shift_id, :manager_id,
                    :contract_type, :salary_cents, :hired_at, :status, :photo_path, :position)
                 RETURNING id',
            );
            $stmt->execute($core);
            $employeeId = (int) $stmt->fetchColumn();

            if (array_filter($satellites['address'] ?? [])) {
                $db->prepare('INSERT INTO employee_addresses (employee_id, zip_code, street, number, district, city, state, complement)
                              VALUES (:e, :zip_code, :street, :number, :district, :city, :state, :complement)')
                   ->execute(['e' => $employeeId] + $satellites['address']);
            }
            if (array_filter($satellites['contact'] ?? [])) {
                $db->prepare('INSERT INTO employee_contacts (employee_id, phone, mobile, email)
                              VALUES (:e, :phone, :mobile, :email)')
                   ->execute(['e' => $employeeId] + $satellites['contact']);
            }
            if (array_filter($satellites['bank'] ?? [])) {
                $db->prepare('INSERT INTO employee_bank_accounts (employee_id, bank, agency, account, account_type, pix_key)
                              VALUES (:e, :bank, :agency, :account, :account_type, :pix_key)')
                   ->execute(['e' => $employeeId] + $satellites['bank']);
            }
            if (! empty($satellites['dependent']['name'])) {
                $db->prepare('INSERT INTO employee_dependents (employee_id, name, cpf, relationship, birth_date)
                              VALUES (:e, :name, :cpf, :relationship, :birth_date)')
                   ->execute(['e' => $employeeId] + $satellites['dependent']);
            }
            if (! empty($satellites['emergency']['name'])) {
                $db->prepare('INSERT INTO employee_emergency_contacts (employee_id, name, relationship, phone)
                              VALUES (:e, :name, :relationship, :phone)')
                   ->execute(['e' => $employeeId] + $satellites['emergency']);
            }

            // Contrato vigente + históricos iniciais
            $db->prepare('INSERT INTO employee_contracts (employee_id, contract_type, start_date, salary_cents)
                          VALUES (:e, :type, :start, :salary)')
               ->execute(['e' => $employeeId, 'type' => $core['contract_type'],
                   'start' => $core['hired_at'], 'salary' => $core['salary_cents']]);

            if ($core['salary_cents'] !== null) {
                $db->prepare('INSERT INTO employee_salary_history (employee_id, old_salary_cents, new_salary_cents, reason, changed_by)
                              VALUES (:e, NULL, :new, :reason, :by)')
                   ->execute(['e' => $employeeId, 'new' => $core['salary_cents'], 'reason' => 'Admissão', 'by' => $actorUserId]);
            }
            $db->prepare('INSERT INTO employee_status_history (employee_id, old_status, new_status, reason, changed_by)
                          VALUES (:e, NULL, :status, :reason, :by)')
               ->execute(['e' => $employeeId, 'status' => $core['status'], 'reason' => 'Cadastro inicial', 'by' => $actorUserId]);

            // Admissão digital com checklist padrão
            $stmt = $db->prepare('INSERT INTO admissions (company_id, employee_id) VALUES (:c, :e) RETURNING id');
            $stmt->execute(['c' => $core['company_id'], 'e' => $employeeId]);
            $admissionId = (int) $stmt->fetchColumn();
            $itemStmt = $db->prepare('INSERT INTO admission_items (admission_id, item) VALUES (:a, :i)');
            foreach (self::ADMISSION_CHECKLIST as $item) {
                $itemStmt->execute(['a' => $admissionId, 'i' => $item]);
            }

            // Período aquisitivo de férias (12 meses a partir da admissão)
            if ($core['hired_at'] !== null) {
                $db->prepare(
                    "INSERT INTO vacation_periods (employee_id, acq_start, acq_end, concessive_end)
                     VALUES (:e, :start, :start::date + interval '1 year' - interval '1 day',
                                        :start::date + interval '2 year' - interval '1 day')
                     ON CONFLICT DO NOTHING"
                )->execute(['e' => $employeeId, 'start' => $core['hired_at']]);
            }

            $db->commit();

            return $employeeId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function registrationExists(int $companyId, string $registration): bool
    {
        return $this->selectOne(
            'SELECT 1 FROM employees WHERE company_id = :c AND registration = :r',
            ['c' => $companyId, 'r' => $registration],
        ) !== null;
    }

    public function statusCounts(): array
    {
        $rows = $this->select('SELECT status, COUNT(*) AS total FROM employees GROUP BY status');
        $counts = ['active' => 0, 'vacation' => 0, 'admission' => 0, 'on_leave' => 0, 'terminated' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function recent(int $limit = 5): array
    {
        return $this->select(
            'SELECT e.full_name, COALESCE(p.title, e.position) AS position, e.status, e.hired_at, d.name AS department
             FROM employees e
             LEFT JOIN positions p ON p.id = e.position_id
             LEFT JOIN departments d ON d.id = e.department_id
             ORDER BY e.created_at DESC LIMIT '.max(1, $limit),
        );
    }
}
