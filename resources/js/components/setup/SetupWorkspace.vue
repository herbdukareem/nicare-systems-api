<template>
  <AdminLayout>
    <div class="tw-space-y-5">
      <div class="tw-flex tw-flex-col tw-gap-3 sm:tw-flex-row sm:tw-items-center sm:tw-justify-between">
        <div>
          <h1 class="tw-text-2xl tw-font-bold tw-text-slate-950">Setup</h1>
          <p class="tw-text-sm tw-text-slate-500">Manage locations, facilities, funding, benefit packages, and sponsors.</p>
        </div>
        <v-btn v-if="hasVisibleSections && canCreateCurrent" color="primary" prepend-icon="mdi-plus" @click="openCreate">New {{ current.singular }}</v-btn>
      </div>

      <div v-if="hasVisibleSections" class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white">
        <v-tabs v-model="activeKey" color="primary" density="comfortable">
          <v-tab v-for="item in visibleSections" :key="item.key" :value="item.key">
            <v-icon start size="18">{{ item.icon }}</v-icon>{{ item.label }}
          </v-tab>
        </v-tabs>
      </div>

      <v-card v-if="!hasVisibleSections" class="tw-border tw-border-slate-200 tw-p-6 tw-text-center" elevation="0">
        <p class="tw-text-base tw-font-semibold tw-text-slate-900">No setup sections available</p>
        <p class="tw-mt-2 tw-text-sm tw-text-slate-500">Your account does not currently have permission to view any setup tab.</p>
      </v-card>

      <div v-else class="tw-rounded-lg tw-border tw-border-slate-200 tw-bg-white tw-p-4">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-4">
          <v-text-field v-model="filters.search" label="Search" prepend-inner-icon="mdi-magnify" density="compact" variant="outlined" clearable @keyup.enter="loadItems" />
          <v-select v-if="activeKey === 'wards'" v-model="filters.lga_id" :items="lookups.lgas" item-title="name" item-value="id" label="LGA" density="compact" variant="outlined" clearable />
          <v-select v-if="activeKey === 'facilities'" v-model="filters.ownership" :items="ownershipOptions" label="Ownership" density="compact" variant="outlined" clearable />
          <v-select v-if="activeKey === 'facilities'" v-model="filters.accreditation_status" :items="accreditationOptions" label="Accreditation" density="compact" variant="outlined" clearable />
          <v-select v-if="activeKey === 'benefactors'" v-model="filters.type" :items="benefactorTypes" label="Type" density="compact" variant="outlined" clearable />
          <v-select v-model="filters.status" :items="statusOptions" item-title="title" item-value="value" label="Status" density="compact" variant="outlined" clearable />
          <div class="tw-flex tw-gap-2">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-filter" @click="loadItems">Apply</v-btn>
            <v-btn variant="text" @click="clearFilters">Clear</v-btn>
          </div>
        </div>
      </div>

      <v-card v-if="hasVisibleSections" class="tw-border tw-border-slate-200" elevation="0">
        <AppDataTable
          :headers="currentHeaders"
          :items="items"
          :loading="loading"
          :items-length="meta.total"
          v-model:page="page"
          v-model:items-per-page="perPage"
          item-value="id"
          hover
        >
          <template #item.status="{ item }">
            <v-chip size="small" :color="Number(item.status) === 1 ? 'success' : 'grey'" variant="flat">
              {{ Number(item.status) === 1 ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>
          <template #item.lga="{ item }">{{ item.lga?.name || 'N/A' }}</template>
          <template #item.ward="{ item }">{{ item.ward?.name || 'N/A' }}</template>
          <template #item.actions="{ item }">
            <div class="tw-flex tw-justify-end tw-gap-1">
              <v-btn v-if="canUpdateCurrent" icon size="small" variant="text" @click="openEdit(item)"><v-icon size="18">mdi-pencil</v-icon></v-btn>
              <v-btn v-if="canDeleteCurrent" icon size="small" variant="text" color="error" @click="removeItem(item)"><v-icon size="18">mdi-delete-outline</v-icon></v-btn>
            </div>
          </template>
        </AppDataTable>
      </v-card>

      <AppModal v-if="hasVisibleSections && current" v-model="dialog" :title="`${editingId ? 'Edit' : 'Create'} ${current.singular}`" size="md" :loading="saving">
        <div class="tw-grid tw-gap-3 md:tw-grid-cols-2">
          <component
            v-for="field in current.fields"
            :key="field.key"
            :is="field.type === 'textarea' ? 'v-textarea' : field.type === 'select' ? 'v-select' : 'v-text-field'"
            v-model="form[field.key]"
            :items="field.items ? field.items() : undefined"
            :item-title="field.itemTitle || 'title'"
            :item-value="field.itemValue || 'value'"
            :label="field.label"
            :type="field.inputType || 'text'"
            :density="'compact'"
            :variant="'outlined'"
            :clearable="field.clearable !== false"
            :rows="field.type === 'textarea' ? 3 : undefined"
          />
        </div>
        <template #actions>
          <v-btn variant="outlined" @click="dialog = false">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="saveItem">{{ editingId ? 'Save Changes' : 'Create' }}</v-btn>
        </template>
      </AppModal>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import AdminLayout from '../layout/AdminLayout.vue';
import AppModal from '../common/AppModal.vue';
import AppDataTable from '../common/AppDataTable.vue';
import { useToast } from '../../composables/useToast';
import { benefactorAPI, benefitPackageAPI, facilityAPI, fundingTypeAPI, lgaAPI, wardAPI } from '../../utils/api';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { success, error } = useToast();

const statusOptions = [{ title: 'Active', value: 1 }, { title: 'Inactive', value: 0 }];
const optionObjects = (values) => values.map((value) => ({ title: value, value }));
const ownershipOptions = optionObjects(['Public', 'Private', 'Faith-Based']);
const facilityTypes = optionObjects(['Primary', 'Secondary', 'Tertiary']);
const accreditationOptions = optionObjects(['active', 'suspended', 'revoked']);
const benefactorTypes = optionObjects(['individual', 'employer', 'government', 'donor', 'institution', 'association', 'ngo', 'group', 'philanthropist']);

const lookups = reactive({ lgas: [], wards: [] });
const filters = reactive({ search: '', status: null, lga_id: null, ownership: null, accreditation_status: null, type: null });
const items = ref([]);
const meta = reactive({ total: 0 });
const page = ref(1);
const perPage = ref(25);
const loading = ref(false);
const saving = ref(false);
const dialog = ref(false);
const editingId = ref(null);
const form = reactive({});

const apiData = (response) => response?.data?.data?.data || response?.data?.data || [];
const apiMeta = (response) => response?.data?.data?.meta || response?.data?.meta || {};

const sections = [
  {
    key: 'locations',
    label: 'LGAs',
    singular: 'LGA',
    icon: 'mdi-map-outline',
    api: lgaAPI,
    permissions: {
      view: ['setup.lga.view'],
      create: ['setup.lga.create'],
      update: ['setup.lga.update'],
      delete: ['setup.lga.delete'],
    },
    headers: [
      { title: 'Name', key: 'name' },
      { title: 'Code', key: 'code' },
      { title: 'Zone', key: 'zone' },
      { title: 'Wards', key: 'wards_count' },
      { title: 'Status', key: 'status' },
      { title: '', key: 'actions', align: 'end', sortable: false },
    ],
    fields: [
      { key: 'name', label: 'Name' },
      { key: 'code', label: 'Code' },
      { key: 'zone', label: 'Zone', inputType: 'number' },
      { key: 'status', label: 'Status', type: 'select', items: () => statusOptions },
    ],
  },
  {
    key: 'wards',
    label: 'Wards',
    singular: 'Ward',
    icon: 'mdi-map-marker-outline',
    api: wardAPI,
    permissions: {
      view: ['setup.ward.view'],
      create: ['setup.ward.create'],
      update: ['setup.ward.update'],
      delete: ['setup.ward.delete'],
    },
    headers: [
      { title: 'Name', key: 'name' },
      { title: 'LGA', key: 'lga' },
      { title: 'Settlement', key: 'settlement_type' },
      { title: 'Facilities', key: 'facilities_count' },
      { title: 'Status', key: 'status' },
      { title: '', key: 'actions', align: 'end', sortable: false },
    ],
    fields: [
      { key: 'name', label: 'Name' },
      { key: 'lga_id', label: 'LGA', type: 'select', items: () => lookups.lgas, itemTitle: 'name', itemValue: 'id' },
      { key: 'settlement_type', label: 'Settlement Type', type: 'select', items: () => [{ title: 'Urban', value: 1 }, { title: 'Rural', value: 2 }] },
      { key: 'status', label: 'Status', type: 'select', items: () => statusOptions },
    ],
  },
  {
    key: 'facilities',
    label: 'Facilities',
    singular: 'Facility',
    icon: 'mdi-hospital-building',
    api: facilityAPI,
    permissions: {
      view: ['setup.facility.view'],
      create: ['setup.facility.create'],
      update: ['setup.facility.update'],
      delete: ['setup.facility.delete'],
    },
    headers: [
      { title: 'HCP Code', key: 'hcp_code' },
      { title: 'Name', key: 'name' },
      { title: 'Ownership', key: 'ownership' },
      { title: 'Type', key: 'type' },
      { title: 'LGA', key: 'lga' },
      { title: 'Ward', key: 'ward' },
      { title: 'Accreditation', key: 'accreditation_status' },
      { title: 'Status', key: 'status' },
      { title: '', key: 'actions', align: 'end', sortable: false },
    ],
    fields: [
      { key: 'hcp_code', label: 'HCP Code' },
      { key: 'name', label: 'Name' },
      { key: 'ownership', label: 'Ownership', type: 'select', items: () => ownershipOptions },
      { key: 'type', label: 'Type', type: 'select', items: () => facilityTypes },
      { key: 'lga_id', label: 'LGA', type: 'select', items: () => lookups.lgas, itemTitle: 'name', itemValue: 'id' },
      { key: 'ward_id', label: 'Ward', type: 'select', items: () => lookups.wards, itemTitle: 'name', itemValue: 'id' },
      { key: 'capacity', label: 'Capacity', inputType: 'number' },
      { key: 'phone', label: 'Phone' },
      { key: 'email', label: 'Email', inputType: 'email' },
      { key: 'address', label: 'Address', type: 'textarea' },
      { key: 'accreditation_status', label: 'Accreditation', type: 'select', items: () => accreditationOptions },
      { key: 'status', label: 'Status', type: 'select', items: () => statusOptions },
    ],
  },
  {
    key: 'benefit-packages',
    label: 'Benefit Packages',
    singular: 'Benefit Package',
    icon: 'mdi-package-variant',
    api: benefitPackageAPI,
    permissions: {
      view: ['setup.benefit-package.view'],
      create: ['setup.benefit-package.create'],
      update: ['setup.benefit-package.update'],
      delete: ['setup.benefit-package.delete'],
    },
    headers: [
      { title: 'Name', key: 'name' },
      { title: 'Code', key: 'code' },
      { title: 'Plans', key: 'premium_plans_count' },
      { title: 'Status', key: 'status' },
      { title: '', key: 'actions', align: 'end', sortable: false },
    ],
    fields: [
      { key: 'name', label: 'Name' },
      { key: 'code', label: 'Code' },
      { key: 'description', label: 'Description', type: 'textarea' },
      { key: 'status', label: 'Status', type: 'select', items: () => statusOptions },
    ],
  },
  {
    key: 'funding-types',
    label: 'Funding Types',
    singular: 'Funding Type',
    icon: 'mdi-cash-multiple',
    api: fundingTypeAPI,
    permissions: {
      view: ['setup.funding-type.view'],
      create: ['setup.funding-type.create'],
      update: ['setup.funding-type.update'],
      delete: ['setup.funding-type.delete'],
    },
    headers: [
      { title: 'Name', key: 'name' },
      { title: 'Description', key: 'description' },
      { title: 'Capitation Rate', key: 'capitation_rate' },
      { title: 'Enrollees', key: 'enrollees_count' },
      { title: 'Status', key: 'status' },
      { title: '', key: 'actions', align: 'end', sortable: false },
    ],
    fields: [
      { key: 'name', label: 'Name' },
      { key: 'description', label: 'Description', type: 'textarea' },
      { key: 'capitation_rate', label: 'Capitation Rate', inputType: 'number' },
      { key: 'status', label: 'Status', type: 'select', items: () => statusOptions },
    ],
  },
  {
    key: 'benefactors',
    label: 'Benefactors',
    singular: 'Benefactor',
    icon: 'mdi-account-heart-outline',
    api: benefactorAPI,
    permissions: {
      view: ['setup.benefactor.view'],
      create: ['setup.benefactor.create'],
      update: ['setup.benefactor.update'],
      delete: ['setup.benefactor.delete'],
    },
    headers: [
      { title: 'Name', key: 'name' },
      { title: 'Type', key: 'type' },
      { title: 'Contact', key: 'contact_person' },
      { title: 'Phone', key: 'phone' },
      { title: 'Enrollees', key: 'enrollees_count' },
      { title: 'Status', key: 'status' },
      { title: '', key: 'actions', align: 'end', sortable: false },
    ],
    fields: [
      { key: 'name', label: 'Name' },
      { key: 'type', label: 'Type', type: 'select', items: () => benefactorTypes },
      { key: 'registration_number', label: 'Registration Number' },
      { key: 'contact_person', label: 'Contact Person' },
      { key: 'email', label: 'Email', inputType: 'email' },
      { key: 'phone', label: 'Phone' },
      { key: 'address', label: 'Address', type: 'textarea' },
      { key: 'status', label: 'Status', type: 'select', items: () => statusOptions },
    ],
  },
];

const hasAnyPermission = (permissions = []) => permissions.some((permission) => auth.hasPermission(permission));
const visibleSections = computed(() => sections.filter((item) => hasAnyPermission(item.permissions?.view || [])));
const hasVisibleSections = computed(() => visibleSections.value.length > 0);
const defaultSectionKey = computed(() => visibleSections.value[0]?.key || null);
const current = computed(() => visibleSections.value.find((item) => item.key === activeKey.value) || visibleSections.value[0] || null);
const canCreateCurrent = computed(() => hasAnyPermission(current.value?.permissions?.create || []));
const canUpdateCurrent = computed(() => hasAnyPermission(current.value?.permissions?.update || []));
const canDeleteCurrent = computed(() => hasAnyPermission(current.value?.permissions?.delete || []));
const needsLgaLookups = computed(() => hasAnyPermission([
  'setup.lga.view',
  'setup.ward.view',
  'setup.ward.create',
  'setup.ward.update',
  'setup.facility.view',
  'setup.facility.create',
  'setup.facility.update',
]));
const needsWardLookups = computed(() => hasAnyPermission([
  'setup.facility.view',
  'setup.facility.create',
  'setup.facility.update',
]));
const currentHeaders = computed(() => {
  const headers = current.value?.headers || [];
  if (canUpdateCurrent.value || canDeleteCurrent.value) {
    return headers;
  }
  return headers.filter((header) => header.key !== 'actions');
});

const resolveSectionKey = () => {
  const requested = visibleSections.value.find((item) => item.key === route.params.section)?.key;
  return requested || defaultSectionKey.value;
};

const activeKey = ref(resolveSectionKey());

const syncAccessibleSection = () => {
  if (!hasVisibleSections.value) {
    activeKey.value = null;
    return;
  }

  const nextKey = resolveSectionKey();
  if (nextKey && nextKey !== activeKey.value) {
    activeKey.value = nextKey;
    return;
  }

  if (route.params.section !== activeKey.value) {
    router.replace(`/setup/${activeKey.value}`);
  }
};

const loadLookups = async () => {
  const tasks = [];

  if (needsLgaLookups.value) {
    tasks.push(
      lgaAPI.getAll({ per_page: 500 }).then((response) => {
        lookups.lgas = apiData(response);
      })
    );
  } else {
    lookups.lgas = [];
  }

  if (needsWardLookups.value) {
    tasks.push(
      wardAPI.getAll({ per_page: 1000 }).then((response) => {
        lookups.wards = apiData(response);
      })
    );
  } else {
    lookups.wards = [];
  }

  await Promise.all(tasks);
};

const loadItems = async () => {
  if (!hasVisibleSections.value || !current.value) {
    items.value = [];
    meta.total = 0;
    return;
  }

  loading.value = true;
  try {
    const params = { ...filters, page: page.value, per_page: perPage.value };
    Object.keys(params).forEach((key) => (params[key] === '' || params[key] === null) && delete params[key]);
    const response = await current.value.api.getAll(params);
    items.value = apiData(response);
    meta.total = apiMeta(response).total || items.value.length;
  } catch (e) {
    error(e.response?.data?.message || 'Failed to load setup records');
  } finally {
    loading.value = false;
  }
};

const clearFilters = () => {
  Object.assign(filters, { search: '', status: null, lga_id: null, ownership: null, accreditation_status: null, type: null });
  loadItems();
};

const resetForm = () => {
  Object.keys(form).forEach((key) => delete form[key]);
  current.value.fields.forEach((field) => {
    form[field.key] = field.key === 'status' ? 1 : field.key === 'accreditation_status' ? 'active' : null;
  });
};

const openCreate = () => {
  if (!canCreateCurrent.value) {
    error(`You do not have permission to create ${current.value.singular.toLowerCase()} records.`);
    return;
  }
  editingId.value = null;
  resetForm();
  dialog.value = true;
};

const openEdit = (item) => {
  if (!canUpdateCurrent.value) {
    error(`You do not have permission to edit ${current.value.singular.toLowerCase()} records.`);
    return;
  }
  editingId.value = item.id;
  resetForm();
  current.value.fields.forEach((field) => {
    form[field.key] = item[field.key] ?? (field.key === 'lga_id' ? item.lga?.id : field.key === 'ward_id' ? item.ward?.id : null);
  });
  dialog.value = true;
};

const saveItem = async () => {
  if ((editingId.value && !canUpdateCurrent.value) || (!editingId.value && !canCreateCurrent.value)) {
    error(`You do not have permission to save ${current.value.singular.toLowerCase()} records.`);
    return;
  }

  saving.value = true;
  try {
    const payload = { ...form };
    Object.keys(payload).forEach((key) => payload[key] === undefined && delete payload[key]);
    if (editingId.value) {
      await current.value.api.update(editingId.value, payload);
      success(`${current.value.singular} updated`);
    } else {
      await current.value.api.create(payload);
      success(`${current.value.singular} created`);
    }
    dialog.value = false;
    await loadItems();
    await loadLookups();
  } catch (e) {
    error(e.response?.data?.message || `Could not save ${current.value.singular.toLowerCase()}`);
  } finally {
    saving.value = false;
  }
};

const removeItem = async (item) => {
  if (!canDeleteCurrent.value) {
    error(`You do not have permission to delete ${current.value.singular.toLowerCase()} records.`);
    return;
  }
  if (!window.confirm(`Delete ${item.name || item.code}? Records in use will be deactivated instead.`)) return;
  try {
    await current.value.api.delete(item.id);
    success(`${current.value.singular} removed`);
    await loadItems();
  } catch (e) {
    error(e.response?.data?.message || `Could not delete ${current.value.singular.toLowerCase()}`);
  }
};

watch(activeKey, async (key) => {
  if (!key) return;
  if (route.params.section !== key) router.replace(`/setup/${key}`);
  page.value = 1;
  await loadItems();
});
watch([page, perPage], loadItems);
watch(() => route.params.section, (value) => {
  const key = visibleSections.value.find((item) => item.key === value)?.key;
  if (key && key !== activeKey.value) {
    activeKey.value = key;
    return;
  }

  if (!key) {
    syncAccessibleSection();
  }
});
watch(visibleSections, syncAccessibleSection, { immediate: true });

onMounted(async () => {
  syncAccessibleSection();
  if (!hasVisibleSections.value) {
    return;
  }
  await loadLookups();
  await loadItems();
});
</script>
