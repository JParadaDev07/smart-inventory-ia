<template>
  <div class="mx-auto max-w-4xl space-y-8 px-1 sm:px-0">
    <header>
      <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ t('subscription.title') }}</h1>
      <p class="text-muted-foreground mt-1 text-sm sm:text-base">
        {{ t('subscription.plansSubtitle') }}
      </p>
    </header>

    <!-- Redirected here because subscription expired (403) -->
    <div
      v-if="isRedirectedExpired"
      class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
    >
      <p class="text-sm">{{ t('subscription.redirectedExpired') }}</p>
    </div>

    <!-- Expired banner -->
    <div
      v-if="subscriptionData?.status === 'expired'"
      class="rounded-xl border border-red-200 bg-red-50 p-5 text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200"
    >
      <p class="font-semibold">{{ t('subscription.expiredTitle') }}</p>
      <p class="mt-1 text-sm opacity-90">{{ t('subscription.expiredMessage') }}</p>
      <Button class="mt-4" :disabled="createPaymentMutation.isPending.value" @click="renewPlan">
        {{ t('subscription.renewPlan') }}
      </Button>
    </div>

    <!-- Current plan -->
    <Card v-if="subscriptionData" content-class="p-5">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-muted-foreground text-xs font-medium uppercase tracking-wider">
            {{ t('subscription.currentPlan') }}
          </p>
          <p class="mt-1 text-xl font-semibold">
            {{ subscriptionData.plan === 'pro' ? t('subscription.proName') : t('subscription.basicName') }}
          </p>
          <p class="text-muted-foreground mt-1 flex items-center gap-2 text-sm">
            <span
              class="inline-flex h-2 w-2 rounded-full"
              :class="subscriptionData.is_active ? 'bg-green-500' : 'bg-amber-500'"
            />
            {{ statusLabel(subscriptionData.status) }}
            <template v-if="subscriptionData.trial_days_remaining != null">
              · {{ t('subscription.trialDaysLeft') }}: {{ subscriptionData.trial_days_remaining }}
            </template>
            <template v-else-if="subscriptionData.days_remaining != null && subscriptionData.is_active">
              · {{ t('subscription.daysLeftInPeriod') }}: {{ subscriptionData.days_remaining }}
            </template>
          </p>
        </div>
        <Button
          v-if="
            subscriptionData.status === 'expired' ||
            (subscriptionData.is_active && subscriptionData.days_remaining != null && subscriptionData.days_remaining <= 7)
          "
          variant="outline"
          :disabled="createPaymentMutation.isPending.value"
          @click="renewPlan"
        >
          {{ t('subscription.renewPlan') }}
        </Button>
      </div>
    </Card>

    <!-- Loading subscription -->
    <Card v-if="subscriptionQuery.isLoading.value && !subscriptionData" content-class="p-8">
      <div class="animate-pulse space-y-3">
        <div class="h-6 w-40 rounded bg-muted" />
        <div class="h-4 max-w-xs rounded bg-muted" />
      </div>
    </Card>
    <Card v-else-if="subscriptionQuery.isError.value && !subscriptionData" content-class="p-6">
      <p class="text-destructive text-sm">{{ t('subscription.error') }}</p>
    </Card>

    <!-- Plans: Basic, Pro y Enterprise -->
    <section class="space-y-4">
      <h2 class="text-lg font-semibold" data-tour="subscription-plans-title">{{ t('subscription.plansTitle') }}</h2>
      <div class="grid gap-4 sm:grid-cols-1 md:grid-cols-3">
        <!-- Basic -->
        <Card
          content-class="relative flex h-full flex-col p-6"
          :class="
            subscriptionData?.plan === 'basic' && subscriptionData?.is_active
              ? 'ring-2 ring-primary/30'
              : 'opacity-95'
          "
        >
          <div
            v-if="subscriptionData?.plan === 'basic' && subscriptionData?.is_active"
            class="bg-muted text-muted-foreground absolute right-3 top-3 rounded-full px-2 py-0.5 text-xs font-medium"
          >
            {{ t('subscription.currentPlan') }}
          </div>
          <p class="text-lg font-semibold">{{ plansData?.basic?.name ?? t('subscription.basicName') }}</p>
          <p class="mt-2 text-2xl font-bold tabular-nums">
            ${{ formatCop(plansData?.basic?.price ?? 0) }}
            <span class="text-muted-foreground text-sm font-normal">{{ t('subscription.perMonth') }}</span>
          </p>
          <p class="text-muted-foreground mt-3 flex-1 text-sm">{{ t('subscription.basicDesc') }}</p>
          <p v-if="subscriptionData?.plan !== 'basic'" class="text-muted-foreground mt-4 text-sm">
            {{ t('subscription.upgradeToPro') }}
          </p>
        </Card>

        <!-- Pro: un solo botón = abre el widget de pago al instante -->
        <Card
          content-class="relative flex h-full flex-col border-primary/40 bg-primary/5 p-6 dark:bg-primary/10"
        >
          <div
            v-if="subscriptionData?.plan === 'pro' && subscriptionData?.is_active"
            class="bg-primary/20 text-primary absolute right-3 top-3 rounded-full px-2 py-0.5 text-xs font-medium"
          >
            {{ t('subscription.currentPlan') }}
          </div>
          <p class="text-lg font-semibold">{{ plansData?.pro?.name ?? t('subscription.proName') }}</p>
          <p class="mt-2 text-2xl font-bold tabular-nums">
            ${{ formatCop(plansData?.pro?.price ?? 0) }}
            <span class="text-muted-foreground text-sm font-normal">{{ t('subscription.perMonth') }}</span>
          </p>
          <p class="text-muted-foreground mt-3 flex-1 text-sm">{{ t('subscription.proDesc') }}</p>
          <template v-if="!subscriptionData?.is_pro">
            <Button
              class="mt-4 w-full"
              :disabled="createPaymentMutation.isPending.value"
              @click="openProCheckout"
            >
              <span v-if="createPaymentMutation.isPending.value">{{ t('subscription.preparing') }}</span>
              <span v-else>{{ t('subscription.subscribePro') }}</span>
            </Button>
          </template>
          <template v-else-if="subscriptionData?.status === 'expired' || (subscriptionData.days_remaining != null && subscriptionData.days_remaining <= 7)">
            <Button class="mt-4 w-full" :disabled="createPaymentMutation.isPending.value" @click="renewPlan">
              {{ t('subscription.renewPlan') }}
            </Button>
          </template>
          <template v-else-if="subscriptionData?.is_pro && subscriptionData?.is_active">
            <Button
              class="mt-4 w-full"
              variant="outline"
              :disabled="updateSubscriptionMutation.isPending.value"
              @click="downgradeToBasic"
            >
              {{ t('subscription.downgradeToBasic') }}
            </Button>
          </template>
        </Card>
        <!-- Enterprise: contacto comercial -->
        <Card
          content-class="relative flex h-full flex-col p-6"
        >
          <div
            v-if="subscriptionData?.plan === 'enterprise' && subscriptionData?.is_active"
            class="bg-primary/20 text-primary absolute right-3 top-3 rounded-full px-2 py-0.5 text-xs font-medium"
          >
            {{ t('subscription.currentPlan') }}
          </div>
          <p class="text-lg font-semibold">
            {{ plansData?.enterprise?.name ?? t('subscription.enterpriseName') }}
          </p>
          <p class="mt-2 text-2xl font-bold tabular-nums">
            ${{ formatCop(plansData?.enterprise?.price ?? 0) }}
            <span class="text-muted-foreground text-sm font-normal">{{ t('subscription.perMonth') }}</span>
          </p>
          <p class="text-muted-foreground mt-3 flex-1 text-sm">
            {{ t('subscription.enterpriseDesc') }}
          </p>
          <template v-if="subscriptionData?.plan === 'enterprise' && subscriptionData?.is_active">
            <Button class="mt-4 w-full" variant="outline" disabled>
              Enterprise activo
            </Button>
          </template>

          <template
            v-else-if="
              subscriptionData?.status === 'expired' ||
              (subscriptionData?.days_remaining != null && subscriptionData?.days_remaining <= 7)
            "
          >
            <Button
              class="mt-4 w-full"
              :disabled="createPaymentMutation.isPending.value"
              @click="renewEnterprisePlan"
            >
              {{
                createPaymentMutation.isPending.value
                  ? t('subscription.preparing')
                  : t('subscription.renewPlan')
              }}
            </Button>
          </template>

          <template v-else>
            <Button
              class="mt-4 w-full"
              :disabled="createPaymentMutation.isPending.value"
              @click="openEnterpriseCheckout"
            >
              <span v-if="createPaymentMutation.isPending.value">{{ t('subscription.preparing') }}</span>
              <span v-else>{{ t('subscription.subscribeEnterprise') }}</span>
            </Button>
          </template>
        </Card>
      </div>
    </section>

    <!-- Plans loading -->
    <Card v-if="plansQuery.isLoading && !plansData" content-class="p-8">
      <div class="animate-pulse grid gap-4 sm:grid-cols-2">
        <div class="h-40 rounded-lg bg-muted/50" />
        <div class="h-40 rounded-lg bg-muted/50" />
      </div>
    </Card>
    <Card v-else-if="plansQuery.isError && !plansData" content-class="p-6">
      <p class="text-destructive text-sm">{{ t('subscription.plansError') }}</p>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery, useMutation } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import type { SubscriptionData } from '@/types'

