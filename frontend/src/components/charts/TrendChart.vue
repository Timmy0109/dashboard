<script setup lang="ts">
// TrendChart — 6 個月趨勢 bar chart，含 grid + 上方數值標籤
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  BarElement,
  CategoryScale,
  LinearScale,
} from 'chart.js'
import type { ChartData, ChartOptions, Plugin } from 'chart.js'
import { useTheme } from 'vuetify'
import EmptyState from '@/components/ui/EmptyState.vue'

ChartJS.register(Title, Tooltip, BarElement, CategoryScale, LinearScale)

interface TrendDatum {
  month: string
  count: number
}

const props = defineProps<{
  data: TrendDatum[]
}>()

const theme = useTheme()
const themeColors = computed(() => theme.global.current.value.colors)

const hasData = computed(() => props.data.length > 0)

const chartData = computed<ChartData<'bar'>>(() => ({
  labels: props.data.map((d) => d.month),
  datasets: [
    {
      label: '數量',
      data: props.data.map((d) => d.count),
      backgroundColor: themeColors.value.primary,
      borderRadius: 6,
      borderSkipped: false,
      maxBarThickness: 32,
    },
  ],
}))

// 在 bar 頂端繪製數值標籤
const valueLabelPlugin: Plugin<'bar'> = {
  id: 'valueLabelPlugin',
  afterDatasetsDraw(chart) {
    const { ctx } = chart
    const onSurface = (themeColors.value['on-surface'] as string) || '#000'
    chart.data.datasets.forEach((dataset, dsIndex) => {
      const meta = chart.getDatasetMeta(dsIndex)
      meta.data.forEach((bar, i) => {
        const val = dataset.data[i] as number
        if (val == null) return
        ctx.save()
        ctx.fillStyle = onSurface
        ctx.font = '600 11px system-ui, -apple-system, sans-serif'
        ctx.textAlign = 'center'
        ctx.textBaseline = 'bottom'
        ctx.fillText(String(val), bar.x, bar.y - 4)
        ctx.restore()
      })
    })
  },
}

const chartOptions = computed<ChartOptions<'bar'>>(() => {
  const onSurface = themeColors.value['on-surface'] || '#000'
  const surface = themeColors.value.surface || '#fff'
  const gridColor = `${onSurface}1F`
  const tickColor = `${onSurface}99`
  return {
    responsive: true,
    maintainAspectRatio: false,
    layout: { padding: { top: 20 } },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: surface,
        titleColor: onSurface,
        bodyColor: onSurface,
        borderColor: gridColor,
        borderWidth: 1,
        padding: 8,
        displayColors: false,
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: { color: tickColor, font: { size: 11 } },
      },
      y: {
        beginAtZero: true,
        grid: { color: gridColor },
        ticks: { color: tickColor, font: { size: 11 }, precision: 0 },
      },
    },
  }
})

const plugins = computed(() => [valueLabelPlugin])
</script>

<template>
  <EmptyState
    v-if="!hasData"
    icon="mdi-chart-timeline-variant"
    title="尚無資料"
    sub="目前沒有趨勢數據"
  />
  <div v-else style="height: 240px; position: relative">
    <Bar :data="chartData" :options="chartOptions" :plugins="plugins" />
  </div>
</template>
