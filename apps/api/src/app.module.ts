import { MiddlewareConsumer, Module, NestModule } from "@nestjs/common";
import { ConfigModule } from "@nestjs/config";
import { APP_GUARD, APP_INTERCEPTOR } from "@nestjs/core";
import { EventEmitterModule } from "@nestjs/event-emitter";
import { ScheduleModule } from "@nestjs/schedule";
import { ThrottlerGuard, ThrottlerModule } from "@nestjs/throttler";
import { LoggerModule } from "nestjs-pino";

// Core — a fundação da plataforma (nunca depende dos Modules)
import { AuditInterceptor } from "./core/audit/audit.interceptor";
import { AuditModule } from "./core/audit/audit.module";
import { AuthModule } from "./core/auth/auth.module";
import { JwtAuthGuard } from "./core/auth/guards/jwt-auth.guard";
import { EventBusModule } from "./core/events/event-bus.module";
import { FeatureFlagsModule } from "./core/feature-flags/feature-flags.module";
import { HealthModule } from "./core/health/health.module";
import { PrismaModule } from "./core/prisma/prisma.module";
import { PermissionsGuard } from "./core/rbac/permissions.guard";
import { RbacModule } from "./core/rbac/rbac.module";
import { TenancyModule } from "./core/tenancy/tenancy.module";
import { TenantMiddleware } from "./core/tenancy/tenant.middleware";
import { WorkflowModule } from "./core/workflow/workflow.module";
import { AiModule } from "./core/ai/ai.module";

// Modules — funcionalidades vendáveis, habilitadas por feature flag/plano
import { PeopleModule } from "./modules/people/people.module";

@Module({
  imports: [
    ConfigModule.forRoot({ isGlobal: true }),
    LoggerModule.forRoot({
      pinoHttp: {
        level: process.env.NODE_ENV === "production" ? "info" : "debug",
        redact: ["req.headers.authorization", "req.headers.cookie"], // nunca logar credenciais
      },
    }),
    EventEmitterModule.forRoot(),
    ScheduleModule.forRoot(),
    // Rate limit global; limites finos por tenant/API key vivem no Redis (fase 2).
    ThrottlerModule.forRoot([{ ttl: 60_000, limit: 120 }]),

    // Core
    PrismaModule,
    TenancyModule,
    AuthModule,
    RbacModule,
    AuditModule,
    EventBusModule,
    FeatureFlagsModule,
    HealthModule,
    WorkflowModule,
    AiModule,

    // Modules
    PeopleModule,
  ],
  providers: [
    // Ordem importa: autenticação → autorização → rate limit → auditoria.
    { provide: APP_GUARD, useClass: JwtAuthGuard },
    { provide: APP_GUARD, useClass: PermissionsGuard },
    { provide: APP_GUARD, useClass: ThrottlerGuard },
    { provide: APP_INTERCEPTOR, useClass: AuditInterceptor },
  ],
})
export class AppModule implements NestModule {
  configure(consumer: MiddlewareConsumer) {
    // Resolve o tenant (subdomínio / header / JWT) antes de qualquer handler.
    consumer.apply(TenantMiddleware).forRoutes("*");
  }
}
