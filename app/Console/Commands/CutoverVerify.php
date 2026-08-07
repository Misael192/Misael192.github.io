<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Cutover\CutoverVerifier;
use Illuminate\Console\Command;

/**
 * Reconfere um tenant importado contra o export de origem (Go/No-Go do cutover).
 * Ver docs/CUTOVER.md. Falha (código de erro) se houver qualquer divergência.
 *
 *   php artisan cutover:verify storage/app/cutover/acme.json acme
 */
class CutoverVerify extends Command
{
    protected $signature = 'cutover:verify {path : JSON de export do MVP} {tenant : slug do tenant importado}';

    protected $description = 'Compara um tenant importado com o export (contagens e totais de folha)';

    public function handle(CutoverVerifier $verifier): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $export = json_decode((string) file_get_contents($path), true);
        if (! is_array($export)) {
            $this->error('JSON inválido.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->where('slug', $this->argument('tenant'))->first();
        if ($tenant === null) {
            $this->error("Tenant '{$this->argument('tenant')}' não encontrado.");

            return self::FAILURE;
        }

        $result = $verifier->verify($export, $tenant);

        if ($result['ok']) {
            $this->info("OK — {$result['checked']} folha(s) conferida(s), sem divergências. Pronto para o Go.");

            return self::SUCCESS;
        }

        $this->error(count($result['issues']).' divergência(s) encontrada(s):');
        foreach ($result['issues'] as $issue) {
            $this->warn('• '.$issue);
        }

        return self::FAILURE;
    }
}
