<template>
  <AdminLayout>
    <div class="tw-space-y-6">
      <!-- Page Header -->
      <div class="tw-flex tw-items-center tw-justify-between tw-animate-fade-in-up">
        <div>
          <h1 class="tw-text-3xl tw-font-bold tw-text-gray-900">Feedback Management</h1>
          <p class="tw-text-gray-600 tw-mt-1">Manage referral and PA code feedback for claims vetting</p>
        </div>
        <div class="tw-flex tw-space-x-3">
          <v-btn 
            color="primary" 
            prepend-icon="mdi-plus"
            @click="showCreateDialog = true"
            class="tw-hover-lift tw-transition-all tw-duration-300"
          >
            Create Feedback
          </v-btn>
          <v-btn 
            color="blue" 
            variant="outlined" 
            prepend-icon="mdi-account-group"
            @click="showMyFeedbacks = !showMyFeedbacks"
            class="tw-hover-lift tw-transition-all tw-duration-300"
          >
            {{ showMyFeedbacks ? 'All Feedbacks' : 'My Feedbacks' }}
          </v-btn>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-6">
        <v-card class="tw-text-center tw-p-4 tw-bg-gradient-to-br tw-from-blue-50 tw-to-blue-100">
          <v-icon color="blue" size="32" class="tw-mb-2">mdi-clipboard-list</v-icon>
          <p class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ statistics.total_feedbacks || 0 }}</p>
          <p class="tw-text-sm tw-text-gray-600">Total Feedbacks</p>
        </v-card>
        <v-card class="tw-text-center tw-p-4 tw-bg-gradient-to-br tw-from-orange-50 tw-to-orange-100">
          <v-icon color="orange" size="32" class="tw-mb-2">mdi-clock-outline</v-icon>
          <p class="tw-text-2xl tw-font-bold tw-text-orange-600">{{ statistics.pending_feedbacks || 0 }}</p>
          <p class="tw-text-sm tw-text-gray-600">Pending</p>
        </v-card>
        <v-card class="tw-text-center tw-p-4 tw-bg-gradient-to-br tw-from-blue-50 tw-to-blue-100">
          <v-icon color="blue" size="32" class="tw-mb-2">mdi-progress-clock</v-icon>
          <p class="tw-text-2xl tw-font-bold tw-text-blue-600">{{ statistics.in_progress_feedbacks || 0 }}</p>
          <p class="tw-text-sm tw-text-gray-600">In Progress</p>
        </v-card>
        <v-card class="tw-text-center tw-p-4 tw-bg-gradient-to-br tw-from-green-50 tw-to-green-100">
          <v-icon color="green" size="32" class="tw-mb-2">mdi-check-circle</v-icon>
          <p class="tw-text-2xl tw-font-bold tw-text-green-600">{{ statistics.completed_feedbacks || 0 }}</p>
          <p class="tw-text-sm tw-text-gray-600">Completed</p>
        </v-card>
      </div>

      <!-- Filters and Search -->
      <v-card>
        <v-card-text>
          <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-4 tw-gap-4">
            <v-text-field
              v-model="filters.search"
              label="Search feedbacks..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="compact"
              clearable
              @input="debouncedSearch"
            />
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Filter by Status"
              variant="outlined"
              density="compact"
              clearable
              @update:modelValue="loadFeedbacks"
            />
            <v-select
              v-model="filters.feedback_type"
              :items="typeOptions"
              label="Filter by Type"
              variant="outlined"
              density="compact"
              clearable
              @update:modelValue="loadFeedbacks"
            />
            <v-select
              v-model="filters.priority"
              :items="priorityOptions"
              label="Filter by Priority"
              variant="outlined"
              density="compact"
              clearable
              @update:modelValue="loadFeedbacks"
            />
          </div>
        </v-card-text>
      </v-card>

      <!-- Feedbacks Table -->
      <v-card>
        <v-card-title class="tw-flex tw-items-center tw-justify-between">
          <span>{{ showMyFeedbacks ? 'My Feedbacks' : 'All Feedbacks' }}</span>
          <v-btn
            icon="mdi-refresh"
            variant="text"
            @click="loadFeedbacks"
            :loading="loading"
          />
        </v-card-title>
        <v-card-text>
          <v-data-table
            :headers="headers"
            :items="feedbacks"
            :loading="loading"
            :items-per-page="15"
            :server-items-length="totalItems"
            @update:options="handleTableUpdate"
            class="tw-elevation-0"
          >
            <template v-slot:item.feedback_code="{ item }">
              <v-chip
                color="primary"
                variant="outlined"
                size="small"
                @click="viewFeedback(item)"
                class="tw-cursor-pointer"
              >
                {{ item.feedback_code }}
              </v-chip>
            </template>

            <template v-slot:item.enrollee="{ item }">
              <div>
                <p class="tw-font-medium">{{ item.enrollee?.full_name }}</p>
                <p class="tw-text-sm tw-text-gray-600">{{ item.enrollee?.nicare_number }}</p>
              </div>
            </template>

            <template v-slot:item.feedback_type="{ item }">
              <v-chip
                :color="getTypeColor(item.feedback_type)"
                size="small"
                variant="flat"
              >
                {{ formatType(item.feedback_type) }}
              </v-chip>
            </template>

            <template v-slot:item.status="{ item }">
              <v-chip
                :color="getStatusColor(item.status)"
                size="small"
                variant="flat"
              >
                {{ formatStatus(item.status) }}
              </v-chip>
            </template>

            <template v-slot:item.priority="{ item }">
              <v-chip
                :color="getPriorityColor(item.priority)"
                size="small"
                variant="flat"
              >
                {{ formatPriority(item.priority) }}
              </v-chip>
            </template>

            <template v-slot:item.feedback_officer="{ item }">
              <div v-if="item.feedback_officer">
                <p class="tw-font-medium">{{ item.feedback_officer.name }}</p>
                <p class="tw-text-sm tw-text-gray-600">{{ item.feedback_officer.email }}</p>
              </div>
              <span v-else class="tw-text-gray-400">Unassigned</span>
            </template>

            <template v-slot:item.created_at="{ item }">
              {{ formatDate(item.created_at) }}
            </template>

            <template v-slot:item.actions="{ item }">
              <div class="tw-flex tw-space-x-2">
                <v-btn
                  icon="mdi-eye"
                  size="small"
                  variant="text"
                  @click="viewFeedback(item)"
                />
                <v-btn
                  icon="mdi-pencil"
                  size="small"
                  variant="text"
                  @click="editFeedback(item)"
                />
                <v-btn
                  v-if="!item.feedback_officer_id"
                  icon="mdi-account-plus"
                  size="small"
                  variant="text"
                  color="blue"
                  @click="assignFeedback(item)"
                />
              </div>
            </template>
          </v-data-table>
        </v-card-text>
      </v-card>
    </div>

    <!-- Create Feedback Dialog -->
    <CreateFeedbackDialog
      v-model="showCreateDialog"
      @created="onFeedbackCreated"
    />

    <!-- View Feedback Dialog -->
    <ViewFeedbackDialog
      v-model="showViewDialog"
      :feedback="selectedFeedback"
    />

    <!-- Edit Feedback Dialog -->
    <EditFeedbackDialog
      v-model="showEditDialog"
      :feedback="selectedFeedback"
      @updated="onFeedbackUpdated"
    />

    <!-- Assign Feedback Dialog -->
    <AssignFeedbackDialog
      v-model="showAssignDialog"
      :feedback="selectedFeedback"
      @assigned="onFeedbackAssigned"
    />
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AdminLayout from '../layout/AdminLayout.vue';
import CreateFeedbackDialog from './components/CreateFeedbackDialog.vue';
import ViewFeedbackDialog from './components/ViewFeedbackDialog.vue';
import EditFeedbackDialog from './components/EditFeedbackDialog.vue';
import AssignFeedbackDialog from './components/AssignFeedbackDialog.vue';
import { feedbackAPI } from '../../utils/api.js';
import { useToast } from '../../composables/useToast';
import { debounce } from 'lodash-es';

