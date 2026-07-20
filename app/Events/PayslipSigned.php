<?php

declare(strict_types=1);

namespace App\Events;

class PayslipSigned extends DomainEvent
{
    public const NAME = 'payslip.signed';
}
