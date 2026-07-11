/**
 * TenantContext — coração do multi-tenancy (ADR-002).
 *
 * Usa AsyncLocalStorage para propagar o tenant da requisição por toda a pilha
 * (services, repositórios, handlers de evento) sem passar parâmetros à mão.
 * Nenhum código de domínio conhece a ESTRATÉGIA de isolamento; ele só pergunta
 * "qual é o tenant atual?".
 */
import { Injectable } from "@nestjs/common";
import { AsyncLocalStorage } from "node:async_hooks";

export interface TenantContextData {
  tenantId: string;
  /** COLUMN | SCHEMA | DATABASE — usado apenas pelo TenantConnectionResolver. */
  isolationLevel: "COLUMN" | "SCHEMA" | "DATABASE";
  userId?: string;
}

@Injectable()
export class TenantContext {
  private readonly storage = new AsyncLocalStorage<TenantContextData>();

  /** Executa `fn` com o tenant fixado no contexto assíncrono. */
  run<T>(data: TenantContextData, fn: () => T): T {
    return this.storage.run(data, fn);
  }

  get(): TenantContextData | undefined {
    return this.storage.getStore();
  }

  /** Lança se não houver tenant — protege contra queries "órfãs". */
  getOrThrow(): TenantContextData {
    const ctx = this.get();
    if (!ctx) {
      throw new Error(
        "Nenhum tenant no contexto — a rota passou pelo TenantMiddleware?",
      );
    }
    return ctx;
  }
}
