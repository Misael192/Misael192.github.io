-- ═══════════════════════════════════════════════════════════════════════════
-- PeopleFlow MVP — Seeds (Fase 1)
-- Papéis, permissões, empresa demo e usuário admin.
--   Login: admin@demo.com  ·  Senha: password  (hash Argon2id)
-- Aplicar com: psql -U peopleflow -d peopleflow_mvp -f database/seeds.sql
-- ═══════════════════════════════════════════════════════════════════════════

BEGIN;

INSERT INTO roles (code, name) VALUES
  ('admin',       'Administrador'),
  ('rh',          'Recursos Humanos'),
  ('dp',          'Departamento Pessoal'),
  ('gestor',      'Gestor'),
  ('colaborador', 'Colaborador')
ON CONFLICT (code) DO NOTHING;

INSERT INTO permissions (code, description) VALUES
  ('companies:manage',  'Gerenciar empresas'),
  ('users:manage',      'Gerenciar usuários'),
  ('employees:read',    'Ver colaboradores'),
  ('employees:manage',  'Gerenciar colaboradores'),
  ('departments:manage','Gerenciar departamentos')
ON CONFLICT (code) DO NOTHING;

-- admin recebe todas as permissões; rh/dp recebem as operacionais
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.code = 'admin'
ON CONFLICT DO NOTHING;

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
  ON p.code IN ('employees:read', 'employees:manage', 'departments:manage')
WHERE r.code IN ('rh', 'dp')
ON CONFLICT DO NOTHING;

-- Empresa demo
INSERT INTO companies (name, trade_name, cnpj, email)
VALUES ('Empresa Demonstração LTDA', 'PeopleFlow Demo', '00.000.000/0001-00', 'contato@demo.com')
ON CONFLICT (cnpj) DO NOTHING;

-- Usuário admin (senha: password)
INSERT INTO users (company_id, role_id, name, email, password)
SELECT c.id, r.id, 'Admin Demo', 'admin@demo.com',
       '$argon2id$v=19$m=65536,t=4,p=1$MWJQVUZFdmx2bkx2R1c1OA$StESJbjLUGBEJJQFrWGR6Ae0FVKdCAUeUwJnH9I0IVw'
FROM companies c, roles r
WHERE c.cnpj = '00.000.000/0001-00' AND r.code = 'admin'
ON CONFLICT (company_id, email) DO NOTHING;

-- Departamentos e colaboradores de exemplo (alimentam o dashboard)
INSERT INTO departments (company_id, name)
SELECT c.id, d.name FROM companies c,
  (VALUES ('Departamento Pessoal'), ('Recursos Humanos'), ('Tecnologia'), ('Comercial')) AS d(name)
WHERE c.cnpj = '00.000.000/0001-00'
ON CONFLICT DO NOTHING;

INSERT INTO employees (company_id, department_id, registration, full_name, position, status, hired_at)
SELECT c.id,
       (SELECT id FROM departments WHERE company_id = c.id AND name = e.dept),
       e.reg, e.nome, e.cargo, e.status, e.adm::date
FROM companies c,
  (VALUES
    ('00001', 'Ana Souza',       'Analista de DP',       'Departamento Pessoal', 'active',    '2022-03-15'),
    ('00002', 'Bruno Ferreira',  'Desenvolvedor Sênior', 'Tecnologia',           'vacation',  '2021-08-02'),
    ('00003', 'Carlos Lima',     'Assistente Comercial', 'Comercial',            'admission', '2026-07-01'),
    ('00004', 'Daniela Rocha',   'Coordenadora de RH',   'Recursos Humanos',     'active',    '2020-01-10'),
    ('00005', 'Fernanda Alves',  'Designer de Produto',  'Tecnologia',           'active',    '2024-11-05')
  ) AS e(reg, nome, cargo, dept, status, adm)
WHERE c.cnpj = '00.000.000/0001-00'
ON CONFLICT (company_id, registration) DO NOTHING;

COMMIT;
