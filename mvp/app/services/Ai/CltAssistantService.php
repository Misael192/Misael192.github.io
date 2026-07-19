<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Payroll\FgtsCalculator;
use App\Services\Payroll\InssCalculator;
use App\Services\Payroll\IrrfCalculator;
use App\Services\Payroll\TaxTableRepository;
use App\Services\Payroll\ThirteenthCalculator;
use App\Services\Payroll\VacationCalculator;

/**
 * Assistente CLT — motor de conhecimento + cálculo do MVP.
 *
 * Princípio: NUNCA responder valor de imposto "de cabeça". Toda resposta
 * numérica sai das calculadoras da folha com as tabelas VIGENTES no banco
 * (mudou a lei → muda a tabela → o assistente muda junto). O texto legal
 * cita os artigos da CLT/leis. Estrutura pronta para plugar um provedor
 * LLM externo depois (mesma interface answer()).
 */
final class CltAssistantService
{
    private InssCalculator $inss;

    private IrrfCalculator $irrf;

    private FgtsCalculator $fgts;

    public function __construct()
    {
        $tables = new TaxTableRepository;
        $t = $tables->forCompetency(date('Y-m'));
        $this->inss = $tables->buildInss($t);
        $this->irrf = $tables->buildIrrf($t);
        $this->fgts = $tables->buildFgts($t);
    }

    public function answer(string $question): string
    {
        $q = mb_strtolower($question);
        $money = $this->extractMoney($question);
        $days = $this->extractInt($q, '/(\d{1,2})\s*dias?/u');
        $hours = $this->extractFloat($q, '/(\d{1,3}(?:[.,]\d{1,2})?)\s*horas?/u');
        $dependents = $this->extractInt($q, '/(\d{1,2})\s*dependentes?/u') ?? 0;

        return match (true) {
            $this->has($q, ['líquido', 'liquido', 'salário líquido']) && $money !== null => $this->netSalary($money, $dependents),
            $this->has($q, ['inss']) && $money !== null => $this->inssAnswer($money),
            $this->has($q, ['irrf', 'imposto de renda', 'ir ']) && $money !== null => $this->irrfAnswer($money, $dependents),
            $this->has($q, ['fgts']) && $money !== null => $this->fgtsAnswer($money),
            $this->has($q, ['férias', 'ferias']) && $money !== null => $this->vacationAnswer($money, $days ?? 30, $dependents),
            $this->has($q, ['férias', 'ferias']) => $this->vacationRules(),
            $this->has($q, ['13º', '13o', 'décimo terceiro', 'decimo terceiro']) && $money !== null => $this->thirteenthAnswer($money),
            $this->has($q, ['13º', '13o', 'décimo terceiro', 'decimo terceiro']) => $this->thirteenthRules(),
            $this->has($q, ['hora extra', 'horas extras', 'he ']) && $money !== null => $this->overtimeAnswer($money, $hours ?? 1.0, $this->has($q, ['100'])),
            $this->has($q, ['hora extra', 'horas extras']) => $this->overtimeRules(),
            $this->has($q, ['aviso prévio', 'aviso previo']) => $this->noticeRules($q),
            $this->has($q, ['rescis', 'demiss', 'desligamento', 'justa causa']) => $this->terminationRules(),
            $this->has($q, ['banco de horas']) => $this->timeBankRules(),
            $this->has($q, ['jornada', '12x36', '12 x 36', '44 horas']) => $this->workdayRules(),
            $this->has($q, ['adicional noturno', 'noturno']) => $this->nightShiftRules(),
            $this->has($q, ['insalubridade', 'periculosidade']) => $this->hazardRules(),
            $this->has($q, ['maternidade', 'paternidade', 'licença', 'licenca']) => $this->leaveRules(),
            $this->has($q, ['experiência', 'experiencia', 'contrato de experiência']) => $this->probationRules(),
            $this->has($q, ['vale transporte', 'vt']) => $this->transportRules(),
            $this->has($q, ['salário família', 'salario familia', 'salário-família']) => $this->familyAllowanceRules(),
            $this->has($q, ['inss', 'irrf', 'tabela']) => $this->tablesAnswer(),
            default => $this->fallback(),
        };
    }

