import { Body, Controller, Param, ParseUUIDPipe, Post, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiOperation, ApiTags } from "@nestjs/swagger";
import { IsDateString, IsInt, IsOptional, IsUUID, Max, Min } from "class-validator";
import { CurrentUser } from "../../../core/auth/decorators/current-user.decorator";
import type { AccessTokenPayload } from "../../../core/auth/auth.service";
import { ModuleGuard } from "../../../core/feature-flags/module.guard";
import { RequireModule } from "../../../core/feature-flags/require-module.decorator";
import { RequirePermissions } from "../../../core/rbac/permissions.decorator";
import { VacationsService } from "../application/vacations.service";

class RequestVacationDto {
  @IsUUID()
  employeeId: string;

  @IsDateString()
  startDate: string;

  @IsDateString()
  endDate: string;

  /** Abono pecuniário: no máximo 10 dias (CLT, art. 143). */
  @IsOptional()
  @IsInt()
  @Min(0)
  @Max(10)
  sellDays?: number;
}

@ApiTags("people")
@ApiBearerAuth()
@RequireModule("people")
@UseGuards(ModuleGuard)
@Controller({ path: "vacations", version: "1" })
export class VacationsController {
  constructor(private readonly vacations: VacationsService) {}

  @Post()
  @RequirePermissions("vacations:request")
  @ApiOperation({ summary: "Solicita férias (dispara o workflow do tenant)" })
  request(@Body() dto: RequestVacationDto) {
    return this.vacations.request({
      employeeId: dto.employeeId,
      startDate: new Date(dto.startDate),
      endDate: new Date(dto.endDate),
      sellDays: dto.sellDays,
    });
  }

  @Post(":id/approve")
  @RequirePermissions("vacations:approve")
  @ApiOperation({ summary: "Aprova uma solicitação (gestor da equipe ou admin)" })
  approve(@Param("id", ParseUUIDPipe) id: string, @CurrentUser() user: AccessTokenPayload) {
    return this.vacations.approve(id, user.sub);
  }
}
