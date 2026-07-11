<?php

declare(strict_types=1);

namespace App\Events;

class CandidateHired extends DomainEvent
{
    public const NAME = 'candidate.hired';
}
