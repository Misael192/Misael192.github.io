<?php

declare(strict_types=1);

namespace App\Services\Esocial;

use App\Models\Database;
use PDO;

/**
 * Gerador de eventos eSocial a partir dos dados reais do sistema:
 *  - S-2200: Cadastramento Inicial/Admissão do trabalhador;
 *  - S-1200: Remuneração (rubricas da folha FECHADA da competência).
 * O XML segue a estrutura dos leiautes (evtAdmissao/evtRemun) em versão
 * simplificada; a transmissão ao webservice (certificado A1) é a etapa
 * seguinte do roadmap — aqui o evento fica gerado, validado e auditável.
 */
final class EsocialService
{
    private function db(): PDO
    {
        return Database::connection();
    }

    /** Gera S-2200 para todos os colaboradores sem evento. @return array{0: bool, 1: string} */
    public function generateAdmissions(int $companyId, int $userId): array
    {
        $db = $this->db();

        $employees = $db->prepare(
            "SELECT e.*, c.cnpj, c.name AS company_name, pos.title AS position_name, pos.cbo_code
             FROM employees e
             JOIN companies c ON c.id = e.company_id
             LEFT JOIN positions pos ON pos.id = e.position_id
             WHERE e.company_id = :c AND e.status <> 'terminated'
               AND NOT EXISTS (SELECT 1 FROM esocial_events ev
                                WHERE ev.company_id = e.company_id
                                  AND ev.event_type = 'S-2200' AND ev.reference = e.registration)",
        );
        $employees->execute(['c' => $companyId]);

        $count = 0;
        $skipped = [];
        foreach ($employees->fetchAll() as $e) {
            if ($e['cpf'] === null || $e['salary_cents'] === null) {
                $skipped[] = $e['full_name'];

                continue;
            }
            $this->store($companyId, (int) $e['id'], 'S-2200', $e['registration'],
                $this->admissionXml($e), $userId);
            $count++;
        }

        $message = "S-2200 gerado para {$count} colaborador(es).";
        if ($skipped !== []) {
            $message .= ' Sem CPF/salário (pendências de cadastro): '.implode(', ', $skipped).'.';
        }

        return [$count > 0 || $skipped === [], $message];
    }

    /** Gera S-1200 da competência (exige folha FECHADA). @return array{0: bool, 1: string} */
    public function generateRemuneration(int $companyId, string $competency, int $userId): array
    {
        $db = $this->db();

        $stmt = $db->prepare(
            "SELECT id, status FROM payroll_periods WHERE company_id = :c AND competency = :m",
        );
        $stmt->execute(['c' => $companyId, 'm' => $competency]);
        $period = $stmt->fetch();

        if ($period === false) {
            return [false, "Não há folha calculada em {$competency}."];
        }
        if ($period['status'] !== 'closed') {
            return [false, "A folha de {$competency} precisa estar FECHADA para gerar o S-1200 (está: {$period['status']})."];
        }

        $company = $db->prepare('SELECT cnpj, name FROM companies WHERE id = :c');
        $company->execute(['c' => $companyId]);
        $company = $company->fetch();

        $payrolls = $db->prepare(
            'SELECT p.*, e.cpf, e.registration, e.full_name FROM payrolls p
             JOIN employees e ON e.id = p.employee_id
             WHERE p.period_id = :p ORDER BY e.full_name, p.kind',
        );
        $payrolls->execute(['p' => $period['id']]);
        $rows = $payrolls->fetchAll();

        if ($rows === []) {
            return [false, "Folha de {$competency} não tem colaboradores calculados."];
        }

        $items = $db->prepare('SELECT * FROM payroll_items WHERE payroll_id = :p ORDER BY rubric_code');

        // Um demonstrativo (dmDev) por folha do trabalhador na competência
        $byEmployee = [];
        foreach ($rows as $p) {
            $items->execute(['p' => $p['id']]);
            $p['items'] = $items->fetchAll();
            $byEmployee[$p['employee_id']]['info'] = $p;
            $byEmployee[$p['employee_id']]['payrolls'][] = $p;
        }

        $xml = $this->remunerationXml($company, $competency, $byEmployee);
        $this->store($companyId, null, 'S-1200', $competency, $xml, $userId);

        return [true, 'S-1200 de '.$competency.' gerado com '.count($byEmployee).' trabalhador(es).'];
    }

    private function store(int $companyId, ?int $employeeId, string $type, string $reference, string $xml, int $userId): void
    {
        $this->db()->prepare(
            'INSERT INTO esocial_events (company_id, employee_id, event_type, reference, xml, created_by)
             VALUES (:c, :e, :t, :r, :x, :u)
             ON CONFLICT (company_id, event_type, reference)
             DO UPDATE SET xml = :x, created_by = :u, created_at = now(), status = \'generated\'',
        )->execute(['c' => $companyId, 'e' => $employeeId, 't' => $type, 'r' => $reference,
            'x' => $xml, 'u' => $userId]);
    }

