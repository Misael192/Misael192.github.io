<?php

declare(strict_types=1);

namespace App\Events;

class EmployeeCreated extends DomainEvent
{
    public const NAME = 'employee.created';
}
