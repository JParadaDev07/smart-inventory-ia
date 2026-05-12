import { ref, reactive, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import type { Product, PaginatedResponse } from '@/types'

export type ProductsSortKey = 'name' | 'sku' | 'stock' | 'min' | 'price' | 'created_at' | 'updated_at'

interface ApiError extends Error {
  response?: { data?: { message?: string } }
}

export function useProducts() {
  const { t } = useI18n()
  const queryClient = useQueryClient()

  // --- Paginación y filtros ---
  const page = ref(1)
  const perPage = ref(15)
  const search = ref('')
  const dateFrom = ref('')
  const dateTo = ref('')
  const sortBy = ref<ProductsSortKey>('name')
  const sortDir = ref<'asc' | 'desc'>('asc')

  const visibleColumns = reactive({
    sku: true,
    min: true,
    price: true,
    createdAt: false,
    updatedAt: false,
  })

  function setSort(column: ProductsSortKey) {
    if (sortBy.value === column) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortBy.value = column
      sortDir.value = 'asc'
    }
  }

  // --- Query ---
  const productsQuery = useQuery({
    queryKey: computed(() => ['products', page.value, perPage.value]),
    queryFn: async (): Promise<PaginatedResponse<Product>> => {
      const { data } = await api.get<PaginatedResponse<Product>>('/products', {
        params: { page: page.value, per_page: perPage.value },
      })
      return data as PaginatedResponse<Product>
    },
  })

  const productList = computed<Product[]>(() => {
    const res = productsQuery.data.value
    const raw =
      res && typeof res === 'object' && 'data' in res
        ? (res as PaginatedResponse<Product>).data
        : Array.isArray(res)
          ? res
          : []
    return Array.isArray(raw) ? raw.filter((p): p is Product => p != null && typeof p === 'object') : []
  })

  const filteredSorted = computed<Product[]>(() => {
    const term = search.value.trim().toLowerCase()
    let rows = term
      ? productList.value.filter(
          (p) =>
            (p.name ?? '').toLowerCase().includes(term) ||
            (p.sku ?? '').toLowerCase().includes(term),
        )
      : [...productList.value]

    const fromTs = dateFrom.value ? new Date(`${dateFrom.value}T00:00:00`).getTime() : null
    const toTs = dateTo.value ? new Date(`${dateTo.value}T23:59:59`).getTime() : null
    if (fromTs != null || toTs != null) {
      rows = rows.filter((p) => {
        if (!p.created_at) return false
        const ts = new Date(p.created_at).getTime()
        if (fromTs != null && ts < fromTs) return false
        if (toTs != null && ts > toTs) return false
        return true
      })
    }

    const dir = sortDir.value === 'asc' ? 1 : -1
    rows.sort((a, b) => {
      switch (sortBy.value) {
        case 'name':
          return (a.name ?? '').localeCompare(b.name ?? '') * dir
        case 'sku':
          return (a.sku ?? '').localeCompare(b.sku ?? '') * dir
        case 'stock':
          return (a.current_stock - b.current_stock) * dir
        case 'min':
          return (a.minimum_stock - b.minimum_stock) * dir
        case 'price':
          return (Number(a.sale_price) - Number(b.sale_price)) * dir
        case 'created_at': {
          const at = a.created_at ? new Date(a.created_at).getTime() : 0
          const bt = b.created_at ? new Date(b.created_at).getTime() : 0
          return (at - bt) * dir
        }
        case 'updated_at': {
          const at = a.updated_at ? new Date(a.updated_at).getTime() : 0
          const bt = b.updated_at ? new Date(b.updated_at).getTime() : 0
          return (at - bt) * dir
        }
        default:
          return 0
      }
    })
    return rows
  })

  type RawPagination = PaginatedResponse<Product> & {
    current_page?: number
    last_page?: number
    total?: number
    per_page?: number
  }

  const pagination = computed(() => {
    const res = productsQuery.data.value as RawPagination | undefined
    if (!res) return null
    if (res.meta) return res.meta
    if (typeof res.current_page === 'number' && typeof res.last_page === 'number') {
      return {
        current_page: res.current_page,
        last_page: res.last_page,
        total: res.total ?? 0,
        per_page: res.per_page ?? 15,
      }
    }
    return null
  })

  const errorMessage = computed(() => {
    const err = productsQuery.error.value as ApiError | null
    if (!err) return t('products.errorLoading')
    return err.response?.data?.message ?? err.message ?? t('products.errorLoading')
  })

  // --- Mutations ---
  type ProductForm = {
    name: string
    sku: string
    cost_price: number
    sale_price: number
    current_stock: number
    minimum_stock: number
    supplier_lead_time_days: number
    perecedero: boolean
    expired_mode: string
    expiration_alert_days: number
  }

  const formError = ref('')

  const createMutation = useMutation({
    mutationFn: (payload: ProductForm) => api.post<Product>('/products', { ...payload }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] })
      formError.value = ''
    },
    onError: (err: ApiError) => {
      formError.value = err.response?.data?.message ?? t('products.failedCreate')
    },
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<ProductForm> }) =>
      api.put<Product>(`/products/${id}`, { ...payload }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['products'] })
      formError.value = ''
    },
    onError: (err: ApiError) => {
      formError.value = err.response?.data?.message ?? t('products.failedUpdate')
    },
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/products/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['products'] }),
  })

  return {
    // query state
    productsQuery,
    productList,
    filteredSorted,
    pagination,
    errorMessage,
    // filters
    page,
    perPage,
    search,
    dateFrom,
    dateTo,
    sortBy,
    sortDir,
    visibleColumns,
    setSort,
    // mutations
    formError,
    createMutation,
    updateMutation,
    deleteMutation,
  }
}
