<?php

declare(strict_types=1);

namespace App\Models;

class SurveyResponse extends TenantModel
{
    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }
}
