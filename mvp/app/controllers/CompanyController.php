<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Auth;
use App\Middleware\Csrf;
use App\Models\Company;

class CompanyController
{
    public function __construct(private readonly Company $companies = new Company)
    {
    }

    /** GET: listagem + formulário · POST: cria empresa. */
    public function index(): void
    {
        Auth::check();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $this->store();
        }

        view('companies', ['companies' => $this->companies->all()]);
    }

    private function store(): void
    {
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'trade_name' => trim((string) ($_POST['trade_name'] ?? '')) ?: null,
            'cnpj' => trim((string) ($_POST['cnpj'] ?? '')) ?: null,
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
        ];

        // Validação simples e explícita (Fase 1)
        if ($data['name'] === '' || mb_strlen($data['name']) > 160) {
            flash('error', 'Informe a razão social (até 160 caracteres).');
            redirect('empresas.php');
        }
        if ($data['cnpj'] !== null && ! preg_match('/^\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}$/', $data['cnpj'])) {
            flash('error', 'CNPJ inválido — use o formato 00.000.000/0000-00.');
            redirect('empresas.php');
        }
        if ($data['cnpj'] !== null && $this->companies->cnpjExists($data['cnpj'])) {
            flash('error', 'Já existe uma empresa com este CNPJ.');
            redirect('empresas.php');
        }

        $this->companies->create($data);
        flash('success', "Empresa \"{$data['name']}\" cadastrada com sucesso.");
        redirect('empresas.php');
    }
}
