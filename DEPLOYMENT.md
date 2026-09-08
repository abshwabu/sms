# Bina Schools Multi-Tenant SMS — Production Deployment & Demo Guide

This document provides complete instructions for deploying the **Bina Schools** multi-tenant School Management System (SMS) into production environments, setting up background workers and scheduled tasks, executing automated tenant-level backups, and exploring the system across all five user roles.

---

## 1. System Architecture & Prerequisites

### Technical Stack
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: PostgreSQL 15+ or MySQL 8.0+ (SQLite supported in local dev and tests)
- **Cache & Queue**: Redis 7.0+
- **Reverse Proxy**: Nginx with SSL wildcard support (`*.bina.example.com`)
- **Process Manager**: Supervisor for queue workers and Horizon

### Server Requirements
- PHP 8.2+ with extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql` / `pdo_pgsql`, `redis`, `tokenizer`, `xml`, `zip`.
- Composer 2.7+
- Node.js 20+ & NPM

---

## 2. Production Deployment Steps

### Step 1: Clone and Configure Environment
```bash
cd /var/www/bina-schools
composer install --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate
```

Configure `.env` for production:
```env
APP_NAME="Bina Schools"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bina.example.com
CENTRAL_DOMAIN=bina.example.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bina_schools
DB_USERNAME=bina_user
DB_PASSWORD=your_secure_password

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@bina.example.com"
MAIL_FROM_NAME="Bina Schools"

TELEGRAM_BOT_TOKEN="your_telegram_bot_token"

# Chapa Payment Gateway (Prompt 16)
CHAPA_PUBLIC_KEY="CHAPUBK_TEST-xxxxxx"
CHAPA_SECRET_KEY="CHASECK_TEST-xxxxxx"
CHAPA_WEBHOOK_SECRET="your_chapa_webhook_secret"
CHAPA_CURRENCY="ETB"
CHAPA_SIMULATE=false
```

### Step 2: Database Migrations & Optimization
```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 3: Nginx Wildcard Configuration
Configure Nginx to route central and tenant subdomains (`greenwood.bina.example.com`, `maplewood.bina.example.com`, etc.):

