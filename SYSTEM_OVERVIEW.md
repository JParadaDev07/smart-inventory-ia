## Smart Inventory AI – Descripción del sistema

Smart Inventory AI es una plataforma SaaS para **ferreterías y comercios** que:

- Centraliza **productos, stock y ventas**.
- Calcula puntos de reorden y recomienda **qué comprar, cuánto y cuándo**.
- Usa **IA (servicio Python/FastAPI)** para pronosticar demanda y evitar quiebres de stock.
- Funciona con un modelo de **suscripción** (planes Básico, Pro y Enterprise) y cobro con **Wompi**.

---

## Módulos principales de negocio

- **Gestión de productos**
  - Alta, edición y eliminación de productos.
  - Campos clave: nombre, SKU, stock actual, stock mínimo, precios, tiempo de entrega del proveedor.
  - Listados paginados, búsqueda y exportación a CSV.

- **Registro de ventas**
  - Registro de ventas con fecha, total y detalle por producto y cantidad.
  - Anulación de ventas con restauración automática de stock.
  - Listado con filtros por fecha y total, y exportación a CSV.

- **Inteligencia de inventario**
  - Cálculo de:
    - Demanda diaria promedio.
    - Stock de seguridad.
    - Punto de reorden.
    - Cantidad de compra recomendada.
    - Fecha estimada de ruptura de stock.
  - Vista dedicada por producto para entender su comportamiento.

- **Predicción con IA (Pro y Enterprise)**
  - Llamadas al servicio de IA (FastAPI) para:
    - Predicción de demanda a 30 días.
    - Promedio diario futuro.
    - Fecha de quiebre proyectada.
  - En planes Pro/Enterprise, se muestra información adicional de pronóstico avanzado.

- **Alertas inteligentes**
  - Listado de:
    - Productos con **stock bajo** (por debajo del mínimo).
    - Productos con **compra recomendada** según demanda.
  - Enlaces rápidos desde alertas a la inteligencia del producto.

- **Panel / Dashboard**
  - Métricas resumidas:
    - Total de productos.
    - Conteo de productos con stock bajo.
    - Ventas de los últimos 30 días.
    - Tarjetas/resumen de alertas generadas por IA.
  - Gráficos de ventas e inventario para tener una visión rápida del negocio.

- **Tickets de soporte**
  - Creación y seguimiento de tickets por parte del cliente.
  - Administración de tickets desde cuenta “admin global”.
  - Estados: abierto, en progreso, resuelto.

- **Suscripciones y planes**
  - Planes:
    - **Básico**: inventario e inteligencia local (1 usuario).
    - **Pro**: incluye IA de demanda y más usuarios.
    - **Enterprise**: IA avanzada, capacidades multinivel y soporte prioritario.
  - Gestión de:
    - Estado de la suscripción (prueba, activa, vencida, pago pendiente).
    - Días restantes de prueba / período actual.
  - Cobro recurrente con **Wompi** (Colombia) y control de acceso según plan.

---

## Arquitectura general

- **Frontend**
  - Framework: **Vue 3 + TypeScript**.
  - Routing: **Vue Router**.
  - Estado y datos remotos: **@tanstack/vue-query**.
  - Internacionalización: **vue-i18n** (es/en).
  - UI: componentes reutilizables (`Card`, `Button`, etc.) y diseño responsive tipo dashboard.
  - Vistas principales:
    - `LandingView`: página pública de marketing, explicación y precios.
    - `LoginView` / `RegisterView`: autenticación y registro de negocio.
    - `DashboardView`: panel principal del negocio.
    - `ProductsView`, `SalesView`, `SaleDetailView`, `ProductIntelligenceView`, `AlertsView`.
    - `SubscriptionView`: gestión de suscripción y pago.
    - `TicketsView`, `ProfileView`, etc.

