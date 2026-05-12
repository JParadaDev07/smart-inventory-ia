import { driver as createDriver } from 'driver.js'
import { useRouter } from 'vue-router'

const STORAGE_KEY = 'smart_inventory_onboarding_done'

function waitForSelector(selector: string, timeoutMs = 6000, intervalMs = 100): Promise<void> {
  return new Promise((resolve) => {
    const start = Date.now()
    const tick = () => {
      const el = document.querySelector(selector)
      if (el) return resolve()
      if (Date.now() - start >= timeoutMs) return resolve()
      window.setTimeout(tick, intervalMs)
    }
    tick()
  })
}

function getFirstVisibleElement(selector: string): Element {
  const els = Array.from(document.querySelectorAll(selector)) as HTMLElement[]
  const visible = els.find((el) => el.offsetParent !== null || el.getClientRects().length > 0)
  return visible ?? els[0]
}

export function useOnboardingTour() {
  const router = useRouter()

  function hasSeenTour() {
    return window.localStorage.getItem(STORAGE_KEY) === '1'
  }

  function skipTour() {
    window.localStorage.setItem(STORAGE_KEY, '1')
  }

  function startDashboardTour() {
    // Mark as seen immediately so closing it won't show the modal again.
    window.localStorage.setItem(STORAGE_KEY, '1')

    const tour = createDriver({
      showProgress: true,
      allowClose: true,
      nextBtnText: 'Siguiente',
      prevBtnText: 'Atrás',
      doneBtnText: 'Terminar',
    })

    tour.setSteps([
      {
        element: () => getFirstVisibleElement('[data-tour="nav-dashboard"]'),
        popover: {
          title: 'Panel principal',
          description: 'Aquí tienes un resumen rápido de tus productos, ventas y alertas.',
          side: 'bottom',
        },
      },
      {
        element: '[data-tour="dashboard-metrics"]',
        popover: {
          title: 'Métricas clave',
          description: 'Revisa indicadores y gráficos para entender el estado de tu inventario.',
          side: 'bottom',
        },
      },
      {
        element: () => getFirstVisibleElement('[data-tour="tour-theme-toggle"]'),
        popover: {
          title: 'Tema',
          description: 'Cambia entre modo claro y oscuro desde el encabezado.',
          side: 'bottom',
        },
      },
      {
        element: () => getFirstVisibleElement('[data-tour="nav-products"]'),
        popover: {
          title: 'Productos',
          description: 'Gestiona stock, precios y crea nuevos productos.',
          side: 'bottom',
          onNextClick: async (_el: Element | undefined, _step: any, opts: any) => {
            await router.push('/app/products')
            await waitForSelector('[data-tour="products-add"]')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: '[data-tour="products-add"]',
        popover: {
          title: 'Agregar producto',
          description: 'Abre el formulario para crear un producto.',
          side: 'bottom',
          onNextClick: async (el: Element | undefined, _step: any, opts: any) => {
            ;(el as HTMLElement | null)?.click()
            await waitForSelector('#product-name')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: '#product-name',
        popover: {
          title: 'Datos del producto',
          description: 'Completa el nombre (y el resto de campos) para crear el producto.',
          side: 'bottom',
        },
      },
      {
        element: '[data-tour="products-save-product"]',
        popover: {
          title: 'Guardar (demo)',
          description: 'Para la guía, avanzamos sin guardar: se cerrará el formulario.',
          side: 'bottom',
          onNextClick: async (_el: Element | undefined, _step: any, opts: any) => {
            const cancelBtn = document.querySelector('[data-tour="products-modal-cancel"]') as HTMLElement | null
            cancelBtn?.click()
            opts.driver.moveNext()
          },
        },
      },
      {
        element: () => getFirstVisibleElement('[data-tour="nav-sales"]'),
        popover: {
          title: 'Ventas',
          description: 'Crea una venta y revisa el historial.',
          side: 'bottom',
          onNextClick: async (_el: Element | undefined, _step: any, opts: any) => {
            await router.push('/app/sales')
            await waitForSelector('[data-tour="sales-new-sale"]')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: '[data-tour="sales-new-sale"]',
        popover: {
          title: 'Nueva venta',
          description: 'Abre el formulario para registrar una venta.',
          side: 'bottom',
          onNextClick: async (el: Element | undefined, _step: any, opts: any) => {
            ;(el as HTMLElement | null)?.click()
            await waitForSelector('[data-tour="sales-new-sale-date"]')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: '[data-tour="sales-new-sale-date"]',
        popover: {
          title: 'Fecha de la venta',
          description: 'Revisa la fecha y luego pasamos a cerrar el formulario (sin crear la venta).',
          side: 'bottom',
        },
      },
      {
        element: '[data-tour="sales-modal-cancel"]',
        popover: {
          title: 'Cerrar formulario',
          description: 'Cancelamos para no crear una venta durante el recorrido.',
          side: 'bottom',
          onNextClick: async (el: Element | undefined, _step: any, opts: any) => {
            ;(el as HTMLElement | null)?.click()
            await waitForSelector('[data-tour="nav-alerts"]')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: () => getFirstVisibleElement('[data-tour="nav-alerts"]'),
        popover: {
          title: 'Alertas de inventario',
          description: 'Detecta productos con stock bajo y recomendaciones de compra.',
          side: 'bottom',
          onNextClick: async (_el: Element | undefined, _step: any, opts: any) => {
            await router.push('/app/alerts')
            await waitForSelector('[data-tour="alerts-low-stock-title"]')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: '[data-tour="alerts-low-stock-title"]',
        popover: {
          title: 'Stock bajo',
          description: 'Aquí ves los productos que requieren atención por su nivel de inventario.',
          side: 'bottom',
        },
      },
      {
        element: () => getFirstVisibleElement('[data-tour="nav-subscription"]'),
        popover: {
          title: 'Suscripción',
          description: 'Administra tu plan y renueva cuando sea necesario.',
          side: 'bottom',
          onNextClick: async (_el: Element | undefined, _step: any, opts: any) => {
            await router.push('/app/subscription')
            await waitForSelector('[data-tour="subscription-plans-title"]')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: '[data-tour="subscription-plans-title"]',
        popover: {
          title: 'Planes',
          description: 'Selecciona el plan que mejor se ajuste a tu negocio.',
          side: 'bottom',
        },
      },
      {
        element: '[data-tour="user-menu-toggle"]',
        popover: {
          title: 'Perfil',
          description: 'Actualiza tus datos de negocio y tu información de cuenta.',
          side: 'bottom',
          onNextClick: async (_el: Element | undefined, _step: any, opts: any) => {
            await router.push('/app/profile')
            await waitForSelector('[data-tour="profile-save"]')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: '[data-tour="profile-save"]',
        popover: {
          title: 'Guardar cambios',
          description: 'Revisa los campos y guarda tu configuración.',
          side: 'bottom',
        },
      },
      {
        element: '[data-tour="user-menu-toggle"]',
        popover: {
          title: 'Tickets',
          description: 'Crea solicitudes para soporte o incidencias.',
          side: 'bottom',
          onNextClick: async (_el: Element | undefined, _step: any, opts: any) => {
            await router.push('/app/tickets')
            await waitForSelector('[data-tour="tickets-create"]')
            opts.driver.moveNext()
          },
        },
      },
      {
        element: '[data-tour="tickets-create"]',
        popover: {
          title: 'Crear ticket',
          description: 'Completa sujeto, descripción y prioridad para enviar un ticket.',
          side: 'bottom',
        },
      },
    ])

    tour.drive()
  }

  return { startDashboardTour, hasSeenTour, skipTour }
}

