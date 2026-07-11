/**
 * Event Bus da plataforma (ADR-004).
 *
 * Contrato estável que os módulos usam para publicar/assinar eventos de domínio.
 * Implementação atual: outbox no Postgres (mesma transação do agregado) +
 * despacho in-process via EventEmitter2. Quando houver réplicas horizontais,
 * o dispatcher troca o transporte para Redis Streams — os módulos não mudam.
 */
import { Injectable, Logger } from "@nestjs/common";
import { EventEmitter2 } from "@nestjs/event-emitter";
import { Cron, CronExpression } from "@nestjs/schedule";
import { PrismaService } from "../prisma/prisma.service";

/** Evento de domínio. `type` é versionado: "vacation.approved.v1". */
export interface DomainEvent<T = unknown> {
  type: string;
  tenantId: string;
  payload: T;
}

@Injectable()
export class EventBus {
  private readonly logger = new Logger(EventBus.name);

  constructor(
    private readonly prisma: PrismaService,
    private readonly emitter: EventEmitter2,
  ) {}

  /**
   * Publica gravando primeiro no outbox — se o processo cair antes do despacho,
   * o dispatcher reprocessa. Handlers DEVEM ser idempotentes.
   */
  async publish<T>(event: DomainEvent<T>): Promise<void> {
    await this.prisma.eventOutbox.create({
      data: { tenantId: event.tenantId, type: event.type, payload: event.payload as object },
    });
    // Despacho otimista imediato; o cron abaixo é a rede de segurança.
    this.emitter.emit(event.type, event);
  }

  subscribe<T>(type: string, handler: (event: DomainEvent<T>) => Promise<void> | void): void {
    this.emitter.on(type, handler);
  }

  /** Rede de segurança: redespacha eventos que ficaram sem publicação. */
  @Cron(CronExpression.EVERY_30_SECONDS)
  async dispatchPending(): Promise<void> {
    const pending = await this.prisma.eventOutbox.findMany({
      where: { publishedAt: null, attempts: { lt: 10 } },
      orderBy: { createdAt: "asc" },
      take: 100,
    });
    for (const row of pending) {
      try {
        this.emitter.emit(row.type, { type: row.type, tenantId: row.tenantId, payload: row.payload });
        await this.prisma.eventOutbox.update({
          where: { id: row.id },
          data: { publishedAt: new Date() },
        });
      } catch (err) {
        this.logger.error(`Falha ao despachar evento ${row.type}`, err as Error);
        await this.prisma.eventOutbox.update({
          where: { id: row.id },
          data: { attempts: { increment: 1 } },
        });
      }
    }
  }
}
