/**
 * Seed inicial da PeopleFlow:
 *  - catálogo de módulos comerciais
 *  - catálogo de permissões e grupos
 *  - planos de billing
 *  - tenant de demonstração com papéis de sistema
 */
import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();

const MODULES = [
  { code: "people", name: "Pessoas & DP", isCore: true },
  { code: "documents", name: "GED — Documentos", isCore: true },
  { code: "payroll", name: "Folha de Pagamento" },
  { code: "recruitment", name: "Recrutamento & Seleção" },
  { code: "benefits", name: "Benefícios" },
  { code: "learning", name: "Treinamentos" },
  { code: "performance", name: "Desempenho" },
  { code: "analytics", name: "Analytics" },
  { code: "ai", name: "Assistente de IA" },
  { code: "marketplace", name: "Marketplace" },
];

// Permissões no formato recurso:ação, agrupadas por módulo.
const PERMISSION_GROUPS: Record<string, string[]> = {
  people: [
    "employees:read", "employees:create", "employees:update", "employees:terminate",
    "vacations:read", "vacations:request", "vacations:approve",
    "time-entries:read", "time-entries:register", "time-entries:approve",
  ],
  documents: ["documents:read", "documents:upload", "documents:sign", "documents:share"],
  recruitment: ["jobs:read", "jobs:manage", "candidates:read", "candidates:manage"],
  benefits: ["benefits:read", "benefits:manage"],
  performance: ["reviews:read", "reviews:manage", "goals:read", "goals:manage"],
  learning: ["trainings:read", "trainings:manage"],
  admin: [
    "users:read", "users:manage", "roles:manage", "settings:manage",
    "billing:manage", "audit:read", "integrations:manage", "workflows:manage",
  ],
  ai: ["ai:chat", "ai:generate-documents"],
};

// Papéis de sistema e suas permissões (por prefixo de grupo ou código exato).
const SYSTEM_ROLES: Record<string, { name: string; permissions: string[] | "*" }> = {
  OWNER: { name: "Proprietário", permissions: "*" },
  ADMIN: { name: "Administrador", permissions: "*" },
  HR: {
    name: "RH",
    permissions: [
      ...PERMISSION_GROUPS.people, ...PERMISSION_GROUPS.recruitment,
      ...PERMISSION_GROUPS.benefits, ...PERMISSION_GROUPS.performance,
      ...PERMISSION_GROUPS.learning, ...PERMISSION_GROUPS.documents, ...PERMISSION_GROUPS.ai,
    ],
  },
  DP: {
    name: "Departamento Pessoal",
    permissions: [...PERMISSION_GROUPS.people, ...PERMISSION_GROUPS.documents, ...PERMISSION_GROUPS.ai],
  },
  MANAGER: {
    name: "Gestor",
    permissions: [
      "employees:read", "vacations:read", "vacations:approve",
      "time-entries:read", "time-entries:approve", "documents:read", "reviews:manage", "goals:manage",
    ],
  },
  EMPLOYEE: {
    name: "Colaborador",
    permissions: [
      "vacations:request", "time-entries:register", "documents:read",
      "documents:upload", "documents:sign", "trainings:read",
    ],
  },
};

const PLANS = [
  { code: "starter", name: "Starter", priceCents: 29900, moduleCodes: ["people", "documents"], limits: { maxEmployees: 50, maxUsers: 10, aiTokensMonth: 0, storageGb: 5 } },
  { code: "business", name: "Business", priceCents: 79900, moduleCodes: ["people", "documents", "recruitment", "benefits", "performance", "learning", "analytics"], limits: { maxEmployees: 300, maxUsers: 50, aiTokensMonth: 500000, storageGb: 50 } },
  { code: "enterprise", name: "Enterprise", priceCents: 0, moduleCodes: MODULES.map((m) => m.code), limits: { maxEmployees: -1, maxUsers: -1, aiTokensMonth: -1, storageGb: -1 } },
];

async function main() {
  // Catálogo de módulos
  for (const m of MODULES) {
    await prisma.module.upsert({ where: { code: m.code }, update: m, create: m });
  }

  // Grupos e permissões
  const allPermissionIds = new Map<string, string>();
  for (const [groupCode, codes] of Object.entries(PERMISSION_GROUPS)) {
    const group = await prisma.permissionGroup.upsert({
      where: { code: groupCode },
      update: {},
      create: { code: groupCode, name: groupCode },
    });
    for (const code of codes) {
      const p = await prisma.permission.upsert({
        where: { code },
        update: { groupId: group.id },
        create: { code, description: code, groupId: group.id },
      });
      allPermissionIds.set(code, p.id);
    }
  }

  // Planos
  for (const plan of PLANS) {
    await prisma.plan.upsert({ where: { code: plan.code }, update: plan, create: plan });
  }

  // Tenant demo + papéis de sistema
  const tenant = await prisma.tenant.upsert({
    where: { slug: "demo" },
    update: {},
    create: { slug: "demo", name: "Empresa Demonstração" },
  });

  for (const [code, def] of Object.entries(SYSTEM_ROLES)) {
    const role = await prisma.role.upsert({
      where: { tenantId_code: { tenantId: tenant.id, code } },
      update: {},
      create: { tenantId: tenant.id, code, name: def.name, isSystem: true },
    });
    const permIds =
      def.permissions === "*"
        ? [...allPermissionIds.values()]
        : def.permissions.map((c) => allPermissionIds.get(c)!).filter(Boolean);
    for (const permissionId of permIds) {
      await prisma.rolePermission.upsert({
        where: { roleId_permissionId: { roleId: role.id, permissionId } },
        update: {},
        create: { roleId: role.id, permissionId },
      });
    }
  }

  console.log("Seed concluído: módulos, permissões, planos e tenant demo.");
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(() => prisma.$disconnect());
