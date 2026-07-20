<?php

declare(strict_types=1);

namespace App\Models;

class Role extends Model
{
    protected string $table = 'roles';

    public function all(): array
    {
        return $this->select('SELECT id, code, name FROM roles ORDER BY id');
    }
}