const { t } = useI18n()
const route = useRoute()

const isRedirectedExpired = computed(() => route.query.expired === '1')

interface PlansResponse {
  currency: string
  basic: { name: string; price: number }
  pro: { name: string; price: number }
  enterprise: { name: string; price: number }
}

interface WidgetParams {
  public_key: string
  amount_in_cents: number
  reference: string
  signature_integrity: string
  currency: string
  redirect_url: string | null
}

declare global {
  interface Window {
    WidgetCheckout?: new (config: {
      currency: string
      amountInCents: number
      reference: string
      publicKey: string
      signature: { integrity: string }
      redirectUrl?: string
    }) => { open: (callback: (result: { transaction?: { id: string } }) => void) => void }
  }
}

const subscriptionQuery = useQuery({
  queryKey: ['subscription'],
  queryFn: async (): Promise<SubscriptionData> => {
    const { data } = await api.get<SubscriptionData>('/subscription')
    return data
  },
})

const plansQuery = useQuery({
  queryKey: ['plans'],
  queryFn: async (): Promise<PlansResponse> => {
    const { data } = await api.get<PlansResponse>('/plans')
    return data
  },
})

const subscriptionData = computed<SubscriptionData | undefined>(() => subscriptionQuery.data.value)
const plansData = computed(() => plansQuery.data.value)

