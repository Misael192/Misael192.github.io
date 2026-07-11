import { Global, Module } from "@nestjs/common";
import { AuditInterceptor } from "./audit.interceptor";
import { AuditService } from "./audit.service";

@Global()
@Module({
  providers: [AuditService, AuditInterceptor],
  exports: [AuditService],
})
export class AuditModule {}
