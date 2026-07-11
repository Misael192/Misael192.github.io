<?php

declare(strict_types=1);

namespace App\Events;

class EmployeeUpdated extends DomainEvent
{
    public const NAME = 'employee.updated';
}
