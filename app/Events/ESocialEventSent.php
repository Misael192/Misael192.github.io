<?php

declare(strict_types=1);

namespace App\Events;

class ESocialEventSent extends DomainEvent
{
    public const NAME = 'esocial.event-sent';
}
