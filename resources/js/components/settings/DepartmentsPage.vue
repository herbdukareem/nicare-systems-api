<template>
  <AdminLayout>
    <div class="tw-space-y-6">

      <AppPageHeader title="Departments" subtitle="Manage organizational departments" icon="mdi-office-building" icon-color="primary">
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Department</v-btn>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar :active-count="activeFiltersCount" @clear="clearFilters" :cols="3">
        <v-text-field
          v-model="filters.search"
          label="Search departments…"
          prepend-inner-icon="mdi-magnify"
          variant="outlined" density="compact" clearable hide-details
          @input="debounceSearch"
        />
        <v-select
          v-model="filters.status"
          label="Status"
          :items="[{ title: 'Active', value: 1 }, { title: 'Inactive', value: 0 }]"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchDepartments"
        />
        <v-select
          v-model="filters.per_page"
          label="Per Page"
          :items="[10, 25, 50, 100]"
          variant="outlined" density="compact" hide-details
          @update:model-value="fetchDepartments"
        />
      </AppFilterBar>

      <!-- Table -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <v-data-table
          :headers="headers"
          :items="departments"
          :loading="loading"
          :items-per-page="filters.per_page"
          hide-default-footer
          class="tw-rounded-xl"
        >
          <template #item.status="{ item }">
            <AppStatusChip :status="item.status === 1 || item.status === true ? 'active' : 'inactive'" />
          </template>

          <template #item.created_at="{ item }">
            <span class="tw-text-xs tw-text-gray-500">{{ formatDate(item.created_at) }}</span>
          </template>

          <template #item.actions="{ item }">
            <div class="tw-flex tw-gap-1">
              <v-btn icon size="small" variant="text" color="primary" @click="openEdit(item)">
                <v-icon size="16">mdi-pencil</v-icon>
                <v-tooltip activator="parent">Edit</v-tooltip>
              </v-btn>
              <v-btn icon size="small" variant="text" color="error" @click="confirmDelete(item)">
                <v-icon size="16">mdi-delete</v-icon>
                <v-tooltip activator="parent">Delete</v-tooltip>
              </v-btn>
            </div>
          </template>

          <template #no-data>
            <AppEmptyState
              icon="mdi-office-building-outline"
              title="No departments found"
              description="No departments match the current filters. Add one to get started."
            >
              <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Department</v-btn>
            </AppEmptyState>
          </template>
        </v-data-table>

        <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3 tw-border-t tw-border-gray-100">
          <span class="tw-text-sm tw-text-gray-500">{{ pagination.total.toLocaleString() }} total records</span>
          <v-pagination
            v-model="pagination.current_page"
            :length="pagination.last_page"
            :total-visible="5"
            density="compact"
            @update:model-value="fetchDepartments"
          />
        </div>
      </v-card>

      <!-- Create / Edit Dialog -->
      <v-dialog v-model="formDialog" max-width="480" persistent>
        <v-card class="tw-rounded-xl" elevation="0">
          <v-card-title class="tw-px-6 tw-pt-6 tw-pb-2 tw-flex tw-items-center tw-justify-between">
            <span class="tw-text-lg tw-font-bold">{{ isEditing ? 'Edit Department' : 'Add Department' }}</span>
            <v-btn icon="mdi-close" variant="text" @click="closeForm" />
          </v-card-title>
          <v-divider />
          <v-card-text class="tw-px-6 tw-py-4">
            <v-form ref="formRef" @submit.prevent="submitForm">
              <div class="tw-space-y-4">
                <v-text-field
                  v-model="form.name"
                  label="Department Name *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Name is required']"
                />
                <v-text-field
                  v-model="form.code"
                  label="Department Code"
                  variant="outlined"
                  density="compact"
                  hint="Optional short code e.g. HR, FIN"
                  persistent-hint
                />
                <v-textarea
                  v-model="form.description"
                  label="Description"
                  variant="outlined"
                  density="compact"
                  rows="3"
                  auto-grow
                />
                <v-select
                  v-if="isEditing"
                  v-model="form.status"
                  label="Status"
                  :items="[{ title: 'Active', value: 1 }, { title: 'Inactive', value: 0 }]"
                  variant="outlined"
                  density="compact"
                />
              </div>
            </v-form>
          </v-card-text>
          <v-card-actions class="tw-px-6 tw-pb-4 tw-gap-2">
            <v-spacer />
            <v-btn variant="outlined" @click="closeForm">Cancel</v-btn>
            <v-btn color="primary" variant="flat" :loading="saving" @click="submitForm">
              {{ isEditing ? 'Save Changes' : 'Create' }}
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

      <!-- Delete Confirm -->
      <v-dialog v-model="deleteDialog" max-width="400">
        <v-card class="tw-rounded-xl" elevation="0">
          <v-card-title class="tw-px-6 tw-pt-6 tw-pb-2">Delete Department</v-card-title>
          <v-card-text class="tw-px-6">
            Are you sure you want to delete <strong>{{ selectedItem?.name }}</strong>? This action cannot be undone.
          </v-card-text>
          <v-card-actions class="tw-px-6 tw-pb-4 tw-gap-2">
            <v-spacer />
            <v-btn variant="outlined" @click="deleteDialog = false">Cancel</v-btn>
            <v-btn color="error" variant="flat" :loading="deleting" @click="deleteDepartment">Delete</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { debounce } from 'lodash';