    // ── Cálculos (engine real) ───────────────────────────────────────────────

    private function netSalary(int $salary, int $dependents): string
    {
        $inss = $this->inss->calculate($salary);
        $irrf = $this->irrf->calculate($salary, $inss, $dependents);
        $fgts = $this->fgts->calculate($salary);
        $net = $salary - $inss - $irrf;

        return "Salário líquido de {$this->m($salary)}".($dependents ? " com {$dependents} dependente(s)" : '').":\n"
            ."• INSS (progressivo): −{$this->m($inss)}\n"
            ."• IRRF (menor entre legal e simplificado): −{$this->m($irrf)}\n"
            ."• Líquido: {$this->m($net)}\n"
            ."• FGTS depositado pelo empregador (não desconta): {$this->m($fgts)}\n\n"
            .'Calculado com as tabelas vigentes cadastradas no sistema. Benefícios e descontos recorrentes podem alterar o valor — a folha oficial considera tudo.';
    }

    private function inssAnswer(int $base): string
    {
        $value = $this->inss->calculate($base);
        $effective = $base > 0 ? number_format($value / $base * 100, 2, ',', '.') : '0';

        return "INSS sobre {$this->m($base)}: {$this->m($value)} (alíquota efetiva {$effective}%).\n\n"
            ."O INSS é progressivo por faixas (como o IR): cada fatia do salário paga a alíquota da sua faixa, com desconto limitado ao teto. Base legal: Lei 8.212/91, tabela vigente cadastrada no sistema.";
    }

    private function irrfAnswer(int $base, int $dependents): string
    {
        $inss = $this->inss->calculate($base);
        $legal = $this->irrf->calculateLegal($base, $inss, $dependents);
        $simplified = $this->irrf->calculateSimplified($base);
        $winner = min($legal, $simplified);

        return "IRRF sobre {$this->m($base)}".($dependents ? " com {$dependents} dependente(s)" : '').":\n"
            ."• Regra legal (base − INSS − dependentes): {$this->m($legal)}\n"
            ."• Desconto simplificado: {$this->m($simplified)}\n"
            ."• Retido (aplica-se o MENOR): {$this->m($winner)}\n\n"
            .'Desde maio/2023 a fonte deve aplicar automaticamente o desconto simplificado quando for mais vantajoso (Lei 14.663/2023).';
    }

    private function fgtsAnswer(int $base): string
    {
        return "FGTS sobre {$this->m($base)}: {$this->m($this->fgts->calculate($base))} por mês (8%; aprendiz recolhe 2%).\n\n"
            .'O FGTS é depositado pelo empregador na Caixa — não é descontado do salário. Incide também sobre 13º, férias gozadas e aviso indenizado. Base: Lei 8.036/90.';
    }

    private function vacationAnswer(int $salary, int $days, int $dependents): string
    {
        $calc = (new VacationCalculator($this->inss, $this->irrf))->calculate($salary, $days, 0, $dependents);

        return "Férias de {$days} dia(s) com salário {$this->m($salary)}:\n"
            ."• Férias ({$days}d): {$this->m($calc['items'][0]['amount'])}\n"
            ."• 1/3 constitucional: {$this->m($calc['items'][1]['amount'])}\n"
            ."• INSS: −{$this->m($calc['inss'])} · IRRF: −{$this->m($calc['irrf'])}\n"
            ."• Líquido a receber: {$this->m($calc['net'])}\n\n"
            .'O pagamento deve sair até 2 dias antes do início do gozo (CLT art. 145). Venda de até 10 dias (abono pecuniário, art. 143) é isenta de INSS/IRRF.';
    }