const { success, error } = useToast();

// Reactive data
const feedbacks = ref([]);
const statistics = ref({});
const loading = ref(false);
const totalItems = ref(0);
const showMyFeedbacks = ref(false);

// Dialog states
const showCreateDialog = ref(false);
const showViewDialog = ref(false);
const showEditDialog = ref(false);
const showAssignDialog = ref(false);
const selectedFeedback = ref(null);

// Filters
const filters = ref({
  search: '',
  status: '',
  feedback_type: '',
  priority: ''
});

// Table configuration
const headers = [
  { title: 'Feedback Code', key: 'feedback_code', sortable: true },
  { title: 'Enrollee', key: 'enrollee', sortable: false },
  { title: 'Type', key: 'feedback_type', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Priority', key: 'priority', sortable: true },
  { title: 'Officer', key: 'feedback_officer', sortable: false },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
];

// Options
const statusOptions = [
  { title: 'Pending', value: 'pending' },
  { title: 'In Progress', value: 'in_progress' },
  { title: 'Completed', value: 'completed' },
  { title: 'Escalated', value: 'escalated' }
];

const typeOptions = [
  { title: 'Referral', value: 'referral' },
  { title: 'PA Code', value: 'pa_code' },
  { title: 'General', value: 'general' }
];

const priorityOptions = [
  { title: 'Low', value: 'low' },
  { title: 'Medium', value: 'medium' },
  { title: 'High', value: 'high' },
  { title: 'Urgent', value: 'urgent' }
];

// Methods
const loadFeedbacks = async (options = {}) => {
  try {
    loading.value = true;
    const params = {
      ...filters.value,
      ...options
    };

    const response = showMyFeedbacks.value 
      ? await feedbackAPI.getMyFeedbacks(params)
      : await feedbackAPI.getAll(params);

    if (response.data.success) {
      feedbacks.value = response.data.data.data || response.data.data || [];
      totalItems.value = response.data.data.total || feedbacks.value.length;
    }
  } catch (err) {
    console.error('Failed to load feedbacks:', err);
    error('Failed to load feedbacks');
  } finally {
    loading.value = false;
  }
};

const loadStatistics = async () => {
  try {
    const response = await feedbackAPI.getStatistics();
    if (response.data.success) {
      statistics.value = response.data.data;
    }
  } catch (err) {
    console.error('Failed to load statistics:', err);
  }
};

const debouncedSearch = debounce(() => {
  loadFeedbacks();
}, 300);

const handleTableUpdate = (options) => {
  loadFeedbacks({
    page: options.page,
    per_page: options.itemsPerPage,
    sort_by: options.sortBy?.[0]?.key,
    sort_order: options.sortBy?.[0]?.order
  });
};

const viewFeedback = (feedback) => {
  selectedFeedback.value = feedback;
  showViewDialog.value = true;
};

const editFeedback = (feedback) => {
  selectedFeedback.value = feedback;
  showEditDialog.value = true;
};

const assignFeedback = (feedback) => {
  selectedFeedback.value = feedback;
  showAssignDialog.value = true;
};

const onFeedbackCreated = () => {
  loadFeedbacks();
  loadStatistics();
  success('Feedback created successfully');
};

const onFeedbackUpdated = () => {
  loadFeedbacks();
  loadStatistics();
  success('Feedback updated successfully');
};

const onFeedbackAssigned = () => {
  loadFeedbacks();
  success('Feedback assigned successfully');
};

// Utility methods
const getStatusColor = (status) => {
  const colors = {
    pending: 'orange',
    in_progress: 'blue',
    completed: 'green',
    escalated: 'red'
  };
  return colors[status] || 'grey';
};

const getTypeColor = (type) => {
  const colors = {
    referral: 'blue',
    pa_code: 'green',
    general: 'purple'
  };
  return colors[type] || 'grey';
};

const getPriorityColor = (priority) => {
  const colors = {
    low: 'green',
    medium: 'orange',
    high: 'red',
    urgent: 'purple'
  };
  return colors[priority] || 'grey';
};

const formatStatus = (status) => {
  return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatType = (type) => {
  return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatPriority = (priority) => {
  return priority.charAt(0).toUpperCase() + priority.slice(1);
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};

// Lifecycle
onMounted(() => {
  loadFeedbacks();
  loadStatistics();
});
</script>
