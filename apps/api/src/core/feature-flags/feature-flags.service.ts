/**
 * Feature flags e habilitação de módulos por tenant (ARCHITECTURE.md §11).
 * Um plano é, na prática, um conjunto de módulos + quotas — por isso a
 * verificação de módulo consulta TenantModule (alimentada pelo Billing).
 */
import { Injectable } from "@nestjs/common";
import { PrismaService } from "../prisma/prisma.service";
import { TenantContext } from "../tenancy/tenant-context";

@Injectable()
export class FeatureFlagsService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly tenantContext: TenantContext,
  ) {}

  /** Módulo comercial habilitado para o tenant atual? (people, recruitment…) */
  async isModuleEnabled(moduleCode: string): Promise<boolean> {
    const { tenantId } = this.tenantContext.getOrThrow();
    const tm = await this.prisma.tenantModule.findFirst({
      where: {
        tenantId,
        isEnabled: true,
        module: { code: moduleCode },
        OR: [{ expiresAt: null }, { expiresAt: { gt: new Date() } }],
      },
    });
    return Boolean(tm);
  }

  /** Flag granular: global → rollout percentual → override por tenant. */
  async isFlagEnabled(key: string): Promise<boolean> {
    const { tenantId } = this.tenantContext.getOrThrow();
    const flag = await this.prisma.featureFlag.findUnique({ where: { key } });
    if (!flag) return false;
    if (flag.disabledTenantIds.includes(tenantId)) return false;
    if (flag.enabledTenantIds.includes(tenantId)) return true;
    if (flag.enabledGlobally) return true;
    if (flag.rolloutPercentage > 0) {
      // Hash determinístico do tenant → mesmo tenant sempre no mesmo bucket.
      const bucket = [...tenantId].reduce((acc, c) => acc + c.charCodeAt(0), 0) % 100;
      return bucket < flag.rolloutPercentage;
    }
    return false;
  }
}
