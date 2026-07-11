/**
 * Use cases de férias. A aprovação demonstra o padrão ABAC sobre RBAC:
 * o guard já garantiu `vacations:approve`; aqui verificamos o ATRIBUTO
 * (o aprovador gerencia este colaborador?).
 */
import { ForbiddenException, Injectable, NotFoundException } from "@nestjs/common";
import type { VacationRequest } from "@peopleflow/database";
import { EventBus } from "../../../core/events/event-bus";
import { PrismaService } from "../../../core/prisma/prisma.service";
import { TenantContext } from "../../../core/tenancy/tenant-context";

@Injectable()
export class VacationsService {
  constructor(
    private readonly prisma: PrismaService,
    private readonly tenantContext: TenantContext,
    private readonly eventBus: EventBus,
  ) {}

  async request(params: { employeeId: string; startDate: Date; endDate: Date; sellDays?: number }): Promise<VacationRequest> {
    const { tenantId } = this.tenantContext.getOrThrow();
    const days = Math.ceil(
      (params.endDate.getTime() - params.startDate.getTime()) / 86_400_000,
    ) + 1;

    const request = await this.prisma.forTenant((tx) =>
      tx.vacationRequest.create({
        data: {
          tenantId,
          employeeId: params.employeeId,
          startDate: params.startDate,
          endDate: params.endDate,
          days,
          sellDays: params.sellDays ?? 0,
        },
      }),
    );

    // O Workflow Engine do tenant decide o que acontece a partir daqui
    // (aprovação simples, dupla, geração de aviso, assinatura…).
    await this.eventBus.publish({
      type: "vacation.requested.v1",
      tenantId,
      payload: { id: request.id, employeeId: params.employeeId, days },
    });
    return request;
  }

  async approve(requestId: string, approverUserId: string): Promise<VacationRequest> {
    const { tenantId } = this.tenantContext.getOrThrow();

    const request = await this.prisma.forTenant((tx) =>
      tx.vacationRequest.findFirst({
        where: { id: requestId, status: "REQUESTED" },
        include: { employee: true },
      }),
    );
    if (!request) throw new NotFoundException("Solicitação não encontrada ou já decidida");

    // ABAC: o aprovador precisa ser o gestor do colaborador (ou admin).
    const approverEmployee = await this.prisma.forTenant((tx) =>
      tx.employee.findFirst({ where: { userId: approverUserId } }),
    );
    const isManagerOfEmployee = approverEmployee?.id === request.employee.managerId;
    const approverRoles = await this.prisma.userRole.findMany({
      where: { userId: approverUserId },
      include: { role: true },
    });
    const isAdmin = approverRoles.some((r) => ["OWNER", "ADMIN", "DP", "HR"].includes(r.role.code));
    if (!isManagerOfEmployee && !isAdmin) {
      throw new ForbiddenException("Você só pode aprovar férias da sua própria equipe");
    }

    const updated = await this.prisma.forTenant((tx) =>
      tx.vacationRequest.update({
        where: { id: requestId },
        data: { status: "APPROVED", approvedById: approverUserId, decidedAt: new Date() },
      }),
    );

    await this.eventBus.publish({
      type: "vacation.approved.v1",
      tenantId,
      payload: { id: updated.id, employeeId: updated.employeeId },
    });
    return updated;
  }
}
