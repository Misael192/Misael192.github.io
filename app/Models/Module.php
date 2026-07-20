<?php

declare(strict_types=1);

namespace App\Models;

/** Catálogo de módulos comerciais da plataforma (people, payroll, …). */
class Module extends GlobalModel
{
    protected function casts(): array
    {
        return ['is_core' => 'boolean'];
    }
}
