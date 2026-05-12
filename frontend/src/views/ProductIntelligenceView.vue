<template>
  <div class="space-y-6">
    <div class="flex items-center gap-4">
      <Button variant="ghost" size="sm" @click="$router.push({ name: 'Products' })">{{ t('intelligence.backToProducts') }}</Button>
      <h1 class="text-2xl font-semibold">{{ t('intelligence.title') }}</h1>
    </div>
    <div v-if="intelligenceQuery.isLoading.value" class="space-y-4">
      <div class="grid gap-4 md:grid-cols-2">
        <Card class="animate-pulse">
          <div class="h-4 w-24 rounded bg-muted mb-4" />
          <div class="h-6 w-16 rounded bg-muted mb-2" />
          <div class="h-3 w-24 rounded bg-muted mb-4" />
          <div class="h-6 w-16 rounded bg-muted mb-2" />
          <div class="h-3 w-24 rounded bg-muted" />
        </Card>
        <Card class="animate-pulse">
          <div class="h-4 w-32 rounded bg-muted mb-4" />
          <div class="space-y-2">
            <div class="h-3 w-full rounded bg-muted" />
            <div class="h-3 w-3/4 rounded bg-muted" />
            <div class="h-3 w-2/3 rounded bg-muted" />
            <div class="h-3 w-1/2 rounded bg-muted" />
          </div>
        </Card>
      </div>
      <Card class="animate-pulse">
        <div class="h-4 w-40 rounded bg-muted mb-4" />
        <div class="grid gap-2 sm:grid-cols-2">
          <div class="h-3 w-full rounded bg-muted" />
          <div class="h-3 w-5/6 rounded bg-muted" />
          <div class="h-3 w-4/5 rounded bg-muted" />
          <div class="h-3 w-2/3 rounded bg-muted" />
        </div>
      </Card>
    </div>
    <template v-else-if="intelligenceQuery.data">
      <div class="grid gap-4 md:grid-cols-2">
        <Card>
          <template #header>
            <span class="text-muted-foreground text-sm font-medium">{{ t('intelligence.stock') }}</span>
          </template>
          <p class="text-2xl font-bold">{{ intelligenceQuery.data?.value?.product?.current_stock ?? '—' }}</p>
          <p class="text-muted-foreground text-sm">{{ t('intelligence.currentStock') }}</p>
          <p class="mt-2 text-2xl font-bold">{{ intelligenceQuery.data?.value?.product?.minimum_stock ?? '—' }}</p>
          <p class="text-muted-foreground text-sm">{{ t('intelligence.minimumStock') }}</p>
          <div v-if="coverageDays" class="mt-4 rounded-md bg-muted px-3 py-2">
            <p class="text-xs text-muted-foreground">{{ t('intelligence.coverageDaysLabel') }}</p>
            <p class="text-lg font-semibold">
              {{ coverageDays }}
              <span class="text-xs font-normal text-muted-foreground">{{ t('intelligence.coverageDaysSuffix') }}</span>
            </p>
          </div>
          <div class="mt-4 pt-4 border-t">
            <Button variant="outline" size="sm" @click="showAdjust = true">{{ t('intelligence.adjustStock') }}</Button>
          </div>
        </Card>
        <Card>
          <template #header>
            <span class="text-muted-foreground text-sm font-medium">{{ t('intelligence.localIntelligence') }}</span>
          </template>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-muted-foreground">{{ t('intelligence.avgDailyDemand') }}</dt>
              <dd class="font-medium">{{ intelligenceQuery.data?.value?.intelligence?.average_daily_demand ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-muted-foreground">{{ t('intelligence.reorderPoint') }}</dt>
              <dd class="font-medium">{{ intelligenceQuery.data?.value?.intelligence?.reorder_point ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-muted-foreground">{{ t('intelligence.recommendedPurchase') }}</dt>
              <dd class="font-medium">{{ intelligenceQuery.data?.value?.intelligence?.recommended_purchase_quantity ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-muted-foreground">{{ t('intelligence.estimatedStockOutDate') }}</dt>
              <dd class="font-medium">{{ intelligenceQuery.data?.value?.intelligence?.estimated_stock_out_date ?? '—' }}</dd>
            </div>
          </dl>
        </Card>
      </div>
      <Card v-if="intelligenceQuery.data?.value?.intelligence?.recommended_purchase_quantity && intelligenceQuery.data?.value?.intelligence?.recommended_purchase_quantity > 0" class="border-destructive bg-destructive/5">
        <div class="flex items-center gap-2">
          <AlertTriangle class="text-destructive h-5 w-5 shrink-0" />
          <p class="font-medium">
            {{ t('intelligence.buyUnitsThisWeek', { count: intelligenceQuery.data?.value?.intelligence?.recommended_purchase_quantity ?? '—' }) }}
          </p>
        </div>
      </Card>
      <Card v-if="intelligenceQuery.data?.value?.ai_prediction" class="border-primary/50">
        <template #header>
          <div class="flex items-center justify-between gap-2">
            <div>
              <span class="text-muted-foreground text-sm font-medium">{{ t('intelligence.aiPrediction') }}</span>
              <p class="text-muted-foreground text-xs">
                Predicción de demanda basada en el historial de ventas.
              </p>
            </div>
            <span class="rounded-full border border-primary/60 px-2 py-0.5 text-[10px] font-semibold uppercase text-primary">
              Pro
            </span>
          </div>
        </template>
        <dl class="grid gap-2 sm:grid-cols-2 text-sm">
          <div>
            <dt class="text-muted-foreground">{{ t('intelligence.predictedNext30Days') }}</dt>
            <dd class="font-medium">{{ intelligenceQuery.data?.value?.ai_prediction?.predicted_next_30_days ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">{{ t('intelligence.predictedDailyAverage') }}</dt>
            <dd class="font-medium">{{ intelligenceQuery.data?.value?.ai_prediction?.predicted_daily_average ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">{{ t('intelligence.predictedStockOutDate') }}</dt>
            <dd class="font-medium">{{ intelligenceQuery.data?.value?.ai_prediction?.predicted_stock_out_date ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">{{ t('intelligence.recommendedPurchaseAi') }}</dt>
            <dd class="font-medium">{{ intelligenceQuery.data?.value?.ai_prediction?.recommended_purchase_quantity ?? '—' }}</dd>
          </div>
        </dl>
        <div
          v-if="advancedHorizons"
          class="mt-4 border-t pt-3"
        >
          <div class="mb-2 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-muted-foreground">Horizonte de pronóstico</span>
            <div class="inline-flex rounded-md border bg-background p-0.5 text-xs">
              <button
                v-for="option in horizonOptions"
                :key="option"
                type="button"
                class="rounded px-2 py-1"
                :class="selectedHorizon === option ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                @click="selectedHorizon = option"
              >
                {{ option }}d
              </button>
            </div>
          </div>
          <dl class="grid gap-2 sm:grid-cols-2 text-sm">
            <div>
              <dt class="text-muted-foreground">Ventas estimadas en el horizonte seleccionado</dt>
              <dd class="font-medium">{{ selectedHorizonData?.total ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">{{ t('intelligence.predictedDailyAverage') }}</dt>
              <dd class="font-medium">{{ selectedHorizonData?.daily_average ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">{{ t('intelligence.predictedStockOutDate') }}</dt>
              <dd class="font-medium">{{ selectedHorizonData?.stock_out_date ?? '—' }}</dd>
            </div>
            <div>
              <dt class="text-muted-foreground">{{ t('intelligence.recommendedPurchaseAi') }}</dt>
              <dd class="font-medium">{{ selectedHorizonData?.recommended_purchase_quantity ?? '—' }}</dd>
            </div>
          </dl>
        </div>
      </Card>
      <Teleport to="body">
        <div
          v-if="showAdjust"
          class="fixed inset-0 z-40 flex items-center justify-center px-4 py-6 sm:py-12"
          role="alertdialog"
          :aria-label="t('intelligence.adjustStock')"
          aria-modal="true"
        >
          <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showAdjust = false" />
          <div class="relative z-10 w-full max-w-xl mx-auto">
            <Card>
              <template #header>
                <span class="text-muted-foreground text-sm font-medium">{{ t('intelligence.adjustStock') }}</span>
              </template>
              <form class="space-y-4 p-6" @submit.prevent="submitAdjustment">
                <p class="text-muted-foreground text-sm">{{ t('intelligence.adjustHelp') }}</p>
                <div class="flex flex-wrap items-end gap-4">
                  <div class="space-y-2">
                    <Label>{{ t('intelligence.quantityChange') }}</Label>
                    <Input v-model.number="adjustQuantity" type="number" required />
                  </div>
                  <div class="space-y-2">
                    <Label>{{ t('intelligence.type') }}</Label>
                    <select
                      v-model="adjustType"
                      class="flex h-10 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    >
                      <option value="restock">{{ t('intelligence.restock') }}</option>
                      <option value="adjustment">{{ t('intelligence.adjustment') }}</option>
                      <option value="correction">{{ t('intelligence.correction') }}</option>
                    </select>
                  </div>
                </div>

                <div v-if="isPerishable && adjustQuantity > 0" class="space-y-3">
                  <div class="space-y-2">
                    <Label for="restock-expiration">{{ t('intelligence.expirationDate') }}</Label>
                    <Input id="restock-expiration" v-model="restockExpirationDate" type="date" required />
                  </div>
                  <div class="space-y-2">
                    <Label for="restock-cost-unit">{{ t('intelligence.costUnit') }}</Label>
                    <Input id="restock-cost-unit" v-model="restockCostUnit" type="number" step="0.01" min="0" placeholder="(optional)" />
                  </div>
                </div>
                <p v-if="adjustError" class="text-destructive text-sm">{{ adjustError }}</p>
                <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row">
                  <Button type="submit" class="w-full sm:w-auto" :disabled="adjustMutation.isPending.value">
                    {{ t('intelligence.apply') }}
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="showAdjust = false"
                  >
                    {{ t('intelligence.cancel') }}
                  </Button>
                </div>
              </form>
            </Card>
          </div>
        </div>
      </Teleport>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/api'

const { t } = useI18n()
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import { AlertTriangle } from 'lucide-vue-next'
import type { AiPredictionHorizon, ProductIntelligenceResponse } from '@/types'

const route = useRoute()
const queryClient = useQueryClient()
const id = computed(() => String(route.params.id))
const showAdjust = ref(false)
const adjustQuantity = ref(0)
const adjustType = ref<'restock' | 'adjustment' | 'correction'>('restock')
const adjustError = ref('')
const restockExpirationDate = ref<string>(new Date().toISOString().slice(0, 10))
const restockCostUnit = ref<string>('')
const selectedHorizon = ref<'7' | '30' | '90'>('30')

const intelligenceQuery = useQuery({
  queryKey: ['product-intelligence', id],
  queryFn: async (): Promise<ProductIntelligenceResponse> => {
    const { data } = await api.get<ProductIntelligenceResponse>(`/products/${id.value}/intelligence`)
    return data
  },
  enabled: computed(() => !!id.value),
})

const isPerishable = computed(() => intelligenceQuery.data?.value?.product?.perecedero === true)

const coverageDays = computed(() => {
  const data = intelligenceQuery.data?.value
  const stock = data?.product?.current_stock
  const avg = data?.intelligence?.average_daily_demand
  if (stock == null || avg == null || avg <= 0) {
    return null
  }
  return Math.round((stock / avg) * 10) / 10
})

const advancedHorizons = computed<Record<string, AiPredictionHorizon> | null>(() => {
  const data = intelligenceQuery.data?.value
  const raw = data?.ai_prediction_advanced
  if (!raw) return null
  const entries: [string, AiPredictionHorizon][] = Object.entries(raw).filter(
    ([, v]) => v !== null && typeof v === 'object',
  ) as [string, AiPredictionHorizon][]
  if (entries.length === 0) return null
  return Object.fromEntries(entries)
})

const horizonOptions = computed(() => {
  const horizons = advancedHorizons.value
  if (!horizons) return ['30']
  const keys = Object.keys(horizons)
  return ['7', '30', '90'].filter((k) => keys.includes(k))
})

const selectedHorizonData = computed<AiPredictionHorizon | null>(() => {
  const horizons = advancedHorizons.value
  if (!horizons) return null
  const key = horizonOptions.value.includes(selectedHorizon.value) ? selectedHorizon.value : horizonOptions.value[0]
  selectedHorizon.value = key as '7' | '30' | '90'
  return horizons[key] ?? null
})

const adjustMutation = useMutation({
  mutationFn: (payload: {
    quantity_change: number
    type: string
    expiration_date?: string
    cost_unit?: number
  }) =>
    api.post(`/products/${id.value}/stock-adjustment`, payload),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['product-intelligence', id] })
    queryClient.invalidateQueries({ queryKey: ['products'] })
    showAdjust.value = false
    adjustQuantity.value = 0
    adjustError.value = ''
    restockCostUnit.value = ''
    restockExpirationDate.value = new Date().toISOString().slice(0, 10)
  },
  onError: (err: { response?: { data?: { message?: string } } }) => {
    adjustError.value = err.response?.data?.message ?? t('intelligence.failedAdjust')
  },
})

function submitAdjustment() {
  adjustError.value = ''
  const payload: { quantity_change: number; type: string; expiration_date?: string; cost_unit?: number } = {
    quantity_change: adjustQuantity.value,
    type: adjustType.value,
  }

  if (isPerishable.value && adjustQuantity.value > 0) {
    const exp = restockExpirationDate.value
    if (!exp) {
      adjustError.value = 'Expiration date is required for perishable restock.'
      return
    }
    payload.expiration_date = exp

    const cost = restockCostUnit.value.trim()
    if (cost) {
      payload.cost_unit = Number(cost)
    }
  }

  adjustMutation.mutate(payload)
}
</script>
