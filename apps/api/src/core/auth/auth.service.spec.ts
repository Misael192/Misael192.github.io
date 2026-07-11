/**
 * Testes unitários do AuthService — foco no comportamento de segurança:
 * rotação de refresh token e detecção de reuso (ADR-005).
 */
import { UnauthorizedException } from "@nestjs/common";
import { JwtService } from "@nestjs/jwt";
import { createHash } from "node:crypto";
import { AuthService } from "./auth.service";

const hash = (v: string) => createHash("sha256").update(v).digest("hex");

function buildService(overrides: { session?: unknown } = {}) {
  const prisma = {
    session: {
      findUnique: jest.fn().mockResolvedValue(overrides.session ?? null),
      update: jest.fn().mockResolvedValue({}),
      updateMany: jest.fn().mockResolvedValue({}),
      create: jest.fn(),
    },
    user: { findUnique: jest.fn(), update: jest.fn() },
  };
  const jwt = { signAsync: jest.fn().mockResolvedValue("signed.jwt") } as unknown as JwtService;
  const tenantContext = { getOrThrow: () => ({ tenantId: "t-1", isolationLevel: "COLUMN" }) };
  const eventBus = { publish: jest.fn() };

  const service = new AuthService(prisma as never, jwt, tenantContext as never, eventBus as never);
  return { service, prisma };
}

describe("AuthService.refresh", () => {
  const baseSession = {
    id: "s-1",
    tenantId: "t-1",
    userId: "u-1",
    revokedAt: null,
    expiresAt: new Date(Date.now() + 60_000),
    user: { email: "a@b.com" },
  };

  it("rotaciona o token quando o segredo atual é apresentado", async () => {
    const { service, prisma } = buildService({
      session: { ...baseSession, refreshTokenHash: hash("secret-atual") },
    });

    const pair = await service.refresh("s-1.secret-atual");

    expect(pair.accessToken).toBe("signed.jwt");
    // O novo refresh token pertence à mesma sessão, mas com segredo novo.
    expect(pair.refreshToken.startsWith("s-1.")).toBe(true);
    expect(pair.refreshToken).not.toBe("s-1.secret-atual");
    expect(prisma.session.update).toHaveBeenCalledWith(
      expect.objectContaining({ where: { id: "s-1" } }),
    );
  });

  it("revoga a sessão inteira quando um token antigo é reusado", async () => {
    const { service, prisma } = buildService({
      session: { ...baseSession, refreshTokenHash: hash("segredo-novo") },
    });

    await expect(service.refresh("s-1.segredo-antigo")).rejects.toThrow(UnauthorizedException);
    expect(prisma.session.update).toHaveBeenCalledWith({
      where: { id: "s-1" },
      data: { revokedAt: expect.any(Date) },
    });
  });

  it("rejeita sessão expirada", async () => {
    const { service } = buildService({
      session: { ...baseSession, expiresAt: new Date(Date.now() - 1), refreshTokenHash: hash("x") },
    });
    await expect(service.refresh("s-1.x")).rejects.toThrow(UnauthorizedException);
  });

  it("rejeita token malformado", async () => {
    const { service } = buildService();
    await expect(service.refresh("sem-separador")).rejects.toThrow(UnauthorizedException);
  });
});