function formatCop(value: number): string {
  return new Intl.NumberFormat('es-CO').format(value)
}

function statusLabel(status: string): string {
  const map: Record<string, string> = {
    trial: t('subscription.statusTrial'),
    active: t('subscription.statusActive'),
    expired: t('subscription.statusExpired'),
    pending_payment: t('subscription.statusPendingPayment'),
  }
  return map[status] ?? status
}

function loadWompiScript(): Promise<void> {
  if (document.querySelector('script[src*="checkout.wompi.co/widget"]')) {
    return Promise.resolve()
  }
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = 'https://checkout.wompi.co/widget.js'
    script.async = true
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('No se pudo cargar el widget de pago.'))
    document.head.appendChild(script)
  })
}

function openWidgetWithParams(wp: WidgetParams) {
  const checkout = new window.WidgetCheckout!({
    currency: wp.currency,
    amountInCents: wp.amount_in_cents,
    reference: wp.reference,
    publicKey: wp.public_key,
    signature: { integrity: wp.signature_integrity },
    ...(wp.redirect_url ? { redirectUrl: wp.redirect_url } : {}),
  })
  checkout.open((result) => {
    if (result?.transaction?.id) {
      subscriptionQuery.refetch()
      if (wp.redirect_url) window.location.href = wp.redirect_url
    }
  })
}

const updateSubscriptionMutation = useMutation({
  mutationFn: async (plan: 'basic' | 'pro') => {
    const { data } = await api.put<SubscriptionData>('/subscription', { plan })
    return data
  },
  onSuccess: () => {
    subscriptionQuery.refetch()
  },
})

const createPaymentMutation = useMutation({
  mutationFn: async (plan: 'pro' | 'enterprise') => {
    const { data } = await api.post<{
      checkout_url: string
      reference: string
      widget_params: WidgetParams | null
    }>('/billing/create-payment', { plan })
    return data
  },
  onSuccess: async (data) => {
    if (!data) return
    const wp = data.widget_params
    if (wp?.public_key && wp?.reference && wp?.signature_integrity) {
      await loadWompiScript()
      if (typeof window.WidgetCheckout === 'function') {
        openWidgetWithParams(wp)
        return
      }
    }
    if (data.checkout_url) window.location.href = data.checkout_url
  },
})

/** Un solo clic: crea el pago y abre el widget de Wompi directamente. */
function openCheckout(plan: 'pro' | 'enterprise') {
  createPaymentMutation.mutate(plan)
}

function openProCheckout() {
  openCheckout('pro')
}

function renewPlan() {
  openProCheckout()
}

function openEnterpriseCheckout() {
  openCheckout('enterprise')
}

function renewEnterprisePlan() {
  openEnterpriseCheckout()
}

function downgradeToBasic() {
  updateSubscriptionMutation.mutate('basic')
}
</script>
