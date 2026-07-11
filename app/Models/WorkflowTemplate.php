<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowTemplate extends TenantModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'definition' => 'array', // grafo {nodes, edges} — o MESMO JSON do editor
            'is_active' => 'boolean',
        ];
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class, 'template_id');
    }
}
