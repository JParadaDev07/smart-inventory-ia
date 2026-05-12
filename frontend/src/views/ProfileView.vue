<template>
  <div class="mx-auto max-w-3xl space-y-6 px-1 sm:px-0">
    <header>
      <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ t('profile.title') }}</h1>
      <p class="text-muted-foreground mt-1 text-sm sm:text-base">
        {{ t('profile.subtitle') }}
      </p>
    </header>

    <Card content-class="p-6 space-y-6">
      <div v-if="!user">
        <div class="animate-pulse space-y-3">
          <div class="h-5 w-40 rounded bg-muted" />
          <div class="h-10 rounded bg-muted" />
          <div class="h-5 w-32 rounded bg-muted mt-4" />
          <div class="h-10 rounded bg-muted" />
        </div>
      </div>
      <form v-else class="space-y-6" @submit.prevent="save">
        <section class="space-y-4">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            {{ t('profile.sectionUser') }}
          </h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label>{{ t('auth.yourName') }}</Label>
              <Input v-model="form.name" required />
            </div>
            <div class="space-y-2">
              <Label>{{ t('auth.email') }}</Label>
              <Input v-model="form.email" type="email" disabled class="bg-muted/70" />
              <p class="text-muted-foreground mt-1 text-xs">
                {{ t('profile.emailHelp') }}
              </p>
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
            {{ t('profile.sectionBusiness') }}
          </h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label>{{ t('auth.businessName') }}</Label>
              <Input v-model="form.business_name" />
            </div>
            <div class="space-y-2">
              <Label>{{ t('auth.phoneOptional') }}</Label>
              <Input v-model="form.phone" />
            </div>
          </div>
          <div class="space-y-2">
            <Label>{{ t('auth.addressOptional') }}</Label>
            <Input v-model="form.address" />
          </div>
        </section>

        <p v-if="error" class="text-destructive text-sm">
          {{ error }}
        </p>
        <p v-if="success" class="text-emerald-600 dark:text-emerald-400 text-sm">
          {{ success }}
        </p>

        <div class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
          <Button
            type="button"
            variant="outline"
            class="w-full sm:w-auto"
            :disabled="saving"
            @click="resetForm"
          >
            {{ t('profile.cancel') }}
          </Button>
          <Button type="submit" class="w-full sm:w-auto" data-tour="profile-save" :disabled="saving">
            <span v-if="saving">{{ t('subscription.loading') }}</span>
            <span v-else>{{ t('profile.save') }}</span>
          </Button>
        </div>
      </form>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import type { User } from '@/types'

const { t } = useI18n()
const auth = useAuthStore()

const user = computed(() => auth.user as User | null)

const form = reactive({
  name: '',
  email: '',
  business_name: '',
  address: '',
  phone: '',
})

const saving = ref(false)
const error = ref('')
const success = ref('')

function fillFormFromUser(u: User | null) {
  if (!u) return
  form.name = u.name ?? ''
  form.email = u.email ?? ''
  form.business_name = u.business?.name ?? ''
  form.address = u.business?.address ?? ''
  form.phone = u.business?.phone ?? ''
}

function resetForm() {
  error.value = ''
  success.value = ''
  fillFormFromUser(user.value)
}

watch(
  user,
  (u) => {
    fillFormFromUser(u)
  },
  { immediate: true }
)

onMounted(() => {
  if (!auth.user && auth.token) {
    auth.fetchUser()
  }
})

async function save() {
  if (!user.value) return
  error.value = ''
  success.value = ''
  saving.value = true
  try {
    const payload = {
      name: form.name,
      business_name: form.business_name || null,
      business_address: form.address || null,
      business_phone: form.phone || null,
    }
    const { data } = await api.put<{ user: User }>('/auth/user', payload)
    auth.setUser(data.user)
    success.value = t('profile.updated')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    const msg = err.response?.data?.errors
      ? Object.values(err.response.data.errors).flat().join(' ')
      : err.response?.data?.message ?? t('profile.error')
    error.value = msg
  } finally {
    saving.value = false
  }
}
</script>

