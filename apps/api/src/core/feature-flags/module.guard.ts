import { CanActivate, ExecutionContext, ForbiddenException, Injectable } from "@nestjs/common";
import { Reflector } from "@nestjs/core";
import { FeatureFlagsService } from "./feature-flags.service";
import { REQUIRED_MODULE_KEY } from "./require-module.decorator";

/** Bloqueia rotas de módulos não contratados pelo tenant (upsell na resposta). */
@Injectable()
export class ModuleGuard implements CanActivate {
  constructor(
    private readonly reflector: Reflector,
    private readonly flags: FeatureFlagsService,
  ) {}

  async canActivate(context: ExecutionContext): Promise<boolean> {
    const moduleCode = this.reflector.getAllAndOverride<string>(REQUIRED_MODULE_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);
    if (!moduleCode) return true;

    if (!(await this.flags.isModuleEnabled(moduleCode))) {
      throw new ForbiddenException(
        `O módulo "${moduleCode}" não está habilitado para esta empresa`,
      );
    }
    return true;
  }
}
