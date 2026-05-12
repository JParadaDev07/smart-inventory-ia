<template>
  <div class="h-[260px] w-full min-w-0">
    <Doughnut v-if="chartData" :data="chartData" :options="chartOptions" />
    <p v-else class="flex h-full items-center justify-center text-muted-foreground text-sm">
      No hay suficientes datos de inventario.
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
} from 'chart.js'
import { Doughnut } from 'vue-chartjs'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps<{
  totalProducts?: number | null
  lowStockProducts?: number | null
}>()

const chartData = computed(() => {
  const total = props.totalProducts ?? 0
  const low = props.lowStockProducts ?? 0
  if (total <= 0) return null

  const healthy = Math.max(total - low, 0)

  return {
    labels: ['Stock saludable', 'Stock bajo'],
    datasets: [
      {
        data: [healthy, low],
        backgroundColor: ['rgba(34, 197, 94, 0.6)', 'rgba(239, 68, 68, 0.7)'],
        borderColor: ['rgb(22, 163, 74)', 'rgb(220, 38, 38)'],
        borderWidth: 1,
      },
    ],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom' as const,
      labels: {
        usePointStyle: true,
      },
    },
    tooltip: {
      callbacks: {
        label: (ctx: { label?: string; raw: number }) => {
          const total = (props.totalProducts ?? 0) || 1
          const value = ctx.raw
          const pct = Math.round((value / total) * 100)
          return `${ctx.label}: ${value} (${pct}%)`
        },
      },
    },
  },
}
</script>

