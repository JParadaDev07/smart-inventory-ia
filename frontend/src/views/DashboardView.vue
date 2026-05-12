<template>
  <div class="space-y-6">
    <h1 class="text-xl font-semibold sm:text-2xl">{{ t('dashboard.title') }}</h1>
    <div class="grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4" data-tour="dashboard-metrics">
      <Card>
        <div class="flex flex-row items-center justify-between space-y-0 pb-2">
          <span class="text-muted-foreground text-sm font-medium">{{ t('dashboard.totalProducts') }}</span>
          <Package class="text-muted-foreground h-4 w-4" />
        </div>
        <p class="text-2xl font-bold">{{ dashboardData?.total_products ?? '—' }}</p>
      </Card>
      <Card class="transition-colors hover:bg-muted/30">
        <router-link to="/app/alerts" class="block">
          <div class="flex flex-row items-center justify-between space-y-0 pb-2">
            <span class="text-muted-foreground text-sm font-medium">{{ t('dashboard.lowStock') }}</span>
            <AlertTriangle class="text-muted-foreground h-4 w-4" />
          </div>
          <p class="text-2xl font-bold">{{ dashboardData?.low_stock_products ?? '—' }}</p>
        </router-link>
      </Card>
      <Card>
        <div class="flex flex-row items-center justify-between space-y-0 pb-2">
          <span class="text-muted-foreground text-sm font-medium">{{ t('dashboard.salesLast30') }}</span>
          <TrendingUp class="text-muted-foreground h-4 w-4" />
        </div>
        <p class="text-2xl font-bold">{{ formatCurrency(dashboardData?.sales_last_30_days) }}</p>
      </Card>
      <Card class="transition-colors hover:bg-muted/30">
        <router-link to="/app/alerts" class="block">
          <div class="flex flex-row items-center justify-between space-y-0 pb-2">
            <span class="text-muted-foreground text-sm font-medium">{{ t('dashboard.aiAlerts') }}</span>
            <Sparkles class="text-muted-foreground h-4 w-4" />
          </div>
          <p class="text-2xl font-bold">{{ dashboardData?.ai_alerts ?? '—' }}</p>
        </router-link>
      </Card>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <Card>
        <template #header>
          <span class="text-muted-foreground text-sm font-medium">{{ t('dashboard.salesLast30Days') }}</span>
        </template>
        <SalesChart :sales-by-day="dashboardData?.sales_by_day" />
      </Card>
      <Card>
        <template #header>
          <span class="text-muted-foreground text-sm font-medium">{{ t('dashboard.inventoryHealth') }}</span>
        </template>
        <InventoryHealthChart
          :total-products="dashboardData?.total_products"
          :low-stock-products="dashboardData?.low_stock_products"
        />
      </Card>
    </div>

    <Card>
      <template #header>
        <span class="text-muted-foreground text-sm font-medium">{{ t('dashboard.topProductsTitle') }}</span>
      </template>
      <TopProductsChart :top-products="dashboardData?.top_products" />
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuery } from '@tanstack/vue-query'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import SalesChart from '@/components/SalesChart.vue'
import InventoryHealthChart from '@/components/InventoryHealthChart.vue'
import TopProductsChart from '@/components/TopProductsChart.vue'
import { Package, AlertTriangle, TrendingUp, Sparkles } from 'lucide-vue-next'
import type { DashboardData } from '@/types'

const { t } = useI18n()

const dashboardQuery = useQuery({
  queryKey: ['dashboard'],
  queryFn: async (): Promise<DashboardData> => {
    const { data } = await api.get<DashboardData>('/dashboard')
    return data
  },
})

const dashboardData = computed(() => dashboardQuery.data.value)

function formatCurrency(val: number | null | undefined): string {
  if (val == null) return '—'
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(val)
}
</script>
