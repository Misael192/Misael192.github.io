<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Core\Tenancy\TenantContext;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Login da UI web (guard de sessão). O tenant vem do formulário (slug) e é
 * fixado no contexto ANTES do Auth::attempt — sem isso o escopo global não
 * enxerga o usuário. Em sucesso, grava `tenant_slug` na sessão para o
 * SetTenantFromSession resolver nas próximas requisições.
 */
#[Layout('layouts.app')]
class Login extends Component
{
    #[Validate('required|string')]
    public string $tenant = '';

    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public function authenticate(TenantContext $context)
    {
        $this->validate();

        $tenant = Tenant::query()->where('slug', $this->tenant)->first();
        if ($tenant === null || ! $tenant->is_active) {
            throw ValidationException::withMessages(['tenant' => 'Empresa não encontrada ou inativa.']);
        }

        $context->set($tenant);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password, 'is_active' => true])) {
            throw ValidationException::withMessages(['email' => 'Credenciais inválidas.']);
        }

        session()->put('tenant_slug', $tenant->slug);
        session()->regenerate();

        return $this->redirectIntended('/painel', navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
