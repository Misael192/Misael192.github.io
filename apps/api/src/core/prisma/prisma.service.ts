/**
 * PrismaService — único ponto de acesso ao banco.
 *
 * É AQUI (e somente aqui) que a estratégia de multi-tenancy é aplicada:
 *  - COLUMN   → conexão compartilhada + `SET LOCAL app.tenant_id` (ativa a RLS)
 *  - SCHEMA   → conexão com search_path do tenant   (fase de escala)
 *  - DATABASE → client dedicado via Tenant.databaseUrl (fase de escala)
 *
 * Código de domínio usa `forTenant()` e nunca sabe qual estratégia está ativa.
 */
import { Injectable, OnModuleDestroy, OnModuleInit } from "@nestjs/common";
import { Prisma, PrismaClient, Tenant } from "@peopleflow/database";
import { TenantContext } from "../tenancy/tenant-context";

@Injectable()
export class PrismaService extends PrismaClient implements OnModuleInit, OnModuleDestroy {
  constructor(private readonly tenantContext: TenantContext) {
    super();
  }

  async onModuleInit() {
    await this.$connect();
  }

  async onModuleDestroy() {
    await this.$disconnect();
  }

  /** Lookup do tenant por slug ou id (fora da RLS — é o ponto de entrada). */
  findTenant(slugOrId: string): Promise<Tenant | null> {
    const byId = /^[0-9a-f-]{36}$/i.test(slugOrId);
    return this.tenant.findFirst({
      where: byId ? { id: slugOrId } : { slug: slugOrId },
    });
  }

  /**
   * Executa `fn` numa transação com o tenant atual fixado na sessão Postgres,
   * ativando as políticas de RLS (defesa em profundidade — ADR-002).
   */
  async forTenant<T>(fn: (tx: Prisma.TransactionClient) => Promise<T>): Promise<T> {
    const { tenantId } = this.tenantContext.getOrThrow();
    return this.$transaction(async (tx) => {
      await tx.$executeRawUnsafe(`SET LOCAL app.tenant_id = '${tenantId.replace(/'/g, "")}'`);
      return fn(tx);
    });
  }
}
