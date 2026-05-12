# Smart Inventory AI — Project Overview

> Documento de referencia técnica: estructura, enfoque y funcionalidad general.  
> Generado: 2026-05-12

---

## 1. Propósito

**Smart Inventory AI** es una plataforma SaaS B2B para ferreterías y comercios minoristas que centraliza la gestión de inventario, automatiza el cálculo de puntos de reorden y usa modelos de IA para predecir demanda futura, reduciendo quiebres de stock y sobrecompras.

---

## 2. Enfoque Arquitectónico

El sistema sigue una arquitectura de **tres capas desacopladas**, comunicadas vía HTTP/REST:

```
┌─────────────────────┐        ┌──────────────────────────┐
│   Frontend          │──/api──▶   Backend (Laravel)       │──HTTP──▶ AI Service (FastAPI)
│   Vue 3 + TS        │        │   Monolítico modular      │          Python · Stateless
│   Puerto 5173       │        │   Puerto 8000             │          Puerto 8001 (interno)
└─────────────────────┘        └──────────────────────────┘
                                         │
                                    MySQL 8
                                  (single DB,
                                  multi-tenant)
```

### Principios de diseño

- **Multi-tenant single-database**: todas las tablas de negocio llevan `business_id` con índice. El tenant se deriva exclusivamente del usuario autenticado, nunca del cliente.
- **Monolítico modular**: Laravel concentra auth, lógica de negocio, tenant, billing y proxy hacia FastAPI. Sin microservicios innecesarios.
- **AI como servicio externo stateless**: FastAPI no tiene DB ni auth. Recibe datos del backend y devuelve predicciones. El backend actúa como orquestador con fallback automático.
- **Subscription-gated features**: el middleware `CheckSubscription` controla el acceso por plan (Basic / Pro / Enterprise) en cada ruta protegida.

---

## 3. Stack Tecnológico

### Backend — `backend/`

| Capa | Tecnología | Versión |
|---|---|---|
| Framework | Laravel | 10.x |
| Lenguaje | PHP | 8.2+ |
| Auth | Laravel Sanctum | — |
| HTTP Client | Guzzle | — |
| Base de datos | MySQL | 8.x |
| ORM | Eloquent | — |
| Pagos | Wompi (Colombia) | — |

### AI Service — `ai-service/`

| Capa | Tecnología | Versión |
|---|---|---|
| Framework | FastAPI | ≥0.109 |
| Validación | Pydantic v2 | ≥2.5 |
| ML — regresión | scikit-learn | ≥1.3 |
| ML — series de tiempo | Prophet *(opcional)* | ≥1.1 |
| Servidor ASGI | Uvicorn | ≥0.27 |
| Data wrangling | pandas / numpy | — |

### Frontend — `frontend/`

| Capa | Tecnología | Versión |
|---|---|---|
| Framework | Vue 3 + TypeScript | 3.4 / TS 5.3 |
| Estado global | Pinia | 2.x |
| Data fetching | @tanstack/vue-query | 5.x |
| HTTP | Axios | 1.x |
| UI | Tailwind CSS + ShadCN-style | 3.4 |
| Iconos | Lucide Vue Next | — |
| i18n | vue-i18n | 9.x |
| Gráficas | Chart.js + vue-chartjs | 4.x |
| Exportación | jsPDF + jspdf-autotable | — |
| Onboarding | driver.js | — |
| Tests | Vitest + @vue/test-utils | 1.x |

### DevOps

| Item | Tecnología |
|---|---|
| Contenedores | Docker + Docker Compose |
| Servidor dev Laravel | `php artisan serve` |
| Servidor dev Frontend | Vite |
| Servidor AI | Uvicorn `--reload` |

---

## 4. Estructura de Archivos

