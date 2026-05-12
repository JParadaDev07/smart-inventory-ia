import { ref, reactive, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import type { Sale, Product, PaginatedResponse } from '@/types'

export type SalesSortKey = 'created_at' | 'updated_at' | 'total'

interface ApiError extends Error {
  response?: { data?: { message?: string } }
}

export interface SaleItemForm {
  product_id: string | number
  quantity: number
}

export function useSales() {
  const { t } = useI18n()
  const queryClient = useQueryClient()

  // --- Paginación y filtros ---
  const page = ref(1)
  const perPage = ref(15)
  const search = ref('')
  const dateFrom = ref('')
  const dateTo = ref('')
  const sortBy = ref<SalesSortKey>('created_at')
  const sortDir = ref<'asc' | 'desc'>('desc')

  const visibleColumns = reactive({
    createdAt: true,
    updatedAt: false,
    total: true,
  })

  function setSort(column: SalesSortKey) {
    if (sortBy.value === column) {
      sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
      sortBy.value = column
      sortDir.value = 'asc'
    }
    page.value = 1
  }

  watch([dateFrom, dateTo], () => { page.value = 1 })

  // --- Query de ventas ---
  const salesQuery = useQuery({
    queryKey: computed(() => [
      'sales',
      page.value,
      perPage.value,
      dateFrom.value,
      dateTo.value,
      sortBy.value,
      sortDir.value,
    ]),
    queryFn: async (): Promise<PaginatedResponse<Sale>> => {
      const params: Record<string, string | number> = {
        page: page.value,
        per_page: perPage.value,
        sort_by: sortBy.value === 'total' ? 'total' : sortBy.value,
        sort_dir: sortDir.value,
      }
      if (dateFrom.value) params.from = dateFrom.value
      if (dateTo.value) params.to = dateTo.value
      const { data } = await api.get<PaginatedResponse<Sale>>('/sales', { params })
      return data as PaginatedResponse<Sale>
    },
  })

  // --- Query de productos (para selector de items en nueva venta) ---
  const productsQuery = useQuery({
    queryKey: ['products-all'],
    queryFn: async (): Promise<Product[]> => {
      const { data } = await api.get<PaginatedResponse<Product> | Product[]>('/products', {
        params: { per_page: 100 },
      })
      const list = Array.isArray(data) ? data : (data as PaginatedResponse<Product>)?.data ?? []
      return Array.isArray(list)
        ? list.filter((p): p is Product => p != null && typeof p === 'object' && 'id' in p)
        : []
    },
  })

  const salesList = computed<Sale[]>(() => {
    const res = salesQuery.data.value
    const raw =
      res && typeof res === 'object' && 'data' in res
        ? (res as PaginatedResponse<Sale>).data
        : Array.isArray(res)
          ? res
          : []
    return Array.isArray(raw)
      ? raw.filter((s): s is Sale => s != null && typeof s === 'object' && 'id' in s)
      : []
  })

  const filteredSales = computed<Sale[]>(() => {
    const term = search.value.trim().toLowerCase()
    if (!term) return salesList.value
    return salesList.value.filter((s) => {
      const dateStr = (s.created_at ?? '').toLowerCase()
      const totalStr = String(s.total_amount ?? '').toLowerCase()
      return dateStr.includes(term) || totalStr.includes(term)
    })
  })

  type RawPagination = PaginatedResponse<Sale> & {
    current_page?: number
    last_page?: number
    total?: number
    per_page?: number
  }

  const pagination = computed(() => {
    const res = salesQuery.data.value as RawPagination | undefined
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

  const productList = computed<Product[]>(() => {
    const raw = productsQuery.data.value ?? []
    return Array.isArray(raw)
      ? raw.filter((p): p is Product => p != null && typeof p === 'object' && 'id' in p)
      : []
  })

  const errorMessage = computed(() => {
    const err = salesQuery.error.value as ApiError | null
    if (!err) return t('sales.errorLoading')
    return err.response?.data?.message ?? err.message ?? t('sales.errorLoading')
  })

  // --- Formulario nueva venta ---
  const saleError = ref('')
  const saleDate = ref<string>(new Date().toISOString().slice(0, 10))
  const saleItems = reactive<SaleItemForm[]>([{ product_id: '', quantity: 1 }])

  function removeSaleItem(index: number) {
    saleItems.splice(index, 1)
    if (saleItems.length === 0) saleItems.push({ product_id: '', quantity: 1 })
  }

  // --- Mutation ---
  const createSaleMutation = useMutation({
    mutationFn: (payload: {
      date?: string
      branch_id?: number | null
      items: { product_id: number; quantity: number }[]
    }) => api.post<Sale>('/sales', payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales'] })
      saleItems.length = 0
      saleItems.push({ product_id: '', quantity: 1 })
      saleDate.value = new Date().toISOString().slice(0, 10)
      saleError.value = ''
    },
    onError: (err: ApiError) => {
      saleError.value = err.response?.data?.message ?? t('sales.failedCreate')
    },
  })

  async function createSale(selectedBranchId: number) {
    const items = saleItems
      .filter((i) => i.product_id && i.quantity > 0)
      .map((i) => ({ product_id: Number(i.product_id), quantity: i.quantity }))
    if (!items.length) {
      saleError.value = t('sales.addOneItem')
      return
    }
    saleError.value = ''
    await createSaleMutation.mutateAsync({
      date: saleDate.value,
      branch_id: selectedBranchId || null,
      items,
    })
  }

  return {
    // query state
    salesQuery,
    salesList,
    filteredSales,
    pagination,
    productList,
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
    // nueva venta
    saleError,
    saleDate,
    saleItems,
    removeSaleItem,
    createSale,
    createSaleMutation,
  }
}
