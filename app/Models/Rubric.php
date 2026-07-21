<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Rubrica de folha (parametriza a engine): incidências e fórmula. Lei
 * federal — igual para todo tenant, por isso é catálogo global.
 */
class Rubric extends GlobalModel
{
    protected $casts = [
        'incides_inss' => 'boolean',
        'incides_irrf' => 'boolean',
        'incides_fgts' => 'boolean',
        'is_active' => 'boolean',
    ];
}
