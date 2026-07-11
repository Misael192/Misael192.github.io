<?php

declare(strict_types=1);

namespace App\Models;



class PerformanceReview extends TenantModel
{

    protected function casts(): array
    {
        return [
            'competencies' => 'array',
            'overall_score' => 'float',
        ];
    }
}
