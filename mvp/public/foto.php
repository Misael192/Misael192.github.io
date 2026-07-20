<?php

/** Foto do colaborador (?id=) — servida do storage, exige autenticação. */
require __DIR__.'/../app/bootstrap.php';

App\Middleware\Auth::check();

$employee = (new App\Models\Employee)->findFull((int) ($_GET['id'] ?? 0), auth_user()['company_id']);
$file = $employee['photo_path'] ?? null ? STORAGE_PATH.'/uploads/'.$employee['photo_path'] : null;

if ($file === null || ! is_file($file)) {
    http_response_code(404);
    exit;
}

header('Content-Type: '.mime_content_type($file));
header('Cache-Control: private, max-age=3600');
readfile($file);