    // ── XML builders ─────────────────────────────────────────────────────────

    private function admissionXml(array $e): string
    {
        $cnpj = preg_replace('/\D/', '', (string) $e['cnpj']);
        $cpf = preg_replace('/\D/', '', (string) $e['cpf']);
        $pis = preg_replace('/\D/', '', (string) ($e['pis'] ?? ''));
        $id = 'ID1'.$cnpj.date('YmdHis').str_pad((string) $e['id'], 5, '0', STR_PAD_LEFT);

        return $this->pretty(<<<XML
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAdmissao/v_S_01_03_00">
  <evtAdmissao Id="{$id}">
    <ideEvento><indRetif>1</indRetif><tpAmb>2</tpAmb><procEmi>1</procEmi><verProc>PeopleFlow MVP</verProc></ideEvento>
    <ideEmpregador><tpInsc>1</tpInsc><nrInsc>{$cnpj}</nrInsc></ideEmpregador>
    <trabalhador>
      <cpfTrab>{$cpf}</cpfTrab>
      <nmTrab>{$this->x($e['full_name'])}</nmTrab>
      <sexo>{$this->x(strtoupper(substr((string) ($e['gender'] ?? 'N'), 0, 1)))}</sexo>
      <dtNascto>{$this->x($e['birth_date'] ?? '')}</dtNascto>
      <nisTrab>{$pis}</nisTrab>
    </trabalhador>
    <vinculo>
      <matricula>{$this->x($e['registration'])}</matricula>
      <tpRegTrab>1</tpRegTrab><tpRegPrev>1</tpRegPrev>
      <infoRegimeTrab><infoCeletista>
        <dtAdm>{$this->x($e['hired_at'])}</dtAdm>
        <tpAdmissao>1</tpAdmissao><indAdmissao>1</indAdmissao><tpRegJor>1</tpRegJor><natAtividade>1</natAtividade>
      </infoCeletista></infoRegimeTrab>
      <infoContrato>
        <nmCargo>{$this->x($e['position_name'] ?? 'Não informado')}</nmCargo>
        <CBOCargo>{$this->x($e['cbo_code'] ?? '')}</CBOCargo>
        <remuneracao><vrSalFx>{$this->money((int) $e['salary_cents'])}</vrSalFx><undSalFixo>5</undSalFixo></remuneracao>
        <duracao><tpContr>1</tpContr></duracao>
      </infoContrato>
    </vinculo>
  </evtAdmissao>
</eSocial>
XML);
    }

    private function remunerationXml(array $company, string $competency, array $byEmployee): string
    {
        $cnpj = preg_replace('/\D/', '', (string) $company['cnpj']);
        $id = 'ID1'.$cnpj.date('YmdHis').'01200';
        $workers = '';

        foreach ($byEmployee as $data) {
            $info = $data['info'];
            $cpf = preg_replace('/\D/', '', (string) $info['cpf']);
            $dmDevs = '';
            foreach ($data['payrolls'] as $n => $p) {
                $itens = '';
                foreach ($p['items'] as $i) {
                    $type = $i['type'] === 'deduction' ? '2' : ($i['type'] === 'info' ? '3' : '1'); // tpRubr informativo
                    $itens .= "            <itensRemun><codRubr>{$this->x($i['rubric_code'])}<".'/codRubr>'
                        ."<ideTabRubr>PF01</ideTabRubr><tpRubr>{$type}</tpRubr>"
                        .'<vrRubr>'.$this->money((int) $i['amount_cents'])."</vrRubr></itensRemun>\n";
                }
                $ideDmDev = $this->x($p['kind']).'-'.($n + 1);
                $dmDevs .= "        <dmDev><ideDmDev>{$ideDmDev}</ideDmDev><codCateg>101</codCateg>\n"
                    ."          <infoPerApur><ideEstabLot><tpInsc>1</tpInsc><nrInsc>{$cnpj}</nrInsc><codLotacao>PF</codLotacao>\n"
                    ."            <remunPerApur><matricula>{$this->x($info['registration'])}</matricula>\n{$itens}"
                    ."            </remunPerApur></ideEstabLot></infoPerApur></dmDev>\n";
            }
            $workers .= "    <ideTrabalhador><cpfTrab>{$cpf}</cpfTrab></ideTrabalhador>\n{$dmDevs}";
        }

        return $this->pretty(<<<XML
<eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v_S_01_03_00">
  <evtRemun Id="{$id}">
    <ideEvento><indRetif>1</indRetif><indApuracao>1</indApuracao><perApur>{$competency}</perApur><tpAmb>2</tpAmb><procEmi>1</procEmi><verProc>PeopleFlow MVP</verProc></ideEvento>
    <ideEmpregador><tpInsc>1</tpInsc><nrInsc>{$cnpj}</nrInsc></ideEmpregador>
{$workers}  </evtRemun>
</eSocial>
XML);
    }

    private function x(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function pretty(string $xml): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$xml."\n";
    }
}
