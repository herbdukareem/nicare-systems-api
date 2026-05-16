<template>
  <div :class="[$attrs.class]" :style="$attrs.style" class="tw-overflow-hidden tw-rounded-xl tw-border tw-border-slate-200 tw-bg-white tw-shadow-sm">
    <!-- Toolbar: search + slot -->
    <div
      v-if="searchable || $slots.toolbar"
      class="tw-flex tw-flex-wrap tw-items-center tw-gap-3 tw-border-b tw-border-slate-200 tw-bg-slate-50/70 tw-px-4 tw-py-3"
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

    <!-- Data table -->
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
      <!-- Forward all parent slots except the ones we own -->
      <template v-for="name in forwardedSlots" :key="name" #[name]="slotProps">
        <slot :name="name" v-bind="slotProps ?? {}" />
      </template>

      <!-- Custom empty state -->
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

    <!-- Pagination footer -->
    <div class="tw-flex tw-flex-col tw-gap-3 tw-border-t tw-border-slate-200 tw-bg-slate-50/70 tw-px-4 tw-py-3 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between">
      <!-- Left: info + rows per page -->
      <div class="tw-flex tw-items-center tw-gap-4">
        <p class="tw-text-xs tw-text-slate-500">{{ recordsInfo }}</p>
        <div class="tw-flex tw-items-center tw-gap-1.5">
          <span class="tw-text-xs tw-text-slate-400">Rows:</span>
          <select
            v-model.number="localPerPage"
            class="tw-cursor-pointer tw-rounded-md tw-border tw-border-slate-300 tw-bg-white tw-px-2 tw-py-1 tw-text-xs tw-text-slate-700 focus:tw-outline-none focus:tw-ring-1 focus:tw-ring-cyan-500"
            @change="onPerPageChange"
          >
            <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
          </select>
        </div>
      </div>

      <!-- Right: page navigation -->
      <div class="tw-flex tw-items-center tw-gap-1.5">
        <!-- First -->
        <button
          type="button"
          :disabled="localPage <= 1"
          class="tw-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-transition-colors hover:tw-bg-slate-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-40"
          @click="goFirst"
        >
          <v-icon size="14">mdi-page-first</v-icon>
        </button>
        <!-- Prev -->
        <button
          type="button"
          :disabled="localPage <= 1"
          class="tw-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-transition-colors hover:tw-bg-slate-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-40"
          @click="goPrev"
        >
          <v-icon size="14">mdi-chevron-left</v-icon>
        </button>

        <!-- Page jump input -->
        <div class="tw-flex tw-items-center tw-gap-1">
          <span class="tw-text-xs tw-text-slate-400">Page</span>
          <input
            v-model.number="jumpPage"
            type="number"
            :min="1"
            :max="totalPages"
            class="tw-w-14 tw-rounded-md tw-border tw-border-slate-300 tw-bg-white tw-px-1 tw-py-0.5 tw-text-center tw-text-xs tw-text-slate-700 focus:tw-outline-none focus:tw-ring-1 focus:tw-ring-cyan-500"
            @keyup.enter="goToPage"
            @blur="goToPage"
          />
          <span class="tw-text-xs tw-text-slate-400">of {{ totalPages }}</span>
        </div>

        <!-- Next -->
        <button
          type="button"
          :disabled="localPage >= totalPages"
          class="tw-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-transition-colors hover:tw-bg-slate-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-40"
          @click="goNext"
        >
          <v-icon size="14">mdi-chevron-right</v-icon>
        </button>
        <!-- Last -->
        <button
          type="button"
          :disabled="localPage >= totalPages"
          class="tw-flex tw-h-7 tw-w-7 tw-items-center tw-justify-center tw-rounded-md tw-border tw-border-slate-200 tw-bg-white tw-text-slate-600 tw-transition-colors hover:tw-bg-slate-100 disabled:tw-cursor-not-allowed disabled:tw-opacity-40"
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

// Attrs that should go to v-data-table (exclude class/style which go to root div)
const tableAttrs = computed(() => {
  const { class: _c, style: _s, ...rest } = attrs
  return rest
})

// Slots we own / handle ourselves — don't forward these to v-data-table
const OWNED_SLOTS = new Set(['no-data', 'toolbar'])
const forwardedSlots = computed(() =>
  Object.keys(slots).filter((name) => !OWNED_SLOTS.has(name)),
)

// --- Local reactive state ---
const localPage = ref(props.page)
const localPerPage = ref(props.itemsPerPage)
const localSearch = ref(props.search)
const jumpPage = ref(props.page)

// Sync props → local when parent changes them externally
watch(() => props.page, (v) => { localPage.value = v; jumpPage.value = v })
watch(() => props.itemsPerPage, (v) => { localPerPage.value = v })
watch(() => props.search, (v) => { localSearch.value = v })

// --- Pagination math ---
const effectiveTotal = computed(() =>
  props.itemsLength > 0 ? props.itemsLength : props.items.length,
)
const totalPages = computed(() => Math.max(1, Math.ceil(effectiveTotal.value / localPerPage.value)))
const startRecord = computed(() => Math.min((localPage.value - 1) * localPerPage.value + 1, effectiveTotal.value))
const endRecord = computed(() => Math.min(localPage.value * localPerPage.value, effectiveTotal.value))
const recordsInfo = computed(() =>
  effectiveTotal.value === 0
    ? 'No records'
    : `Showing ${startRecord.value}–${endRecord.value} of ${effectiveTotal.value.toLocaleString()} records`,
)

// --- Page navigation ---
const emitPage = () => {
  jumpPage.value = localPage.value
  emit('update:page', localPage.value)
}
const goFirst = () => { localPage.value = 1; emitPage() }
const goPrev = () => { if (localPage.value > 1) { localPage.value--; emitPage() } }
const goNext = () => { if (localPage.value < totalPages.value) { localPage.value++; emitPage() } }
const goLast = () => { localPage.value = totalPages.value; emitPage() }
const goToPage = () => {
  const p = Math.max(1, Math.min(Math.floor(jumpPage.value || 1), totalPages.value))
  localPage.value = p
  jumpPage.value = p
  emit('update:page', p)
}

const onPerPageChange = () => {
  localPage.value = 1
  jumpPage.value = 1
  emit('update:items-per-page', localPerPage.value)
  emit('update:page', 1)
}

// --- Search ---
let searchTimer = null
const handleSearch = (val) => {
  localSearch.value = val ?? ''
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
