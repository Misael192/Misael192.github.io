<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Férias: períodos aquisitivos/concessivos + solicitações.
 * Saldo = dias de direito − gozados − vendidos (por período aberto).
 */
class Vacation extends Model
{
    protected string $table = 'vacations';

    public function openPeriod(int $employeeId): ?array
    {
        return $this->selectOne(
            "SELECT *, days_entitled - days_taken - days_sold AS balance
             FROM vacation_periods
             WHERE employee_id = :e AND status = 'open'
             ORDER BY acq_start LIMIT 1",
            ['e' => $employeeId],
        );
    }

    public function listForCompany(int $companyId): array
    {
        return $this->select(
            'SELECT v.*, e.full_name, u.name AS approver
             FROM vacations v
             JOIN employees e ON e.id = v.employee_id
             LEFT JOIN users u ON u.id = v.approved_by
             WHERE v.company_id = :c
             ORDER BY v.created_at DESC',
            ['c' => $companyId],
        );
    }

    public function request(int $companyId, int $employeeId, string $start, string $end, int $sellDays): array
    {
        $period = $this->openPeriod($employeeId);
        if ($period === null) {
            return [false, 'Colaborador sem período aquisitivo aberto.'];
        }

        $days = (int) ((strtotime($end) - strtotime($start)) / 86400) + 1;
        if ($days + $sellDays > (int) $period['balance']) {
            return [false, "Saldo insuficiente: {$period['balance']} dias disponíveis no período."];
        }

        $this->execute(
            'INSERT INTO vacations (company_id, employee_id, period_id, start_date, end_date, days, sell_days)
             VALUES (:c, :e, :p, :start, :end, :days, :sell)',
            ['c' => $companyId, 'e' => $employeeId, 'p' => $period['id'],
                'start' => $start, 'end' => $end, 'days' => $days, 'sell' => $sellDays],
        );

        return [true, "Solicitação de {$days} dias registrada."];
    }

    /** Aprova/rejeita; aprovação debita o período em transação. */
    public function decide(int $vacationId, int $companyId, bool $approve, int $approverId): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $vacation = $this->selectOne(
                "SELECT * FROM vacations WHERE id = :id AND company_id = :c AND status = 'requested' FOR UPDATE",
                ['id' => $vacationId, 'c' => $companyId],
            );
            if ($vacation === null) {
                $db->rollBack();

                return false;
            }

            $this->execute(
                'UPDATE vacations SET status = :s, approved_by = :by, decided_at = now() WHERE id = :id',
                ['s' => $approve ? 'approved' : 'rejected', 'by' => $approverId, 'id' => $vacationId],
            );

            if ($approve) {
                $this->execute(
                    'UPDATE vacation_periods SET days_taken = days_taken + :d, days_sold = days_sold + :sold
                     WHERE id = :p',
                    ['d' => $vacation['days'], 'sold' => $vacation['sell_days'], 'p' => $vacation['period_id']],
                );
            }

            $db->commit();

            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
