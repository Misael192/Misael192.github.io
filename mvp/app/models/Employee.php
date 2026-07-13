<?php

declare(strict_types=1);

namespace App\Models;

class Employee extends Model
{
    protected string $table = 'employees';

    /** Contagens por status — alimentam o dashboard. */
    public function statusCounts(): array
    {
        $rows = $this->select(
            'SELECT status, COUNT(*) AS total FROM employees GROUP BY status',
        );
        $counts = ['active' => 0, 'vacation' => 0, 'admission' => 0, 'on_leave' => 0, 'terminated' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    public function recent(int $limit = 5): array
    {
        return $this->select(
            'SELECT e.full_name, e.position, e.status, e.hired_at, d.name AS department
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             ORDER BY e.created_at DESC
             LIMIT '.max(1, $limit),
        );
    }
}
