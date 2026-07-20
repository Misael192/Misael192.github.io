<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Auth;
use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthService;

class UserController
{
    public function __construct(
        private readonly User $users = new User,
        private readonly AuthService $auth = new AuthService,
    ) {
    }

    /** GET: listagem + formulário · POST: cria usuário. */
    public function index(): void
    {
        Auth::check();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Can::check('users:manage');
            Csrf::verify();
            match ($_POST['action'] ?? '') {
                'role' => $this->changeRole(),
                'toggle' => $this->toggleActive(),
                default => $this->store(),
            };
        }

        view('users', [
            'users' => $this->users->allWithRelations(),
            'companies' => (new Company)->forSelect(),
            'roles' => (new Role)->all(),
        ]);
    }

    /** Troca o perfil (role) de um usuário — muda o que ele pode fazer no próximo login. */
    private function changeRole(): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);

        if ($userId === auth_user()['id']) {
            flash('error', 'Você não pode alterar o próprio perfil.');
            redirect('usuarios.php');
        }
        if ($userId < 1 || $roleId < 1) {
            flash('error', 'Usuário ou perfil inválido.');
            redirect('usuarios.php');
        }

        $this->users->changeRole($userId, $roleId);
        AuditService::log('user.role', 'user', $userId, null, ['role_id' => $roleId]);
        flash('success', 'Perfil atualizado — vale a partir do próximo login do usuário.');
        redirect('usuarios.php');
    }

    /** Ativa/desativa acesso. Usuário inativo não consegue mais logar. */
    private function toggleActive(): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId === auth_user()['id']) {
            flash('error', 'Você não pode desativar a si mesmo.');
            redirect('usuarios.php');
        }

        $this->users->toggleActive($userId);
        AuditService::log('user.toggle_active', 'user', $userId);
        flash('success', 'Acesso do usuário atualizado.');
        redirect('usuarios.php');
    }

    private function store(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $companyId = (int) ($_POST['company_id'] ?? 0);
        $roleId = (int) ($_POST['role_id'] ?? 0);

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $companyId < 1 || $roleId < 1) {
            flash('error', 'Preencha nome, e-mail válido, empresa e perfil.');
            redirect('usuarios.php');
        }
        if (strlen($password) < 8) {
            flash('error', 'A senha precisa de pelo menos 8 caracteres.');
            redirect('usuarios.php');
        }
        if ($this->users->emailExists($companyId, $email)) {
            flash('error', 'Este e-mail já está cadastrado nesta empresa.');
            redirect('usuarios.php');
        }

        $this->users->create([
            'company_id' => $companyId,
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'password' => $this->auth->hashPassword($password),
        ]);

        flash('success', "Usuário \"{$name}\" criado com sucesso.");
        redirect('usuarios.php');
    }
}