import AdminLayout from '../layout/AdminLayout.vue';
import AppPageHeader from '../common/AppPageHeader.vue';
import AppFilterBar from '../common/AppFilterBar.vue';
import AppStatusChip from '../common/AppStatusChip.vue';
import AppEmptyState from '../common/AppEmptyState.vue';
import { departmentAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();

const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const departments = ref([]);
const formDialog = ref(false);
const deleteDialog = ref(false);
const isEditing = ref(false);
const selectedItem = ref(null);
const formRef = ref(null);

const filters = ref({ search: '', status: null, per_page: 25 });
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const form = ref({ name: '', code: '', description: '', status: 1 });

const headers = [
  { title: 'Name', key: 'name', minWidth: 160 },
  { title: 'Code', key: 'code', width: 100 },
  { title: 'Description', key: 'description', minWidth: 200 },
  { title: 'Status', key: 'status', width: 100 },
  { title: 'Created', key: 'created_at', width: 120 },
  { title: 'Actions', key: 'actions', width: 100, sortable: false, align: 'end' },
];

const activeFiltersCount = computed(() =>
  [filters.value.search, filters.value.status].filter(v => v !== null && v !== '').length
);

async function fetchDepartments() {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: filters.value.per_page,
      ...(filters.value.search && { search: filters.value.search }),
      ...(filters.value.status !== null && { status: filters.value.status }),
    };
    const res = await departmentAPI.getAll(params);
    const data = res.data?.data ?? res.data;
    departments.value = data.data ?? data ?? [];
    const m = res.data?.data?.meta ?? res.data?.meta ?? res.data?.data;
    if (m?.total !== undefined) {
      pagination.value = { current_page: m.current_page, last_page: m.last_page, total: m.total };
    }
  } catch {
    showToast('Failed to load departments', 'error');
  } finally {
    loading.value = false;
  }
}

function clearFilters() {
  filters.value.search = '';
  filters.value.status = null;
  pagination.value.current_page = 1;
  fetchDepartments();
}

const debounceSearch = debounce(() => {
  pagination.value.current_page = 1;
  fetchDepartments();
}, 400);

function openCreate() {
  isEditing.value = false;
  form.value = { name: '', code: '', description: '', status: 1 };
  formDialog.value = true;
}

function openEdit(item) {
  isEditing.value = true;
  selectedItem.value = item;
  form.value = { name: item.name, code: item.code || '', description: item.description || '', status: item.status };
  formDialog.value = true;
}

function closeForm() {
  formDialog.value = false;
  formRef.value?.reset();
}

async function submitForm() {
  const valid = await formRef.value?.validate();
  if (!valid?.valid) return;
  saving.value = true;
  try {
    if (isEditing.value) {
      await departmentAPI.update(selectedItem.value.id, form.value);
      showToast('Department updated successfully', 'success');
    } else {
      await departmentAPI.create(form.value);
      showToast('Department created successfully', 'success');
    }
    closeForm();
    fetchDepartments();
  } catch (err) {
    showToast(err.response?.data?.message || 'Failed to save department', 'error');
  } finally {
    saving.value = false;
  }
}

function confirmDelete(item) {
  selectedItem.value = item;
  deleteDialog.value = true;
}

async function deleteDepartment() {
  deleting.value = true;
  try {
    await departmentAPI.delete(selectedItem.value.id);
    showToast('Department deleted', 'success');
    deleteDialog.value = false;
    fetchDepartments();
  } catch {
    showToast('Failed to delete department', 'error');
  } finally {
    deleting.value = false;
  }
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-NG', { day: '2-digit', month: 'short', year: 'numeric' });
}

onMounted(fetchDepartments);
</script>
