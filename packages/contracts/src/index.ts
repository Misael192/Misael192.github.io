/**
 * @peopleflow/contracts — contratos compartilhados entre api e web.
 * Tipos de eventos de domínio e DTOs públicos vivem aqui para que front e
 * back nunca divirjam silenciosamente.
 */

/** Catálogo de eventos de domínio (nomes versionados — ADR-004). */
export const DomainEvents = {
  EmployeeCreated: "employee.created.v1",
  EmployeeUpdated: "employee.updated.v1",
  VacationRequested: "vacation.requested.v1",
  VacationApproved: "vacation.approved.v1",
  TimeEntryRegistered: "time-entry.registered.v1",
  DocumentSigned: "document.signed.v1",
  PayrollGenerated: "payroll.generated.v1",
  PayslipSigned: "payslip.signed.v1",
  ESocialEventSent: "esocial.event-sent.v1",
  CandidateApplied: "candidate.applied.v1",
  WorkflowStepCompleted: "workflow.step.completed.v1",
  WorkflowCompleted: "workflow.completed.v1",
} as const;

export type DomainEventType = (typeof DomainEvents)[keyof typeof DomainEvents];

/** Códigos dos módulos comerciais (catálogo `Module` no banco). */
export type ModuleCode =
  | "people"
  | "documents"
  | "payroll"
  | "recruitment"
  | "benefits"
  | "learning"
  | "performance"
  | "analytics"
  | "ai"
  | "marketplace";

/** Papéis de sistema semeados em todo tenant. */
export type SystemRole = "OWNER" | "ADMIN" | "HR" | "DP" | "MANAGER" | "EMPLOYEE";
