<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Bundle Management</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage bundle tariffs and configurations for the hybrid payment model</p>
        </div>
        <v-btn color="primary" @click="showCreateDialog = true">
          <v-icon left>mdi-plus</v-icon>
          Create Bundle
        </v-btn>
      </div>

      <!-- Filters -->
      <v-card>
        <v-card-text class="tw-flex tw-gap-4 tw-flex-wrap">
          <v-text-field
            v-model="search"
            density="compact"
            label="Search bundles"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            hide-details
            class="tw-max-w-xs"
            @update:modelValue="debouncedFetch"
          />
          <v-select
            v-model="filters.category"
            :items="categories"
            label="Category"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            class="tw-max-w-xs"
            @update:modelValue="fetchBundles"
          />
          <v-select
            v-model="filters.is_active"
            :items="[{ title: 'Active', value: true }, { title: 'Inactive', value: false }]"
            label="Status"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            class="tw-max-w-xs"
            @update:modelValue="fetchBundles"
          />
        </v-card-text>
      </v-card>

      <!-- Bundles Table -->
      <v-card>
        <v-data-table-server
          :headers="headers"
          :items="bundles"
          :items-length="totalItems"
          :loading="loading"
          :items-per-page="itemsPerPage"
          :page="page"
          @update:page="onPageChange"
          @update:items-per-page="onItemsPerPageChange"
        >
          <template #item.bundle_name="{ item }">
            <div>
              <div class="tw-font-medium">{{ item.bundle_name }}</div>
              <div class="tw-text-sm tw-text-gray-500 tw-font-mono">{{ item.bundle_code }}</div>
            </div>
          </template>

          <template #item.icd_10_primary="{ item }">
            <v-chip size="small" color="purple" variant="outlined">{{ item.icd_10_primary }}</v-chip>
          </template>

          <template #item.tariff_amount="{ item }">
            <div class="tw-font-semibold tw-text-green-600">₦{{ formatNumber(item.tariff_amount) }}</div>
          </template>

          <template #item.is_active="{ item }">
            <v-chip :color="item.is_active ? 'success' : 'grey'" size="small">
              {{ item.is_active ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <v-btn size="small" variant="text" color="primary" @click="editBundle(item)">
              <v-icon>mdi-pencil</v-icon>
            </v-btn>
            <v-btn size="small" variant="text" color="info" @click="viewBundle(item)">
              <v-icon>mdi-eye</v-icon>
            </v-btn>
          </template>
        </v-data-table-server>
      </v-card>

      <!-- Create/Edit Bundle Dialog -->
      <v-dialog v-model="showCreateDialog" max-width="700" persistent>
        <v-card>
          <v-card-title class="tw-bg-indigo-50 tw-text-indigo-800">
            {{ editingBundle ? 'Edit Bundle' : 'Create Bundle' }}
          </v-card-title>
          <v-card-text class="tw-p-6">
            <v-form ref="formRef" v-model="formValid">
              <v-row>
                <v-col cols="12" md="6">
                  <v-text-field v-model="form.bundle_code" label="Bundle Code *" variant="outlined" :rules="[v => !!v || 'Required']" />
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field v-model="form.bundle_name" label="Bundle Name *" variant="outlined" :rules="[v => !!v || 'Required']" />
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field v-model="form.icd_10_primary" label="Primary ICD-10 Code *" variant="outlined" :rules="[v => !!v || 'Required']" />
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field v-model.number="form.tariff_amount" label="Tariff Amount (₦) *" type="number" variant="outlined" :rules="[v => v > 0 || 'Must be positive']" />
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field v-model="form.category" label="Category" variant="outlined" />
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field v-model.number="form.max_ward_days" label="Max Ward Days" type="number" variant="outlined" />
                </v-col>
                <v-col cols="12">
                  <v-textarea v-model="form.description" label="Description" variant="outlined" rows="2" />
                </v-col>
                <v-col cols="12">
                  <v-textarea v-model="form.included_services" label="Included Services (comma-separated)" variant="outlined" rows="2" />
                </v-col>
                <v-col cols="12">
                  <v-textarea v-model="form.excluded_services" label="Excluded Services (comma-separated)" variant="outlined" rows="2" />
                </v-col>
                <v-col cols="12">
                  <v-switch v-model="form.is_active" label="Active" color="success" />
                </v-col>
              </v-row>
            </v-form>
          </v-card-text>
          <v-card-actions class="tw-px-6 tw-pb-4">
            <v-spacer />
            <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
            <v-btn color="primary" :loading="saving" :disabled="!formValid" @click="saveBundle">
              {{ editingBundle ? 'Update' : 'Create' }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AdminLayout from '@/js/components/layout/AdminLayout.vue'
import { bundleAPI } from '@/js/utils/api'
import { useToast } from 'primevue/usetoast'
import { debounce } from 'lodash-es'

const toast = useToast()
const loading = ref(false)
const bundles = ref([])
const search = ref('')
const page = ref(1)
const itemsPerPage = ref(10)
const totalItems = ref(0)
const filters = ref({ category: null, is_active: null })
const categories = ['Surgical', 'Medical', 'Obstetric', 'Pediatric', 'Emergency']

const showCreateDialog = ref(false)
const editingBundle = ref(null)
const formRef = ref(null)
const formValid = ref(false)
const saving = ref(false)

const form = ref({ bundle_code: '', bundle_name: '', icd_10_primary: '', tariff_amount: 0, category: '', max_ward_days: 5, description: '', included_services: '', excluded_services: '', is_active: true })

const headers = [
  { title: 'Bundle', key: 'bundle_name', sortable: true },
  { title: 'ICD-10', key: 'icd_10_primary', sortable: true },
  { title: 'Tariff', key: 'tariff_amount', sortable: true },
  { title: 'Category', key: 'category', sortable: true },
  { title: 'Status', key: 'is_active', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end' }
]

const fetchBundles = async () => {
  loading.value = true
  try {
    const res = await bundleAPI.getAll({ page: page.value, per_page: itemsPerPage.value, search: search.value || undefined, ...filters.value })
    bundles.value = res.data.data || []
    totalItems.value = res.data.meta?.total || 0
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

const debouncedFetch = debounce(fetchBundles, 300)
const onPageChange = (p) => { page.value = p; fetchBundles() }
const onItemsPerPageChange = (pp) => { itemsPerPage.value = pp; page.value = 1; fetchBundles() }
const formatNumber = (num) => num?.toLocaleString() || '0'

const editBundle = (bundle) => { editingBundle.value = bundle; form.value = { ...bundle }; showCreateDialog.value = true }
const viewBundle = (bundle) => { /* TODO: implement view */ console.log('View', bundle) }
const closeDialog = () => { showCreateDialog.value = false; editingBundle.value = null; form.value = { bundle_code: '', bundle_name: '', icd_10_primary: '', tariff_amount: 0, category: '', max_ward_days: 5, description: '', included_services: '', excluded_services: '', is_active: true } }

const saveBundle = async () => {
  if (!formRef.value?.validate()) return
  saving.value = true
  try {
    if (editingBundle.value) {
      await bundleAPI.update(editingBundle.value.id, form.value)
    } else {
      await bundleAPI.create(form.value)
    }
    toast.add({ severity: 'success', summary: 'Success', detail: `Bundle ${editingBundle.value ? 'updated' : 'created'}`, life: 3000 })
    closeDialog()
    fetchBundles()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: e.response?.data?.message || 'Failed to save', life: 5000 })
  } finally { saving.value = false }
}

onMounted(fetchBundles)
</script>

