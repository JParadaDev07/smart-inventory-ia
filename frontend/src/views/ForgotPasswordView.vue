<template>
  <div class="flex min-h-screen items-center justify-center bg-muted/30 px-4">
    <Card class="w-full max-w-md">
      <template #header>
        <h1 class="text-2xl font-semibold">{{ t('auth.forgotPasswordTitle') }}</h1>
        <p class="text-muted-foreground text-sm">{{ t('auth.forgotPasswordSubtitle') }}</p>
      </template>
      <form class="space-y-4" @submit.prevent="submit">
        <div class="space-y-2">
          <Label>{{ t('auth.email') }}</Label>
          <Input v-model="email" type="email" :placeholder="t('auth.emailPlaceholder')" required />
        </div>
        <p v-if="message" class="text-sm" :class="error ? 'text-destructive' : 'text-muted-foreground'">{{ message }}</p>
        <Button type="submit" class="w-full" :disabled="loading">{{ t('auth.sendResetLink') }}</Button>
      </form>
      <template #footer>
        <p class="text-center text-muted-foreground text-sm">
          <router-link to="/login" class="text-primary hover:underline">{{ t('auth.backToSignIn') }}</router-link>
        </p>
      </template>
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
const email = ref('')
const message = ref('')
const error = ref(false)
const loading = ref(false)

async function submit() {
  message.value = ''
  error.value = false
  loading.value = true
  try {
    const { data } = await api.post<{ message: string }>('/auth/forgot-password', { email: email.value })
    message.value = data?.message ?? t('auth.forgotPasswordSubtitle')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    message.value = err.response?.data?.message ?? 'Something went wrong.'
    error.value = true
  } finally {
    loading.value = false
  }
}
</script>
