import { mount } from '@vue/test-utils'
import AlertsView from '@/views/AlertsView.vue'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'
import { createI18n } from 'vue-i18n'
import es from '@/locales/es'

const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0))

vi.mock('vue-router', () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
}))

vi.mock('@/api', () => ({
  default: {
    get: vi.fn().mockResolvedValue({
      data: {
        low_stock: [
          { id: 1, name: 'Producto Bajo Stock', sku: 'LOW-1', current_stock: 1, minimum_stock: 5 },
        ],
        recommended_purchase: [
          {
            product: {
              id: 2,
              name: 'Producto Recomendar',
              sku: 'REC-1',
              current_stock: 0,
              minimum_stock: 5,
            },
            recommended_quantity: 10,
          },
        ],
      },
    }),
  },
}))

describe('AlertsView', () => {
  it('comprueba que se visualice el log de alertas (tablas de stock bajo y compra recomendada)', async () => {
    const queryClient = new QueryClient()
    const i18n = createI18n({
      legacy: false,
      locale: 'es',
      messages: { es },
    })

    const wrapper = mount(AlertsView, {
      global: {
        plugins: [[VueQueryPlugin, { queryClient }], i18n],
      },
    })

    await flushPromises()

    // 1) El título de la vista debe estar visible
    expect(wrapper.text()).toContain('Alertas')

    // 2) El log de alertas debe visualizarse: las dos tablas (stock bajo y compra recomendada)
    const tables = wrapper.findAll('table')
    expect(tables.length).toBe(2)

    // 3) Log de stock bajo: al menos una fila con el producto
    const lowStockRows = tables[0].findAll('tbody tr')
    expect(lowStockRows.length).toBeGreaterThanOrEqual(1)
    expect(wrapper.text()).toContain('Producto Bajo Stock')
    expect(wrapper.text()).toContain('Stock bajo')

    // 4) Log de compra recomendada: al menos una fila con el producto
    const recommendedRows = tables[1].findAll('tbody tr')
    expect(recommendedRows.length).toBeGreaterThanOrEqual(1)
    expect(wrapper.text()).toContain('Producto Recomendar')
    expect(wrapper.text()).toContain('Compra recomendada')
  })
})

