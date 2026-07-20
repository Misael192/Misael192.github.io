<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Ponto: registro diário manual, aprovação e banco de horas.
 * Ao aprovar um dia, a diferença entre horas trabalhadas e a jornada
 * diária da escala vira lançamento no banco de horas (overtime_bank).
 */
class TimeClock extends Model
{
    protected string $table = 'time_clock_records';

    public function listForCompany(int $companyId, int $limit = 30): array
    {
        return $this->select(
            'SELECT t.*, e.full_name, ws.daily_hours
             FROM time_clock_records t
             JOIN employees e ON e.id = t.employee_id
             LEFT JOIN work_shifts ws ON ws.id = e.work_shift_id
             WHERE t.company_id = :c
             ORDER BY t.work_date DESC, e.full_name
             LIMIT '.max(1, $limit),
            ['c' => $companyId],
        );
    }

    public function register(int $companyId, int $employeeId, string $date, array $times): array
    {
        $exists = $this->selectOne(
            'SELECT 1 FROM time_clock_records WHERE employee_id = :e AND work_date = :d',
            ['e' => $employeeId, 'd' => $date],
        );
        if ($exists !== null) {
            return [false, 'Já existe registro de ponto para este colaborador nesta data.'];
        }

        $this->execute(
            'INSERT INTO time_clock_records (company_id, employee_id, work_date, clock_in, lunch_out, lunch_in, clock_out)
             VALUES (:c, :e, :d, :in, :lo, :li, :out)',
            ['c' => $companyId, 'e' => $employeeId, 'd' => $date,
                'in' => $times['clock_in'] ?: null, 'lo' => $times['lunch_out'] ?: null,
                'li' => $times['lunch_in'] ?: null, 'out' => $times['clock_out'] ?: null],
        );

        return [true, 'Ponto registrado.'];
    }

    /** Minutos trabalhados no dia (desconta o intervalo de almoço). */
    public function workedMinutes(array $record): ?int
    {
        if (! $record['clock_in'] || ! $record['clock_out']) {
            return null;
        }
        $minutes = (strtotime($record['clock_out']) - strtotime($record['clock_in'])) / 60;
        if ($record['lunch_out'] && $record['lunch_in']) {
            $minutes -= (strtotime($record['lunch_in']) - strtotime($record['lunch_out'])) / 60;
        }

        return (int) $minutes;
    }

    /** Aprova o dia e lança a diferença vs. jornada no banco de horas. */
    public function approve(int $recordId, int $companyId, int $approverId): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $record = $this->selectOne(
                "SELECT t.*, COALESCE(ws.daily_hours, 8) AS daily_hours
                 FROM time_clock_records t
                 JOIN employees e ON e.id = t.employee_id
                 LEFT JOIN work_shifts ws ON ws.id = e.work_shift_id
                 WHERE t.id = :id AND t.company_id = :c AND t.status = 'recorded' FOR UPDATE OF t",
                ['id' => $recordId, 'c' => $companyId],
            );
            if ($record === null) {
                $db->rollBack();

                return false;
            }

            $this->execute(
                "UPDATE time_clock_records SET status = 'approved', approved_by = :by WHERE id = :id",
                ['by' => $approverId, 'id' => $recordId],
            );

            $worked = $this->workedMinutes($record);
            if ($worked !== null) {
                $delta = $worked - (int) round(((float) $record['daily_hours']) * 60);
                if ($delta !== 0) {
                    $this->execute(
                        'INSERT INTO overtime_bank (employee_id, work_date, minutes, reason, created_by)
                         VALUES (:e, :d, :m, :r, :by)',
                        ['e' => $record['employee_id'], 'd' => $record['work_date'], 'm' => $delta,
                            'r' => $delta > 0 ? 'overtime' : 'compensation', 'by' => $approverId],
                    );
                }
            }

            $db->commit();

            return true;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** Saldo do banco de horas em minutos, por colaborador da empresa. */
    public function bankBalances(int $companyId): array
    {
        return $this->select(
            'SELECT e.id, e.full_name, COALESCE(SUM(o.minutes), 0) AS balance
             FROM employees e
             LEFT JOIN overtime_bank o ON o.employee_id = e.id
             WHERE e.company_id = :c
             GROUP BY e.id, e.full_name
             HAVING COALESCE(SUM(o.minutes), 0) <> 0
             ORDER BY balance DESC',
            ['c' => $companyId],
        );
    }
}
