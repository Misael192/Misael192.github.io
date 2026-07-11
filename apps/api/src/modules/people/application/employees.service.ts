/**
 * Use cases de colaboradores. Toda escrita publica o evento de domínio
 * correspondente — é assim que Benefits, Payroll e o Workflow Engine reagem
 * à vida do colaborador sem acoplamento (ADR-004).
 */
import { Injectable, NotFoundException } from "@nestjs/common";
import type { Employee } from "@peopleflow/database";
import { EventBus } from "../../../core/events/event-bus";
import { PrismaService } from "../../../core/prisma/prisma.service";
import { TenantContext } from "../../../core/tenancy/tenant-context";
import type { CreateEmployeeDto, UpdateEmployeeDto } from "../presentation/employees.dto";

@Injectable()
export class EmployeesService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly tenantContext: TenantContext,
    private readonly eventBus: EventBus,
  ) {}

  async list(params: { companyId?: string; status?: string; cursor?: string; take?: number }): Promise<Employee[]> {
    return this.prisma.forTenant((tx) =>
      tx.employee.findMany({
        where: {
          deletedAt: null,
          companyId: params.companyId,
          status: params.status as never,
        },
        orderBy: { createdAt: "desc" },
        take: Math.min(params.take ?? 50, 100),
        ...(params.cursor ? { cursor: { id: params.cursor }, skip: 1 } : {}),
      }),
    );
  }

  async findById(id: string): Promise<Employee> {
    const employee = await this.prisma.forTenant((tx) =>
      tx.employee.findFirst({ where: { id, deletedAt: null } }),
    );
    if (!employee) throw new NotFoundException("Colaborador não encontrado");
    return employee;
  }

  async create(dto: CreateEmployeeDto): Promise<Employee> {
    const { tenantId } = this.tenantContext.getOrThrow();
    const employee = await this.prisma.forTenant((tx) =>
      tx.employee.create({
        data: { ...dto, tenantId, status: "ADMISSION" },
      }),
    );
    // Dispara a admissão digital: checklist, documentos e workflow do tenant.
    await this.eventBus.publish({
      type: "employee.created.v1",
      tenantId,
      payload: { id: employee.id, companyId: employee.companyId },
    });
    return employee;
  }

  async update(id: string, dto: UpdateEmployeeDto): Promise<Employee> {
    const { tenantId } = this.tenantContext.getOrThrow();
    await this.findById(id); // garante existência + tenant correto
    const employee = await this.prisma.forTenant((tx) =>
      tx.employee.update({ where: { id }, data: dto }),
    );
    await this.eventBus.publish({
      type: "employee.updated.v1",
      tenantId,
      payload: { id: employee.id },
    });
    return employee;
  }
}
