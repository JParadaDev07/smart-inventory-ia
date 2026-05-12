<template>
  <div class="space-y-6 px-1 sm:px-0">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-xl font-semibold sm:text-2xl">{{ t('products.title') }}</h1>
      <div class="flex flex-wrap gap-2">
        <Button variant="outline" size="sm" :disabled="!productList.length" @click="exportProductsCsv">
          {{ t('products.exportCsv') }}
        </Button>
        <Button variant="outline" size="sm" :disabled="!productList.length" @click="exportProductsPdf">
          PDF
        </Button>
        <Button class="w-full sm:w-auto shrink-0" data-tour="products-add" @click="showCreate = true">
          {{ t('products.addProduct') }}
        </Button>
      </div>
    </div>

    <Card>
      <!-- Skeleton -->
      <div v-if="productsQuery.isLoading.value && !productsQuery.data.value" class="p-6 sm:p-8">
        <div class="animate-pulse space-y-3">
          <div v-for="i in 5" :key="i" class="h-10 rounded bg-muted" />
        </div>
      </div>

      <template v-else>
        <!-- Filters toolbar -->
        <ProductFilters
          v-model:search="search"
          v-model:date-from="dateFrom"
          v-model:date-to="dateTo"
          v-model:per-page="perPage"
          :visible-columns="visibleColumns"
          @reset-page="page = 1"
          @toggle-column="(key) => { (visibleColumns as Record<string, boolean>)[key] = !(visibleColumns as Record<string, boolean>)[key] }"
        />

        <!-- Table -->
        <ProductsTable
          :products="filteredSorted"
          :sort-by="sortBy"
          :sort-dir="sortDir"
          :visible-columns="visibleColumns"
          @sort="setSort"
          @edit="openEdit"
          @delete="productToDelete = $event"
          @intelligence="goIntelligence"
        />

        <!-- Error / empty state -->
        <div v-if="productsQuery.isError.value" class="p-6 sm:p-8 text-center text-destructive text-sm">
          {{ errorMessage }}
        </div>
        <div v-else-if="!productList.length" class="p-6 sm:p-8 text-center text-muted-foreground text-sm">
          {{ t('products.noProductsYet') }}
        </div>

        <!-- Pagination -->
        <div
          v-if="pagination && pagination.total > 0"
          class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
          <p class="text-muted-foreground text-sm order-2 sm:order-1 text-center sm:text-left">
            {{ t('products.pageOf', { current: pagination.current_page, last: pagination.last_page }) }}
            <span class="hidden sm:inline"> ({{ pagination.total }} {{ t('products.total') }})</span>
          </p>
          <div class="flex gap-2 justify-center sm:order-2">
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="pagination.current_page <= 1"
              @click="page = Math.max(1, pagination.current_page - 1)"
            >
              {{ t('products.previous') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="pagination.current_page >= pagination.last_page"
              @click="page = Math.min(pagination.last_page, pagination.current_page + 1)"
            >
              {{ t('products.next') }}
            </Button>
          </div>
        </div>
      </template>
    </Card>

    <!-- Create / Edit modal -->
    <ProductModal
      v-if="showCreate || editingProduct"
      :product="editingProduct"
      :error="formError"
      :is-pending="createMutation.isPending.value || updateMutation.isPending.value"
      @close="closeForm"
      @submit="handleFormSubmit"
    />

    <!-- Delete confirmation -->
    <ProductDeleteDialog
      v-if="productToDelete"
      :product="productToDelete"
      :is-pending="deleteMutation.isPending.value"
      @confirm="executeDelete"
      @cancel="productToDelete = null"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import ProductFilters from '@/components/products/ProductFilters.vue'
import ProductsTable from '@/components/products/ProductsTable.vue'
import ProductModal from '@/components/products/ProductModal.vue'
import ProductDeleteDialog from '@/components/products/ProductDeleteDialog.vue'
import { useProducts } from '@/composables/useProducts'
import type { Product } from '@/types'

const { t } = useI18n()
const router = useRouter()

const {
  productsQuery,
  productList,
  filteredSorted,
  pagination,
  errorMessage,
  page,
  perPage,
  search,
  dateFrom,
  dateTo,
  sortBy,
  sortDir,
  visibleColumns,
  setSort,
  formError,
  createMutation,
  updateMutation,
  deleteMutation,
} = useProducts()

// --- UI state ---
const showCreate = ref(false)
const editingProduct = ref<Product | null>(null)
const productToDelete = ref<Product | null>(null)

function openEdit(p: Product) {
  editingProduct.value = p
}

function closeForm() {
  showCreate.value = false
  editingProduct.value = null
  formError.value = ''
}

async function handleFormSubmit(form: {
  name: string; sku: string; cost_price: number; sale_price: number
  current_stock: number; minimum_stock: number; supplier_lead_time_days: number
  perecedero: boolean; expired_mode: string; expiration_alert_days: number
}) {
  if (editingProduct.value) {
    const payload = { ...form }
    if (payload.perecedero) delete (payload as Partial<typeof payload>).current_stock
    await updateMutation.mutateAsync({ id: editingProduct.value.id, payload })
    if (!updateMutation.isError.value) closeForm()
  } else {
    const payload = { ...form }
    if (payload.perecedero) payload.current_stock = 0
    await createMutation.mutateAsync(payload)
    if (!createMutation.isError.value) closeForm()
  }
}

function executeDelete() {
  if (!productToDelete.value) return
  const id = productToDelete.value.id
  productToDelete.value = null
  deleteMutation.mutate(id)
}

function goIntelligence(id: number) {
  router.push({ name: 'ProductIntelligence', params: { id: String(id) } })
}

// --- Export helpers ---
function formatCurrency(v: number | string | null | undefined): string {
  return v != null
    ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v))
    : '—'
}

function exportProductsCsv() {
  const headers = [t('products.name'), t('products.sku'), t('products.stock'), t('products.min'), t('products.price')]
  const rows = productList.value.map((p) => [p.name, p.sku ?? '', p.current_stock, p.minimum_stock, Number(p.sale_price)])
  const csv = [headers.join(','), ...rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))].join('\n')
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `productos-${new Date().toISOString().slice(0, 10)}.csv`
  link.click()
  URL.revokeObjectURL(link.href)
}

function exportProductsPdf() {
  if (!productList.value.length) return
  const doc = new jsPDF()
  const date = new Date().toISOString().slice(0, 10)
  doc.setFontSize(16)
  doc.text(String(t('products.title')), 14, 18)
  doc.setFontSize(10)
  doc.setTextColor(100)
  doc.text(`Generado el ${date}`, 14, 25)
  autoTable(doc, {
    startY: 32,
    head: [[t('products.name'), t('products.sku'), t('products.stock'), t('products.min'), t('products.price')].map(String)],
    body: productList.value.map((p) => [p.name, p.sku ?? '', String(p.current_stock), String(p.minimum_stock), formatCurrency(p.sale_price)]),
    styles: { fontSize: 8 },
    headStyles: { fillColor: [15, 23, 42] },
    alternateRowStyles: { fillColor: [248, 250, 252] },
  })
  doc.save(`productos-${date}.pdf`)
}
</script>
