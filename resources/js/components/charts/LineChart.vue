<template>
  <div
    ref="container"
    class="tw-relative tw-h-full"
    :style="{ height: height + 'px' }"
  >
    <canvas ref="chartCanvas" class="tw-w-full tw-h-full"></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  LineController,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  LineController,
  Title,
  Tooltip,
  Legend,
  Filler
)

const props = defineProps({
  data: { type: Object, required: true },
  options: { type: Object, default: () => ({}) },
  height: { type: Number, default: 300 },
})

const container = ref(null)
const chartCanvas = ref(null)
let chartInstance = null
let ro = null

const defaultOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
      labels: { usePointStyle: true, padding: 20, font: { size: 12, family: 'Inter, sans-serif' } },
    },
    tooltip: {
      backgroundColor: 'rgba(0,0,0,.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: 'rgba(255,255,255,.1)',
      borderWidth: 1,
      cornerRadius: 8,
      displayColors: true,
      mode: 'index',
      intersect: false,
    },
  },
  scales: {
    x: { grid: { display: false }, ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: '#6B7280' } },
    y: {
      grid: { color: 'rgba(107,114,128,.1)', borderDash: [5, 5] },
      ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: '#6B7280' },
    },
  },
  elements: {
    line: { tension: 0.4, borderWidth: 3 },
    point: { radius: 4, hoverRadius: 6, borderWidth: 2, backgroundColor: '#fff' },
  },
  interaction: { intersect: false, mode: 'index' },
}

function createChart() {
  if (!chartCanvas.value) return
  if (chartInstance) chartInstance.destroy()

  const ctx = chartCanvas.value.getContext('2d')
  chartInstance = new ChartJS(ctx, {
    type: 'line',
    data: props.data,
    options: { ...defaultOptions, ...props.options },
  })
}

onMounted(() => {
  createChart()

  // Resize when the container’s box changes (tab show, window resize, etc.)
  if ('ResizeObserver' in window) {
    ro = new ResizeObserver(() => {
      if (chartInstance) chartInstance.resize()
    })
    if (container.value) ro.observe(container.value)
  }
})

onUnmounted(() => {
  if (chartInstance) chartInstance.destroy()
  if (ro) ro.disconnect()
})

watch(
  () => props.data,
  () => {
    if (!chartInstance) return createChart()
    chartInstance.data = props.data
    chartInstance.update('none')
  },
  { deep: true }
)

watch(
  () => props.options,
  () => {
    // options changes are simplest to apply by recreating
    createChart()
  },
  { deep: true }
)

watch(
  () => props.height,
  () => {
    // height prop changed -> container height changed -> resize chart
    if (chartInstance) chartInstance.resize()
  }
)
</script>

<style scoped>
/* canvas fills its parent; parent gets explicit height via :style */
canvas { display: block; }
</style>
