<?php

declare(strict_types=1);

/**
 * Gera o export portátil de uma empresa do MVP para o cutover.
 *
 *   php mvp/bin/cutover_export.php <company_id> <tenant_slug> [organization_name] > acme.json
 *
 * O JSON resultante alimenta, na plataforma Laravel:
 *   php artisan cutover:import acme.json          (carga, idempotente)
 *   php artisan cutover:verify acme.json <slug>   (reconferência Go/No-Go)
 */

require __DIR__.'/../app/bootstrap.php';

use App\Models\Database;
use App\Services\Cutover\CutoverExporter;

$companyId = isset($argv[1]) ? (int) $argv[1] : 0;
$tenantSlug = $argv[2] ?? '';
$organizationName = $argv[3] ?? null;

if ($companyId <= 0 || $tenantSlug === '') {
    fwrite(STDERR, "Uso: php mvp/bin/cutover_export.php <company_id> <tenant_slug> [organization_name]\n");
    exit(1);
}

$export = (new CutoverExporter(Database::connection()))->export($companyId, $tenantSlug, $organizationName);

echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
