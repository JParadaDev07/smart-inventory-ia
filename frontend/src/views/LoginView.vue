<template>
  <div class="flex min-h-screen items-center justify-center bg-muted/30 px-4">
    <Card class="w-full max-w-md">
      <template #header>
        <h1 class="text-2xl font-semibold">{{ t('auth.signIn') }}</h1>
        <p class="text-muted-foreground text-sm">{{ t('auth.signInSubtitle') }}</p>
      </template>
      <form class="space-y-4" @submit.prevent="submit">
        <div class="space-y-2">
          <Label>{{ t('auth.email') }}</Label>
          <Input v-model="email" type="email" :placeholder="t('auth.emailPlaceholder')" required />
        </div>
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <Label>{{ t('auth.password') }}</Label>
            <router-link to="/forgot-password" class="text-muted-foreground text-xs hover:text-primary">
              {{ t('auth.forgotPassword') }}
            </router-link>
          </div>
          <div class="relative">
            <Input
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              required
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
        <p v-if="error" class="text-destructive text-sm">{{ error }}</p>
        <p v-if="successMessage" class="text-green-600 dark:text-green-400 text-sm">{{ successMessage }}</p>
        <Button type="submit" class="w-full" :disabled="loading">{{ t('auth.signInButton') }}</Button>
      </form>
      <template #footer>
        <p class="text-center text-muted-foreground text-sm">
          {{ t('auth.noAccount') }}
          <router-link to="/register" class="text-primary hover:underline">{{ t('auth.register') }}</router-link>
        </p>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import { useAuthStore } from '@/stores/auth'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'

const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const auth = useAuthStore()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const error = ref('')
const successMessage = ref('')
const loading = ref(false)

onMounted(() => {
  if (route.query.verified === '1') {
    successMessage.value = 'Your email has been verified. You can sign in now.'
  }
})

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const { data } = await api.post<{ token: string; user: unknown }>('/auth/login', {
      email: email.value,
      password: password.value,
    } as { email: string; password: string })
    auth.setAuth(data.token)
    auth.setUser(data.user as import('@/types').User)
    router.push('/app')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string } } }
    error.value = err.response?.data?.message ?? t('auth.loginFailed')
  } finally {
    loading.value = false
  }
}
</script>
