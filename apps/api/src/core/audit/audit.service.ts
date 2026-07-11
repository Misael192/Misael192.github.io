import { Injectable } from "@nestjs/common";
import { PrismaService } from "../prisma/prisma.service";
import { TenantContext } from "../tenancy/tenant-context";

/** Campos que jamais podem aparecer na trilha de auditoria (LGPD). */
const SENSITIVE_KEYS = new Set([
  "password", "passwordHash", "refreshToken", "secret", "cpf", "rg", "bankInfo", "mfaCode",
]);

export interface AuditEntry {
  action: string;
  entityType: string;
  entityId?: string;
  actorId?: string;
  actorType?: "user" | "api_key" | "system";
  before?: unknown;
  after?: unknown;
  ipAddress?: string;
  userAgent?: string;
}

@Injectable()
export class AuditService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly tenantContext: TenantContext,
  ) {}

  async record(entry: AuditEntry): Promise<void> {
    const { tenantId } = this.tenantContext.getOrThrow();
    await this.prisma.auditLog.create({
      data: {
        tenantId,
        action: entry.action,
        entityType: entry.entityType,
        entityId: entry.entityId,
        actorId: entry.actorId,
        actorType: entry.actorType ?? "user",
        before: this.scrub(entry.before) as object | undefined,
        after: this.scrub(entry.after) as object | undefined,
        ipAddress: entry.ipAddress,
        userAgent: entry.userAgent,
      },
    });
  }

  /** Remove recursivamente campos sensíveis antes de persistir. */
  private scrub(value: unknown): unknown {
    if (Array.isArray(value)) return value.map((v) => this.scrub(v));
    if (value && typeof value === "object") {
      return Object.fromEntries(
        Object.entries(value as Record<string, unknown>).map(([k, v]) =>
          SENSITIVE_KEYS.has(k) ? [k, "[REDACTED]"] : [k, this.scrub(v)],
        ),
      );
    }
    return value;
  }
}
