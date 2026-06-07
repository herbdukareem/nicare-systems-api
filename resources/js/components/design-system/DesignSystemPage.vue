<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader title="Design System" icon="mdi-palette-outline" icon-color="secondary">
        <span class="tw-text-xs tw-text-slate-500">QDS — Quorum Design System v2</span>
      </AppPageHeader>

      <!-- Color Tokens -->
      <AppCard title="Color Tokens" icon="mdi-palette" tone="primary">
        <div class="tw-grid tw-grid-cols-2 tw-gap-3 sm:tw-grid-cols-3 md:tw-grid-cols-6">
          <div v-for="color in colorTokens" :key="color.name" class="tw-space-y-1.5">
            <div class="tw-h-10 tw-w-full tw-border tw-border-slate-200" :style="{ background: color.value }" />
            <p class="tw-text-xs tw-font-medium tw-text-slate-700">{{ color.name }}</p>
            <p class="tw-font-mono tw-text-[10px] tw-text-slate-400">{{ color.value }}</p>
          </div>
        </div>
      </AppCard>

      <!-- Typography -->
      <AppCard title="Typography" icon="mdi-format-text" tone="secondary">
        <div class="tw-space-y-3">
          <div class="tw-border-b tw-border-slate-100 tw-pb-3"><p class="tw-text-xl tw-font-bold tw-text-slate-900">Page Title — text-xl font-bold</p></div>
          <div class="tw-border-b tw-border-slate-100 tw-pb-3"><p class="tw-text-base tw-font-semibold tw-text-slate-900">Section Title — text-base font-semibold</p></div>
          <div class="tw-border-b tw-border-slate-100 tw-pb-3"><p class="tw-text-sm tw-font-medium tw-text-slate-700">Body — text-sm font-medium</p></div>
          <div class="tw-border-b tw-border-slate-100 tw-pb-3"><p class="tw-text-xs tw-text-slate-500">Caption — text-xs text-slate-500</p></div>
          <div><p class="tw-font-mono tw-text-xs tw-text-slate-400">Monospace — font-mono text-xs</p></div>
        </div>
      </AppCard>

      <!-- Spacing -->
      <AppCard title="Spacing Tokens" icon="mdi-arrow-expand-all" tone="info">
        <div class="tw-grid tw-gap-3 sm:tw-grid-cols-2 md:tw-grid-cols-4">
          <div v-for="s in spacingTokens" :key="s.name" class="tw-border tw-border-slate-200 tw-bg-slate-50 tw-p-2">
            <p class="tw-text-xs tw-font-medium tw-text-slate-700">{{ s.name }}</p>
            <p class="tw-font-mono tw-text-[10px] tw-text-slate-400">{{ s.value }}</p>
            <div class="tw-mt-1.5 tw-bg-primary/10 tw-border tw-border-primary/20" :style="{ height: '8px', width: s.value }" />
          </div>
        </div>
      </AppCard>

      <!-- Tones / Badges -->
      <AppCard title="Tones & Badges" icon="mdi-label-multiple-outline" tone="primary">
        <div class="tw-space-y-4">
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">Solid badges (default)</p>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <AppBadge v-for="tone in tones" :key="tone" :label="tone" :tone="tone" />
            </div>
          </div>
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">Outline badges</p>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <AppBadge v-for="tone in tones" :key="tone" :label="tone" :tone="tone" outline />
            </div>
          </div>
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">Small badges</p>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <AppBadge v-for="tone in tones" :key="tone" :label="tone" :tone="tone" size="sm" />
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Icon Shells -->
      <AppCard title="Icon Shells" icon="mdi-shape-outline" tone="secondary">
        <div class="tw-flex tw-flex-wrap tw-gap-4">
          <div v-for="tone in tones" :key="tone" class="tw-flex tw-flex-col tw-items-center tw-gap-1.5">
            <div class="qds-icon-shell" :class="`qds-tone-${tone}`">
              <v-icon size="18">mdi-check-circle-outline</v-icon>
            </div>
            <p class="tw-text-[10px] tw-text-slate-500">{{ tone }}</p>
          </div>
          <div class="tw-flex tw-flex-col tw-items-center tw-gap-1.5">
            <div class="qds-icon-shell-sm qds-tone-primary">
              <v-icon size="14">mdi-check</v-icon>
            </div>
            <p class="tw-text-[10px] tw-text-slate-500">sm</p>
          </div>
        </div>
      </AppCard>

      <!-- Stat Cards -->
      <AppCard title="AppStatCard" icon="mdi-card-outline" tone="info">
        <div class="tw-space-y-4">
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">Compact variant (admin use)</p>
            <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-5">
              <AppStatCard compact v-for="tone in ['primary','secondary','success','warning','info']" :key="tone"
                :label="`${tone} stat`" :value="1284" :icon="toneIcons[tone]" :color="tone" />
            </div>
          </div>
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">Default variant</p>
            <div class="tw-grid tw-gap-3 tw-grid-cols-1 md:tw-grid-cols-2">
              <AppStatCard label="Default Stat" :value="8492" icon="mdi-account-group" color="primary" sub-label="As at today" />
              <AppStatCard label="With Change" :value="3271" icon="mdi-chart-line" color="success" :change="12" sub-label="vs last month" />
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Buttons -->
      <AppCard title="Buttons" icon="mdi-button-cursor" tone="primary">
        <div class="tw-space-y-4">
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">Flat / filled</p>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <v-btn color="primary" variant="flat">Primary</v-btn>
              <v-btn color="success" variant="flat">Success</v-btn>
              <v-btn color="warning" variant="flat">Warning</v-btn>
              <v-btn color="error" variant="flat">Danger</v-btn>
              <v-btn color="info" variant="flat">Info</v-btn>
            </div>
          </div>
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">Outlined</p>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <v-btn color="primary" variant="outlined">Primary</v-btn>
              <v-btn color="success" variant="outlined">Success</v-btn>
              <v-btn color="error" variant="outlined">Danger</v-btn>
              <v-btn variant="outlined">Default</v-btn>
            </div>
          </div>
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">Small (admin default)</p>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <v-btn size="small" color="primary" variant="flat">Primary</v-btn>
              <v-btn size="small" color="primary" variant="outlined">Outlined</v-btn>
              <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh">Refresh</v-btn>
              <v-btn size="small" color="error" variant="outlined" prepend-icon="mdi-delete-outline">Delete</v-btn>
            </div>
          </div>
          <div>
            <p class="tw-mb-2 tw-text-xs tw-font-semibold tw-uppercase tw-tracking-wider tw-text-slate-400">With icon</p>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <v-btn color="primary" prepend-icon="mdi-plus">Add Record</v-btn>
              <v-btn variant="outlined" prepend-icon="mdi-download">Export</v-btn>
              <v-btn icon size="small" variant="text"><v-icon>mdi-eye-outline</v-icon></v-btn>
              <v-btn icon size="small" variant="text"><v-icon>mdi-pencil-outline</v-icon></v-btn>
              <v-btn icon size="small" variant="text" color="error"><v-icon>mdi-delete-outline</v-icon></v-btn>
            </div>
          </div>
        </div>
      </AppCard>

      <!-- Cards -->
      <AppCard title="AppCard Variants" icon="mdi-card-multiple-outline" tone="warning">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-2">
          <AppCard title="Default Card" subtitle="With a subtitle line" icon="mdi-information-outline" tone="primary">
            <p class="tw-text-sm tw-text-slate-600">Card body content goes here.</p>
          </AppCard>
          <AppCard title="No Subtitle" icon="mdi-check-circle-outline" tone="success">
            <p class="tw-text-sm tw-text-slate-600">Card body content goes here.</p>
          </AppCard>
          <AppCard title="With Actions" icon="mdi-dots-horizontal" tone="info">
            <template #actions>
              <v-btn size="small" variant="outlined">Action</v-btn>
            </template>
            <p class="tw-text-sm tw-text-slate-600">Card body content goes here.</p>
          </AppCard>
          <AppCard title="Hover Lift" icon="mdi-cursor-move" tone="secondary" hover>
            <p class="tw-text-sm tw-text-slate-600">Hover over this card to see the lift effect.</p>
          </AppCard>
        </div>
      </AppCard>

      <!-- Alerts -->
      <AppCard title="Alerts" icon="mdi-alert-circle-outline" tone="warning">
        <div class="tw-space-y-2">
          <div v-for="tone in ['info','success','warning','danger']" :key="tone" class="qds-alert" :class="`qds-alert-${tone}`">
            <p class="tw-text-sm tw-font-medium">{{ tone.charAt(0).toUpperCase() + tone.slice(1) }} alert — for contextual feedback messages.</p>
          </div>
        </div>
      </AppCard>

      <!-- Status Badges -->
      <AppCard title="Status Badges" icon="mdi-flag-outline" tone="info">
        <div class="tw-flex tw-flex-wrap tw-gap-2">
          <AppStatusBadge v-for="status in statuses" :key="status" :status="status" :label="status" />
        </div>
      </AppCard>

      <!-- Form Inputs -->
      <AppCard title="Form Inputs" icon="mdi-form-textbox" tone="secondary">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-2">
          <v-text-field label="Text field" variant="outlined" density="compact" hide-details placeholder="Enter value" />
          <v-text-field label="With icon" variant="outlined" density="compact" hide-details prepend-inner-icon="mdi-magnify" placeholder="Search..." />
          <v-select label="Select" variant="outlined" density="compact" hide-details :items="['Option A','Option B','Option C']" />
          <v-autocomplete label="Autocomplete" variant="outlined" density="compact" hide-details :items="['Option A','Option B','Option C']" />
          <v-text-field label="Comfortable density" variant="outlined" density="comfortable" hide-details />
          <v-textarea label="Textarea" variant="outlined" density="compact" rows="2" hide-details />
        </div>
      </AppCard>

      <!-- Filter Bar -->
      <AppCard title="AppFilterBar" icon="mdi-filter-outline" tone="primary" :padded="false">
        <AppFilterBar :active-count="2" :cols="4" @clear="() => {}">
          <v-text-field label="Search" variant="outlined" density="compact" hide-details prepend-inner-icon="mdi-magnify" />
          <v-select label="Status" variant="outlined" density="compact" hide-details :items="['Active','Inactive']" />
          <v-select label="Type" variant="outlined" density="compact" hide-details :items="['Primary','Secondary']" />
          <template #actions>
            <v-btn size="small" color="primary">Load</v-btn>
          </template>
          <template #tags>
            <AppBadge label="Status: Active" tone="warning" size="sm" />
            <AppBadge label="Type: Primary" tone="secondary" size="sm" />
          </template>
        </AppFilterBar>
      </AppCard>

      <!-- Token Reference -->
      <AppCard title="CSS Variable Reference" icon="mdi-code-braces" tone="neutral" :padded="false">
        <div class="tw-overflow-x-auto">
          <table class="tw-min-w-full tw-text-xs">
            <thead class="tw-border-b tw-border-slate-200 tw-bg-slate-50">
              <tr>
                <th class="tw-px-4 tw-py-2.5 tw-text-left tw-font-semibold tw-text-slate-700">Variable</th>
                <th class="tw-px-4 tw-py-2.5 tw-text-left tw-font-semibold tw-text-slate-700">Value</th>
                <th class="tw-px-4 tw-py-2.5 tw-text-left tw-font-semibold tw-text-slate-700">Use</th>
              </tr>
            </thead>
            <tbody class="tw-divide-y tw-divide-slate-100">
              <tr v-for="v in cssVarRef" :key="v.name" class="hover:tw-bg-slate-50">
                <td class="tw-px-4 tw-py-2 tw-font-mono tw-text-primary">{{ v.name }}</td>
                <td class="tw-px-4 tw-py-2 tw-font-mono tw-text-slate-500">{{ v.value }}</td>
                <td class="tw-px-4 tw-py-2 tw-text-slate-600">{{ v.use }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '../layout/AdminLayout.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import AppStatCard from '../common/AppStatCard.vue'
import AppStatusBadge from '../common/AppStatusBadge.vue'

const tones = ['primary', 'secondary', 'success', 'warning', 'danger', 'info', 'neutral']
const toneIcons = {
  primary: 'mdi-account-group', secondary: 'mdi-hospital-building',
  success: 'mdi-check-decagram', warning: 'mdi-clock-outline',
  info: 'mdi-information-outline',
}
const statuses = ['Active', 'Inactive', 'Pending', 'Approved', 'Rejected', 'Draft', 'Submitted', 'Reviewing', 'Paid', 'Suspended']

const colorTokens = [
  { name: 'Primary', value: '#0b6b79' },
  { name: 'Secondary', value: '#2563eb' },
  { name: 'Success', value: '#0f766e' },
  { name: 'Warning', value: '#b45309' },
  { name: 'Danger', value: '#b42318' },
  { name: 'Info', value: '#1d4ed8' },
  { name: 'Background', value: '#f4f7fb' },
  { name: 'Surface', value: '#ffffff' },
  { name: 'Border', value: '#d9e2ec' },
  { name: 'Text', value: '#102a43' },
  { name: 'Text Secondary', value: '#486581' },
  { name: 'Text Muted', value: '#829ab1' },
]

const spacingTokens = [
  { name: 'pageX / pageY', value: '1.25rem' },
  { name: 'section', value: '1rem' },
  { name: 'card', value: '0.875rem' },
  { name: 'cardLg', value: '1.25rem' },
  { name: 'form', value: '0.875rem' },
  { name: 'table', value: '0.75rem' },
]

const cssVarRef = [
  { name: '--qds-radius-sm', value: '0px', use: 'Small elements, icon shells' },
  { name: '--qds-radius-md', value: '0px', use: 'Cards, dropdowns' },
  { name: '--qds-radius-lg', value: '0px', use: 'Modals, large cards' },
  { name: '--qds-radius-pill', value: '2px', use: 'Badges, chips' },
  { name: '--qds-input-height', value: '34px', use: 'Compact inputs' },
  { name: '--qds-btn-height', value: '34px', use: 'Compact buttons' },
  { name: '--qds-table-row-height', value: '40px', use: 'Table rows' },
  { name: '--qds-badge-height', value: '22px', use: 'Inline badges' },
  { name: '--qds-shadow-card', value: '0 16px 40px -28px rgba(15,23,42,.32)', use: 'Card elevation' },
  { name: '--qds-shadow-modal', value: '0 30px 80px -32px rgba(15,23,42,.34)', use: 'Modal elevation' },
]
</script>
