<template>
  <AdminLayout>
    <div class="tw-space-y-6">

      <AppPageHeader title="Designations" subtitle="Manage job designations and positions" icon="mdi-badge-account" icon-color="primary">
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Designation</v-btn>
      </AppPageHeader>

      <!-- Filters -->
      <AppFilterBar :active-count="activeFiltersCount" @clear="clearFilters" :cols="3">
        <v-text-field
          v-model="filters.search"
          label="Search designations…"
          prepend-inner-icon="mdi-magnify"
          variant="outlined" density="compact" clearable hide-details
          @input="debounceSearch"
        />
        <v-select
          v-model="filters.department_id"
          label="Department"
          :items="departments"
          item-title="name"
          item-value="id"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchDesignations"
        />
        <v-select
          v-model="filters.status"
          label="Status"
          :items="[{ title: 'Active', value: 1 }, { title: 'Inactive', value: 0 }]"
          variant="outlined" density="compact" clearable hide-details
          @update:model-value="fetchDesignations"
        />
      </AppFilterBar>

      <!-- Table -->
      <v-card class="tw-border tw-border-gray-100 tw-shadow-sm tw-rounded-xl" elevation="0">
        <AppDataTable
          :headers="headers"
          :items="designations"
          :loading="loading"
          :items-per-page="25"
          class="tw-rounded-xl"
        >
          <template #item.department="{ item }">
            <span class="tw-text-sm">{{ item.department?.name ?? '—' }}</span>
          </template>

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
              icon="mdi-badge-account-outline"
              title="No designations found"
              description="No designations match the current filters."
            >
              <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Designation</v-btn>
            </AppEmptyState>
          </template>
        </AppDataTable>

        <div class="tw-flex tw-items-center tw-justify-between tw-px-4 tw-py-3 tw-border-t tw-border-gray-100">
          <span class="tw-text-sm tw-text-gray-500">{{ pagination.total.toLocaleString() }} total records</span>
          <v-pagination
            v-model="pagination.current_page"
            :length="pagination.last_page"
            :total-visible="5"
            density="compact"
            @update:model-value="fetchDesignations"
          />
        </div>
      </v-card>

      <!-- Create / Edit Dialog -->
      <AppModal v-model="formDialog" :title="isEditing ? 'Edit Designation' : 'Add Designation'" size="sm" :loading="saving" persistent>
            <v-form ref="formRef" @submit.prevent="submitForm">
              <div class="tw-space-y-4">
                <v-text-field
                  v-model="form.name"
                  label="Designation Title *"
                  variant="outlined"
                  density="compact"
                  :rules="[v => !!v || 'Title is required']"
                />
                <v-select
                  v-model="form.department_id"
                  label="Department"
                  :items="departments"
                  item-title="name"
                  item-value="id"
                  variant="outlined"
                  density="compact"
                  clearable
                />
                <v-text-field
                  v-model="form.grade"
                  label="Grade / Level"
                  variant="outlined"
                  density="compact"
                  hint="Optional e.g. GL 07, Senior"
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
        <template #actions>
          <v-btn variant="outlined" :disabled="saving" @click="closeForm">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="submitForm">{{ isEditing ? 'Save Changes' : 'Create' }}</v-btn>
        </template>
      </AppModal>

      <!-- Delete Confirm -->
      <AppModal v-model="deleteDialog" title="Delete Designation" size="sm" color="error" :loading="deleting">
            Are you sure you want to delete <strong>{{ selectedItem?.name }}</strong>?
        <template #actions>
          <v-btn variant="outlined" :disabled="deleting" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" :loading="deleting" @click="deleteDesignation">Delete</v-btn>
        </template>
      </AppModal>

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
import { designationAPI, departmentAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();

const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const designations = ref([]);
const departments = ref([]);
const formDialog = ref(false);
const deleteDialog = ref(false);
const isEditing = ref(false);
const selectedItem = ref(null);
const formRef = ref(null);

const filters = ref({ search: '', department_id: null, status: null });
const pagination = ref({ current_page: 1, last_page: 1, total: 0 });

const form = ref({ name: '', department_id: null, grade: '', description: '', status: 1 });

const headers = [
  { title: 'Title', key: 'name', minWidth: 160 },
  { title: 'Department', key: 'department', minWidth: 140 },
  { title: 'Grade / Level', key: 'grade', width: 120 },
  { title: 'Description', key: 'description', minWidth: 200 },
  { title: 'Status', key: 'status', width: 100 },
  { title: 'Created', key: 'created_at', width: 120 },
  { title: 'Actions', key: 'actions', width: 100, sortable: false, align: 'end' },
];

const activeFiltersCount = computed(() =>
  [filters.value.search, filters.value.department_id, filters.value.status].filter(v => v !== null && v !== '').length
);

async function fetchDesignations() {
  loading.value = true;
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: 25,
      ...(filters.value.search && { search: filters.value.search }),
      ...(filters.value.department_id && { department_id: filters.value.department_id }),
      ...(filters.value.status !== null && { status: filters.value.status }),
    };
    const res = await designationAPI.getAll(params);
    const data = res.data?.data ?? res.data;
    designations.value = data.data ?? data ?? [];
    const m = data.meta ?? res.data?.meta;
    if (m) pagination.value = { current_page: m.current_page, last_page: m.last_page, total: m.total };
  } catch {
    showToast('Failed to load designations', 'error');
  } finally {
    loading.value = false;
  }
}

async function fetchDepartments() {
  try {
    const res = await departmentAPI.getAll({ per_page: 200 });
    const data = res.data?.data ?? res.data;
    departments.value = data.data ?? data ?? [];
  } catch { /* ignore */ }
}

function clearFilters() {
  filters.value = { search: '', department_id: null, status: null };
  pagination.value.current_page = 1;
  fetchDesignations();
}

const debounceSearch = debounce(() => {
  pagination.value.current_page = 1;
  fetchDesignations();
}, 400);

function openCreate() {
  isEditing.value = false;
  form.value = { name: '', department_id: null, grade: '', description: '', status: 1 };
  formDialog.value = true;
}

function openEdit(item) {
  isEditing.value = true;
  selectedItem.value = item;
  form.value = { name: item.name, department_id: item.department_id, grade: item.grade || '', description: item.description || '', status: item.status };
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
      await designationAPI.update(selectedItem.value.id, form.value);
      showToast('Designation updated', 'success');
    } else {
      await designationAPI.create(form.value);
      showToast('Designation created', 'success');
    }
    closeForm();
    fetchDesignations();
  } catch (err) {
    showToast(err.response?.data?.message || 'Failed to save', 'error');
  } finally {
    saving.value = false;
  }
}

function confirmDelete(item) {
  selectedItem.value = item;
  deleteDialog.value = true;
}

async function deleteDesignation() {
  deleting.value = true;
  try {
    await designationAPI.delete(selectedItem.value.id);
    showToast('Designation deleted', 'success');
    deleteDialog.value = false;
    fetchDesignations();
  } catch {
    showToast('Failed to delete', 'error');
  } finally {
    deleting.value = false;
  }
}

function formatDate(d) {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-NG', { day: '2-digit', month: 'short', year: 'numeric' });
}

onMounted(() => {
  fetchDesignations();
  fetchDepartments();
});
</script>
