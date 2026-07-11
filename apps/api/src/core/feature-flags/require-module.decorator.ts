import { SetMetadata } from "@nestjs/common";

export const REQUIRED_MODULE_KEY = "requiredModule";

/**
 * Exige que o módulo comercial esteja habilitado para o tenant.
 * Ex.: @RequireModule("recruitment") no controller de vagas.
 */
export const RequireModule = (moduleCode: string) =>
  SetMetadata(REQUIRED_MODULE_KEY, moduleCode);
