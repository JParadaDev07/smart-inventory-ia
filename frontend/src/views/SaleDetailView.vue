<template>
  <div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-2">
        <router-link to="/app/sales" class="text-muted-foreground hover:text-foreground">{{ t('saleDetail.backToSales') }}</router-link>
        <!-- <h1 class="text-xl font-semibold sm:text-2xl">{{ t('saleDetail.title', { id: route.params.id }) }}</h1> -->
      </div>
    </div>
    <Card v-if="saleQuery.isError.value" class="p-8 text-center text-destructive">
      {{ errorMessage }}
    </Card>
    <Card v-else-if="sale">
      <template #header>
        <div class="flex flex-wrap items-start justify-between gap-2">
          <div>
            <p class="text-muted-foreground text-sm">{{ formatDate(sale.created_at) }}</p>
            <p class="mt-1 text-2xl font-bold">{{ formatCurrency(sale.total_amount) }}</p>
            <p v-if="sale.has_expired_items" class="mt-2 text-sm text-amber-600 dark:text-amber-400">
              Venta con producto vencido (lote con expiración &le; hoy): {{ sale.expired_quantity_total }} unidades
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            class="text-destructive hover:bg-destructive/10"
            :disabled="deleteMutation.isPending.value"
            @click="showCancelSaleDialog = true"
          >
            {{ t('saleDetail.cancelSale') }}
          </Button>
        </div>
      </template>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b">
              <th class="h-10 px-4 text-left font-medium">{{ t('saleDetail.product') }}</th>
              <th class="h-10 px-4 text-right font-medium">{{ t('saleDetail.qty') }}</th>
              <th class="h-10 px-4 text-right font-medium">{{ t('saleDetail.unitPrice') }}</th>
              <th class="h-10 px-4 text-right font-medium">{{ t('saleDetail.subtotal') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in sale.items" :key="item.id" class="border-b last:border-0">
              <td class="px-4 py-3 font-medium">{{ item.product?.name ?? '—' }}</td>
              <td class="px-4 py-3 text-right">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(item.unit_price) }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency((item.quantity * Number(item.unit_price))) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <Teleport to="body">
      <div
        v-if="showCancelSaleDialog"
        class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6 sm:py-12"
        role="alertdialog"
        :aria-label="t('saleDetail.cancelSale')"
        aria-modal="true"
      >
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeCancelSaleDialog" />
        <div class="relative z-10 w-full max-w-sm mx-auto">
          <Card content-class="p-6 space-y-4">
            <div class="flex items-start gap-3">
              <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive/10">
                <AlertTriangle class="h-5 w-5 text-destructive" />
              </div>
              <div class="flex-1 min-w-0">
                <h3 class="text-base font-semibold">{{ t('saleDetail.cancelSale') }}</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                  {{ t('saleDetail.cancelConfirm') }}
                </p>
              </div>
            </div>
            <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
              <Button variant="outline" class="w-full sm:w-auto" @click="closeCancelSaleDialog">
                {{ t('saleDetail.cancel') }}
              </Button>
              <Button
                variant="destructive"
                class="w-full sm:w-auto"
                :disabled="deleteMutation.isPending.value"
                @click="executeCancelSale"
              >
                {{ t('saleDetail.cancelSale') }}
              </Button>
            </div>
          </Card>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import { AlertTriangle } from 'lucide-vue-next'
import type { Sale } from '@/types'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const id = computed(() => String(route.params.id))

const saleQuery = useQuery({
  queryKey: ['sale', id],
  queryFn: async (): Promise<Sale> => {
    const { data } = await api.get<Sale>(`/sales/${id.value}`)
    return data
  },
  enabled: computed(() => !!id.value),
})

const deleteMutation = useMutation({
  mutationFn: () => api.delete(`/sales/${id.value}`),
  onSuccess: () => {
    showCancelSaleDialog.value = false
    queryClient.invalidateQueries({ queryKey: ['sales'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    router.push({ name: 'Sales' })
  },
})

const sale = computed(() => saleQuery.data.value ?? null)
const showCancelSaleDialog = ref(false)

function closeCancelSaleDialog() {
  showCancelSaleDialog.value = false
}

function executeCancelSale() {
  showCancelSaleDialog.value = false
  deleteMutation.mutate()
}

interface ApiError extends Error {
  response?: { data?: { message?: string } }
}
const errorMessage = computed(() => {
  const err = saleQuery.error.value as ApiError | null
  if (!err) return t('saleDetail.errorLoading')
  return err.response?.data?.message ?? err.message ?? t('saleDetail.errorLoading')
})

function formatDate(str: string | undefined): string {
  if (!str) return '—'
  return new Date(str).toLocaleString()
}

function formatCurrency(v: number | string | null | undefined): string {
  return v != null ? new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v)) : '—'
}

</script>
