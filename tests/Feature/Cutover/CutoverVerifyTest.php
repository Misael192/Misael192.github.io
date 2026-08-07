<?php

declare(strict_types=1);

namespace Tests\Feature\Cutover;

use App\Models\Tenant;
use App\Services\Cutover\CutoverImporter;
use App\Services\Cutover\CutoverVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reconferência do cutover: importado × export. Verde quando batem; aponta
 * divergência de total; comando falha quando há divergência.
 */
class CutoverVerifyTest extends TestCase
{
    use RefreshDatabase;

    private function export(): array
    {
        return [
            'tenant' => ['slug' => 'acme', 'name' => 'Acme'],
            'organization' => ['name' => 'Grupo Acme'],
            'company' => ['name' => 'Acme LTDA'],
            'employees' => [[
                'registration_number' => '0001', 'full_name' => 'Maria', 'hired_at' => '2024-01-01',
                'status' => 'active', 'contracts' => [['type' => 'clt', 'salary_cents' => 520000, 'start_date' => '2024-01-01']],
            ]],
            'periods' => [[
                'competency' => '2026-07', 'status' => 'closed',
                'payrolls' => [[
                    'registration_number' => '0001', 'kind' => 'payslip',
                    'gross_cents' => 520000, 'deductions_cents' => 89549, 'net_cents' => 430451,
                    'items' => [], 'charges' => [],
                ]],
            ]],
        ];
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->where('slug', 'acme')->firstOrFail();
    }

    public function test_verifica_ok_quando_importado_bate_com_o_export(): void
    {
        $export = $this->export();
        (new CutoverImporter)->import($export);

        $result = (new CutoverVerifier)->verify($export, $this->tenant());

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['checked']);
        $this->assertSame([], $result['issues']);
    }

    public function test_aponta_divergencia_de_total(): void
    {
        (new CutoverImporter)->import($this->export());

        // Export "de conferência" com líquido adulterado.
        $tampered = $this->export();
        $tampered['periods'][0]['payrolls'][0]['net_cents'] = 999999;

        $result = (new CutoverVerifier)->verify($tampered, $this->tenant());

        $this->assertFalse($result['ok']);
        $this->assertCount(1, $result['issues']);
        $this->assertStringContainsString('net_cents', $result['issues'][0]);
    }

    public function test_comando_falha_com_divergencia(): void
    {
        (new CutoverImporter)->import($this->export());

        $tampered = $this->export();
        $tampered['periods'][0]['payrolls'][0]['gross_cents'] = 1;
        $path = tempnam(sys_get_temp_dir(), 'verify').'.json';
        file_put_contents($path, json_encode($tampered));

        $this->artisan('cutover:verify', ['path' => $path, 'tenant' => 'acme'])
            ->expectsOutputToContain('divergência')
            ->assertFailed();

        @unlink($path);
    }

    public function test_comando_ok_quando_bate(): void
    {
        $export = $this->export();
        (new CutoverImporter)->import($export);
        $path = tempnam(sys_get_temp_dir(), 'verify').'.json';
        file_put_contents($path, json_encode($export));

        $this->artisan('cutover:verify', ['path' => $path, 'tenant' => 'acme'])
            ->expectsOutputToContain('Pronto para o Go')
            ->assertSuccessful();

        @unlink($path);
    }
}
