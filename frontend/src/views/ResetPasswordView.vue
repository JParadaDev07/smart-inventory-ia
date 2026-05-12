<template>
  <div class="flex min-h-screen items-center justify-center bg-muted/30 px-4">
    <Card class="w-full max-w-md">
      <template #header>
        <h1 class="text-2xl font-semibold">{{ t('auth.resetPasswordTitle') }}</h1>
        <p class="text-muted-foreground text-sm">{{ t('auth.resetPasswordSubtitle') }}</p>
      </template>
      <form v-if="token && email" class="space-y-4" @submit.prevent="submit">
        <div class="space-y-2">
          <Label>{{ t('auth.email') }}</Label>
          <Input :model-value="email" type="email" disabled class="bg-muted" />
        </div>
        <div class="space-y-2">
          <Label>{{ t('auth.newPassword') }}</Label>
          <div class="relative">
            <Input
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
              minlength="8"
              class="pr-16"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground hover:text-primary"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? 'Ocultar' : 'Mostrar' }}
            </button>
          </div>
        </div>
        <div class="space-y-2">
          <Label>{{ t('auth.confirmPassword') }}</Label>
          <div class="relative">
            <Input
              v-model="passwordConfirmation"
              :type="showPasswordConfirm ? 'text' : 'password'"
              required
              minlength="8"
              class="pr-16"
            />
            <button
              type="button"
              class="absolute inset-y-0 right-2 flex items-center text-xs text-muted-foreground hover:text-primary"
              @click="showPasswordConfirm = !showPasswordConfirm"
            >
              {{ showPasswordConfirm ? 'Ocultar' : 'Mostrar' }}
            </button>
          </div>
        </div>
        <p v-if="error" class="text-destructive text-sm">{{ error }}</p>
        <p v-if="success" class="text-sm text-green-600 dark:text-green-400">{{ t('auth.resetSuccess') }}</p>
        <Button type="submit" class="w-full" :disabled="loading || success">{{ t('auth.resetButton') }}</Button>
      </form>
      <div v-else class="space-y-4">
        <p class="text-destructive text-sm">{{ t('auth.resetFailed') }}</p>
        <router-link to="/forgot-password">
          <Button variant="outline" class="w-full">{{ t('auth.forgotPasswordTitle') }}</Button>
        </router-link>
      </div>
      <template #footer>
        <p class="text-center text-muted-foreground text-sm">
          <router-link to="/login" class="text-primary hover:underline">{{ t('auth.backToSignIn') }}</router-link>
        </p>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const token = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const error = ref('')
const success = ref(false)
const loading = ref(false)

onMounted(() => {
  token.value = (route.query.token as string) ?? ''
  email.value = (route.query.email as string) ?? ''
})

async function submit() {
  if (password.value !== passwordConfirmation.value) {
    error.value = 'Passwords do not match.'
    return
  }
  error.value = ''
  loading.value = true
  try {
    await api.post('/auth/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    success.value = true
    setTimeout(() => router.push('/login'), 2000)
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const msg = err.response?.data?.message
    const errors = err.response?.data?.errors
    error.value = msg ?? (errors?.email?.[0]) ?? t('auth.resetFailed')
  } finally {
    loading.value = false
  }
}
</script>