```nginx
server {
    listen 80;
    server_name bina.example.com *.bina.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name bina.example.com *.bina.example.com;
    root /var/www/bina-schools/public;

    index index.php index.html;

    # Wildcard SSL certificate (Let's Encrypt DNS challenge or Commercial Cert)
    ssl_certificate /etc/letsencrypt/live/bina.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/bina.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 3. Queue Workers & Notification Pipeline

The unified notification engine (Prompt 14) asynchronously delivers multi-channel notifications (in-app notifications, transactional emails, webhook dispatches, SMS/push hooks, and Telegram messages) via Redis queues.

### Supervisor Configuration (`/etc/supervisor/conf.d/bina-worker.conf`)
```ini
[program:bina-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/bina-schools/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/bina-worker.log
stopwaitsecs=3600
```

Load and start workers:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bina-worker:*
```

---

## 4. Scheduled Jobs & Automation

The application defines automated scheduled cron jobs in `routes/console.php`:

| Command | Schedule | Purpose |
| :--- | :--- | :--- |
| `attendance:remind-daily` | Weekdays at 09:30 | Identifies active sections missing attendance roll call on school days and alerts homeroom teachers. |
| `library:check-overdue` | Daily at 06:00 | Flags overdue loans, calculates progressive fines into the fines ledger, and dispatches reminder notifications. |
| `tenants:backup` | Daily at 01:00 | Generates standalone JSON backup snapshots per tenant school in `storage/app/backups/`. |

### System Crontab Setup
Add the Laravel scheduler entry to `crontab -e -u www-data`:
```cron
* * * * * cd /var/www/bina-schools && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Tenant Data Backup & Disaster Recovery

The multi-tenant backup utility safely isolates each tenant's data into independent JSON archives, complete with metadata, users, academic structure, student rosters, grades, library loans, transport routes, and communications.

### Manual Backup Execution
```bash
# Backup all tenant schools:
php artisan tenants:backup

# Backup a specific school by ID:
php artisan tenants:backup 1
```

Backups are timestamped and preserved in:
`storage/app/backups/{subdomain}_backup_{timestamp}.json`

---

## 6. Demo & Exploration Guide (Walkthrough across 5 Roles)

To initialize a fully populated sandbox environment with 3 distinct schools (High School, Elementary School, and Independent Preparatory), run:

```bash
php artisan migrate:fresh --seed
```

All accounts use the universal demo password: `password123`.

### Demo Schools Overview

| School Name | Subdomain | Type | Characteristics |
| :--- | :--- | :--- | :--- |
| **Greenwood High** | `greenwood` | Secondary High School | Grades 9–12, 100-point Letter Grading (A–F), Bus Routes 101/102, Homeroom teachers. |
| **Maplewood Elementary School** | `maplewood` | Primary Elementary | Kindergarten to Grade 5, Standards-based grading (Exceeds/Meets/Approaching/Below), Yellow Bus Route 10. |
| **Oakridge Academy** | `oakridge` | Independent Preparatory | Secondary & Sixth Form, Autumn/Winter/Spring terms. |

---

### Demo Accounts by Role

#### 1. Super Administrator (Cross-Tenant Platform Overseer)
- **Email**: `superadmin@bina.test`
- **Password**: `password123`
- **Capabilities**:
  - Global overview of all schools (`GET /api/dashboard?role=super_admin`).
  - School provisioning, subscription status, and tenant backup orchestration.

#### 2. School Administrator (School Operations Manager)
- **Greenwood High Admin**: `admin@greenwood.edu` / `password123`
- **Maplewood Elementary Admin**: `admin@maplewood.edu` / `password123`
- **Oakridge Academy Admin**: `admin@oakridge.edu` / `password123`
- **Capabilities**:
  - School dashboard with enrollment statistics, attendance trends, pending registrations.
  - Manage academic structure (years, terms, grade levels, sections).
  - Bulk student import via CSV and end-of-year promotion workflows.
  - School-wide and grade-level targeted announcements.
  - Timetable scheduling with double-booking prevention.
  - Fee structure configuration per grade level / term (tuition, facility, activity).
  - Bulk invoice generation with automatic conditional transport item inclusion/exclusion based on active bus route assignments.
  - Collections dashboard tracking total billed, paid, and outstanding balances across grades and sections.
  - Manual cash and bank transfer payment recording with auto status recalculation.

#### 3. Teacher
- **Greenwood Teacher**: `teacher@greenwood.edu` (Edna Krabappel — Grade 10 Homeroom & English Teacher) / `password123`
- **Greenwood Teacher**: `hoover@greenwood.edu` (Elizabeth Hoover — Mathematics Teacher) / `password123`
- **Maplewood Teacher**: `teacher@maplewood.edu` (Clara Johnson — Grade 3 Homeroom Teacher) / `password123`
- **Capabilities**:
  - Daily roll call marking for assigned sections.
  - Grade entry and assessment tracking for assigned subjects/sections.
  - Personal timetable aggregated across all sections taught.
  - Direct student-scoped messaging threads with parents.

#### 4. Student
- **Greenwood Student**: `student@greenwood.edu` (Bart Simpson — Grade 10) / `password123`
- **Greenwood Student**: `lisa.simpson@greenwood.edu` (Lisa Simpson — Grade 10) / `password123`
- **Maplewood Student**: `student@maplewood.edu` (Tommy Vance — Grade 3) / `password123`
- **Capabilities**:
  - Personal schedule / weekly timetable grid.
  - Published report cards with PDF download.
  - Active library loans and due date tracking.
  - School and grade-level announcements.

#### 5. Parent
- **Greenwood Parent**: `parent@greenwood.edu` (Homer Simpson — Linked to Bart & Lisa) / `password123`
- **Greenwood Parent**: `marge@greenwood.edu` (Marge Simpson — Linked to Bart & Lisa) / `password123`
- **Maplewood Parent**: `parent@maplewood.edu` (Sarah Vance — Linked to Tommy) / `password123`
- **Capabilities**:
  - Multi-child switcher on parent dashboard without re-logging in.
  - Outstanding and past invoices per child with balance tracking.
  - Online payments via Chapa payment gateway (Ethiopian Birr, Telebirr, and cards).
  - Official PDF receipt download.
  - Real-time attendance notifications (absence alerts).
  - Academic progress reports and report card downloads.
  - Assigned school bus routes, designated stops, and pickup/dropoff schedules.
  - Direct communication thread with child's teachers.

---

## 7. Testing & Verification

The test suite thoroughly verifies multi-tenant isolation, cross-section teacher restrictions, and RBAC security boundaries across all modules:

```bash
# Run the entire test suite (145 tests, 1094 assertions):
php artisan test

# Run the comprehensive RBAC & Tenant Isolation test specifically:
php artisan test --filter=ComprehensiveRBACAndIsolationTest
```