    private function thirteenthAnswer(int $salary): string
    {
        $calc = new ThirteenthCalculator($this->inss, $this->irrf);
        $first = $calc->firstInstallment($salary, 12);
        $second = $calc->secondInstallment($salary, 12, $first['net'], 0);

        return "13º salário para {$this->m($salary)} (ano completo, 12/12 avos):\n"
            ."• 1ª parcela (até 30/11): {$this->m($first['net'])} — sem descontos\n"
            ."• 2ª parcela (até 20/12): {$this->m($second['net'])} — integral − INSS {$this->m($second['inss'])} − IRRF {$this->m($second['irrf'])} − adiantamento\n\n"
            .'Cada mês com 15+ dias trabalhados conta 1/12 (Lei 4.090/62). INSS e IRRF do 13º são apurados separadamente do salário do mês.';
    }

    private function overtimeAnswer(int $salary, float $hours, bool $hundred): string
    {
        $rate = $hundred ? 2.0 : 1.5;
        $hourly = $salary / 220;
        $value = (int) round($hourly * $rate * $hours);
        $pct = $hundred ? '100%' : '50%';

        return 'Hora extra '.$pct." para salário {$this->m($salary)} (divisor 220):\n"
            ."• Valor da hora normal: {$this->m((int) round($hourly))}\n"
            ."• ".number_format($hours, 1, ',', '.')."h extra(s) a {$pct}: {$this->m($value)}\n\n"
            .'Mínimo constitucional: +50% (CF art. 7º XVI); domingos/feriados usualmente +100%. Limite de 2h extras/dia (CLT art. 59). HE habitual integra médias de férias, 13º e rescisão.';
    }

    // ── Conhecimento CLT (texto com base legal) ──────────────────────────────

    private function vacationRules(): string
    {
        return "Férias (CLT arts. 129–145):\n"
            ."• Direito a 30 dias após 12 meses (período aquisitivo); gozo dentro dos 12 meses seguintes (concessivo), sob pena de pagamento em dobro (art. 137).\n"
            ."• Remuneração: salário + 1/3 constitucional, pagos até 2 dias antes.\n"
            ."• Pode fracionar em até 3 períodos (um ≥14 dias, demais ≥5) com concordância do empregado.\n"
            ."• Abono pecuniário: vender até 10 dias (art. 143) — verba indenizatória, sem INSS/IRRF.\n"
            ."• Faltas injustificadas reduzem o direito (art. 130).\n\n"
            .'Quer o valor? Pergunte: "férias de 30 dias com salário de R$ 3.000".';
    }

    private function thirteenthRules(): string
    {
        return "13º salário (Lei 4.090/62 e 4.749/65):\n"
            ."• 1/12 da remuneração por mês com 15+ dias trabalhados no ano.\n"
            ."• 1ª parcela até 30/11 (50%, sem descontos); 2ª até 20/12 (com INSS/IRRF sobre o integral, descontando o adiantamento).\n"
            ."• Médias de horas extras e comissões integram a base.\n"
            ."• Na rescisão sem justa causa, o proporcional é devido.\n\n"
            .'Quer o valor? Pergunte: "13º de quem ganha R$ 2.500".';
    }

    private function overtimeRules(): string
    {
        return "Horas extras (CLT arts. 58–61 e CF art. 7º):\n"
            ."• Jornada padrão: 8h/dia e 44h/semana; divisor usual 220.\n"
            ."• Adicional mínimo de 50%; 100% em domingos/feriados (jurisprudência) ou conforme convenção.\n"
            ."• Limite de 2 horas extras por dia (art. 59); pode haver compensação via banco de horas.\n"
            ."• HE habitual reflete em férias, 13º, FGTS e verbas rescisórias.\n\n"
            .'Quer o valor? Pergunte: "10 horas extras com salário de R$ 2.200".';
    }

    private function noticeRules(string $q): string
    {
        $years = $this->extractInt($q, '/(\d{1,2})\s*anos?/u');
        $extra = '';
        if ($years !== null) {
            $daysN = min(90, 30 + 3 * $years);
            $extra = "\nPara {$years} ano(s) de casa: {$daysN} dias de aviso.";
        }

        return "Aviso prévio (CLT arts. 487–491 + Lei 12.506/2011):\n"
            ."• Mínimo de 30 dias + 3 dias por ano completo de serviço, até 90 dias.\n"
            ."• Trabalhado: jornada reduzida em 2h/dia ou 7 dias corridos de dispensa (art. 488).\n"
            ."• Indenizado: pago em dinheiro, projeta o tempo de serviço e tem FGTS.\n"
            ."• No pedido de demissão, quem não cumpre aviso pode ter 30 dias descontados.".$extra;
    }

