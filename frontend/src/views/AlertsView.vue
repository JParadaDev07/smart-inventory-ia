<template>
  <div class="space-y-6">
    <h1 class="text-xl font-semibold sm:text-2xl">{{ t('alerts.title') }}</h1>

    <div v-if="isLoading" class="animate-pulse space-y-4">
      <div class="h-40 rounded-lg bg-muted" />
      <div class="h-40 rounded-lg bg-muted" />
    </div>
    <div v-else-if="isError" class="rounded-lg border border-destructive/30 bg-destructive/5 p-6 text-destructive text-sm">
      {{ t('alerts.errorLoading') }}
    </div>
    <template v-else-if="alerts">
      <Card>
        <template #header>
          <span class="text-muted-foreground text-sm font-medium" data-tour="alerts-low-stock-title">
            {{ t('alerts.lowStock') }}
          </span>
          <span class="text-muted-foreground text-xs">({{ alerts?.low_stock?.length ?? 0 }})</span>
        </template>
        <p class="text-muted-foreground mb-3 text-sm">{{ t('alerts.lowStockDesc') }}</p>
        <div v-if="!alerts?.low_stock?.length" class="py-4 text-center text-sm text-muted-foreground">
          {{ t('alerts.noLowStock') }}
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b bg-muted/30">
                <th class="h-10 px-3 text-left font-medium sm:px-4">{{ t('alerts.product') }}</th>
                <th class="h-10 px-3 text-right font-medium sm:px-4">{{ t('alerts.stock') }}</th>
                <th class="h-10 px-3 text-right font-medium sm:px-4">{{ t('alerts.min') }}</th>
                <th class="w-[1%] px-3 py-2 sm:px-4"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="p in lowStockPaginated"
                :key="p.id"
                class="border-b last:border-0 hover:bg-muted/20"
              >
                <td class="px-3 py-3 sm:px-4 font-medium">{{ p.name }}</td>
                <td class="px-3 py-3 text-right text-destructive font-medium sm:px-4">{{ p.current_stock }}</td>
                <td class="px-3 py-3 text-right sm:px-4">{{ p.minimum_stock }}</td>
                <td class="px-3 py-2 sm:px-4">
                  <Button variant="ghost" size="sm" @click="goToIntelligence(p.id)">
                    {{ t('alerts.viewProduct') }}
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="lowStockPagination.lastPage > 1"
          class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
          <p class="text-muted-foreground text-sm order-2 sm:order-1 text-center sm:text-left">
            {{ t('alerts.pageOf', { current: lowStockPage, last: lowStockPagination.lastPage }) }}
            <span class="hidden sm:inline"> ({{ lowStockPagination.total }} {{ t('alerts.total') }})</span>
          </p>
          <div class="flex gap-2 justify-center sm:order-2">
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="lowStockPage <= 1"
              @click="lowStockPage = Math.max(1, lowStockPage - 1)"
            >
              {{ t('alerts.previous') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="lowStockPage >= lowStockPagination.lastPage"
              @click="lowStockPage = Math.min(lowStockPagination.lastPage, lowStockPage + 1)"
            >
              {{ t('alerts.next') }}
            </Button>
          </div>
        </div>
      </Card>

      <Card>
        <template #header>
          <span class="text-muted-foreground text-sm font-medium">{{ t('alerts.recommendedPurchase') }}</span>
          <span class="text-muted-foreground text-xs">({{ alerts?.recommended_purchase?.length ?? 0 }})</span>
        </template>
        <p class="text-muted-foreground mb-3 text-sm">{{ t('alerts.recommendedPurchaseDesc') }}</p>
        <div v-if="!alerts?.recommended_purchase?.length" class="py-4 text-center text-sm text-muted-foreground">
          {{ t('alerts.noRecommended') }}
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b bg-muted/30">
                <th class="h-10 px-3 text-left font-medium sm:px-4">{{ t('alerts.product') }}</th>
                <th class="h-10 px-3 text-right font-medium sm:px-4">{{ t('alerts.stock') }}</th>
                <th class="h-10 px-3 text-right font-medium sm:px-4">{{ t('alerts.recommendedQty') }}</th>
                <th class="w-[1%] px-3 py-2 sm:px-4"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in recommendedPaginated"
                :key="row.product.id"
                class="border-b last:border-0 hover:bg-muted/20"
              >
                <td class="px-3 py-3 sm:px-4 font-medium">{{ row.product.name }}</td>
                <td class="px-3 py-3 text-right sm:px-4">{{ row.product.current_stock }}</td>
                <td class="px-3 py-3 text-right font-medium sm:px-4">{{ row.recommended_quantity }}</td>
                <td class="px-3 py-2 sm:px-4">
                  <Button variant="ghost" size="sm" @click="goToIntelligence(row.product.id)">
                    {{ t('alerts.viewProduct') }}
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="recommendedPagination.lastPage > 1"
          class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
          <p class="text-muted-foreground text-sm order-2 sm:order-1 text-center sm:text-left">
            {{ t('alerts.pageOf', { current: recommendedPage, last: recommendedPagination.lastPage }) }}
            <span class="hidden sm:inline"> ({{ recommendedPagination.total }} {{ t('alerts.total') }})</span>
          </p>
          <div class="flex gap-2 justify-center sm:order-2">
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="recommendedPage <= 1"
              @click="recommendedPage = Math.max(1, recommendedPage - 1)"
            >
              {{ t('alerts.previous') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="recommendedPage >= recommendedPagination.lastPage"
              @click="recommendedPage = Math.min(recommendedPagination.lastPage, recommendedPage + 1)"
            >
              {{ t('alerts.next') }}
            </Button>
          </div>
        </div>
      </Card>

      <Card>
        <template #header>
          <span class="text-muted-foreground text-sm font-medium">{{ t('alerts.expiringSoon') }}</span>
          <span class="text-muted-foreground text-xs">({{ alerts?.expiring_soon?.length ?? 0 }})</span>
        </template>
        <p class="text-muted-foreground mb-3 text-sm">{{ t('alerts.expiringSoonDesc') }}</p>
        <div v-if="!alerts?.expiring_soon?.length" class="py-4 text-center text-sm text-muted-foreground">
          {{ t('alerts.noExpiringSoon') }}
        </div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b bg-muted/30">
                <th class="h-10 px-3 text-left font-medium sm:px-4">{{ t('alerts.product') }}</th>
                <th class="h-10 px-3 text-left font-medium sm:px-4">{{ t('alerts.branch') }}</th>
                <th class="h-10 px-3 text-right font-medium sm:px-4">{{ t('alerts.stock') }}</th>
                <th class="h-10 px-3 text-right font-medium sm:px-4">{{ t('alerts.expirationDate') }}</th>
                <th class="h-10 px-3 text-right font-medium sm:px-4">{{ t('alerts.daysLeft') }}</th>
                <th class="w-[1%] px-3 py-2 sm:px-4"></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in expiringSoonPaginated"
                :key="row.batch_id"
                class="border-b last:border-0 hover:bg-muted/20"
              >
                <td class="px-3 py-3 sm:px-4 font-medium">{{ row.product.name }}</td>
                <td class="px-3 py-3 sm:px-4 text-muted-foreground">{{ row.branch?.name ?? '—' }}</td>
                <td class="px-3 py-3 text-right sm:px-4">{{ row.quantity_available }}</td>
                <td class="px-3 py-3 text-right sm:px-4">{{ row.expiration_date }}</td>
                <td class="px-3 py-3 text-right sm:px-4">{{ row.days_until }}d</td>
                <td class="px-3 py-2 sm:px-4">
                  <Button variant="ghost" size="sm" @click="goToIntelligence(row.product.id)">
                    {{ t('alerts.viewProduct') }}
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div
          v-if="expiringSoonPagination.lastPage > 1"
          class="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
        >
          <p class="text-muted-foreground text-sm order-2 sm:order-1 text-center sm:text-left">
            {{ t('alerts.pageOf', { current: expiringSoonPage, last: expiringSoonPagination.lastPage }) }}
            <span class="hidden sm:inline"> ({{ expiringSoonPagination.total }} {{ t('alerts.total') }})</span>
          </p>
          <div class="flex gap-2 justify-center sm:order-2">
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="expiringSoonPage <= 1"
              @click="expiringSoonPage = Math.max(1, expiringSoonPage - 1)"
            >
              {{ t('alerts.previous') }}
            </Button>
            <Button
              variant="outline"
              size="sm"
              class="flex-1 sm:flex-none min-w-0"
              :disabled="expiringSoonPage >= expiringSoonPagination.lastPage"
              @click="expiringSoonPage = Math.min(expiringSoonPagination.lastPage, expiringSoonPage + 1)"
            >
              {{ t('alerts.next') }}
            </Button>
          </div>
        </div>
      </Card>
    </template>
    <div v-else class="rounded-lg border bg-card p-8 text-center">
      <p class="text-muted-foreground text-sm">{{ t('alerts.loading') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuery } from '@tanstack/vue-query'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'

const ALERTS_PER_PAGE = 10

const { t } = useI18n()
const router = useRouter()
const lowStockPage = ref(1)
const recommendedPage = ref(1)
const expiringSoonPage = ref(1)

interface AlertsResponse {
  low_stock: Array<{ id: number; name: string; sku: string | null; current_stock: number; minimum_stock: number }>
  recommended_purchase: Array<{
    product: { id: number; name: string; sku: string | null; current_stock: number; minimum_stock: number }
    recommended_quantity: number
  }>
  expiring_soon?: Array<{
    batch_id: number
    product: { id: number; name: string; sku: string | null }
    branch?: { id: number; name: string | null }
    quantity_available: number
    expiration_date: string
    days_until: number
  }>
}

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

const lowStockPagination = computed(() => {
  const list = alerts.value?.low_stock ?? []
  const total = list.length
  const lastPage = Math.max(1, Math.ceil(total / ALERTS_PER_PAGE))
  return { total, lastPage }
})

const lowStockPaginated = computed(() => {
  const list = alerts.value?.low_stock ?? []
  const start = (lowStockPage.value - 1) * ALERTS_PER_PAGE
  return list.slice(start, start + ALERTS_PER_PAGE)
})

const recommendedPagination = computed(() => {
  const list = alerts.value?.recommended_purchase ?? []
  const total = list.length
  const lastPage = Math.max(1, Math.ceil(total / ALERTS_PER_PAGE))
  return { total, lastPage }
})

const recommendedPaginated = computed(() => {
  const list = alerts.value?.recommended_purchase ?? []
  const start = (recommendedPage.value - 1) * ALERTS_PER_PAGE
  return list.slice(start, start + ALERTS_PER_PAGE)
})

const expiringSoonPagination = computed(() => {
  const list = alerts.value?.expiring_soon ?? []
  const total = list.length
  const lastPage = Math.max(1, Math.ceil(total / ALERTS_PER_PAGE))
  return { total, lastPage }
})

const expiringSoonPaginated = computed(() => {
  const list = alerts.value?.expiring_soon ?? []
  const start = (expiringSoonPage.value - 1) * ALERTS_PER_PAGE
  return list.slice(start, start + ALERTS_PER_PAGE)
})

watch(lowStockPagination, ({ lastPage }) => {
  if (lowStockPage.value > lastPage) lowStockPage.value = 1
})
watch(recommendedPagination, ({ lastPage }) => {
  if (recommendedPage.value > lastPage) recommendedPage.value = 1
})
watch(expiringSoonPagination, ({ lastPage }) => {
  if (expiringSoonPage.value > lastPage) expiringSoonPage.value = 1
})

function goToIntelligence(productId: number) {
  router.push({ name: 'ProductIntelligence', params: { id: String(productId) } })
}

</script>
