import { Global, Module } from "@nestjs/common";
import { TenantContext } from "./tenant-context";
import { TenantMiddleware } from "./tenant.middleware";

/** Global: o TenantContext é usado por praticamente todos os providers. */
@Global()
@Module({
  providers: [TenantContext, TenantMiddleware],
  exports: [TenantContext],
})
export class TenancyModule {}
