import { Body, Controller, Get, Param, ParseUUIDPipe, Patch, Post, Query, UseGuards } from "@nestjs/common";
import { ApiBearerAuth, ApiOperation, ApiTags } from "@nestjs/swagger";
import { ModuleGuard } from "../../../core/feature-flags/module.guard";
import { RequireModule } from "../../../core/feature-flags/require-module.decorator";
import { RequirePermissions } from "../../../core/rbac/permissions.decorator";
import { EmployeesService } from "../application/employees.service";
import { CreateEmployeeDto, UpdateEmployeeDto } from "./employees.dto";

@ApiTags("people")
@ApiBearerAuth()
@RequireModule("people")
@UseGuards(ModuleGuard)
@Controller({ path: "employees", version: "1" })
export class EmployeesController {
  constructor(private readonly employees: EmployeesService) {}

  @Get()
  @RequirePermissions("employees:read")
  @ApiOperation({ summary: "Lista colaboradores (paginação por cursor)" })
  list(
    @Query("companyId") companyId?: string,
    @Query("status") status?: string,
    @Query("cursor") cursor?: string,
  ) {
    return this.employees.list({ companyId, status, cursor });
  }

  @Get(":id")
  @RequirePermissions("employees:read")
  @ApiOperation({ summary: "Detalha um colaborador" })
  findOne(@Param("id", ParseUUIDPipe) id: string) {
    return this.employees.findById(id);
  }

  @Post()
  @RequirePermissions("employees:create")
  @ApiOperation({ summary: "Inicia a admissão digital de um colaborador" })
  create(@Body() dto: CreateEmployeeDto) {
    return this.employees.create(dto);
  }

  @Patch(":id")
  @RequirePermissions("employees:update")
  @ApiOperation({ summary: "Atualiza dados cadastrais" })
  update(@Param("id", ParseUUIDPipe) id: string, @Body() dto: UpdateEmployeeDto) {
    return this.employees.update(id, dto);
  }
}
