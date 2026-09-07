# Bina Schools — Multi-Tenant Architecture & Monorepo

A modern, high-performance multi-tenant school management platform built with **Laravel 11 API**, **Sanctum**, and **Vue 3 + Vite + Pinia + TailwindCSS SPA**.

---

## 🏛️ Architecture Overview

- **Single Database Multi-Tenancy**: All tenants share a single PostgreSQL/SQLite database with column-level tenant scoping (`school_id`).
- **Global Eloquent Scope**: All tenant-owned models implement `TenantScoped`, enforcing strict automatic query filtering and auto-assignment on creation.
- **Tenant Context Enforcement**: Querying any tenant model without an active tenant throws a `TenantContextRequiredException`.
- **Dual Resolution Channels**: Middleware resolves the active school tenant from:
  1. `X-School-Id` HTTP Request Header (supports numeric ID or subdomain string).
  2. Subdomain hostname (e.g. `greenwood.localhost` or `oakridge.bina-schools.test`).
- **Standardized API Envelope**: Unified JSON:API-like envelope format for success, paginated listings, validation failures, and exceptions.
- **Vue 3 SPA**: Reactive single-page application with Pinia store management, automatic Axios tenant context injection, dynamic school switching, and health inspection.

---

## 📁 Repository Structure

```
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/     # HealthController, SchoolController, CourseController
│   │   ├── Middleware/          # ResolveTenant, RequireTenant
│   │   ├── Requests/            # BaseApiRequest, StoreCourseRequest
│   │   ├── Responses/           # ApiResponse envelope helper
│   │   └── Traits/              # HasApiResponse
│   ├── Models/                  # School, Course (TenantScoped), User
│   ├── Providers/               # AppServiceProvider (TenantManager singleton)
│   └── Tenancy/
│       ├── Exceptions/          # TenantContextRequiredException, TenantNotFoundException
│       ├── Scopes/              # TenantScope (GlobalScope)
│       ├── Traits/              # TenantScoped
│       └── TenantManager.php    # Active tenant context & bypass manager
├── database/
│   ├── migrations/              # schools, courses, users (with school_id)
│   └── seeders/                 # SchoolSeeder, DatabaseSeeder
├── resources/
│   ├── css/app.css              # TailwindCSS
│   ├── js/
│   │   ├── views/               # DashboardView, SchoolsView, CoursesView, HealthView
│   │   ├── stores/              # Pinia tenant & courses stores
│   │   ├── router/              # Vue Router
│   │   ├── App.vue              # Root SPA layout
│   │   └── app.js               # SPA entrypoint
│   └── views/app.blade.php      # Blade SPA host template
├── tests/Feature/               # TenantScopingTest, TenantResolutionTest, HealthCheckTest, ApiResponseFormatTest
├── docker-compose.yml           # Docker Compose (app, postgres, redis)
├── Dockerfile                   # PHP 8.3 CLI + Node + Composer image
└── .env.example                 # Environment configuration template
```

---

## 🚀 Quick Start (Local Development)

### 1. Environment & Setup

```bash
cp .env.example .env
php artisan key:generate
composer install
npm install
```

### 2. Database Migrations & Seeders

Seed two isolated schools (**Greenwood High** and **Oakridge Academy**) with tenant data:

```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

### 3. Run Dev Server

```bash
# Terminal 1: Backend API & SPA
php artisan serve

# Terminal 2: Frontend Vite
npm run dev
```

Visit [http://localhost:8000](http://localhost:8000) to open the interactive SPA dashboard.

---

## 🐳 Docker Compose Setup

Run the entire stack with PostgreSQL and Redis:

```bash
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Services:
- **`app`**: PHP 8.3 CLI + Node + Vite on ports `8000` & `5173`
- **`postgres`**: PostgreSQL 16 on port `5432`
- **`redis`**: Redis 7 on port `6379`

---

## 🧪 Testing Multi-Tenancy & Isolation

Run the automated test suite:

```bash
php artisan test
```

### Manual Verification via Tinker

```bash
# 1. Querying without tenant context throws TenantContextRequiredException
php artisan tinker --execute="App\Models\Course::all();"
# Result: App\Tenancy\Exceptions\TenantContextRequiredException: Cannot query tenant-scoped model [App\Models\Course] without an active tenant context.

# 2. Act as Greenwood High
php artisan tinker --execute="
\$school = App\Models\School::where('subdomain', 'greenwood')->first();
app(App\Tenancy\TenantManager::class)->setTenant(\$school);
dump(App\Models\Course::pluck('name'));
"

# 3. Act as Oakridge Academy
php artisan tinker --execute="
\$school = App\Models\School::where('subdomain', 'oakridge')->first();
app(App\Tenancy\TenantManager::class)->setTenant(\$school);
dump(App\Models\Course::pluck('name'));
"
```

---

## 📡 API Endpoints & Response Envelope

### Standard Success Format:
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "timestamp": "2026-09-07T10:45:00+00:00",
    "version": "v1",
    "tenant": {
      "id": 1,
      "name": "Greenwood High",
      "subdomain": "greenwood"
    }
  }
}
```

### Health Check Endpoint:
- **`GET /api/health`** (with header `X-School-Id: greenwood` or `1`)
  - Returns `200 OK` with tenant context resolved.
- **`GET /api/tenant/health`**
  - Strict tenant endpoint guarded by `tenant.require`.