    private function terminationRules(): string
    {
        return "Verbas rescisórias por modalidade:\n"
            ."• Sem justa causa: saldo de salário, aviso (Lei 12.506), férias vencidas e proporcionais + 1/3, 13º proporcional, multa de 40% do FGTS e saque + seguro-desemprego.\n"
            ."• Pedido de demissão: saldo, férias + 1/3, 13º proporcional — sem multa, sem saque, sem seguro.\n"
            ."• Acordo (CLT art. 484-A): metade do aviso e da multa (20%), saque de 80% do FGTS, sem seguro.\n"
            ."• Justa causa (art. 482): apenas saldo de salário e férias vencidas + 1/3.\n"
            ."• Prazo de pagamento: até 10 dias do término (art. 477 §6º).\n\n"
            .'Simule com valores reais na tela Rescisão do sistema.';
    }

    private function timeBankRules(): string
    {
        return "Banco de horas (CLT art. 59 §§2º–6º):\n"
            ."• Compensação de horas extras por folgas em vez de pagamento.\n"
            ."• Acordo individual escrito: compensação em até 6 meses; convenção/acordo coletivo: até 1 ano.\n"
            ."• Na rescisão, o saldo positivo é pago como HE com adicional.\n"
            ."• No sistema, o ponto aprovado alimenta o banco automaticamente contra a jornada da escala.";
    }

    private function workdayRules(): string
    {
        return "Jornada de trabalho (CF art. 7º XIII, CLT arts. 58–75):\n"
            ."• Padrão: 8h/dia, 44h/semana.\n"
            ."• 12x36 (CLT art. 59-A): 12h de trabalho por 36h de descanso, por acordo individual escrito ou coletivo; feriados já indenizados na escala.\n"
            ."• Intervalo intrajornada: 1h (mín. 30min por acordo) para jornadas acima de 6h; interjornada de 11h (art. 66).\n"
            ."• Teletrabalho por produção não tem controle de jornada (art. 62 III).";
    }

    private function nightShiftRules(): string
    {
        return "Adicional noturno (CLT art. 73):\n"
            ."• Urbano: trabalho entre 22h e 5h, adicional mínimo de 20% e hora reduzida de 52min30s.\n"
            ."• Rural: 25% (lavoura 21h–5h; pecuária 20h–4h), sem hora reduzida.\n"
            ."• Prorrogação após as 5h em jornada integralmente noturna mantém o adicional (Súmula 60 TST).";
    }

    private function hazardRules(): string
    {
        return "Insalubridade e periculosidade (CLT arts. 189–197):\n"
            ."• Insalubridade: 10%, 20% ou 40% do salário MÍNIMO, conforme o grau (NR-15), com laudo.\n"
            ."• Periculosidade: 30% do salário BASE (inflamáveis, explosivos, energia elétrica, segurança patrimonial — NR-16).\n"
            ."• Não se acumulam: o empregado opta pelo mais vantajoso (art. 193 §2º).";
    }

    private function leaveRules(): string
    {
        return "Licenças e afastamentos:\n"
            ."• Maternidade: 120 dias (CLT art. 392), estabilidade da confirmação da gravidez até 5 meses pós-parto (ADCT art. 10 II b); Empresa Cidadã estende a 180.\n"
            ."• Paternidade: 5 dias (ADCT art. 10 §1º); 20 na Empresa Cidadã.\n"
            ."• Doença/acidente: empresa paga os primeiros 15 dias; do 16º em diante, INSS (auxílio por incapacidade).\n"
            ."• Acidente de trabalho gera estabilidade de 12 meses após a alta (Lei 8.213 art. 118).\n"
            ."• No sistema, afastamentos médicos com CID ficam na ficha do colaborador e suspendem descontos por falta.";
    }

