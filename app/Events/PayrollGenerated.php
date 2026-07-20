<?php

declare(strict_types=1);

namespace App\Events;

class PayrollGenerated extends DomainEvent
{
    public const NAME = 'payroll.generated';
}
