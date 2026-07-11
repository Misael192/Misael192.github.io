/**
 * Auditoria transversal (ARCHITECTURE.md §7): toda mutação HTTP bem-sucedida
 * (POST/PUT/PATCH/DELETE) gera uma linha em audit_logs — quem, o quê, quando,
 * de onde. A tabela é append-only (trigger SQL impede UPDATE/DELETE).
 */
import { CallHandler, ExecutionContext, Injectable, Logger, NestInterceptor } from "@nestjs/common";
import { Observable, tap } from "rxjs";
import { TenantContext } from "../tenancy/tenant-context";
import { AuditService } from "./audit.service";

const MUTATING_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

@Injectable()
export class AuditInterceptor implements NestInterceptor {
  private readonly logger = new Logger(AuditInterceptor.name);

  constructor(
    private readonly audit: AuditService,
    private readonly tenantContext: TenantContext,
  ) {}

  intercept(context: ExecutionContext, next: CallHandler): Observable<unknown> {
    const req = context.switchToHttp().getRequest();
    if (!MUTATING_METHODS.has(req.method) || !this.tenantContext.get()) {
      return next.handle();
    }

    return next.handle().pipe(
      tap(() => {
        // Auditoria nunca derruba a requisição do usuário.
        this.audit
          .record({
            action: `${req.method} ${req.route?.path ?? req.path}`,
            entityType: req.path.split("/")[3] ?? "unknown", // /api/v1/<recurso>
            actorId: req.user?.sub,
            ipAddress: req.ip,
            userAgent: req.headers["user-agent"],
            // Corpo sem campos sensíveis — o service aplica o scrubbing.
            after: req.body,
          })
          .catch((err) => this.logger.error("Falha ao gravar auditoria", err));
      }),
    );
  }
}
