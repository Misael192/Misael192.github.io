<?php

declare(strict_types=1);

namespace App\Events;

class UserInvited extends DomainEvent
{
    public const NAME = 'user.invited';
}
