/**
 * Guard global de autenticação: toda rota exige JWT válido, exceto as marcadas
 * com @Public(). Também valida que o tenant do token bate com o tenant da
 * requisição — um token de um tenant nunca funciona em outro.
 */
import { CanActivate, ExecutionContext, Injectable, UnauthorizedException } from "@nestjs/common";
import { Reflector } from "@nestjs/core";
import { JwtService } from "@nestjs/jwt";
import { TenantContext } from "../../tenancy/tenant-context";
import type { AccessTokenPayload } from "../auth.service";
import { IS_PUBLIC_KEY } from "../decorators/public.decorator";

@Injectable()
export class JwtAuthGuard implements CanActivate {
  constructor(
    private readonly jwt: JwtService,
    private readonly reflector: Reflector,
    private readonly tenantContext: TenantContext,
  ) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const isPublic = this.reflector.getAllAndOverride<boolean>(IS_PUBLIC_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);
    if (isPublic) return true;

    const request = context.switchToHttp().getRequest();
    const token = request.headers.authorization?.replace(/^Bearer\s+/i, "");
    if (!token) throw new UnauthorizedException("Token ausente");

    let payload: AccessTokenPayload;
    try {
      payload = await this.jwt.verifyAsync<AccessTokenPayload>(token, {
        secret: process.env.JWT_ACCESS_SECRET,
      });
    } catch {
      throw new UnauthorizedException("Token inválido ou expirado");
    }

    // Cross-tenant check: o tenant do token deve ser o tenant da requisição.
    const ctx = this.tenantContext.get();
    if (ctx && ctx.tenantId !== payload.tenantId) {
      throw new UnauthorizedException("Token não pertence a este tenant");
    }

    request.user = payload;
    return true;
  }
}
