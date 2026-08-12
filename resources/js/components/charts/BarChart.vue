<template>
  <div class="tw-relative" :style="containerStyle">
    <canvas ref="chartCanvas" @click="handleCanvasClick"></canvas>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  BarController,
  Title,
  Tooltip,
  Legend
} from 'chart.js';

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  BarController,
  Title,
  Tooltip,
  Legend
);

const props = defineProps({
  data: {
    type: Object,
    required: true
  },
  options: {
    type: Object,
    default: () => ({})
  },
  height: {
    type: Number,
    default: 300
  }
});

const emit = defineEmits(['select']);

const chartCanvas = ref(null);
let chartInstance = null;

const containerStyle = computed(() => ({
  height: `${props.height}px`,
}));

const defaultOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'top',
      labels: {
        usePointStyle: true,
        padding: 20,
        font: {
          size: 12,
          family: 'Inter, sans-serif'
        }
      }
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: 'white',
      bodyColor: 'white',
      borderColor: 'rgba(255, 255, 255, 0.1)',
      borderWidth: 1,
      cornerRadius: 8,
      displayColors: true,
      mode: 'index',
      intersect: false,
    }
  },
  scales: {
    x: {
      grid: {
        display: false
      },
      ticks: {
        font: {
          size: 11,
          family: 'Inter, sans-serif'
        },
        color: '#6B7280'
      }
    },
    y: {
      grid: {
        color: 'rgba(107, 114, 128, 0.1)',
        borderDash: [5, 5]
      },
      ticks: {
        font: {
          size: 11,
          family: 'Inter, sans-serif'
        },
        color: '#6B7280'
      }
    }
  },
  elements: {
    bar: {
      borderRadius: 6,
      borderSkipped: false,
    }
  },
  interaction: {
    intersect: false,
    mode: 'index'
  }
};

const createChart = () => {
  if (chartInstance) {
    chartInstance.destroy();
  }

  const ctx = chartCanvas.value.getContext('2d');
  chartInstance = new ChartJS(ctx, {
    type: 'bar',
    data: props.data,
    options: {
      ...defaultOptions,
      ...props.options
    }
  });
};

const handleCanvasClick = (event) => {
  if (!chartInstance) {
    return;
  }

  const elements = chartInstance.getElementsAtEventForMode(
    event,
    'nearest',
    { intersect: true },
    true
  );

  if (!elements.length) {
    return;
  }

  const [{ index, datasetIndex }] = elements;
  const label = chartInstance.data?.labels?.[index];
  const dataset = chartInstance.data?.datasets?.[datasetIndex];
  const value = dataset?.data?.[index];

  emit('select', {
    index,
    datasetIndex,
    label,
    value,
    datasetLabel: dataset?.label ?? '',
  });
};

onMounted(() => {
  createChart();
});

onUnmounted(() => {
  if (chartInstance) {
    chartInstance.destroy();
  }
});

watch(() => props.data, () => {
  if (chartInstance) {
    chartInstance.data = props.data;
    chartInstance.update('active');
  }
}, { deep: true });

watch(() => props.options, () => {
  createChart();
}, { deep: true });
</script>

<style scoped>
canvas {
  display: block;
  width: 100% !important;
  height: 100% !important;
}
</style>
