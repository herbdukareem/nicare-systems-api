<template>
  <AdminLayout>
    <div class="do-facility-assignment-page">
      <v-container fluid>
        <!-- Page Header -->
        <v-row class="mb-4">
          <v-col cols="12">
            <div class="d-flex justify-space-between align-center">
              <div>
                <h1 class="text-h4 font-weight-bold">
                  <v-icon size="32" color="primary" class="mr-2">mdi-hospital-marker</v-icon>
                  Facility Assignments
                </h1>
                <p class="text-subtitle-1 text-grey mt-2">
                  Assign facilities to users for management
                </p>
              </div>
              <v-btn
                color="primary"
                size="large"
                prepend-icon="mdi-plus"
                @click="openCreateDialog"
              >
                Assign Facility
              </v-btn>
            </div>
          </v-col>
        </v-row>

        <!-- Filters -->
        <v-row class="mb-4">
          <v-col cols="12" md="6">
            <v-text-field
              v-model="searchQuery"
              label="Search by user or facility"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-magnify"
              clearable
              hide-details
            />
          </v-col>
        </v-row>

        <!-- Assignments Table -->
        <v-row>
          <v-col cols="12">
            <v-card elevation="2">
              <v-card-text>
                <v-data-table
                  :headers="headers"
                  :items="filteredAssignments"
                  :loading="loading"
                  class="elevation-0"
                  item-value="id"
                >
                  <template v-slot:item.user="{ item }">
                    <div>
                      <div class="font-weight-medium">{{ item.user?.name }}</div>
                      <div class="text-caption text-grey">{{ item.user?.username }}</div>
                    </div>
                  </template>

                  <template v-slot:item.facility="{ item }">
                    <div>
                      <div class="font-weight-medium">{{ item.facility?.name }}</div>
                      <div class="text-caption text-grey">{{ item.facility?.level_of_care }}</div>
                    </div>
                  </template>

                  <template v-slot:item.assigned_at="{ item }">
                    {{ formatDate(item.assigned_at) }}
                  </template>

                  <template v-slot:item.actions="{ item }">
                    <v-btn
                      color="error"
                      size="small"
                      variant="text"
                      @click="deleteAssignment(item)"
                      prepend-icon="mdi-delete"
                    >
                      Remove
                    </v-btn>
                  </template>

                  <template v-slot:no-data>
                    <div class="text-center py-8">
                      <v-icon size="64" color="grey-lighten-2">mdi-hospital-marker-outline</v-icon>
                      <p class="text-h6 text-grey mt-4">No assignments found</p>
                      <p class="text-body-2 text-grey">Click "Assign Facility" to create a new assignment</p>
                    </div>
                  </template>
                </v-data-table>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Create/Edit Dialog -->
        <v-dialog v-model="showDialog" max-width="600px" persistent>
          <v-card>
            <v-card-title class="bg-primary text-white">
              <v-icon start>mdi-hospital-marker</v-icon>
              Assign Facility to User
            </v-card-title>
            <v-card-text class="pt-4">
              <v-form ref="assignmentForm">
                <v-autocomplete
                  v-model="form.user_id"
                  label="User"
                  :items="deskOfficers"
                  :loading="loadingUsers"
                  item-title="name"
                  item-value="id"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-account"
                  :rules="[v => !!v || 'User is required']"
                  class="mb-4"
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>
                        {{ item.raw.name }}
                      </template>
                      <template v-slot:subtitle>
                        {{ item.raw.username }} - {{ item.raw.email }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>

                <v-autocomplete
                  v-model="form.facility_id"
                  label="Facility"
                  :items="availableFacilities"
                  item-title="name"
                  item-value="id"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-hospital-building"
                  :rules="[v => !!v || 'Facility is required']"
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>
                        {{ item.raw.name }}
                      </template>
                      <template v-slot:subtitle>
                        {{ item.raw.level_of_care }} - {{ item.raw.lga?.name }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-form>
            </v-card-text>
            <v-divider></v-divider>
            <v-card-actions class="pa-4">
              <v-spacer></v-spacer>
              <v-btn
                color="secondary"
                variant="outlined"
                @click="closeDialog"
              >
                Cancel
              </v-btn>
              <v-btn
                color="primary"
                @click="saveAssignment"
                :loading="saving"
              >
                <v-icon start>mdi-check</v-icon>
                Assign
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </v-container>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { doFacilityAPI } from '../../utils/api';
import { useToast } from '../../composables/useToast';
import AdminLayout from '../layout/AdminLayout.vue';

const { success: showSuccess, error: showError } = useToast();

// State
const loading = ref(false);
const loadingUsers = ref(false);
const saving = ref(false);
const assignments = ref([]);
const deskOfficers = ref([]);
const facilities = ref([]);
const searchQuery = ref('');
const showDialog = ref(false);
const assignmentForm = ref(null);
const form = ref({
  user_id: null,
  facility_id: null,
});

// Table headers
const headers = [
  { title: 'User', value: 'user', key: 'user' },
  { title: 'Facility', value: 'facility', key: 'facility' },
  { title: 'LGA', value: 'facility.lga.name', key: 'lga' },
  { title: 'Assigned Date', value: 'assigned_at', key: 'assigned_at' },
  { title: 'Actions', value: 'actions', key: 'actions', sortable: false },
];

// Filtered assignments
const filteredAssignments = computed(() => {
  const list = Array.isArray(assignments.value) ? assignments.value : [];
  if (!searchQuery.value) return list;

  const query = searchQuery.value.toLowerCase();
  return list.filter(assignment =>
    assignment.user?.name?.toLowerCase().includes(query) ||
    assignment.user?.username?.toLowerCase().includes(query) ||
    assignment.facility?.name?.toLowerCase().includes(query)
  );
});

// Available facilities (not yet assigned to selected user)
const availableFacilities = computed(() => {
  if (!form.value.user_id) return facilities.value;

  const assignedFacilityIds = (Array.isArray(assignments.value) ? assignments.value : [])
    .filter(a => a.user_id === form.value.user_id)
    .map(a => a.facility_id);

  return facilities.value.filter(f => !assignedFacilityIds.includes(f.id));
});

// Fetch assignments
const fetchAssignments = async () => {
  loading.value = true;
  try {
    const response = await doFacilityAPI.getAll();
    
    // Prefer paginator array at data.data, then data root; fallback to empty array
    const payload = response.data?.data?.data ?? response.data?.data ?? response.data ?? [];
    const list = Array.isArray(payload)
      ? payload
      : Array.isArray(payload?.data)
        ? payload.data
        : [];

    assignments.value = list;

    if (!Array.isArray(payload) && !Array.isArray(payload?.data)) {
      console.warn('Assignments API did not return an array; got:', payload);
    }
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch assignments');
    console.error('Fetch assignments error:', err);
  } finally {
    loading.value = false;
  }
};
// Fetch users
const fetchDeskOfficers = async () => {
  loadingUsers.value = true;
  try {
    const response = await doFacilityAPI.getDeskOfficers();
    deskOfficers.value = response.data?.data || response.data || [];
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch users');
    console.error('Fetch users error:', err);
  } 
  finally {
    loadingUsers.value = false;
  }
};

// Fetch facilities
const fetchFacilities = async () => {
  try {
    const response = await doFacilityAPI.getFacilities();
    console.log(response)
    facilities.value = response.data?.data || response.data || [];
    console.log("fac:",facilities.value)
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to fetch facilities');
    console.error('Fetch facilities error:', err);
  }
};

// Open create dialog
const openCreateDialog = async () => {
  resetForm();
  await Promise.all([fetchDeskOfficers(), fetchFacilities()]);
  showDialog.value = true;
};

// Close dialog
const closeDialog = () => {
  showDialog.value = false;
  resetForm();
};

// Reset form
const resetForm = () => {
  form.value = {
    user_id: null,
    facility_id: null,
  };
};

// Save assignment
const saveAssignment = async () => {
  const { valid } = await assignmentForm.value.validate();
  if (!valid) return;

  saving.value = true;
  try {
    await doFacilityAPI.create(form.value);
    showSuccess('Facility assigned successfully');
    closeDialog();
    await fetchAssignments();
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to assign facility');
    console.error('Save assignment error:', err);
  } finally {
    saving.value = false;
  }
};

// Delete assignment
const deleteAssignment = async (assignment) => {
  if (!confirm(`Remove ${assignment.facility?.name} from ${assignment.user?.name}?`)) return;

  try {
    await doFacilityAPI.delete(assignment.id);
    showSuccess('Assignment removed successfully');
    await fetchAssignments();
  } catch (err) {
    showError(err.response?.data?.message || 'Failed to remove assignment');
    console.error('Delete assignment error:', err);
  }
};

// Format date
const formatDate = (date) => {
  if (!date) return 'N/A';
  try {
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  } catch (e) {
    return date;
  }
};

// Lifecycle
onMounted(() => {
  fetchAssignments();
});
</script>

<style scoped>
.do-facility-assignment-page {
  padding: 20px 0;
}
</style>
