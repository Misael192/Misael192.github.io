<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\Can;
use App\Middleware\Csrf;
use App\Models\Document;
use App\Models\Employee;
use App\Services\AuditService;

class DocumentController
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10 MB

    private const ALLOWED_MIMES = [
        'application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png',
    ];

    public function __construct(private readonly Document $documents = new Document)
    {
    }

    public function index(): void
    {
        Can::check('documents:read');
        $companyId = auth_user()['company_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            match ($_POST['action'] ?? 'upload') {
                'sign' => $this->sign($companyId),
                default => $this->upload($companyId),
            };
        }

        view('documents', [
            'documents' => $this->documents->listForCompany($companyId),
            'categories' => $this->documents->categories(),
            'employees' => (new Employee)->listForCompany($companyId),
        ]);
    }

    /** Download com controle de acesso (arquivo fora do docroot). */
    public function download(): void
    {
        \App\Middleware\Auth::check();
        $companyId = auth_user()['company_id'];

        $version = $this->documents->latestVersion((int) ($_GET['id'] ?? 0), $companyId);

        // Colaborador baixa os próprios documentos; o resto exige permissão
        if ($version === null || (int) ($version['employee_id'] ?? 0) !== (auth_user()['employee_id'] ?? 0)) {
            Can::check('documents:read');
        }
        $file = $version !== null ? STORAGE_PATH.'/uploads/'.$version['file_path'] : null;

        if ($version === null || ! is_file($file)) {
            http_response_code(404);
            exit('Documento não encontrado.');
        }

        header('Content-Type: '.$version['mime_type']);
        header('Content-Length: '.(string) filesize($file));
        header('Content-Disposition: attachment; filename="'.rawurlencode($version['name']).'"');
        readfile($file);
        exit;
    }

    private function upload(int $companyId): void
    {
        Can::check('documents:manage');

        $name = trim((string) ($_POST['name'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $employeeId = ((int) ($_POST['employee_id'] ?? 0)) ?: null;
        $file = $_FILES['file'] ?? null;

        if ($name === '' || $categoryId < 1 || empty($file['tmp_name']) || ! is_uploaded_file($file['tmp_name'])) {
            flash('error', 'Informe nome, categoria e o arquivo.');
            redirect('documentos.php');
        }

        $mime = mime_content_type($file['tmp_name']);
        $ext = self::ALLOWED_MIMES[$mime] ?? null;
        if ($ext === null || $file['size'] > self::MAX_SIZE) {
            flash('error', 'Arquivo inválido — apenas PDF/JPG/PNG até 10 MB.');
            redirect('documentos.php');
        }

        // Nome aleatório fora do docroot; download só via download.php autenticado.
        $relative = 'docs/'.date('Y/m').'/'.bin2hex(random_bytes(16)).".{$ext}";
        @mkdir(dirname(STORAGE_PATH.'/uploads/'.$relative), 0775, true);
        move_uploaded_file($file['tmp_name'], STORAGE_PATH.'/uploads/'.$relative);

        $result = $this->documents->storeUpload(
            $companyId, $employeeId, $categoryId, $name,
            $relative, $mime, (int) $file['size'],
            hash_file('sha256', STORAGE_PATH.'/uploads/'.$relative),
            auth_user()['id'],
        );

        AuditService::log('document.upload', 'document', $result['document_id'], null,
            ['name' => $name, 'version' => $result['version']]);
        flash('success', "Documento \"{$name}\" salvo (v{$result['version']}).");
        redirect('documentos.php');
    }

    /** Assinatura eletrônica: registra aceite + hash do arquivo + IP/UA. */
    private function sign(int $companyId): void
    {
        Can::check('documents:sign');

        $version = $this->documents->latestVersion((int) ($_POST['document_id'] ?? 0), $companyId);
        if ($version === null) {
            flash('error', 'Documento não encontrado.');
            redirect('documentos.php');
        }

        $this->documents->sign((int) $version['id'], auth_user()['id'], $version['sha256']);
        AuditService::log('document.sign', 'document_version', $version['id'], null, ['sha256' => $version['sha256']]);
        flash('success', 'Documento assinado eletronicamente — hash e evidências registrados.');
        redirect('documentos.php');
    }
}
