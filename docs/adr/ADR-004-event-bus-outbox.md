# ADR-004 — Event bus interno com padrão outbox

**Status:** Aceito · **Data:** 2026-07-11

## Decisão
Barramento de eventos desde a v1, atrás do port `EventBus`. Transporte inicial in-process
(`@nestjs/event-emitter`); eventos persistidos em tabela outbox na mesma transação do
agregado e despachados por um dispatcher assíncrono.

## Justificativa
- Eventos são a costura entre módulos (ex.: Benefits reage a `employee.created` sem acoplamento).
- O outbox garante entrega mesmo com crash entre commit e publish — requisito para eventos
  com consequência legal (eSocial, assinaturas).
- Trocar o transporte para Redis Streams/BullMQ quando houver réplicas é trocar um adapter;
  os módulos não mudam.

## Alternativas consideradas
- **Chamadas diretas entre módulos** — rejeitado: acopla e impede extração futura.
- **Kafka/RabbitMQ desde o início** — rejeitado: infraestrutura e operação desproporcionais;
  Redis já está na stack.

## Consequências
- Handlers devem ser **idempotentes** (eventos podem ser reentregues).
- Nomes de evento são versionados (`vacation.approved.v1`) e documentados em `docs/events/`.
