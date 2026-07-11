/**
 * Resolve as permissões efetivas de um usuário (união das permissões de todos
 * os seus papéis). Cache em memória com TTL curto; na fase de réplicas o cache
 * migra para Redis com invalidação por evento `role.updated`.
 */
import { Injectable } from "@nestjs/common";
import { PrismaService } from "../prisma/prisma.service";

const CACHE_TTL_MS = 30_000;

@Injectable()
export class RbacService {
  private readonly cache = new Map<string, { permissions: Set<string>; expiresAt: number }>();

  constructor(private readonly prisma: PrismaService) {}

  async getPermissionsForUser(userId: string): Promise<Set<string>> {
    const cached = this.cache.get(userId);
    if (cached && cached.expiresAt > Date.now()) return cached.permissions;

    const userRoles = await this.prisma.userRole.findMany({
      where: { userId },
      include: { role: { include: { permissions: { include: { permission: true } } } } },
    });

    const permissions = new Set<string>();
    for (const ur of userRoles) {
      for (const rp of ur.role.permissions) permissions.add(rp.permission.code);
    }

    this.cache.set(userId, { permissions, expiresAt: Date.now() + CACHE_TTL_MS });
    return permissions;
  }

  /** Invalidação explícita (chamada por handlers de eventos de RBAC). */
  invalidate(userId: string): void {
    this.cache.delete(userId);
  }
}
