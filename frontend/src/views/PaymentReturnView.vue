<template>
  <div class="flex min-h-[40vh] flex-col items-center justify-center gap-4 p-6">
    <p class="text-muted-foreground text-center">
      {{ message }}
    </p>
    <p v-if="pollCount > 0" class="text-muted-foreground text-center text-sm">
      {{ t('paymentReturn.checkingCount', { count: pollCount }) }}
    </p>
    <Button v-if="done && subscription?.is_active" @click="goToApp">
      {{ t('paymentReturn.goHome') }}
    </Button>
    <Button v-else-if="done && !subscription?.is_active" variant="outline" @click="goToSubscription">
      {{ t('paymentReturn.goSubscription') }}
    </Button>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import Button from '@/components/ui/Button.vue'
import type { SubscriptionData } from '@/types'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const message = ref(t('paymentReturn.checking'))
const done = ref(false)
const subscription = ref<SubscriptionData | null>(null)
const pollCount = ref(0)

const POLL_INTERVAL_MS = 3000
const MAX_POLLS = 40

let pollTimer: ReturnType<typeof setInterval> | null = null

async function fetchSubscription(): Promise<SubscriptionData> {
  const { data } = await api.get<SubscriptionData>('/subscription')
  return data
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
  done.value = true
}

onMounted(async () => {
  await auth.fetchUser()
  subscription.value = await fetchSubscription()

  if (subscription.value?.is_active) {
    message.value = t('paymentReturn.paymentConfirmed')
    done.value = true
    return
  }

  pollTimer = setInterval(async () => {
    pollCount.value += 1
    try {
      subscription.value = await fetchSubscription()
      await auth.fetchUser()
      if (subscription.value?.is_active) {
        message.value = t('paymentReturn.paymentConfirmed')
        stopPolling()
        return
      }
    } catch {
      // keep polling
    }
    if (pollCount.value >= MAX_POLLS) {
      message.value = t('paymentReturn.takingTooLong')
      stopPolling()
    }
  }, POLL_INTERVAL_MS)
})

function goToApp() {
  stopPolling()
  router.push({ name: 'Dashboard' })
}

function goToSubscription() {
  stopPolling()
  router.push({ name: 'Subscription' })
}

onUnmounted(() => {
  stopPolling()
})
</script>
