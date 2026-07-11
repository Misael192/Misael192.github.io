import { Controller, Get } from "@nestjs/common";
import { ApiTags } from "@nestjs/swagger";
import { Public } from "../auth/decorators/public.decorator";
import { PrismaService } from "../prisma/prisma.service";

/** Health checks para orquestradores e uptime monitoring (§14). */
@ApiTags("health")
@Controller({ path: "health", version: "1" })
export class HealthController {
  constructor(private readonly prisma: PrismaService) {}

  @Public()
  @Get("live")
  live() {
    return { status: "ok" };
  }

  @Public()
  @Get("ready")
  async ready() {
    // Readiness = dependências críticas alcançáveis.
    await this.prisma.$queryRaw`SELECT 1`;
    return { status: "ok", checks: { database: "up" } };
  }
}
