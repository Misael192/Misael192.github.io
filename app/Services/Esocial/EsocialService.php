<?php

declare(strict_types=1);

namespace App\Services\Esocial;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\EsocialEvent;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Gerador de eventos eSocial a partir dos dados reais (migração de
 * mvp/app/services/Esocial). S-2200 (admissão) e S-1200 (remuneração da folha
 * FECHADA), XML nos leiautes evtAdmissao/evtRemun (versão simplificada). A
 * transmissão ao webservice (certificado A1) é a etapa seguinte — aqui o
 * evento fica gerado, versionado e auditável.
 */
class EsocialService
{
    /** Gera S-2200 para colaboradores ativos ainda sem evento. @return array{0: bool, 1: string} */
    public function generateAdmissions(Company $company, User $user): array
    {
        $existing = EsocialEvent::query()
            ->where('company_id', $company->id)
            ->where('event_type', EsocialEvent::TYPE_ADMISSION)
            ->pluck('reference')
            ->all();

        $employees = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', '!=', Employee::STATUS_TERMINATED)
            ->whereNotIn('registration_number', $existing)
            ->get();

        $count = 0;
        $skipped = [];
        foreach ($employees as $employee) {
            $contract = $this->activeContract($employee);
            if ($employee->cpf === null || $contract === null || $contract->salary_cents === null) {
                $skipped[] = $employee->full_name;

                continue;
            }

            $this->store($company, $employee->id, EsocialEvent::TYPE_ADMISSION, $employee->registration_number,
                $this->admissionXml($company, $employee, $contract), $user);
            $count++;
        }

        $message = "S-2200 gerado para {$count} colaborador(es).";
        if ($skipped !== []) {
            $message .= ' Sem CPF/salário (pendências de cadastro): '.implode(', ', $skipped).'.';
        }

        return [$count > 0, $message];
    }

    /** Gera S-1200 da competência (exige folha FECHADA). @return array{0: bool, 1: string} */
    public function generateRemuneration(Company $company, string $competency, User $user): array
    {
        $period = PayrollPeriod::query()
            ->where('company_id', $company->id)
            ->where('competency', $competency)
            ->first();

        if ($period === null) {
            return [false, "Não há folha calculada em {$competency}."];
        }
        if ($period->status !== PayrollPeriod::STATUS_CLOSED) {
            return [false, "A folha de {$competency} precisa estar FECHADA para gerar o S-1200 (está: {$period->status})."];
        }

        $payrolls = Payroll::query()
            ->where('period_id', $period->id)
            ->with(['items', 'employee'])
            ->get();

        if ($payrolls->isEmpty()) {
            return [false, "Folha de {$competency} não tem colaboradores calculados."];
        }

        $byEmployee = $payrolls->groupBy('employee_id');
        $xml = $this->remunerationXml($company, $competency, $byEmployee);
        $this->store($company, null, EsocialEvent::TYPE_REMUNERATION, $competency, $xml, $user);

        return [true, 'S-1200 de '.$competency.' gerado com '.$byEmployee->count().' trabalhador(es).'];
    }

    private function activeContract(Employee $employee): ?EmploymentContract
    {
        return EmploymentContract::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->first();
    }

    private function store(Company $company, ?string $employeeId, string $type, string $reference, string $xml, User $user): void
    {
        EsocialEvent::query()->updateOrCreate(
            ['company_id' => $company->id, 'event_type' => $type, 'reference' => $reference],
            ['employee_id' => $employeeId, 'xml' => $xml, 'created_by_id' => $user->id, 'status' => 'generated'],
        );
    }

    // ── XML builders ─────────────────────────────────────────────────────────