    private function probationRules(): string
    {
        return "Contrato de experiência (CLT arts. 443/445/451):\n"
            ."• Máximo de 90 dias, com uma única prorrogação dentro desse limite (ex.: 45+45).\n"
            ."• Rescisão antecipada sem justa causa: indenização de metade dos dias restantes (art. 479).\n"
            ."• Vencido o prazo sem desligamento, vira contrato por prazo indeterminado automaticamente.";
    }

    private function transportRules(): string
    {
        return "Vale-transporte (Lei 7.418/85):\n"
            ."• Obrigatório para o deslocamento casa–trabalho em transporte público.\n"
            ."• Desconto do empregado limitado a 6% do salário BASE — se o custo for menor, desconta-se o custo.\n"
            ."• Não tem natureza salarial (não gera FGTS/INSS).\n"
            ."• No sistema, o benefício VT ativa o desconto automático na folha com esse teto.";
    }

    private function familyAllowanceRules(): string
    {
        return "Salário-família (CF art. 7º XII, Lei 8.213 arts. 65–70):\n"
            ."• Cota mensal por filho de até 14 anos (ou inválido de qualquer idade) para quem ganha até o limite da tabela vigente.\n"
            ."• Pago pelo empregador na folha e compensado no recolhimento ao INSS.\n"
            ."• Exige certidão de nascimento e caderneta de vacinação em dia.\n"
            ."• O sistema aplica automaticamente conforme os dependentes cadastrados e o limite da tabela.";
    }

    private function tablesAnswer(): string
    {
        return "As tabelas oficiais (INSS, IRRF, salário-família, FGTS) ficam CADASTRADAS no sistema com vigência — quando a lei muda, o DP atualiza a tabela e todos os cálculos (folha, férias, 13º, rescisão e este assistente) passam a usar a nova automaticamente, sem mexer em código.\n\n"
            .'Pergunte, por exemplo: "INSS de R$ 3.000", "IRRF de R$ 5.200 com 2 dependentes", "salário líquido de R$ 4.000".';
    }

    private function fallback(): string
    {
        return "Posso ajudar com departamento pessoal e CLT. Exemplos:\n"
            ."• \"Salário líquido de R$ 3.500 com 1 dependente\"\n"
            ."• \"INSS de R$ 2.800\" · \"IRRF de R$ 5.200 com 2 dependentes\"\n"
            ."• \"Férias de 20 dias com salário de R$ 3.000\"\n"
            ."• \"13º de quem ganha R$ 2.500\" · \"10 horas extras com salário de R$ 2.200\"\n"
            ."• Regras: férias, 13º, aviso prévio, rescisão, banco de horas, jornada 12x36, adicional noturno, insalubridade/periculosidade, licença maternidade, contrato de experiência, vale-transporte, salário-família.";
    }

    // ── Parsing/formatação ───────────────────────────────────────────────────

    private function has(string $q, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($q, $n)) {
                return true;
            }
        }

        return false;
    }

    /** "R$ 3.500,00" | "3500" | "3.500" → centavos; null se não achou. */
    private function extractMoney(string $text): ?int
    {
        if (preg_match('/r\$?\s*([\d.]+(?:,\d{1,2})?)/iu', $text, $m)
            || preg_match('/([\d]{3,}(?:\.[\d]{3})*(?:,\d{1,2})?)/u', $text, $m)) {
            $value = (float) str_replace(',', '.', str_replace('.', '', $m[1]));

            return $value > 0 ? (int) round($value * 100) : null;
        }

        return null;
    }

    private function extractInt(string $q, string $pattern): ?int
    {
        return preg_match($pattern, $q, $m) ? (int) $m[1] : null;
    }

    private function extractFloat(string $q, string $pattern): ?float
    {
        return preg_match($pattern, $q, $m) ? (float) str_replace(',', '.', $m[1]) : null;
    }

    private function m(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
