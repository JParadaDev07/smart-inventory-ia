<template>
  <div class="mx-auto max-w-md space-y-6 px-4 py-8">
    <Card content-class="p-6">
      <h1 class="text-xl font-semibold">{{ t('auth.verifyEmailTitle') }}</h1>
      <p class="text-muted-foreground mt-2 text-sm">{{ t('auth.verifyEmailMessage') }}</p>
      <p v-if="sent" class="mt-4 text-sm text-green-600 dark:text-green-400">{{ t('auth.verificationSent') }}</p>
      <Button class="mt-6" :disabled="loading" @click="resend">
        {{ t('auth.resendVerification') }}
      </Button>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'

const { t } = useI18n()
const loading = ref(false)
const sent = ref(false)

async function resend() {
  loading.value = true
  sent.value = false
  try {
    await api.post('/auth/email/verification-notification')
    sent.value = true
  } catch {
    // error shown by interceptor or ignore
  } finally {
    loading.value = false
  }
}
</script>
