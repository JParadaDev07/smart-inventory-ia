<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ t('wompiCheckout.title') }}</h1>
    <Card>
      <form @submit.prevent="goToCheckout" class="space-y-4">
        <div class="space-y-2">
          <Label for="amount">{{ t('wompiCheckout.amountLabel') }}</Label>
          <Input
            id="amount"
            v-model.number="amount"
            type="number"
            min="1"
            step="1"
            required
            :placeholder="t('wompiCheckout.amountPlaceholder')"
          />
        </div>
        <p v-if="error" class="text-destructive text-sm">{{ error }}</p>
        <Button type="submit" :disabled="loading">
          {{ loading ? t('wompiCheckout.redirecting') : t('wompiCheckout.submit') }}
        </Button>
      </form>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'

const { t } = useI18n()
const amount = ref<number>(79900)
const loading = ref(false)
const error = ref('')

async function goToCheckout() {
  const value = Number(amount.value)
  if (!Number.isInteger(value) || value < 1) {
    error.value = t('wompiCheckout.errorAmount')
    return
  }
  error.value = ''
  loading.value = true
  try {
    const { data } = await api.post<{ checkout_url: string }>('/payments/wompi/checkout', { amount: value })
    if (data.checkout_url) {
      window.location.href = data.checkout_url
    } else {
      error.value = t('wompiCheckout.errorNoUrl')
      loading.value = false
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string }; status?: number } }
    error.value = err.response?.data?.message ?? t('wompiCheckout.errorCreate')
    loading.value = false
  }
}
</script>
