<template>
  <div :class="[$attrs.class]" :style="$attrs.style" class="qds-card tw-overflow-hidden">
    <div
      v-if="searchable || $slots.toolbar"
      class="tw-flex tw-flex-wrap tw-items-center tw-gap-3 tw-border-b tw-border-slate-200 tw-bg-slate-50/80 tw-px-4 tw-py-3"
    >
      <div v-if="searchable" class="tw-w-full sm:tw-max-w-xs">
        <v-text-field
          :model-value="localSearch"
          :placeholder="searchPlaceholder"
          prepend-inner-icon="mdi-magnify"
          density="compact"
          variant="outlined"
          hide-details
          clearable
          bg-color="white"
          @update:model-value="handleSearch"
          @click:clear="handleClear"
          @keyup.enter="$emit('search', localSearch)"
        />
      </div>
      <slot name="toolbar" />
    </div>

    <v-data-table
      :headers="headers"
      :items="items"
      :loading="loading"
      :items-length="effectiveTotal > items.length ? effectiveTotal : undefined"
      :page="localPage"
      :items-per-page="localPerPage"
      hide-default-footer
      v-bind="tableAttrs"
    >
      <template v-for="name in forwardedSlots" :key="name" #[name]="slotProps">
        <slot :name="name" v-bind="slotProps ?? {}" />
      </template>

      <template #no-data>
        <slot name="no-data">
          <div class="tw-flex tw-flex-col tw-items-center tw-py-14 tw-text-slate-400">
            <v-icon size="48" class="tw-mb-3 tw-opacity-40">mdi-table-off</v-icon>
            <p class="tw-text-sm tw-font-medium tw-text-slate-500">No records found</p>
            <p class="tw-mt-1 tw-text-xs">Try adjusting your search or filters</p>
          </div>
        </slot>
      </template>
    </v-data-table>

    <div class="tw-flex tw-flex-col tw-gap-3 tw-border-t tw-border-slate-200 tw-bg-slate-50/80 tw-px-4 tw-py-3 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between">
      <div class="tw-flex tw-items-center tw-gap-4">
        <p class="tw-text-xs tw-text-slate-500">{{ recordsInfo }}</p>
        <div class="tw-flex tw-items-center tw-gap-1.5">
          <span class="tw-text-xs tw-text-slate-400">Rows:</span>
          <select
            v-model.number="localPerPage"
            class="tw-cursor-pointer tw-border tw-border-slate-300 tw-bg-white tw-px-2 tw-py-1 tw-text-xs tw-text-slate-700 focus:tw-outline-none focus:tw-ring-1 focus:tw-ring-cyan-500"
            @change="onPerPageChange"
          >
            <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
      </div>

      <div class="tw-flex tw-items-center tw-gap-1.5">
        <button
          type="button"
          :disabled="localPage <= 1"
          class="tw-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 hover:tw-bg-slate-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-40"
          @click="goFirst"
        >
          <v-icon size="14">mdi-page-first</v-icon>
        </button>
        <button
          type="button"
          :disabled="localPage <= 1"
          class="tw-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 hover:tw-bg-slate-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-40"
          @click="goPrev"
        >
          <v-icon size="14">mdi-chevron-left</v-icon>
        </button>

        <div class="tw-flex tw-items-center tw-gap-1">
          <span class="tw-text-xs tw-text-slate-400">Page</span>
          <input
            v-model.number="jumpPage"
            type="number"
            :min="1"
            :max="totalPages"
            class="tw-w-14 tw-border tw-border-slate-300 tw-bg-white tw-px-1 tw-py-0.5 tw-text-center tw-text-xs tw-text-slate-700 focus:tw-outline-none focus:tw-ring-1 focus:tw-ring-cyan-500"
            @keyup.enter="goToPage"
            @blur="goToPage"
          />
          <span class="tw-text-xs tw-text-slate-400">of {{ totalPages }}</span>
        </div>

        <button
          type="button"
          :disabled="localPage >= totalPages"
          class="tw-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 hover:tw-bg-slate-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-40"
          @click="goNext"
        >
          <v-icon size="14">mdi-chevron-right</v-icon>
        </button>
        <button
          type="button"
          :disabled="localPage >= totalPages"
          class="tw-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 hover:tw-bg-slate-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-40"
          @click="goLast"
        >
          <v-icon size="14">mdi-page-last</v-icon>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, useAttrs, useSlots, watch } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  headers: { type: Array, required: true },
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  itemsLength: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  itemsPerPage: { type: Number, default: 25 },
  search: { type: String, default: '' },
  searchable: { type: Boolean, default: false },
  searchPlaceholder: { type: String, default: 'Search...' },
  perPageOptions: { type: Array, default: () => [10, 25, 50, 100] },
})

const emit = defineEmits([
  'update:page',
  'update:items-per-page',
  'update:search',
  'search',
])

const attrs = useAttrs()
const slots = useSlots()

const tableAttrs = computed(() => {
  const { class: _c, style: _s, ...rest } = attrs
  return rest
})

const ownedSlots = new Set(['no-data', 'toolbar'])
const forwardedSlots = computed(() => Object.keys(slots).filter((name) => !ownedSlots.has(name)))

const localPage = ref(props.page)
const localPerPage = ref(props.itemsPerPage)
const localSearch = ref(props.search)
const jumpPage = ref(props.page)

watch(() => props.page, (value) => { localPage.value = value; jumpPage.value = value })
watch(() => props.itemsPerPage, (value) => { localPerPage.value = value })
watch(() => props.search, (value) => { localSearch.value = value })

const effectiveTotal = computed(() => props.itemsLength > 0 ? props.itemsLength : props.items.length)
const totalPages = computed(() => Math.max(1, Math.ceil(effectiveTotal.value / localPerPage.value)))
const startRecord = computed(() => Math.min((localPage.value - 1) * localPerPage.value + 1, effectiveTotal.value))
const endRecord = computed(() => Math.min(localPage.value * localPerPage.value, effectiveTotal.value))
const recordsInfo = computed(() => effectiveTotal.value === 0
  ? 'No records'
  : `Showing ${startRecord.value}-${endRecord.value} of ${effectiveTotal.value.toLocaleString()} records`)

const emitPage = () => {
  jumpPage.value = localPage.value
  emit('update:page', localPage.value)
}

const goFirst = () => { localPage.value = 1; emitPage() }
const goPrev = () => { if (localPage.value > 1) { localPage.value -= 1; emitPage() } }
const goNext = () => { if (localPage.value < totalPages.value) { localPage.value += 1; emitPage() } }
const goLast = () => { localPage.value = totalPages.value; emitPage() }
const goToPage = () => {
  const page = Math.max(1, Math.min(Math.floor(jumpPage.value || 1), totalPages.value))
  localPage.value = page
  jumpPage.value = page
  emit('update:page', page)
}

const onPerPageChange = () => {
  localPage.value = 1
  jumpPage.value = 1
  emit('update:items-per-page', localPerPage.value)
  emit('update:page', 1)
}

let searchTimer = null
const handleSearch = (value) => {
  localSearch.value = value ?? ''
  emit('update:search', localSearch.value)
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => emit('search', localSearch.value), 320)
}
const handleClear = () => {
  localSearch.value = ''
  emit('update:search', '')
  emit('search', '')
}
</script>
