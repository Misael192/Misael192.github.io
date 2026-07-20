<?php

namespace App\Providers;

use App\Core\AI\AiEngine;
use App\Core\AI\AiManager;
use App\Core\Tenancy\TenantContext;
use App\Core\Workflow\Executors;
use App\Core\Workflow\NodeExecutorRegistry;
use App\Events;
use App\Listeners\StartWorkflowsForDomainEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singletons por request. Sob Octane, `scoped` garante reset a cada
        // requisição — nenhum estado vaza entre requests (nem entre tenants).
        $this->app->scoped(TenantContext::class);

        $this->app->singleton(AiManager::class, fn ($app) => new AiManager($app));
        $this->app->singleton(AiEngine::class);

        $this->app->singleton(NodeExecutorRegistry::class, function () {
            $registry = new NodeExecutorRegistry;
            // Tipos de nó do Workflow Engine — plugáveis (ADR-006).
            $registry->register(new Executors\TriggerExecutor);
            $registry->register(new Executors\ConditionExecutor);
            $registry->register(new Executors\ApprovalExecutor);
            $registry->register(new Executors\SignatureExecutor);
            $registry->register(new Executors\DocumentExecutor);
            $registry->register(new Executors\NotificationExecutor);
            $registry->register(new Executors\WebhookExecutor);

            return $registry;
        });
    }

    public function boot(): void
    {
        /**
         * RBAC via Gate (ARCHITECTURE.md §7): habilidades no formato
         * `recurso:ação` são resolvidas contra as permissões dos papéis do
         * usuário. Retornar null delega às Policies (ABAC fino) — retornar
         * false negaria tudo, inclusive o que as Policies permitiriam.
         */
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, ':')) {
                return in_array($ability, $user->permissionCodes(), true) ? true : null;
            }

            return null;
        });

        // Event Bus → Workflow Engine: qualquer evento de domínio pode iniciar
        // um fluxo desenhado pelo tenant.
        Event::listen([
            Events\EmployeeCreated::class,
            Events\EmployeeUpdated::class,
            Events\VacationRequested::class,
            Events\VacationApproved::class,
            Events\PayrollGenerated::class,
            Events\PayslipSigned::class,
            Events\ESocialEventSent::class,
            Events\CandidateHired::class,
            Events\UserInvited::class,
        ], StartWorkflowsForDomainEvent::class);
    }
}
