import { ref, computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import api from '@/api'

const ALERTS_PER_PAGE = 10

export interface LowStockItem {
  id: number
  name: string
  sku: string | null
  current_stock: number
  minimum_stock: number
}

export interface RecommendedItem {
  product: LowStockItem
  recommended_quantity: number
}

export interface ExpiringSoonItem {
  batch_id: number
  product: { id: number; name: string; sku: string | null }
  branch?: { id: number; name: string | null }
  quantity_available: number
  expiration_date: string
  days_until: number
}

export interface AlertsResponse {
  low_stock: LowStockItem[]
  recommended_purchase: RecommendedItem[]
  expiring_soon?: ExpiringSoonItem[]
}

function paginate<T>(list: T[], page: number): T[] {
  const start = (page - 1) * ALERTS_PER_PAGE
  return list.slice(start, start + ALERTS_PER_PAGE)
}

function paginationMeta(list: unknown[]) {
  const total = list.length
  const lastPage = Math.max(1, Math.ceil(total / ALERTS_PER_PAGE))
  return { total, lastPage }
}

export function useAlerts() {
  const lowStockPage = ref(1)
  const recommendedPage = ref(1)
  const expiringSoonPage = ref(1)

  const alertsQuery = useQuery({
    queryKey: ['alerts'],
    queryFn: async (): Promise<AlertsResponse> => {
      const { data } = await api.get<AlertsResponse>('/alerts')
      return data
    },
  })

  const isLoading = computed(() => alertsQuery.isLoading.value)
  const isError = computed(() => alertsQuery.isError.value)

  const alerts = computed(() => {
    const raw = alertsQuery.data.value
    if (!raw || typeof raw !== 'object') return null
    return {
      low_stock: Array.isArray(raw.low_stock) ? raw.low_stock : [],
      recommended_purchase: Array.isArray(raw.recommended_purchase) ? raw.recommended_purchase : [],
      expiring_soon: Array.isArray(raw.expiring_soon) ? raw.expiring_soon : [],
    }
  })

  // --- Low stock ---
  const lowStockPagination = computed(() => paginationMeta(alerts.value?.low_stock ?? []))
  const lowStockPaginated = computed(() =>
    paginate(alerts.value?.low_stock ?? [], lowStockPage.value),
  )

  // --- Recommended purchase ---
  const recommendedPagination = computed(() =>
    paginationMeta(alerts.value?.recommended_purchase ?? []),
  )
  const recommendedPaginated = computed(() =>
    paginate(alerts.value?.recommended_purchase ?? [], recommendedPage.value),
  )

  // --- Expiring soon ---
  const expiringSoonPagination = computed(() =>
    paginationMeta(alerts.value?.expiring_soon ?? []),
  )
  const expiringSoonPaginated = computed(() =>
    paginate(alerts.value?.expiring_soon ?? [], expiringSoonPage.value),
  )

  // Resetear página si los datos cambian y la página actual queda fuera de rango
  watch(lowStockPagination, ({ lastPage }) => {
    if (lowStockPage.value > lastPage) lowStockPage.value = 1
  })
  watch(recommendedPagination, ({ lastPage }) => {
    if (recommendedPage.value > lastPage) recommendedPage.value = 1
  })
  watch(expiringSoonPagination, ({ lastPage }) => {
    if (expiringSoonPage.value > lastPage) expiringSoonPage.value = 1
  })

  return {
    alertsQuery,
    isLoading,
    isError,
    alerts,
    // low stock
    lowStockPage,
    lowStockPagination,
    lowStockPaginated,
    // recommended
    recommendedPage,
    recommendedPagination,
    recommendedPaginated,
    // expiring soon
    expiringSoonPage,
    expiringSoonPagination,
    expiringSoonPaginated,
  }
}
