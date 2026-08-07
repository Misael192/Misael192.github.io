<?php

namespace Database\Seeders;

use App\Models\Rubric;
use App\Models\TaxTable;
use Illuminate\Database\Seeder;

/**
 * Seed do motor de folha (migrado de mvp/database/fase3.sql): rubricas
 * parametrizadas e tabelas oficiais vigentes (2025). Idempotente.
 */
class PayrollEngineSeeder extends Seeder
{
    private const RUBRICS = [
        // Proventos
        ['code' => '1000', 'name' => 'Salário Base', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1000', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'base_salary'],
        ['code' => '1001', 'name' => 'Hora Extra 50%', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1003', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'overtime_50'],
        ['code' => '1002', 'name' => 'Hora Extra 100%', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1004', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'overtime_100'],
        ['code' => '1003', 'name' => 'Adicional Noturno', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1005', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'night_shift'],
        ['code' => '1004', 'name' => 'Periculosidade', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1202', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'hazard_30'],
        ['code' => '1005', 'name' => 'Insalubridade', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1201', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'unhealthy'],
        ['code' => '1006', 'name' => 'Comissões', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1009', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'manual'],
        ['code' => '1007', 'name' => 'Bônus/Premiação', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1010', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'manual'],
        ['code' => '1100', 'name' => 'Férias + 1/3', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1300', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'vacation'],
        ['code' => '1200', 'name' => '13º Salário', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1350', 'incides_inss' => true, 'incides_irrf' => true, 'incides_fgts' => true, 'formula' => 'thirteenth'],
        ['code' => '1300', 'name' => 'Salário Família', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1409', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'family_allowance'],
        ['code' => '1400', 'name' => 'Verbas Rescisórias', 'type' => 'earning', 'group' => 'proventos', 'nature' => '1400', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'termination_item'],
        // Descontos
        ['code' => '2000', 'name' => 'INSS', 'type' => 'deduction', 'group' => 'descontos', 'nature' => '9201', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'inss'],
        ['code' => '2001', 'name' => 'IRRF', 'type' => 'deduction', 'group' => 'descontos', 'nature' => '9203', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'irrf'],
        ['code' => '2002', 'name' => 'Vale Transporte (6%)', 'type' => 'deduction', 'group' => 'descontos', 'nature' => '9216', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'vt_discount'],
        ['code' => '2003', 'name' => 'Vale Refeição/Alimentação', 'type' => 'deduction', 'group' => 'descontos', 'nature' => '9217', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'benefit_share'],
        ['code' => '2004', 'name' => 'Plano de Saúde', 'type' => 'deduction', 'group' => 'descontos', 'nature' => '9219', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'benefit_share'],
        ['code' => '2005', 'name' => 'Faltas', 'type' => 'deduction', 'group' => 'descontos', 'nature' => '9200', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'absence'],
        ['code' => '2006', 'name' => 'Desconto diverso', 'type' => 'deduction', 'group' => 'descontos', 'nature' => '9299', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'manual'],
        // Encargos (informativos no holerite; não saem do líquido)
        ['code' => '3000', 'name' => 'FGTS (8%)', 'type' => 'info', 'group' => 'encargos', 'nature' => 'FGTS', 'incides_inss' => false, 'incides_irrf' => false, 'incides_fgts' => false, 'formula' => 'fgts'],
    ];

    // Fonte: tabelas progressivas INSS/IRRF vigentes (Lei 14.848/2024, IN RFB).
    private const TAX_TABLES = [
        ['type' => 'inss', 'valid_from' => '2025-01-01', 'brackets' => [
            ['up_to' => 151800, 'rate' => 7.5],
            ['up_to' => 279388, 'rate' => 9.0],
            ['up_to' => 419083, 'rate' => 12.0],
            ['up_to' => 815741, 'rate' => 14.0],
        ]],
        ['type' => 'teto_inss', 'valid_from' => '2025-01-01', 'brackets' => ['value' => 815741]],
        ['type' => 'irrf', 'valid_from' => '2025-05-01', 'brackets' => [
            'brackets' => [
                ['up_to' => 242880, 'rate' => 0, 'deduction' => 0],
                ['up_to' => 282665, 'rate' => 7.5, 'deduction' => 18216],
                ['up_to' => 375105, 'rate' => 15.0, 'deduction' => 39416],
                ['up_to' => 466468, 'rate' => 22.5, 'deduction' => 67549],
                ['up_to' => null, 'rate' => 27.5, 'deduction' => 90873],
            ],
            'dependent_deduction' => 18959,
            'simplified_deduction' => 60720,
        ]],
        ['type' => 'salario_familia', 'valid_from' => '2025-01-01', 'brackets' => ['limit' => 190604, 'per_child' => 6504]],
        ['type' => 'fgts', 'valid_from' => '2020-01-01', 'brackets' => ['rate' => 8.0, 'apprentice_rate' => 2.0]],
    ];

    public function run(): void
    {
        foreach (self::RUBRICS as $rubric) {
            Rubric::query()->updateOrCreate(['code' => $rubric['code']], $rubric);
        }

        foreach (self::TAX_TABLES as $taxTable) {
            TaxTable::query()->updateOrCreate(
                ['type' => $taxTable['type'], 'valid_from' => $taxTable['valid_from']],
                ['brackets' => $taxTable['brackets']],
            );
        }
    }
}
