# ADR-006 — Workflow engine: grafo JSONB interpretado

**Status:** Aceito · **Data:** 2026-07-11

## Decisão
Templates de workflow são grafos (nodes + edges) em JSONB, editados visualmente no frontend
e executados por um interpretador orientado a eventos. Tipos de nó são plugáveis via registry
(trigger, condição, aprovação, documento, assinatura, webhook, agente de IA, fim).

## Justificativa
- Cada empresa desenha seus próprios fluxos — nada é hardcoded.
- O editor visual e o runtime compartilham o mesmo JSON (sem tradução com perda).
- Execução por eventos dá auditoria natural (`WorkflowStepExecution` por passo) e retomada
  após falhas.

## Alternativas consideradas
- **Temporal.io** — excelente durabilidade, mas infra e curva de aprendizado pesadas agora;
  o contrato do motor permite adotá-lo como backend de execução no futuro.
- **Camunda/BPMN** — poder de modelagem que o usuário-alvo (RH/DP) não consegue operar.
- **Fluxos fixos em código** — rejeitado explicitamente pelo requisito aprovado.
