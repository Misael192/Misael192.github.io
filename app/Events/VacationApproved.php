<?php

declare(strict_types=1);

namespace App\Events;

class VacationApproved extends DomainEvent
{
    public const NAME = 'vacation.approved';
}
