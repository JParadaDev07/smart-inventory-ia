<template>
  <div class="overflow-x-auto -mx-4 sm:mx-0 sm:rounded-b-lg" style="-webkit-overflow-scrolling: touch;">
    <table class="w-full min-w-[600px] text-sm">
      <thead>
        <tr class="border-b bg-muted/30">
          <SortableHeader
            column="name"
            :active-column="sortBy"
            :sort-dir="sortDir"
            align="left"
            class="px-3 sm:px-4"
            @sort="$emit('sort', 'name')"
          >
            {{ t('products.name') }}
          </SortableHeader>

          <SortableHeader
            v-if="visibleColumns.sku"
            column="sku"
            :active-column="sortBy"
            :sort-dir="sortDir"
            align="left"
            class="px-3 sm:px-4 hidden sm:table-cell"
            @sort="$emit('sort', 'sku')"
          >
            {{ t('products.sku') }}
          </SortableHeader>

          <SortableHeader
            column="stock"
            :active-column="sortBy"
            :sort-dir="sortDir"
            align="right"
            class="px-3 sm:px-4"
            @sort="$emit('sort', 'stock')"
          >
            {{ t('products.stock') }}
          </SortableHeader>

          <SortableHeader
            v-if="visibleColumns.min"
            column="min"
            :active-column="sortBy"
            :sort-dir="sortDir"
            align="right"
            class="px-3 sm:px-4 hidden sm:table-cell"
            @sort="$emit('sort', 'min')"
          >
            {{ t('products.min') }}
          </SortableHeader>

          <SortableHeader
            v-if="visibleColumns.price"
            column="price"
            :active-column="sortBy"
            :sort-dir="sortDir"
            align="right"
            class="px-3 sm:px-4"
            @sort="$emit('sort', 'price')"
          >
            {{ t('products.price') }}
          </SortableHeader>

          <SortableHeader
            v-if="visibleColumns.createdAt"
            column="created_at"
            :active-column="sortBy"
            :sort-dir="sortDir"
            align="right"
            class="px-3 hidden md:table-cell"
            @sort="$emit('sort', 'created_at')"
          >
            {{ t('products.createdAt') }}
          </SortableHeader>

          <SortableHeader
            v-if="visibleColumns.updatedAt"
            column="updated_at"
            :active-column="sortBy"
            :sort-dir="sortDir"
            align="right"
            class="px-3 hidden md:table-cell"
            @sort="$emit('sort', 'updated_at')"
          >
            {{ t('products.updatedAt') }}
          </SortableHeader>

          <th class="w-[1%] whitespace-nowrap px-3 py-3 sm:px-4 text-right font-medium">
            {{ t('products.actions') }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="p in products"
          :key="p.id"
          class="border-b last:border-0 hover:bg-muted/20 transition-colors"
        >
          <td
            class="px-3 py-3 sm:px-4 font-medium max-w-[140px] truncate sm:max-w-none sm:whitespace-normal sm:overflow-visible"
            :title="p.name"
          >
            {{ p.name }}
          </td>
          <td v-if="visibleColumns.sku" class="px-3 py-3 text-muted-foreground sm:px-4 max-w-[100px] truncate hidden sm:table-cell">
            {{ p.sku || '—' }}
          </td>
          <td
            class="px-3 py-3 text-right sm:px-4 tabular-nums"
            :class="{ 'text-destructive font-semibold': p.current_stock <= p.minimum_stock }"
          >
            {{ p.current_stock }}
          </td>
          <td v-if="visibleColumns.min" class="px-3 py-3 text-right hidden sm:table-cell sm:px-4 tabular-nums">
            {{ p.minimum_stock }}
          </td>
          <td v-if="visibleColumns.price" class="px-3 py-3 text-right sm:px-4 whitespace-nowrap tabular-nums">
            {{ formatCurrency(p.sale_price) }}
          </td>
          <td v-if="visibleColumns.createdAt" class="px-3 py-3 text-right hidden md:table-cell sm:px-4 whitespace-nowrap tabular-nums">
            {{ formatDate(p.created_at) }}
          </td>
          <td v-if="visibleColumns.updatedAt" class="px-3 py-3 text-right hidden md:table-cell sm:px-4 whitespace-nowrap tabular-nums">
            {{ formatDate(p.updated_at) }}
          </td>
          <td class="px-3 py-2 sm:px-4">
            <div class="flex flex-wrap gap-1 justify-end">
              <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :aria-label="t('products.intel')"
                :title="t('products.intel')"
                @click.stop="$emit('intelligence', p.id)"
              >
                <Brain class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8"
                :aria-label="t('products.edit')"
                :title="t('products.edit')"
                @click.stop="$emit('edit', p)"
              >
                <Pencil class="h-4 w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="h-8 w-8 text-destructive"
                :aria-label="t('products.del')"
                :title="t('products.del')"
                @click.stop="$emit('delete', p)"
              >
                <Trash2 class="h-4 w-4" />
              </Button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { Brain, Pencil, Trash2 } from 'lucide-vue-next'
import Button from '@/components/ui/Button.vue'
import SortableHeader from '@/components/ui/SortableHeader.vue'
import type { Product } from '@/types'
import type { ProductsSortKey } from '@/composables/useProducts'

const { t } = useI18n()

defineProps<{
  products: Product[]
  sortBy: ProductsSortKey
  sortDir: 'asc' | 'desc'
  visibleColumns: Record<string, boolean>
}>()

defineEmits<{
  sort: [column: ProductsSortKey]
  edit: [product: Product]
  delete: [product: Product]
  intelligence: [id: number]
}>()

function formatCurrency(v: number | string | null | undefined): string {
  return v != null
    ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v))
    : '—'
}

function formatDate(str: string | null | undefined): string {
  if (!str) return '—'
  const d = new Date(str)
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString()
}
</script>