- **Backend**
  - Framework: **Laravel** (PHP).
  - API REST bajo `/api` con middleware de:
    - Autenticación (`auth:sanctum`).
    - **Tenant** (multi-empresa / multi-negocio).
    - Verificación de email.
    - **CheckSubscription** (control de acceso por estado de suscripción y plan).
  - Módulos backend:
    - **AuthController**: registro, login, reset de contraseña, verificación de email.
    - **ProductController**: CRUD de productos y ajuste de stock.
    - **SaleController**: registro y anulación de ventas.
    - **ProductIntelligenceController**: inteligencia y predicciones de producto.
    - **AlertsController**: generación de alertas de stock y compra recomendada.
    - **DashboardController**: métricas agregadas para el panel.
    - **SubscriptionController**: lectura y actualización del plan actual.
    - **BillingController**: planes públicos, creación de pagos y webhook de Wompi.
    - **TicketController / TicketAdminController**: soporte y gestión de tickets.

- **Servicio de IA (Python / FastAPI)**
  - Servicio externo configurado vía `FASTAPI_URL` en el backend.
  - Expone endpoints que reciben historial de ventas y datos de producto.
  - Devuelve:
    - Predicciones de demanda.
    - Indicadores avanzados de inventario.
  - El backend Laravel actúa como orquestador: llama al servicio de IA y combina la respuesta con sus propios cálculos.

---

## Flujo de trabajo típico

1. **Onboarding y suscripción**
   - El usuario llega a la `LandingView`, ve beneficios y precios.
   - Se registra (registro de negocio) y obtiene **14 días de prueba**.
   - Desde `SubscriptionView` puede activar Pro vía Wompi o contactar para Enterprise.

2. **Configuración inicial**
   - Crea productos: stock actual, stock mínimo, precios.
   - Configura parámetros básicos de inventario.

3. **Operación diaria**
   - Registra ventas día a día en `SalesView`.
   - El sistema recalcula automáticamente demanda promedio y métricas de inventario.

4. **Análisis e inteligencia**
   - Desde `ProductIntelligenceView`, el usuario ve:
     - Punto de reorden y compras recomendadas.
     - Predicciones de IA (en Pro/Enterprise).
   - Desde `AlertsView` identifica productos con riesgo de ruptura o sobrestock.

5. **Toma de decisiones de compra**
   - Con base en las alertas y recomendaciones, decide:
     - Qué productos reabastecer.
     - Cantidades sugeridas por período.

6. **Soporte y mejoras**
   - Si hay problemas o solicitudes, crea tickets de soporte.
   - El equipo “admin global” responde y actualiza el estado de los tickets.

---

## Control de acceso por plan

- **Básico**
  - Acceso a productos, ventas, alertas y panel.
  - Cálculos locales de inventario (sin IA avanzada).

- **Pro**
  - Todo lo de Básico.
  - Acceso a **IA de demanda** e indicadores avanzados en inteligencia de producto.
  - Rutas protegidas por middleware `subscription:pro` (y negocios con plan Pro).

- **Enterprise**
  - Todo lo de Pro.
  - Enfoque en cadenas y empresas:
    - Capacidad de crecimiento a escenarios multi-sucursal y multinivel.
    - Prioridad en soporte y acompañamiento.
  - Comercialmente se contrata por contacto directo (no solo auto-servicio).

---

## Integraciones y configuración clave

- **Wompi (pagos)**
  - Variables en `.env` para entornos **test** y **producción**.
  - Uso del widget de Wompi en el frontend para pagos con tarjeta/PSE.
  - Webhook de Wompi para marcar pagos aprobados y activar suscripciones.

- **Multi-tenant**
  - Cada negocio tiene su propio `Business` y `Subscription`.
  - El middleware `tenant` asegura que todas las operaciones se limiten al negocio del usuario autenticado.

- **Internacionalización**
  - Textos en español e inglés en `frontend/src/locales/es.ts` y `en.ts`.
  - Cambio de idioma desde la UI sin afectar la lógica del backend.

---

## Resumen

En pocas palabras, Smart Inventory AI es un **copiloto de inventario para ferreterías y comercios**, que:

- Usa tus ventas históricas para **predecir demanda**.
- Te avisa antes de que se agote un producto clave.
- Te sugiere **cuánto comprar y cuándo**.
- Se distribuye como **SaaS por suscripción** con cobro automatizado.

