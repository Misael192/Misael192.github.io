/**
 * Bootstrap do API Gateway da PeopleFlow.
 * Aqui ficam as preocupações transversais: segurança de headers, versionamento
 * da API (/api/v1), validação global e documentação OpenAPI.
 */
import { ValidationPipe, VersioningType } from "@nestjs/common";
import { NestFactory } from "@nestjs/core";
import { DocumentBuilder, SwaggerModule } from "@nestjs/swagger";
import helmet from "helmet";
import { Logger } from "nestjs-pino";
import { AppModule } from "./app.module";

async function bootstrap() {
  const app = await NestFactory.create(AppModule, { bufferLogs: true });

  // Logs estruturados (JSON) com traceId/tenantId — ver ARCHITECTURE.md §14.
  app.useLogger(app.get(Logger));

  // Headers de segurança (CSP, HSTS, etc.) — ARCHITECTURE.md §7.
  app.use(
    helmet({
      contentSecurityPolicy: {
        directives: { defaultSrc: ["'self'"] },
      },
    }),
  );

  app.enableCors({
    origin: process.env.WEB_URL?.split(",") ?? true,
    credentials: true,
  });

  // API versionada por URI desde o dia 1: /api/v1/... (requisito aprovado).
  app.setGlobalPrefix("api");
  app.enableVersioning({ type: VersioningType.URI, defaultVersion: "1" });

  // Validação de entrada: rejeita payloads com campos desconhecidos.
  app.useGlobalPipes(
    new ValidationPipe({ whitelist: true, forbidNonWhitelisted: true, transform: true }),
  );

  // Documentação OpenAPI gerada do código, publicada em /api/docs.
  const openApiConfig = new DocumentBuilder()
    .setTitle("PeopleFlow API")
    .setDescription("Plataforma HCM multi-tenant — API pública v1")
    .setVersion("1.0")
    .addBearerAuth()
    .build();
  SwaggerModule.setup("api/docs", app, SwaggerModule.createDocument(app, openApiConfig));

  await app.listen(process.env.API_PORT ?? 3001);
}

bootstrap();
