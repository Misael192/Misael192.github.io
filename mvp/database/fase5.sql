-- ═══════════════════════════════════════════════════════════════════════════
-- PeopleFlow MVP · Fase 5 — Portal do Colaborador
-- Vincula o login (users) à ficha (employees): o colaborador enxerga apenas
-- o que é dele — holerites, férias, ponto e documentos. Idempotente.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE users ADD COLUMN IF NOT EXISTS employee_id INT REFERENCES employees(id);
CREATE INDEX IF NOT EXISTS idx_users_employee ON users(employee_id);

-- Usuária demo do portal: Ana Souza (perfil colaborador) · senha: password
INSERT INTO users (company_id, role_id, name, email, password, employee_id)
SELECT c.id, r.id, 'Ana Souza', 'ana@demo.com',
       '$argon2id$v=19$m=65536,t=4,p=1$MWJQVUZFdmx2bkx2R1c1OA$StESJbjLUGBEJJQFrWGR6Ae0FVKdCAUeUwJnH9I0IVw',
       e.id
FROM companies c, roles r, employees e
WHERE c.cnpj = '00.000.000/0001-00' AND r.code = 'colaborador' AND e.registration = '00001'
ON CONFLICT DO NOTHING;

-- Colaborador NÃO enxerga o GED da empresa inteira: o portal entrega apenas
-- os documentos do próprio vínculo (checagem por employee_id, sem RBAC).
DELETE FROM role_permissions rp
USING roles r, permissions p
WHERE rp.role_id = r.id AND rp.permission_id = p.id
  AND r.code = 'colaborador' AND p.code = 'documents:read';