```
smart-inventory-ia/
│
├── README.md                        # Setup local y Docker
├── SYSTEM_OVERVIEW.md               # Descripción de negocio
├── IMPLEMENTATION.md                # Estado de implementación
├── PROJECT_OVERVIEW.md              # ← Este documento
├── docker-compose.yml               # Orquestación: app, mysql, ai-service, frontend
│
├── backend/                         # Laravel API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── AuthController.php           # Register, login, logout, perfil
│   │   │   │   ├── ProductController.php         # CRUD productos + ajuste stock
│   │   │   │   ├── SaleController.php            # Ventas (crear, listar, anular)
│   │   │   │   ├── ProductIntelligenceController.php  # Inteligencia local + IA
│   │   │   │   ├── AlertsController.php          # Alertas de stock bajo / recompra
│   │   │   │   ├── DashboardController.php       # Métricas del panel
│   │   │   │   ├── SubscriptionController.php    # Plan actual y cambio de plan
│   │   │   │   ├── BillingController.php         # Planes públicos + pago + webhook
│   │   │   │   ├── WompiController.php           # Checkout y webhook Wompi
│   │   │   │   ├── TicketController.php          # Soporte (usuario)
│   │   │   │   ├── TicketAdminController.php     # Soporte (admin global)
│   │   │   │   ├── BranchController.php          # Sucursales [parcial]
│   │   │   │   ├── WhatsAppWebhookController.php # WhatsApp [parcial]
│   │   │   │   └── EmailVerificationController.php
│   │   │   ├── Middleware/
│   │   │   │   ├── CheckSubscription.php         # Gating por plan y estado
│   │   │   │   ├── EnsureTenantAccess.php        # Valida business_id en user
│   │   │   │   └── EnsureEmailVerified.php
│   │   │   └── Requests/                         # Form Requests con validación
│   │   │
│   │   ├── Models/
│   │   │   ├── Business.php                      # Negocio / tenant raíz
│   │   │   ├── User.php                          # Usuario (pertenece a Business)
│   │   │   ├── Subscription.php                  # Plan, estado, fechas
│   │   │   ├── Product.php                       # Producto con stock y SKU
│   │   │   ├── Sale.php / SaleItem.php           # Venta y líneas de venta
│   │   │   ├── SaleDetail.php                    # Detalle alternativo (FIFO)
│   │   │   ├── Batch.php                         # Lotes para perecederos
│   │   │   ├── InventoryLog.php                  # Log de ajustes de stock
│   │   │   ├── InventoryMovement.php             # Movimientos FIFO
│   │   │   ├── AiUsageLog.php                    # Log de llamadas a FastAPI
│   │   │   ├── Payment.php                       # Pagos Wompi
│   │   │   ├── Branch.php / ProductBranchStock.php  # Sucursales [parcial]
│   │   │   ├── Ticket.php / TicketMessage.php    # Soporte
│   │   │   └── Concerns/BelongsToBusiness.php   # Trait + Global Scope tenant
│   │   │
│   │   ├── Services/
│   │   │   ├── AI/AIService.php                  # Orquesta FastAPI + fallback local
│   │   │   ├── Auth/RegisterService.php          # Crea Business + User + Subscription
│   │   │   ├── Billing/
│   │   │   │   ├── SubscriptionService.php       # Expiración trial/período
│   │   │   │   ├── SubscriptionCheckoutService.php
│   │   │   │   └── WompiService.php
│   │   │   ├── Inventory/
│   │   │   │   ├── InventoryAnalysisService.php  # Demanda, ROP, stock seguridad
│   │   │   │   └── StockAdjustmentService.php
│   │   │   ├── Sales/
│   │   │   │   ├── SaleService.php               # Transacción + deducción stock
│   │   │   │   └── FifoSaleService.php           # Descuento FIFO por lotes
│   │   │   └── Notifications/
│   │   │       ├── LowStockNotifier.php
│   │   │       └── WhatsAppService.php           # [parcial]
│   │   │
│   │   ├── Repositories/
│   │   │   └── ProductRepository.php
│   │   └── Policies/
│   │       └── ProductPolicy.php                 # Autorización CRUD por tenant
│   │
│   ├── database/
│   │   ├── migrations/                           # 14 migraciones ordenadas cronológicamente
│   │   ├── seeders/DatabaseSeeder.php            # Demo: business, user, productos, ventas
│   │   └── setup-database.sql
│   │
│   ├── routes/api.php                            # Todas las rutas REST
│   ├── config/
│   │   ├── plans.php                             # Definición de planes y precios
│   │   ├── services.php                          # FASTAPI_URL, timeout
│   │   └── wompi.php
│   └── tests/
│       ├── Feature/                              # BillingTest, SubscriptionTest, etc.
│       └── Unit/
│
├── ai-service/                      # FastAPI — Predicción de demanda
│   ├── main.py                      # Endpoints: /predict, /predict/advanced, /health
│   ├── services/
│   │   └── predict.py               # PredictionService: Prophet / Linear Regression
│   ├── requirements.txt
│   └── tests/
│       ├── test_api.py
│       └── test_predict.py
│
└── frontend/                        # Vue 3 SPA
    ├── src/
    │   ├── main.ts                  # Bootstrap: Vue + Pinia + Router + i18n + Query
    │   ├── App.vue
    │   ├── router/index.ts          # Rutas + auth guard
    │   ├── stores/
    │   │   ├── auth.ts              # Token, user, login/logout
    │   │   ├── theme.ts             # Dark/light mode
    │   │   └── locale.ts            # Idioma activo
    │   ├── api/index.ts             # Axios instance + interceptors
    │   ├── types/index.ts           # Interfaces TypeScript globales
    │   ├── i18n.ts                  # Configuración vue-i18n
    │   ├── locales/
    │   │   ├── es.ts                # Español
    │   │   └── en.ts                # English
    │   ├── layouts/
    │   │   └── DashboardLayout.vue  # Sidebar + nav + slot para vistas
    │   ├── components/
    │   │   ├── ui/                  # Button, Card, Input, Label (ShadCN-style)
    │   │   ├── SalesChart.vue
    │   │   ├── InventoryHealthChart.vue
    │   │   ├── TopProductsChart.vue
    │   │   ├── ThemeToggle.vue
    │   │   └── LangSwitch.vue
    │   ├── composables/
    │   │   └── useOnboardingTour.ts # driver.js tour paso a paso
    │   ├── views/
    │   │   ├── LandingView.vue          # Página pública: marketing + precios
    │   │   ├── LoginView.vue
    │   │   ├── RegisterView.vue
    │   │   ├── ForgotPasswordView.vue
    │   │   ├── ResetPasswordView.vue
    │   │   ├── VerifyEmailView.vue
    │   │   ├── DashboardView.vue        # Panel: métricas + gráficas
    │   │   ├── ProductsView.vue         # Listado + CRUD productos
    │   │   ├── ProductIntelligenceView.vue  # Análisis + predicción IA por producto
    │   │   ├── SalesView.vue            # Registro y listado de ventas
    │   │   ├── SaleDetailView.vue       # Detalle de una venta
    │   │   ├── AlertsView.vue           # Alertas stock bajo / recompra
    │   │   ├── SubscriptionView.vue     # Plan actual + upgrade/downgrade
    │   │   ├── WompiCheckoutView.vue    # Widget de pago Wompi
    │   │   ├── PaymentReturnView.vue    # Retorno post-pago
    │   │   ├── TicketsView.vue          # Soporte al cliente
    │   │   ├── ProfileView.vue
    │   │   └── NotFoundView.vue
    │   └── tests/
    │       └── AlertsView.spec.ts
    ├── vite.config.ts               # Proxy /api → localhost:8000
    ├── tailwind.config.js
    ├── tsconfig.json
    └── vitest.config.ts
```

