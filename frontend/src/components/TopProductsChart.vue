<template>
  <div class="h-[260px] w-full min-w-0">
    <Bar v-if="chartData" :data="chartData" :options="chartOptions" />
    <p v-else class="flex h-full items-center justify-center text-muted-foreground text-sm">
      No hay productos con ventas en los últimos 30 días.
    </p>
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
import type { TopProduct } from '@/types'

ChartJS.register(CategoryScale, LinearScale, BarElement, BarController, Tooltip)

const props = defineProps<{
  topProducts?: TopProduct[] | null
}>()

const chartData = computed(() => {
  const rows = props.topProducts ?? []
  if (!rows.length) return null
  const labels = rows.map((r) => r.name)
  const values = rows.map((r) => r.total_qty)

  return {
    labels,
    datasets: [
      {
        label: 'Unidades vendidas (últimos 30 días)',
        data: values,
        backgroundColor: 'rgba(59, 130, 246, 0.7)',
        borderColor: 'rgb(37, 99, 235)',
        borderWidth: 1,
      },
    ],
  }
})

const chartOptions = {
  indexAxis: 'y' as const,
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    tooltip: {
      callbacks: {
        label: (ctx: { raw: number }) => `${ctx.raw} unidades`,
      },
    },
  },
  scales: {
    x: {
      beginAtZero: true,
      ticks: {
        precision: 0,
      },
    },
  },
}
</script>

