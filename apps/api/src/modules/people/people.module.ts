import { Module } from "@nestjs/common";
import { EmployeesController } from "./presentation/employees.controller";
import { EmployeesService } from "./application/employees.service";
import { VacationsController } from "./presentation/vacations.controller";
import { VacationsService } from "./application/vacations.service";

/**
 * Módulo People (DP) — primeira fatia vertical da plataforma.
 * Serve de referência de layout para todos os demais módulos:
 * presentation (HTTP) → application (use cases) → infra via PrismaService.
 * Comunicação com outros módulos: SOMENTE via EventBus.
 */
@Module({
  controllers: [EmployeesController, VacationsController],
  providers: [EmployeesService, VacationsService],
})
export class PeopleModule {}
