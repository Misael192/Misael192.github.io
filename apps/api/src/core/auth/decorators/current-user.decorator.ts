import { createParamDecorator, ExecutionContext } from "@nestjs/common";
import type { AccessTokenPayload } from "../auth.service";

/** Injeta o payload do JWT autenticado no handler: `@CurrentUser() user`. */
export const CurrentUser = createParamDecorator(
  (_: unknown, ctx: ExecutionContext): AccessTokenPayload =>
    ctx.switchToHttp().getRequest().user,
);