---

## 5. Modelo de Datos Principal

```
Business ──< User
    │
    ├──< Subscription         (plan, status, trial_ends_at, period_end_at)
    ├──< Product              (sku, stock, min_stock, lead_time, perecedero)
    │       └──< Batch        (lotes con expiration_date, para perecederos)
    ├──< Sale ──< SaleItem    (date, total, items con product_id + quantity)
    │       └──< SaleDetail   (FIFO: batch_id, quantity)
    ├──< InventoryLog         (tipo ajuste, antes/después, motivo)
    ├──< InventoryMovement    (movimientos FIFO por lote)
    ├──< AiUsageLog           (product_id, success, response_time_ms, fallback)
    ├──< Payment              (wompi_id, amount, status)
    ├──< Ticket ──< TicketMessage
    └──< Branch ──< ProductBranchStock   [parcial]
```

**Tenant isolation**: `BelongsToBusiness` trait aplica un Global Scope `where business_id = ?` automático en Product, Sale, InventoryLog y AiUsageLog.

---

## 6. Flujo de Predicción IA

```
Frontend                  Laravel Backend              FastAPI AI Service
   │                           │                              │
   │── GET /products/{id}/intelligence ──▶                   │
   │                     [InventoryAnalysisService]           │
   │                     Calcula local: demanda,              │
   │                     ROP, stock seguridad                 │
   │                           │                              │
   │                     Si plan = Pro y ¬perecedero:         │
   │                           │── POST /predict ────────────▶│
   │                           │   {historical_sales,         │
   │                           │    lead_time, stock}         │
   │                           │                   ┌──────────┤
   │                           │                   │ ≥60 pts? │
   │                           │                   │ Prophet  │
   │                           │                   │ sino:    │
   │                           │                   │ LinReg   │
   │                           │◀── {predicted_30d, ─────────┘
   │                           │     daily_avg,
   │                           │     stock_out_date,
   │                           │     rec_purchase}
   │                           │
   │                     Si timeout/error → fallback local
   │                     Log en ai_usage_logs
   │◀── response con local + ai block ──────────────────────
```