    private function admissionXml(Company $company, Employee $employee, EmploymentContract $contract): string
    {
        $cnpj = $this->digits($company->cnpj);
        $cpf = $this->digits($employee->cpf);
        $position = $employee->position_id ? Position::query()->find($employee->position_id) : null;
        $id = 'ID1'.$cnpj.now()->format('YmdHis').substr(preg_replace('/\D/', '', $employee->id), 0, 5);

        return $this->pretty(<<<XML
        <eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtAdmissao/v_S_01_03_00">
          <evtAdmissao Id="{$id}">
            <ideEvento><indRetif>1</indRetif><tpAmb>2</tpAmb><procEmi>1</procEmi><verProc>PeopleFlow</verProc></ideEvento>
            <ideEmpregador><tpInsc>1</tpInsc><nrInsc>{$cnpj}</nrInsc></ideEmpregador>
            <trabalhador>
              <cpfTrab>{$cpf}</cpfTrab>
              <nmTrab>{$this->x($employee->full_name)}</nmTrab>
              <dtNascto>{$this->x($this->date($employee->birth_date))}</dtNascto>
            </trabalhador>
            <vinculo>
              <matricula>{$this->x($employee->registration_number)}</matricula>
              <tpRegTrab>1</tpRegTrab><tpRegPrev>1</tpRegPrev>
              <infoRegimeTrab><infoCeletista>
                <dtAdm>{$this->x($this->date($employee->hired_at))}</dtAdm>
                <tpAdmissao>1</tpAdmissao><indAdmissao>1</indAdmissao><tpRegJor>1</tpRegJor><natAtividade>1</natAtividade>
              </infoCeletista></infoRegimeTrab>
              <infoContrato>
                <nmCargo>{$this->x($position->title ?? 'Não informado')}</nmCargo>
                <CBOCargo>{$this->x($position->cbo_code ?? '')}</CBOCargo>
                <remuneracao><vrSalFx>{$this->money((int) $contract->salary_cents)}</vrSalFx><undSalFixo>5</undSalFixo></remuneracao>
                <duracao><tpContr>1</tpContr></duracao>
              </infoContrato>
            </vinculo>
          </evtAdmissao>
        </eSocial>
        XML);
    }

    private function remunerationXml(Company $company, string $competency, $byEmployee): string
    {
        $cnpj = $this->digits($company->cnpj);
        $id = 'ID1'.$cnpj.now()->format('YmdHis').'01200';
        $workers = '';

        foreach ($byEmployee as $payrolls) {
            $employee = $payrolls->first()->employee;
            $cpf = $this->digits($employee?->cpf);
            $dmDevs = '';
            foreach ($payrolls->values() as $n => $payroll) {
                $itens = '';
                foreach ($payroll->items as $item) {
                    $type = $item->type === 'deduction' ? '2' : ($item->type === 'info' ? '3' : '1');
                    $itens .= '            <itensRemun><codRubr>'.$this->x($item->rubric_code).'</codRubr>'
                        .'<ideTabRubr>PF01</ideTabRubr><tpRubr>'.$type.'</tpRubr>'
                        .'<vrRubr>'.$this->money((int) $item->amount_cents)."</vrRubr></itensRemun>\n";
                }
                $ideDmDev = $this->x($payroll->kind).'-'.($n + 1);
                $dmDevs .= "        <dmDev><ideDmDev>{$ideDmDev}</ideDmDev><codCateg>101</codCateg>\n"
                    ."          <infoPerApur><ideEstabLot><tpInsc>1</tpInsc><nrInsc>{$cnpj}</nrInsc><codLotacao>PF</codLotacao>\n"
                    .'            <remunPerApur><matricula>'.$this->x($employee?->registration_number ?? '')."</matricula>\n{$itens}"
                    ."            </remunPerApur></ideEstabLot></infoPerApur></dmDev>\n";
            }
            $workers .= "    <ideTrabalhador><cpfTrab>{$cpf}</cpfTrab></ideTrabalhador>\n{$dmDevs}";
        }

        return $this->pretty(<<<XML
        <eSocial xmlns="http://www.esocial.gov.br/schema/evt/evtRemun/v_S_01_03_00">
          <evtRemun Id="{$id}">
            <ideEvento><indRetif>1</indRetif><indApuracao>1</indApuracao><perApur>{$competency}</perApur><tpAmb>2</tpAmb><procEmi>1</procEmi><verProc>PeopleFlow</verProc></ideEvento>
            <ideEmpregador><tpInsc>1</tpInsc><nrInsc>{$cnpj}</nrInsc></ideEmpregador>
        {$workers}  </evtRemun>
        </eSocial>
        XML);
    }

    private function digits(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    private function date($value): string
    {
        return $value instanceof Carbon ? $value->format('Y-m-d') : (string) ($value ?? '');
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
