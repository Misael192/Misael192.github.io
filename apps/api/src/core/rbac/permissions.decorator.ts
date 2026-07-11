import { SetMetadata } from "@nestjs/common";

export const PERMISSIONS_KEY = "requiredPermissions";

/**
 * Declara as permissões exigidas por um handler, no formato `recurso:ação`.
 * Ex.: @RequirePermissions("vacations:approve")
 */
export const RequirePermissions = (...permissions: string[]) =>
  SetMetadata(PERMISSIONS_KEY, permissions);