---

## 7. Control de Acceso por Plan

| Funcionalidad | Basic | Pro | Enterprise |
|---|:---:|:---:|:---:|
| Productos, ventas, stock | ✅ | ✅ | ✅ |
| Inteligencia local (ROP, demanda) | ✅ | ✅ | ✅ |
| Alertas de stock bajo | ✅ | ✅ | ✅ |
| Dashboard con gráficas | ✅ | ✅ | ✅ |
| Predicción IA (FastAPI) | ❌ | ✅ | ✅ |
| Horizontes múltiples (7/30/90d) | ❌ | ✅ | ✅ |
| Multi-sucursal | ❌ | ❌ | ✅ |
| Soporte prioritario | ❌ | ❌ | ✅ |

Rutas protegidas por `middleware('subscription')` (activa/trial) y `middleware('subscription:pro')` (plan Pro o superior).

---

## 8. Rutas API — Resumen

### Públicas

| Método | Endpoint | Descripción |
|---|---|---|
| POST | `/api/auth/register` | Registro + crea Business + trial |
| POST | `/api/auth/login` | Login → token Sanctum |
| POST | `/api/auth/forgot-password` | Enviar link de reset |
| POST | `/api/auth/reset-password` | Resetear contraseña |
| GET | `/api/auth/email/verify/{id}/{hash}` | Verificar email |
| GET | `/api/plans` | Planes disponibles y precios |
| POST | `/api/webhooks/wompi` | Webhook de pago Wompi |

### Autenticadas (`auth:sanctum` + `tenant`)

| Método | Endpoint | Plan mínimo | Descripción |
|---|---|---|---|
| GET | `/api/dashboard` | Basic | Métricas principales |
| GET/POST/PUT/DELETE | `/api/products` | Basic | CRUD productos |
| POST | `/api/products/{id}/stock-adjustment` | Basic | Ajuste manual de stock |
| GET | `/api/products/{id}/intelligence` | Basic | Análisis local de inventario |
| GET | `/api/products/{id}/ai-prediction` | **Pro** | Predicción FastAPI |
| GET/POST | `/api/sales` | Basic | Ventas |
| GET/DELETE | `/api/sales/{id}` | Basic | Detalle / anulación |
| GET | `/api/alerts` | Basic | Alertas stock bajo |
| GET/PUT | `/api/subscription` | — | Plan actual / cambio |
| POST | `/api/billing/create-payment` | — | Iniciar checkout Wompi |
| GET/POST | `/api/tickets` | — | Soporte usuario |
| GET/PATCH/POST | `/api/admin/tickets` | — | Soporte admin ⚠️ |

