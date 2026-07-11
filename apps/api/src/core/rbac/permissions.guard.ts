/**
 * Guard global de RBAC. Handlers sem @RequirePermissions passam livres
 * (a autenticação já foi exigida pelo JwtAuthGuard).
 *
 * ABAC: condições por atributo (ex.: "gestor só aprova férias da própria
 * equipe") são verificadas nos use cases, POR CIMA deste guard — o RBAC
 * responde "pode aprovar férias?", o ABAC responde "destas férias?".
 */
import { CanActivate, ExecutionContext, ForbiddenException, Injectable } from "@nestjs/common";
import { Reflector } from "@nestjs/core";
import { RbacService } from "./rbac.service";
import { PERMISSIONS_KEY } from "./permissions.decorator";

@Injectable()
export class PermissionsGuard implements CanActivate {
  constructor(
    private readonly reflector: Reflector,
    private readonly rbac: RbacService,
  ) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const required = this.reflector.getAllAndOverride<string[]>(PERMISSIONS_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);
    if (!required?.length) return true;

    const { user } = context.switchToHttp().getRequest();
    if (!user) return false; // rota pública com @RequirePermissions é erro de programação

    const granted = await this.rbac.getPermissionsForUser(user.sub);
    const missing = required.filter((p) => !granted.has(p));
    if (missing.length) {
      throw new ForbiddenException(`Permissões ausentes: ${missing.join(", ")}`);
    }
    return true;
  }
}
