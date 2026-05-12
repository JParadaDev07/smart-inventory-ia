<template>
  <div class="mx-auto max-w-4xl space-y-6 px-1 sm:px-0">
    <header class="space-y-1">
      <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
        {{ t('tickets.title') }}
      </h1>
      <p class="text-muted-foreground text-sm sm:text-base">
        {{ t('nav.tickets') }}
      </p>
    </header>

    <!-- Nuevo ticket -->
    <Card content-class="p-6 space-y-4">
      <div class="flex items-center justify-between gap-2">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
          {{ t('tickets.newTicket') }}
        </h2>
      </div>
      <form class="space-y-4" @submit.prevent="createTicket">
        <div class="space-y-2">
          <Label>{{ t('tickets.subject') }}</Label>
          <Input v-model="form.subject" required />
        </div>
        <div class="space-y-2">
          <Label>{{ t('tickets.description') }}</Label>
          <textarea
            v-model="form.description"
            required
            rows="4"
            class="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          />
        </div>
        <div class="space-y-2">
          <Label>{{ t('tickets.priority') }}</Label>
          <select
            v-model="form.priority"
            class="flex h-9 w-40 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <option value="low">{{ t('tickets.priorityLow') }}</option>
            <option value="medium">{{ t('tickets.priorityMedium') }}</option>
            <option value="high">{{ t('tickets.priorityHigh') }}</option>
          </select>
        </div>
        <p v-if="error" class="text-destructive text-sm">
          {{ error }}
        </p>
        <div class="flex justify-end gap-2">
          <Button
            type="button"
            variant="outline"
            :disabled="createTicketMutation.isPending.value"
            @click="resetForm"
          >
            {{ t('tickets.cancel') }}
          </Button>
          <Button type="submit" data-tour="tickets-create" :disabled="createTicketMutation.isPending.value">
            {{ t('tickets.create') }}
          </Button>
        </div>
      </form>
    </Card>

    <!-- Listado de tickets -->
    <Card>
      <div v-if="ticketsQuery.isLoading.value && !ticketsData" class="p-6">
        <div class="animate-pulse space-y-3">
          <div v-for="i in 3" :key="i" class="h-10 rounded bg-muted" />
        </div>
      </div>
      <template v-else>
        <div v-if="ticketsError" class="p-6 text-destructive text-sm">
          {{ ticketsError }}
        </div>
        <div v-else-if="!ticketsList.length" class="p-6 text-center text-muted-foreground text-sm">
          {{ t('tickets.noTickets') }}
        </div>
        <div v-else class="overflow-x-auto -mx-4 sm:mx-0 sm:rounded-b-lg">
          <table class="w-full min-w-[320px] text-sm">
            <thead>
              <tr class="border-b bg-muted/30">
                <th class="h-11 px-4 py-3 text-left font-medium">
                  {{ t('tickets.subject') }}
                </th>
                <th class="h-11 px-4 py-3 text-left font-medium">
                  {{ t('tickets.priority') }}
                </th>
                <th class="h-11 px-4 py-3 text-left font-medium">
                  {{ t('tickets.createdAt') }}
                </th>
                <th class="h-11 px-4 py-3 text-left font-medium">
                  {{ t('tickets.lastUpdate') }}
                </th>
                <th class="h-11 px-4 py-3 text-left font-medium">
                  {{ t('alerts.status') }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="tkt in ticketsList"
                :key="tkt.id"
                class="border-b last:border-0"
              >
                <td class="px-4 py-3 font-medium">
                  {{ tkt.subject }}
                </td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="priorityClass(tkt.priority)"
                  >
                    {{ priorityLabel(tkt.priority) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                  {{ formatDate(tkt.created_at) }}
                </td>
                <td class="px-4 py-3 text-muted-foreground">
                  {{ formatDate(tkt.updated_at) }}
                </td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="statusClass(tkt.status)"
                  >
                    {{ statusLabel(tkt.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import api from '@/api'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Label from '@/components/ui/Label.vue'
import type { Ticket, PaginatedResponse } from '@/types'

const { t } = useI18n()
const queryClient = useQueryClient()

const form = reactive({
  subject: '',
  description: '',
  priority: 'medium',
})

const error = ref('')
const ticketsPage = ref(1)

const ticketsQuery = useQuery({
  queryKey: computed(() => ['tickets', ticketsPage.value]),
  queryFn: async (): Promise<PaginatedResponse<Ticket>> => {
    const { data } = await api.get<PaginatedResponse<Ticket>>('/tickets', {
      params: { page: ticketsPage.value },
    })
    return data as PaginatedResponse<Ticket>
  },
})

const ticketsData = computed(() => ticketsQuery.data.value)

const ticketsList = computed<Ticket[]>(() => {
  const res = ticketsData.value
  if (!res) return []
  const raw = Array.isArray(res) ? res : res.data
  return Array.isArray(raw) ? raw : []
})

interface ApiError extends Error {
  response?: { data?: { message?: string } }
}

const ticketsError = computed(() => {
  const err = ticketsQuery.error.value as ApiError | null
  if (!err) return ''
  return err.response?.data?.message ?? err.message ?? t('tickets.errorLoading')
})

const createTicketMutation = useMutation({
  mutationFn: async () => {
    const payload = {
      subject: form.subject,
      description: form.description,
      priority: form.priority,
    }
    const { data } = await api.post<{ ticket: Ticket }>('/tickets', payload)
    return data.ticket
  },
  onSuccess: () => {
    resetForm()
    queryClient.invalidateQueries({ queryKey: ['tickets'] })
  },
  onError: (err: { response?: { data?: { message?: string } } }) => {
    error.value = err.response?.data?.message ?? t('tickets.failedCreate')
  },
})

function resetForm() {
  form.subject = ''
  form.description = ''
  form.priority = 'medium'
  error.value = ''
}

async function createTicket() {
  error.value = ''
  await createTicketMutation.mutateAsync()
}

function formatDate(str: string | undefined): string {
  if (!str) return '—'
  return new Date(str).toLocaleString()
}

function priorityLabel(priority: string): string {
  if (priority === 'low') return t('tickets.priorityLow')
  if (priority === 'high') return t('tickets.priorityHigh')
  return t('tickets.priorityMedium')
}

function priorityClass(priority: string): string {
  if (priority === 'high') return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
  if (priority === 'low') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
  return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
}

function statusLabel(status: string): string {
  if (status === 'resolved') return t('tickets.statusResolved')
  if (status === 'in_progress') return t('tickets.statusInProgress')
  return t('tickets.statusOpen')
}

function statusClass(status: string): string {
  if (status === 'resolved') return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
  if (status === 'in_progress') return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
  return 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300'
}
</script>