> ⚠️ `/api/admin/tickets` solo tiene `auth:sanctum`. Requiere guard adicional por `is_admin`.

---

## 9. Variables de Entorno Clave

### Backend (`backend/.env`)

```env
APP_ENV=local
APP_DEBUG=true

DB_HOST=127.0.0.1
DB_DATABASE=smart_inventory
DB_USERNAME=root
DB_PASSWORD=

FASTAPI_URL=http://localhost:8001
FASTAPI_TIMEOUT=5

FRONTEND_URL=http://localhost:5173

WOMPI_PUBLIC_KEY=...
WOMPI_PRIVATE_KEY=...
WOMPI_INTEGRITY_KEY=...
WOMPI_ENV=test            # test | production

AUTH_MUST_VERIFY_EMAIL=false
AI_SERVICE_ENABLED=true
```

### Frontend (`frontend/.env`)

```env
VITE_API_URL=/api         # Proxy de Vite → localhost:8000
```

---

## 10. Cómo Levantar el Proyecto

### Con Docker (recomendado)

```bash
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

- Frontend: http://localhost:5173  
- Laravel API: http://localhost:8000  
- FastAPI: interno (`ai-service:8000` desde el contenedor `app`)

### Sin Docker

```bash
# 1. DB
mysql -u root -p -e "CREATE DATABASE smart_inventory;"

# 2. Backend
cd backend && cp .env.example .env
composer install && php artisan key:generate
php artisan migrate --seed
php artisan serve                        # :8000

# 3. AI Service
cd ai-service && python -m venv venv && source venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --reload --port 8001    # :8001

# 4. Frontend
cd frontend && npm install
npm run dev                              # :5173
```

**Demo login**: `demo@example.com` / `password`

---

## 11. Tests

| Layer | Framework | Ubicación | Cobertura principal |
|---|---|---|---|
| Backend Feature | PHPUnit | `backend/tests/Feature/` | Auth, billing, subscription, ventas, WhatsApp |
| Backend Unit | PHPUnit | `backend/tests/Unit/` | — |
| AI Service | pytest | `ai-service/tests/` | `/predict` API + lógica PredictionService |
| Frontend | Vitest | `frontend/src/tests/` | AlertsView |

```bash
# Backend
cd backend && php artisan test

# AI Service
cd ai-service && pytest

# Frontend
cd frontend && npm run test
```

---

## 12. Issues Conocidos y Deuda Técnica

| Prioridad | Área | Issue |
|---|---|---|
| 🔴 Alta | Backend | `/api/admin/tickets` sin guard `is_admin` |
| 🔴 Alta | Backend | Webhook Wompi sin validación de firma HMAC |
| 🟡 Media | Backend | `CheckSubscription` expira suscripciones en cada request → mover a Schedule |
| 🟡 Media | Backend | N+1 en `collectHistoricalSales` (eager load sobre `sale.created_at`) |
| 🟡 Media | AI Service | `/predict/advanced` ejecuta 3 horizontes secuencialmente → paralelizar |
| 🟡 Media | Backend | Sin rate limiting en rutas de auth |
| 🟢 Baja | General | `BranchController` y `WhatsAppWebhookController` parcialmente implementados |
| 🟢 Baja | Backend | Duplicidad `SaleItem` vs `SaleDetail` — inconsistencia de naming |
| 🟢 Baja | AI Service | Prophet opcional → en producción puede caer siempre a LinearRegression |
| 🟢 Baja | Frontend | Cobertura de tests muy baja (solo `AlertsView.spec.ts`) |

---

*Documento generado automáticamente a partir del análisis del código fuente.*
