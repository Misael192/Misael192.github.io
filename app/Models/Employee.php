<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends TenantModel
{
    use SoftDeletes;

    public const STATUS_ADMISSION = 'admission';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_VACATION = 'vacation';

    public const STATUS_TERMINATED = 'terminated';

    protected function casts(): array
    {
        return [
            // PII sensível cifrada em repouso (AES-256 via APP_KEY) — LGPD by design.
            'cpf' => 'encrypted',
            'rg' => 'encrypted',
            'bank_info' => 'encrypted:json',
            'address' => 'array',
            'birth_date' => 'date',
            'hired_at' => 'date',
            'terminated_at' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vacationRequests(): HasMany
    {
        return $this->hasMany(VacationRequest::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
