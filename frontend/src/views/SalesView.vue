<template>
  <div class="space-y-6 px-1 sm:px-0">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-xl font-semibold sm:text-2xl">{{ t('sales.title') }}</h1>
      <div class="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" :disabled="!salesList.length" @click="exportSalesCsv">
          {{ t('sales.exportCsv') }}
        </Button>
        <Button class="w-full sm:w-auto shrink-0" data-tour="sales-new-sale" @click="showNewSale = true">
          {{ t('sales.newSale') }}
        </Button>
      </div>
    </div>

    <Card>
      <div v-if="salesQuery.isLoading && !salesQuery.data.value" class="p-6 sm:p-8">
        <div class="animate-pulse space-y-3">
          <div v-for="i in 5" :key="i" class="h-12 rounded bg-muted" />
        </div>
      </div>
      <template v-else>
        <div class="flex flex-col gap-3 px-4 pt-4 sm:flex-row sm:flex-wrap sm:items-center sm:gap-4">
          <input
            v-model="salesSearch"
            type="text"
            class="flex h-9 w-full sm:w-52 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            :placeholder="t('sales.searchPlaceholder')"
          />
          <details class="relative">
            <summary
              class="cursor-pointer list-none select-none rounded-md border border-input bg-background px-3 py-2 text-sm text-muted-foreground hover:bg-accent"
            >
              {{ t('sales.dateFrom') }} / {{ t('sales.dateTo') }}
            </summary>
            <div class="absolute left-0 z-20 mt-2 w-72 rounded-md border bg-card p-3 shadow-lg">
              <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-muted-foreground text-sm whitespace-nowrap">{{ t('sales.dateFrom') }}</label>
                  <input
                    v-model="salesDateFrom"
                    type="date"
                    class="flex h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  />
                </div>
                <div class="flex items-center justify-between gap-3">
                  <label class="text-muted-foreground text-sm whitespace-nowrap">{{ t('sales.dateTo') }}</label>
                  <input
                    v-model="salesDateTo"
                    type="date"
                    class="flex h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  />
                </div>
              </div>
            </div>
          </details>
          <div class="flex flex-wrap items-center justify-end gap-2">
            <div class="flex items-center gap-2">
              <label class="text-muted-foreground text-sm whitespace-nowrap">{{ t('products.rowsPerPage') }}</label>
              <select
                v-model.number="salesPerPage"
                class="flex h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @change="salesPage = 1"
              >
                <option :value="10">10</option>
                <option :value="15">15</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
              </select>
            </div>

            <details class="relative">
              <summary
                class="cursor-pointer list-none select-none rounded-md border border-input bg-background px-3 py-2 text-sm text-muted-foreground hover:bg-accent"
              >
                {{ t('sales.columns') }}
              </summary>
              <div class="absolute right-0 z-20 mt-2 w-64 rounded-md border bg-card p-3 shadow-lg">
                <div class="space-y-2 text-sm">
                  <label class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">{{ t('sales.date') }}</span>
                    <input type="checkbox" v-model="visibleColumns.createdAt" />
                  </label>
                  <label class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">{{ t('sales.updatedAt') }}</span>
                    <input type="checkbox" v-model="visibleColumns.updatedAt" />
                  </label>
                  <label class="flex items-center justify-between gap-3">
                    <span class="text-muted-foreground">{{ t('sales.total') }}</span>
                    <input type="checkbox" v-model="visibleColumns.total" />
                  </label>
                </div>
              </div>
            </details>
          </div>
        </div>
        <div class="overflow-x-auto -mx-4 sm:mx-0 sm:rounded-b-lg" style="-webkit-overflow-scrolling: touch;">
          <table class="w-full min-w-[280px] text-sm">
            <thead>
              <tr class="border-b bg-muted/30">
                <th
                  v-if="visibleColumns.createdAt"
                  class="h-11 px-4 py-3 text-left font-medium cursor-pointer select-none hover:bg-muted/50"
                  @click="setSalesSort('created_at')"
                >
                  <span class="inline-flex items-center gap-1">
                    <span>{{ t('sales.date') }}</span>
                    <ArrowUpDown
                      v-if="salesSortBy !== 'created_at'"
                      class="h-3.5 w-3.5 text-muted-foreground/60"
                    />
                    <ChevronUp
                      v-else-if="salesSortDir === 'asc'"
                      class="h-3.5 w-3.5 text-muted-foreground"
                    />
                    <ChevronDown
                      v-else
                      class="h-3.5 w-3.5 text-muted-foreground"
                    />
                  </span>
                </th>
                <th
                  v-if="visibleColumns.updatedAt"
                  class="h-11 px-4 py-3 text-left font-medium cursor-pointer select-none hover:bg-muted/50"
                  @click="setSalesSort('updated_at')"
                >
                  <span class="inline-flex items-center gap-1">
                    <span>{{ t('sales.updatedAt') }}</span>
                    <ArrowUpDown
                      v-if="salesSortBy !== 'updated_at'"
                      class="h-3.5 w-3.5 text-muted-foreground/60"
                    />
                    <ChevronUp
                      v-else-if="salesSortDir === 'asc'"
                      class="h-3.5 w-3.5 text-muted-foreground"
                    />
                    <ChevronDown
                      v-else
                      class="h-3.5 w-3.5 text-muted-foreground"
                    />
                  </span>
                </th>
                <th
                  v-if="visibleColumns.total"
                  class="h-11 px-4 py-3 text-right font-medium cursor-pointer select-none hover:bg-muted/50"
                  @click="setSalesSort('total')"
                >
                  <span class="inline-flex items-center justify-end gap-1">
                    <span>{{ t('sales.total') }}</span>
                    <ArrowUpDown
                      v-if="salesSortBy !== 'total'"
                      class="h-3.5 w-3.5 text-muted-foreground/60"
                    />
                    <ChevronUp
                      v-else-if="salesSortDir === 'asc'"
                      class="h-3.5 w-3.5 text-muted-foreground"
                    />
                    <ChevronDown
                      v-else
                      class="h-3.5 w-3.5 text-muted-foreground"
                    />
                  </span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="s in salesPaginated"
                :key="s.id"
                class="border-b last:border-0 cursor-pointer hover:bg-muted/20 transition-colors active:bg-muted/30"
                @click="goToSale(s.id)"
              >
                <td v-if="visibleColumns.createdAt" class="px-4 py-3 text-muted-foreground">{{ formatDate(s.created_at) }}</td>
                <td v-if="visibleColumns.updatedAt" class="px-4 py-3 text-muted-foreground">{{ formatDate(s.updated_at) }}</td>
                <td v-if="visibleColumns.total" class="px-4 py-3 text-right font-semibold tabular-nums">{{ formatCurrency(s.total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="salesQuery.isError.value" class="p-6 sm:p-8 text-center text-destructive text-sm">
          {{ salesErrorMessage }}
        </div>
        <div v-else-if="!salesList.length" class="p-6 sm:p-8 text-center text-muted-foreground text-sm">
          {{ t('sales.noSalesYet') }}
        </div>
        <div
          v-else-if="filteredSortedSales.length === 0"
          class="p-6 sm:p-8 text-center text-muted-foreground text-sm"
        >
          {{ t('sales.noSalesYet') }}
        </div>
        <div
          v-if="salesPagination && salesPagination.total > 0"
          class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
          <p class="text-muted-foreground text-sm order-2 sm:order-1 text-center sm:text-left">
            {{ t('sales.pageOf', { current: salesPagination.current_page, last: salesPagination.last_page }) }}
            <span class="hidden sm:inline"> ({{ salesPagination.total }} {{ t('sales.totalLabel') }})</span>
          </p>
          <div class="flex gap-2 justify-center sm:order-2">
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="salesPagination.current_page <= 1"
              @click="salesPage = Math.max(1, salesPagination.current_page - 1)"
            >
              {{ t('sales.previous') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="salesPagination.current_page >= salesPagination.last_page"
              @click="salesPage = Math.min(salesPagination.last_page, salesPagination.current_page + 1)"
            >
              {{ t('sales.next') }}
            </Button>
          </div>
        </div>
      </template>
    </Card>

    <Teleport to="body">
      <div
        v-if="showNewSale"
        class="fixed inset-0 z-40 flex items-center justify-center px-4 py-6 sm:py-12"
        role="alertdialog"
        :aria-label="t('sales.newSale')"
        aria-modal="true"
      >
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showNewSale = false; saleError = ''" />
        <div class="relative z-10 w-full max-w-xl mx-auto">
          <Card content-class="p-6 space-y-4">
            <template #header>
              <h2 class="text-base font-medium sm:text-lg">{{ t('sales.newSale') }}</h2>
            </template>
            <form @submit.prevent="createSale" class="space-y-4">
              <div class="space-y-2">
                <Label>{{ t('sales.date') }}</Label>
                <Input
                  v-model="saleDate"
                  type="date"
                  data-tour="sales-new-sale-date"
                  class="w-48"
                />
              </div>
              <div class="space-y-2">
                <Label>Sucursal</Label>
                <select
                  v-model.number="selectedBranchId"
                  class="flex h-9 w-64 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  :disabled="!branches.length"
                >
                  <option v-if="!branches.length" :value="0" disabled>Loading...</option>
                  <option v-else v-for="b in branches" :key="b.id" :value="b.id">
                    {{ b.name }}
                  </option>
                </select>
              </div>
              <div class="space-y-3">
                <Label>{{ t('sales.itemsLabel') }}</Label>
                <div
                  v-for="(item, i) in saleItems"
                  :key="i"
                  class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3"
                >
                  <select
                    v-model="item.product_id"
                    class="flex h-10 min-w-0 flex-1 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    required
                  >
                    <option value="">{{ t('sales.selectProduct') }}</option>
                    <option v-for="p in productList" :key="p.id" :value="p.id">
                      {{ p.name }} ({{ t('products.stock') }}: {{ p.current_stock }})
                    </option>
                  </select>
                  <div class="flex gap-2 items-center">
                    <Label class="sr-only" :for="`sale-qty-${i}`">{{ t('sales.quantity') }}</Label>
                    <Input
                      :id="`sale-qty-${i}`"
                      v-model.number="item.quantity"
                      type="number"
                      min="1"
                      class="w-20 sm:w-24 shrink-0"
                      :placeholder="t('sales.quantity')"
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      class="h-10 w-10 shrink-0"
                      :aria-label="t('sales.removeLine')"
                      @click="removeSaleItem(i)"
                    >
                      ×
                    </Button>
                  </div>
                </div>
                <Button type="button" variant="outline" size="sm" @click="saleItems.push({ product_id: '', quantity: 1 })">
                  {{ t('sales.addLine') }}
                </Button>
              </div>
              <p v-if="productsQuery.isError" class="text-amber-600 dark:text-amber-400 text-sm">
                {{ t('sales.productsNotLoaded') }}
              </p>
              <p v-if="saleError" class="text-destructive text-sm">{{ saleError }}</p>
              <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row">
                <Button
                  type="submit"
                  class="w-full sm:w-auto"
                  :disabled="createSaleMutation.isPending.value || !productList.length"
                >
                  {{ t('sales.createSale') }}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  class="w-full sm:w-auto"
                  data-tour="sales-modal-cancel"
                  @click="showNewSale = false; saleError = ''"
                >
                  {{ t('sales.cancel') }}
                </Button>
              </div>
            </form>
          </Card>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, reactive, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import { ArrowUpDown, ChevronDown, ChevronUp } from 'lucide-vue-next'
import type { Sale, Product, PaginatedResponse } from '@/types'

const { t } = useI18n()

interface SaleItemForm {
  product_id: string | number
  quantity: number
}

const router = useRouter()
const route = useRoute()
const showNewSale = ref(false)
const saleError = ref('')
const saleItems = reactive<SaleItemForm[]>([{ product_id: '', quantity: 1 }])
const saleDate = ref<string>(new Date().toISOString().slice(0, 10))
const branches = ref<Array<{ id: number; name: string }>>([])
const selectedBranchId = ref<number>(0)
const visibleColumns = reactive({
  createdAt: true,
  updatedAt: false,
  total: true,
})

const queryClient = useQueryClient()

onMounted(() => {
  api
    .get<{ data: Array<{ id: number; name: string }> }>('/branches')
    .then((res) => {
      branches.value = res.data.data
      selectedBranchId.value = branches.value[0]?.id ?? 0
    })
    .catch(() => {
      // Si el endpoint no está disponible para tu plan, el backend usará la sucursal por defecto.
      selectedBranchId.value = 0
    })

  if (route.query.new === '1') {
    showNewSale.value = true
    const { new: _new, ...rest } = route.query
    router.replace({ name: route.name as string, params: route.params, query: { ...rest } })
  }
})

function goToSale(id: number) {
  router.push({ name: 'SaleDetail', params: { id: String(id) } })
}

const salesPage = ref(1)
const salesPerPage = ref(15)
const salesSearch = ref('')
const salesDateFrom = ref('')
const salesDateTo = ref('')
const salesSortBy = ref<'created_at' | 'updated_at' | 'total'>('created_at')
const salesSortDir = ref<'asc' | 'desc'>('desc')

const salesQuery = useQuery({
  queryKey: computed(() => [
    'sales',
    salesPage.value,
    salesPerPage.value,
    salesDateFrom.value,
    salesDateTo.value,
    salesSortBy.value,
    salesSortDir.value,
  ]),
  queryFn: async (): Promise<PaginatedResponse<Sale>> => {
    const params: Record<string, string | number> = {
      page: salesPage.value,
      per_page: salesPerPage.value,
      sort_by: salesSortBy.value === 'total' ? 'total' : salesSortBy.value,
      sort_dir: salesSortDir.value,
    }
    if (salesDateFrom.value) params.from = salesDateFrom.value
    if (salesDateTo.value) params.to = salesDateTo.value
    const { data } = await api.get<PaginatedResponse<Sale>>('/sales', { params })
    return data as PaginatedResponse<Sale>
  },
})

const productsQuery = useQuery({
  queryKey: ['products-all'],
  queryFn: async (): Promise<Product[]> => {
    const { data } = await api.get<PaginatedResponse<Product> | Product[]>('/products', { params: { per_page: 100 } })
    const list = Array.isArray(data) ? data : (data as PaginatedResponse<Product>)?.data ?? []
    return Array.isArray(list) ? list.filter((p): p is Product => p != null && typeof p === 'object' && 'id' in p) : []
  },
})

const salesList = computed(() => {
  const res = salesQuery.data.value
  const raw = res && typeof res === 'object' && 'data' in res ? (res as PaginatedResponse<Sale>).data : Array.isArray(res) ? res : []
  return Array.isArray(raw) ? raw.filter((s): s is Sale => s != null && typeof s === 'object' && 'id' in s) : []
})

const filteredSortedSales = computed(() => {
  const term = salesSearch.value.trim().toLowerCase()
  if (!term) return salesList.value
  return salesList.value.filter((s) => {
    const dateStr = formatDate(s.created_at).toLowerCase()
    const totalStr = String(s.total_amount ?? '').toLowerCase()
    return dateStr.includes(term) || totalStr.includes(term)
  })
})

const salesPaginated = computed(() => filteredSortedSales.value)

const salesPagination = computed(() => {
  const res = salesQuery.data.value as (PaginatedResponse<Sale> & { current_page?: number; last_page?: number; total?: number; per_page?: number }) | undefined
  if (!res) return null
  if (res.meta) return res.meta
  if (typeof res.current_page === 'number' && typeof res.last_page === 'number') {
    return { current_page: res.current_page, last_page: res.last_page, total: res.total ?? 0, per_page: res.per_page ?? 15 }
  }
  return null
})

const productList = computed(() => {
  const raw = productsQuery.data.value ?? []
  return Array.isArray(raw) ? raw.filter((p): p is Product => p != null && typeof p === 'object' && 'id' in p) : []
})

interface ApiError extends Error {
  response?: { data?: { message?: string } }
}
const salesErrorMessage = computed(() => {
  const err = salesQuery.error.value as ApiError | null
  if (!err) return t('sales.errorLoading')
  return err.response?.data?.message ?? err.message ?? t('sales.errorLoading')
})

const createSaleMutation = useMutation({
  mutationFn: (payload: { date?: string; branch_id?: number | null; items: { product_id: number; quantity: number }[] }) =>
    api.post<Sale>('/sales', payload),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['sales'] })
    showNewSale.value = false
    saleItems.length = 0
    saleItems.push({ product_id: '', quantity: 1 })
    saleDate.value = new Date().toISOString().slice(0, 10)
  },
  onError: (err: { response?: { data?: { message?: string } } }) => {
    saleError.value = err.response?.data?.message ?? t('sales.failedCreate')
  },
})

function setSalesSort(column: 'created_at' | 'updated_at' | 'total') {
  if (salesSortBy.value === column) {
    salesSortDir.value = salesSortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    salesSortBy.value = column
    salesSortDir.value = 'asc'
  }
  salesPage.value = 1
}

watch([salesDateFrom, salesDateTo], () => {
  salesPage.value = 1
})

function removeSaleItem(index: number) {
  saleItems.splice(index, 1)
  if (saleItems.length === 0) {
    saleItems.push({ product_id: '', quantity: 1 })
  }
}

async function createSale() {
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
    branch_id: selectedBranchId.value || null,
    items,
  })
}

function exportSalesCsv() {
  const headers = [t('sales.date'), t('sales.total')]
  const rows = salesList.value.map((s) => [s.created_at ?? '', Number(s.total_amount)])
  const csv = [headers.join(','), ...rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `ventas-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

function formatDate(str: string | undefined): string {
  if (!str) return '—'
  // Render date portion only to avoid timezone shifts when backend stores `created_at` at midnight.
  if (typeof str === 'string' && str.length >= 10) {
    return str.slice(0, 10)
  }
  return new Date(str).toISOString().slice(0, 10)
}

function formatCurrency(v: number | null | undefined): string {
  return v != null ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v) : '—'
}
</script>
