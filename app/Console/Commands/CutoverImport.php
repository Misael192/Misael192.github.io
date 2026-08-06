<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cutover\CutoverImporter;
use Illuminate\Console\Command;

/**
 * Importa o export portátil (JSON) de uma empresa do MVP para a plataforma.
 * Ver docs/CUTOVER.md. Idempotente — pode ser reexecutado para capturar deltas.
 *
 *   php artisan cutover:import storage/app/cutover/acme.json
 */
class CutoverImport extends Command
{
    protected $signature = 'cutover:import {path : Caminho do arquivo JSON de export do MVP}
                            {--dry-run : Ensaia sem escrever nada — conta e aponta inconsistências}';

    protected $description = 'Importa o export de uma empresa do MVP para o schema multi-tenant da plataforma';

    public function handle(CutoverImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || ! isset($data['tenant']['slug'])) {
            $this->error('JSON inválido: esperado um objeto com tenant.slug.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $plan = $importer->plan($data);
            $this->info("Dry-run do tenant '{$plan['tenant']}' — nada foi gravado.");
            $this->table(
                ['Colaboradores', 'Competências', 'Folhas'],
                [[$plan['employees'], $plan['periods'], $plan['payrolls']]],
            );

            foreach ($plan['issues'] as $issue) {
                $this->warn('• '.$issue);
            }

            if ($plan['issues'] !== []) {
                $this->error(count($plan['issues']).' inconsistência(s) — corrija o export antes de importar.');

                return self::FAILURE;
            }

            $this->info('Sem inconsistências — pronto para importar.');

            return self::SUCCESS;
        }

        $summary = $importer->import($data);

        $this->info("Import concluído para o tenant '{$summary['tenant']}'.");
        $this->table(
            ['Colaboradores', 'Competências', 'Folhas'],
            [[$summary['employees'], $summary['periods'], $summary['payrolls']]],
        );

        return self::SUCCESS;
    }
}
