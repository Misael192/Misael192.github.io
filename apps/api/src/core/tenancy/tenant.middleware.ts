/**
 * Resolve o tenant da requisição, na seguinte ordem de precedência
 * (ARCHITECTURE.md §4): subdomínio → header X-Tenant-Id → claim do JWT.
 * Quando mais de uma fonte está presente, elas DEVEM concordar — divergência
 * é tratada como tentativa de cross-tenant e rejeitada.
 */
import { BadRequestException, Injectable, NestMiddleware } from "@nestjs/common";
import type { NextFunction, Request, Response } from "express";
import { PrismaService } from "../prisma/prisma.service";
import { TenantContext } from "./tenant-context";

/** Rotas sem tenant: health checks, docs e cadastro de novos tenants. */
const TENANTLESS_PATHS = [/^\/api\/docs/, /^\/api\/v\d+\/health/, /^\/api\/v\d+\/auth\/signup/];

@Injectable()
export class TenantMiddleware implements NestMiddleware {
  constructor(
    private readonly tenantContext: TenantContext,
    private readonly prisma: PrismaService,
  ) {}

  async use(req: Request, res: Response, next: NextFunction) {
    if (TENANTLESS_PATHS.some((p) => p.test(req.path))) return next();

    const fromHeader = req.header("x-tenant-id");
    const fromSubdomain = this.extractSubdomain(req.hostname);

    const slugOrId = fromHeader ?? fromSubdomain;
    if (!slugOrId) {
      throw new BadRequestException("Tenant não identificado (subdomínio ou X-Tenant-Id)");
    }
    if (fromHeader && fromSubdomain && fromHeader !== fromSubdomain) {
      throw new BadRequestException("Fontes de tenant divergentes");
    }

    // Lookup fora de RLS (tabela tenants é o ponto de entrada do isolamento).
    const tenant = await this.prisma.findTenant(slugOrId);
    if (!tenant || !tenant.isActive) {
      throw new BadRequestException("Tenant inválido ou inativo");
    }

    this.tenantContext.run(
      { tenantId: tenant.id, isolationLevel: tenant.isolationLevel },
      next,
    );
  }

  private extractSubdomain(hostname: string): string | undefined {
    // empresa.peopleflow.com.br → "empresa"; localhost não tem subdomínio.
    const parts = hostname.split(".");
    return parts.length > 2 ? parts[0] : undefined;
  }
}
