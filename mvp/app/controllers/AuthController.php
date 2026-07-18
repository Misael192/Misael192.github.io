<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Csrf;
use App\Services\AuthService;

class AuthController
{
    public function __construct(private readonly AuthService $auth = new AuthService)
    {
    }

    /** GET: formulário · POST: tentativa de login. */
    public function login(): void
    {
        if (auth_user() !== null) {
            redirect($this->home());
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();

            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if ($email !== '' && $password !== '' && $this->auth->attempt($email, $password)) {
                redirect($this->home());
            }

            flash('error', 'Credenciais inválidas. Verifique e-mail e senha.');
            redirect('login.php');
        }

        view('login');
    }

    /** Colaborador entra direto no portal; demais perfis, no dashboard. */
    private function home(): string
    {
        return (auth_user()['role'] ?? '') === 'colaborador' ? 'portal.php' : 'dashboard.php';
    }

    public function logout(): void
    {
        $this->auth->logout();
        redirect('login.php');
    }
}
