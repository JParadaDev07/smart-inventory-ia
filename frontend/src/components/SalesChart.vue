<template>
  <div class="h-[280px] w-full min-w-0">
    <Bar v-if="chartData" :data="chartData" :options="chartOptions" />
    <p v-else class="flex h-full items-center justify-center text-muted-foreground text-sm">No sales data for the last 30 days.</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  BarController,
  Tooltip,
} from 'chart.js'
import { Bar } from 'vue-chartjs'
import type { SalesByDay } from '@/types'

ChartJS.register(CategoryScale, LinearScale, BarElement, BarController, Tooltip)

const props = defineProps<{
  salesByDay?: SalesByDay[] | null
}>()

const chartData = computed(() => {
  const rows = props.salesByDay ?? []
  if (!rows.length) return null
  const labels = rows.map((r) => {
    // `r.date` comes from backend as `YYYY-MM-DD`. JS parses this as UTC in many browsers,
    // which can shift the displayed day depending on timezone.
    // Build the date using local parts to keep labels consistent.
    const [y, m, d] = String(r.date).split('-').map((v) => Number(v))
    const dt = new Date(y, (m ?? 1) - 1, d ?? 1)
    return dt.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
  })
  const values = rows.map((r) => r.total)
  return {
    labels,
    datasets: [
      {
        label: 'Sales',
        data: values,
        backgroundColor: 'rgba(59, 130, 246, 0.6)',
        borderColor: 'rgb(59, 130, 246)',
        borderWidth: 1,
      },
    ],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    tooltip: {
      callbacks: {
        // Chart.js types expose `raw` as `unknown`.
        // Cast safely to keep TS happy.
        label: (ctx: any) => {
          const raw = ctx?.raw
          if (typeof raw !== 'number') return '—'
          return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(raw)
        },
      },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        // Tick callback types vary by Chart.js scale.
        callback: (value: any) => {
          if (typeof value === 'number') return '$' + value.toLocaleString()
          if (typeof value === 'string') return value
          return String(value ?? '')
        },
      },
    },
  },
}
</script>
