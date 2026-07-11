/**
 * Autenticação da PeopleFlow (ADR-005):
 *  - senhas: Argon2id
 *  - access token: JWT de 15 min com sub + tenantId
 *  - refresh token: opaco, hasheado, ROTACIONADO a cada uso; reuso de token
 *    antigo revoga a sessão inteira (detecção de roubo)
 *  - MFA: TOTP (RFC 6238) verificado após a senha
 */
import { Injectable, UnauthorizedException } from "@nestjs/common";
import { JwtService } from "@nestjs/jwt";
import * as argon2 from "argon2";
import { createHash, randomBytes } from "node:crypto";
import { authenticator } from "otplib";
import { EventBus } from "../events/event-bus";
import { PrismaService } from "../prisma/prisma.service";
import { TenantContext } from "../tenancy/tenant-context";

export interface AccessTokenPayload {
  sub: string; // userId
  tenantId: string;
  email: string;
}

export interface TokenPair {
  accessToken: string;
  refreshToken: string; // valor opaco "sessionId.secret" — só o hash vai ao banco
}

/** Parâmetros Argon2id recomendados (OWASP): 64 MB de memória, 3 iterações. */
const ARGON2_OPTIONS: argon2.Options = {
  type: argon2.argon2id,
  memoryCost: 64 * 1024,
  timeCost: 3,
};

@Injectable()
export class AuthService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly jwt: JwtService,
    private readonly tenantContext: TenantContext,
    private readonly eventBus: EventBus,
  ) {}

  hashPassword(plain: string): Promise<string> {
    return argon2.hash(plain, ARGON2_OPTIONS);
  }

  async login(email: string, password: string, mfaCode?: string, meta?: { ip?: string; userAgent?: string }): Promise<TokenPair> {
    const { tenantId } = this.tenantContext.getOrThrow();

    const user = await this.prisma.user.findUnique({
      where: { tenantId_email: { tenantId, email } },
    });
    // Mensagem idêntica para usuário inexistente e senha errada — evita enumeração.
    if (!user?.passwordHash || !user.isActive) {
      throw new UnauthorizedException("Credenciais inválidas");
    }
    const valid = await argon2.verify(user.passwordHash, password);
    if (!valid) throw new UnauthorizedException("Credenciais inválidas");

    if (user.mfaEnabled) {
      await this.verifyMfa(user.id, mfaCode);
    }

    const pair = await this.issueTokens(user.id, tenantId, user.email, meta);

    await this.prisma.user.update({ where: { id: user.id }, data: { lastLoginAt: new Date() } });
    await this.eventBus.publish({ type: "auth.user-logged-in.v1", tenantId, payload: { userId: user.id } });

    return pair;
  }

  /**
   * Rotação de refresh token. Se o token recebido não bate com o hash ATUAL da
   * sessão, alguém está reusando um token antigo → revoga a família inteira.
   */
  async refresh(refreshToken: string): Promise<TokenPair> {
    const [sessionId, secret] = refreshToken.split(".");
    if (!sessionId || !secret) throw new UnauthorizedException();

    const session = await this.prisma.session.findUnique({
      where: { id: sessionId },
      include: { user: true },
    });
    if (!session || session.revokedAt || session.expiresAt < new Date()) {
      throw new UnauthorizedException("Sessão expirada");
    }

    const incomingHash = this.hashToken(secret);
    if (incomingHash !== session.refreshTokenHash) {
      // Reuso detectado: token antigo apresentado. Revoga tudo.
      await this.prisma.session.update({
        where: { id: sessionId },
        data: { revokedAt: new Date() },
      });
      throw new UnauthorizedException("Reuso de refresh token detectado — sessão revogada");
    }

    // Rotaciona: novo segredo substitui o anterior na mesma sessão.
    const newSecret = randomBytes(32).toString("base64url");
    await this.prisma.session.update({
      where: { id: sessionId },
      data: { refreshTokenHash: this.hashToken(newSecret), rotationCounter: { increment: 1 } },
    });

    const accessToken = await this.signAccessToken({
      sub: session.userId,
      tenantId: session.tenantId,
      email: session.user.email,
    });
    return { accessToken, refreshToken: `${sessionId}.${newSecret}` };
  }

  async logout(refreshToken: string): Promise<void> {
    const [sessionId] = refreshToken.split(".");
    if (!sessionId) return;
    await this.prisma.session.updateMany({
      where: { id: sessionId, revokedAt: null },
      data: { revokedAt: new Date() },
    });
  }

  // ── internos ───────────────────────────────────────────────────────────────

  private async issueTokens(userId: string, tenantId: string, email: string, meta?: { ip?: string; userAgent?: string }): Promise<TokenPair> {
    const secret = randomBytes(32).toString("base64url");
    const expiresAt = new Date(Date.now() + this.refreshTtlMs());

    const session = await this.prisma.session.create({
      data: {
        tenantId,
        userId,
        refreshTokenHash: this.hashToken(secret),
        ipAddress: meta?.ip,
        userAgent: meta?.userAgent,
        expiresAt,
      },
    });

    const accessToken = await this.signAccessToken({ sub: userId, tenantId, email });
    return { accessToken, refreshToken: `${session.id}.${secret}` };
  }

  private signAccessToken(payload: AccessTokenPayload): Promise<string> {
    return this.jwt.signAsync(payload, {
      secret: process.env.JWT_ACCESS_SECRET,
      expiresIn: process.env.JWT_ACCESS_TTL ?? "15m",
    });
  }

  private async verifyMfa(userId: string, code?: string): Promise<void> {
    if (!code) throw new UnauthorizedException("Código MFA obrigatório");
    const cred = await this.prisma.mfaCredential.findFirst({
      where: { userId, verifiedAt: { not: null } },
    });
    // TODO(fase 2): decifrar `secret` com a ENCRYPTION_KEY (AES-256-GCM).
    if (!cred || !authenticator.verify({ token: code, secret: cred.secret })) {
      throw new UnauthorizedException("Código MFA inválido");
    }
  }

  /** SHA-256 é suficiente para tokens de alta entropia (não são senhas). */
  private hashToken(value: string): string {
    return createHash("sha256").update(value).digest("hex");
  }

  private refreshTtlMs(): number {
    const raw = process.env.JWT_REFRESH_TTL ?? "7d";
    const days = Number.parseInt(raw, 10) || 7;
    return days * 24 * 60 * 60 * 1000;
  }
}
