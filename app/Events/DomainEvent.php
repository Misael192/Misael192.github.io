<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Evento de domínio da PeopleFlow (Event Bus — ADR-004).
 *
 * Toda ação importante dispara um evento; listeners (inclusive o Workflow
 * Engine) reagem sem acoplamento entre módulos. Listeners enfileirados
 * (ShouldQueue) ganham durabilidade via Laravel Queue/Horizon e DEVEM ser
 * idempotentes — eventos podem ser reentregues.
 */
abstract class DomainEvent
{
    use Dispatchable;

    public const NAME = 'domain.event';

    public function __construct(public readonly array $payload)
    {
    }

    public static function name(): string
    {
        return static::NAME;
    }
}
