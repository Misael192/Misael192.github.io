# PeopleFlow MVP — PHP puro + PostgreSQL

Versão enxuta da PeopleFlow em **PHP 8.3+ sem framework** (MVC próprio), para
aprendizado e prototipagem rápida. Convive com a versão Laravel na raiz do
repositório — as duas compartilham o mesmo design system (PeopleFlow UI).

## Fase 1 (implementada)

- ✅ Landing page institucional
- ✅ Login com sessão segura (Argon2id, `session_regenerate_id`, CSRF, cookies HttpOnly/SameSite)
- ✅ Cadastro de empresas (validação de CNPJ e duplicidade)
- ✅ Cadastro de usuários (empresa + perfil RBAC)
- ✅ Dashboard inicial com KPIs reais do banco
- ✅ Auditoria de logins (tabela `sessions`)
- ✅ Banco: `companies`, `users`, `roles`, `permissions`, `departments`, `employees`, `sessions`

**Próximas fases:** módulos de DP (ponto, férias) e RH (recrutamento), conforme o roadmap.

## Estrutura

```
mvp/
├── public/            # Document root (Apache/XAMPP aponta aqui)
│   ├── index.php      # Landing (logado → dashboard)
│   ├── login.php · logout.php · dashboard.php · empresas.php · usuarios.php
│   └── assets/        # PeopleFlow UI (Tailwind/Alpine/FontAwesome/Inter locais)
├── app/
│   ├── bootstrap.php  # Autoload, config, sessão — incluído por toda página
│   ├── controllers/   # AuthController, DashboardController, CompanyController, UserController
│   ├── models/        # Database (PDO), Model base, User, Company, Role, Employee
│   ├── middleware/    # Auth (login obrigatório), Csrf (valida POSTs)
│   ├── services/      # AuthService (login/logout/hash)
│   ├── helpers/       # e(), view(), flash(), csrf_field()…
│   └── views/         # Templates PHP (layout + páginas)
├── config/            # app.php, database.php, auth.php
├── database/          # schema.sql e seeds.sql (PostgreSQL)
└── storage/           # logs, uploads, temp
```

## Como rodar

```bash
# 1. Banco (PostgreSQL; MySQL 8 suportado trocando DB_DRIVER/porta no config)
createdb peopleflow_mvp
psql -d peopleflow_mvp -f database/schema.sql
psql -d peopleflow_mvp -f database/seeds.sql

# 2. Servidor de desenvolvimento
php -S localhost:8091 -t public
# XAMPP/Laragon: aponte o virtual host para mvp/public
```

Login demo: **admin@demo.com** / **password**

## Segurança implementada

| Item | Como |
|---|---|
| Senhas | `password_hash` com **Argon2id** + rehash automático |
| Sessão | `session_regenerate_id` no login, cookie HttpOnly + SameSite=Lax |
| CSRF | Token por sessão validado em todo POST (`Csrf::verify`) |
| SQL Injection | 100% prepared statements (PDO, `EMULATE_PREPARES=false`) |
| XSS | Helper `e()` em toda saída das views |
| Enumeração | Mensagem de erro idêntica para e-mail ou senha errados |
