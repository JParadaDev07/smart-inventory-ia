<template>
  <div class="flex flex-col gap-3 px-4 pt-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
    <input
      :value="search"
      type="text"
      class="flex h-9 w-full sm:w-64 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      :placeholder="t('products.searchPlaceholder')"
      @input="$emit('update:search', ($event.target as HTMLInputElement).value)"
    />
    <div class="flex flex-wrap items-center justify-end gap-2">
      <!-- Date range -->
      <details class="relative">
        <summary
          class="cursor-pointer list-none select-none rounded-md border border-input bg-background px-3 py-2 text-sm text-muted-foreground hover:bg-accent"
        >
          {{ t('products.dateFrom') }} / {{ t('products.dateTo') }}
        </summary>
        <div class="absolute left-0 z-20 mt-2 w-72 rounded-md border bg-card p-3 shadow-lg">
          <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3">
              <label class="text-muted-foreground text-sm whitespace-nowrap">{{ t('products.dateFrom') }}</label>
              <input
                :value="dateFrom"
                type="date"
                class="flex h-9 w-36 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @change="$emit('update:dateFrom', ($event.target as HTMLInputElement).value); $emit('reset-page')"
              />
            </div>
            <div class="flex items-center justify-between gap-3">
              <label class="text-muted-foreground text-sm whitespace-nowrap">{{ t('products.dateTo') }}</label>
              <input
                :value="dateTo"
                type="date"
                class="flex h-9 w-36 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                @change="$emit('update:dateTo', ($event.target as HTMLInputElement).value); $emit('reset-page')"
              />
            </div>
          </div>
        </div>
      </details>

      <!-- Rows per page -->
      <div class="flex items-center gap-2">
        <label class="text-muted-foreground text-sm whitespace-nowrap">{{ t('products.rowsPerPage') }}</label>
        <select
          :value="perPage"
          class="flex h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          @change="$emit('update:perPage', Number(($event.target as HTMLSelectElement).value)); $emit('reset-page')"
        >
          <option :value="10">10</option>
          <option :value="15">15</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
      </div>

      <!-- Column visibility -->
      <details class="relative">
        <summary
          class="cursor-pointer list-none select-none rounded-md border border-input bg-background px-3 py-2 text-sm text-muted-foreground hover:bg-accent"
        >
          {{ t('products.columns') }}
        </summary>
        <div class="absolute right-0 z-20 mt-2 w-64 rounded-md border bg-card p-3 shadow-lg">
          <div class="space-y-2 text-sm">
            <label v-for="col in columnOptions" :key="col.key" class="flex items-center justify-between gap-3">
              <span class="text-muted-foreground">{{ col.label }}</span>
              <input
                type="checkbox"
                :checked="visibleColumns[col.key]"
                @change="$emit('toggle-column', col.key)"
              />
            </label>
          </div>
        </div>
      </details>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { computed } from 'vue'

const { t } = useI18n()

const props = defineProps<{
  search: string
  dateFrom: string
  dateTo: string
  perPage: number
  visibleColumns: Record<string, boolean>
}>()

defineEmits<{
  'update:search': [value: string]
  'update:dateFrom': [value: string]
  'update:dateTo': [value: string]
  'update:perPage': [value: number]
  'reset-page': []
  'toggle-column': [key: string]
}>()

const columnOptions = computed(() => [
  { key: 'sku', label: t('products.sku') },
  { key: 'min', label: t('products.min') },
  { key: 'price', label: t('products.price') },
  { key: 'createdAt', label: t('products.createdAt') },
  { key: 'updatedAt', label: t('products.updatedAt') },
])
</script>
