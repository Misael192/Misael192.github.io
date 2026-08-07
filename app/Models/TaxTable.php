<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Tabela oficial com vigência (INSS/IRRF/FGTS/salário-família). Lei
 * federal — igual para todo tenant, por isso é catálogo global.
 */
class TaxTable extends GlobalModel
{
    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'brackets' => 'array',
    ];
}
