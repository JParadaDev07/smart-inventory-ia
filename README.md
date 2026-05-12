# Smart Inventory AI for Hardware Stores

Production-ready **Multi-Tenant SaaS**: Laravel API (monolithic modular) + Python FastAPI (AI predictions only) + Vue 3 frontend.

## Architecture

- **Laravel (backend/)**: Single database, `business_id` isolation. Auth, tenants, subscriptions, products, sales, local intelligence, and AI proxy. No `business_id` from frontend; always derived from authenticated user.
- **FastAPI (ai-service/)**: Stateless. `POST /predict` only. No DB, no auth. Prophet (if ≥60 points) or Linear Regression.
- **Vue 3 (frontend/)**: Composition API, Pinia, Axios, Tailwind, ShadCN-style components, Lucide icons.

## Requirements

- PHP 8.2+, Composer
- Node 18+, npm
- Python 3.11+ (for AI service)
- MySQL 8
- Docker & Docker Compose (optional)

## Local setup (without Docker)

### 0. Database (before migrations)

Create the MySQL database and user (run as root):

```bash
mysql -u root -p < backend/database/setup-database.sql
```

Or in MySQL client:

```sql
CREATE DATABASE IF NOT EXISTS smart_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'smart_inventory'@'localhost' IDENTIFIED BY 'secret';
GRANT ALL PRIVILEGES ON smart_inventory.* TO 'smart_inventory'@'localhost';
FLUSH PRIVILEGES;
```

Default `.env` uses `DB_USERNAME=root`, `DB_PASSWORD=` (empty). Create the database: `CREATE DATABASE smart_inventory;`

### 1. Laravel API

```bash
cd backend
cp .env.example .env
# Edit .env: DB_* (default: root, no password), FASTAPI_URL=http://localhost:8001
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
# API: http://localhost:8000
```

**Demo login** (after `php artisan db:seed`): `demo@example.com` / `password`

### 2. FastAPI AI service

```bash
cd ai-service
python -m venv venv
# Windows: venv\Scripts\activate
# Unix: source venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --reload --port 8001
# AI: http://localhost:8001
```

### 3. Vue frontend

```bash
cd frontend
npm install
npm run dev
# App: http://localhost:5173
```

Configure frontend proxy in `vite.config.js`: `/api` → `http://localhost:8000`.

## Docker Compose

```bash
# From repo root
docker compose up -d

# First run: migrate and key
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

- **Frontend**: http://localhost:5173  
- **Laravel API**: http://localhost:8000  
- **FastAPI**: internal only (http://ai-service:8000 from app container)

## Multi-tenant rules

- All tenant tables have indexed `business_id`.
- `BelongsToBusiness` trait + global scope on Product, Sale, InventoryLog, AiUsageLog.
- `EnsureTenantAccess` middleware on authenticated API routes.
- Policies (e.g. `ProductPolicy`) enforce ownership.
- Never accept `business_id` from client; always from `auth()->user()->business_id`.

## Subscription

- **Basic**: Local intelligence only (`GET /api/products/{id}/intelligence`).
- **Pro**: Same + AI prediction block (Laravel calls FastAPI internally).
- `CheckSubscription` middleware can gate routes by plan.

## API overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/auth/register | Register (creates Business + trial) |
| POST | /api/auth/login | Login |
| GET | /api/auth/user | Current user |
| GET | /api/dashboard | Dashboard stats |
| CRUD | /api/products | Products (policy) |
| GET | /api/products/{id}/intelligence | Local + optional AI |
| GET/POST | /api/sales | List / create sale |
| GET | /api/subscription | Current plan |

## Security

- No cross-tenant leakage; global scopes and policies enforced.
- AI service internal-only; frontend never talks to FastAPI.
- AI timeout 5s; fallback to local calculation and log in `ai_usage_logs`.

## License

MIT
