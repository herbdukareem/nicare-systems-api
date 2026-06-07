<template>
  <AdminLayout>
    <div class="tw-space-y-4">
      <AppPageHeader title="Roles & Permissions" icon="mdi-shield-account-outline">
        <v-btn size="small" variant="outlined" prepend-icon="mdi-view-grid-outline" @click="showPermissionMatrix = !showPermissionMatrix">
          {{ showPermissionMatrix ? 'Hide Matrix' : 'Matrix' }}
        </v-btn>
        <v-btn size="small" color="primary" prepend-icon="mdi-plus" @click="openCreateDialog">Create Role</v-btn>
      </AppPageHeader>

      <div class="tw-grid tw-gap-2 tw-grid-cols-2 md:tw-grid-cols-4">
        <AppStatCard compact label="Total Roles" :value="totalRoles" icon="mdi-shield-account-outline" color="primary" :loading="loading" />
        <AppStatCard compact label="Permissions" :value="allPermissions.length" icon="mdi-key-outline" color="success" :loading="loading" />
        <AppStatCard compact label="Users Assigned" :value="totalUsersWithRoles" icon="mdi-account-group-outline" color="info" :loading="loading" />
        <AppStatCard compact label="Permission Categories" :value="permissionCategories.length" icon="mdi-shape-outline" color="warning" :loading="loading" />
      </div>

      <AppFilterBar :active-count="selectedRoles.length > 0 ? 1 : 0" :cols="3">
        <v-text-field
          v-model="searchQuery"
          label="Search roles"
          prepend-inner-icon="mdi-magnify"
          variant="outlined"
          density="compact"
          clearable
          hide-details
        />
        <v-btn
          size="small"
          color="error"
          variant="outlined"
          prepend-icon="mdi-delete-outline"
          :disabled="!selectedRoles.length"
          @click="openBulkDeleteDialog"
        >
          Delete Selected
        </v-btn>

        <template #tags>
          <AppBadge v-if="selectedRoles.length" :label="`${selectedRoles.length} role(s) selected`" tone="warning" size="sm" />
        </template>
        <template #actions>
          <v-btn size="small" variant="outlined" prepend-icon="mdi-refresh" :loading="loading" @click="loadRoles">Refresh</v-btn>
        </template>
      </AppFilterBar>

      <AppCard
        title="Roles"
        icon="mdi-format-list-bulleted-square"
        tone="primary"
        :padded="false"
      >
        <AppDataTable
          :headers="roleHeaders"
          :items="roles"
          :items-length="totalRoles"
          :page="currentPage"
          :items-per-page="itemsPerPage"
          :loading="loading"
          item-value="id"
          show-select
          v-model:model-value="selectedRoles"
          class="tw-rounded-none tw-border-0"
          @update:page="onUpdatePage"
          @update:items-per-page="onUpdatePerPage"
          @update:sort-by="onUpdateSort"
        >
          <template #item.name="{ item }">
            <div class="tw-space-y-1">
              <div class="tw-font-semibold tw-text-slate-900">{{ item.label || item.name }}</div>
              <div class="tw-text-xs tw-text-slate-500">{{ item.name }}</div>
            </div>
          </template>
          <template #item.description="{ item }">
            <span class="tw-text-sm tw-text-slate-600">{{ item.description || 'No description provided' }}</span>
          </template>
          <template #item.permissions_count="{ item }">
            <AppBadge :label="`${countRolePermissions(item)} permissions`" tone="primary" size="sm" />
          </template>
          <template #item.users_count="{ item }">
            <AppBadge :label="`${item.users_count || 0} users`" tone="secondary" size="sm" />
          </template>
          <template #item.created_at="{ item }">
            <DateDisplay :value="item.created_at" format="short" />
          </template>
          <template #item.actions="{ item }">
            <div class="tw-flex tw-items-center tw-justify-end tw-gap-1">
              <v-btn icon size="small" variant="text" title="View" @click="viewRole(item)">
                <v-icon size="18">mdi-eye-outline</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" title="Edit" @click="editRole(item)">
                <v-icon size="18">mdi-pencil-outline</v-icon>
              </v-btn>
              <v-btn icon size="small" variant="text" title="Clone" @click="cloneRole(item)">
                <v-icon size="18">mdi-content-copy</v-icon>
              </v-btn>
              <v-btn
                icon
                size="small"
                variant="text"
                color="error"
                :disabled="isProtectedRole(item)"
                title="Delete"
                @click="openDeleteDialog(item)"
              >
                <v-icon size="18">mdi-delete-outline</v-icon>
              </v-btn>
            </div>
          </template>
          <template #no-data>
            <AppEmptyState
              title="No roles found"
              description="Try a different search term or create a new role to get started."
              icon="mdi-shield-lock-outline"
            />
          </template>
        </AppDataTable>
      </AppCard>

      <AppCard
        v-if="showPermissionMatrix"
        title="Permission Matrix"
        icon="mdi-table-large"
        tone="secondary"
      >
        <div v-if="roles.length === 0 || allPermissions.length === 0">
          <AppEmptyState
            title="Permission matrix unavailable"
            description="Load roles and permissions first to inspect the matrix."
            icon="mdi-view-grid-outline"
          />
        </div>
        <div v-else class="tw-overflow-x-auto">
          <table class="tw-w-full tw-min-w-[960px] tw-text-sm">
            <thead>
              <tr class="tw-border-b tw-border-slate-200">
                <th class="tw-px-4 tw-py-3 tw-text-left tw-font-semibold tw-text-slate-900">Permission</th>
                <th
                  v-for="role in roles"
                  :key="role.id"
                  class="tw-min-w-[120px] tw-px-3 tw-py-3 tw-text-center tw-font-semibold tw-text-slate-900"
                >
                  {{ role.label || role.name }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="permission in allPermissions"
                :key="permission.id"
                class="tw-border-b tw-border-slate-100 hover:tw-bg-slate-50/70"
              >
                <td class="tw-px-4 tw-py-3">
                  <div class="tw-space-y-1">
                    <p class="tw-font-medium tw-text-slate-900">{{ permission.label || permission.name }}</p>
                    <p class="tw-text-xs tw-text-slate-500">{{ permission.category || 'General' }}</p>
                  </div>
                </td>
                <td
                  v-for="role in roles"
                  :key="`${permission.id}-${role.id}`"
                  class="tw-px-3 tw-py-3 tw-text-center"
                >
                  <v-icon :color="hasPermission(role, permission) ? 'success' : 'grey-lighten-1'" size="18">
                    {{ hasPermission(role, permission) ? 'mdi-check-circle' : 'mdi-circle-outline' }}
                  </v-icon>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>

      <AppModal
        v-model="showCreateRoleDialog"
        :title="editingRole ? 'Edit Role' : 'Create Role'"
        size="lg"
        icon="mdi-shield-edit-outline"
      >
        <template #actions>
          <v-btn variant="outlined" :disabled="saving" @click="closeRoleDialog">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="saveRole">
            Save Role
          </v-btn>
        </template>

        <div class="tw-space-y-5">
          <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
            <v-text-field v-model="roleForm.name" label="Role Name" variant="outlined" />
            <v-select
              v-model="roleForm.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
            />
          </div>
          <v-textarea v-model="roleForm.description" label="Description" variant="outlined" rows="3" />

          <AppAlert
            tone="info"
            message="Users assigned to this role inherit all selected permissions after the backend sync completes."
          />

          <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3">
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <AppBadge :label="`${roleForm.permissions.length} selected`" tone="primary" />
              <AppBadge :label="`${permissionCategories.length} categories`" tone="secondary" />
            </div>
            <div class="tw-flex tw-gap-2">
              <v-btn size="small" variant="outlined" prepend-icon="mdi-checkbox-multiple-marked-outline" @click="selectAllPermissions">
                Select All
              </v-btn>
              <v-btn size="small" variant="outlined" color="error" prepend-icon="mdi-checkbox-multiple-blank-outline" @click="clearAllPermissions">
                Clear All
              </v-btn>
            </div>
          </div>

          <div class="tw-grid tw-gap-4 md:tw-grid-cols-2">
            <AppCard
              v-for="category in permissionCategories"
              :key="category.name"
              :title="category.name"
              icon="mdi-folder-key-outline"
              tone="secondary"
            >
              <template #actions>
                <v-btn size="x-small" variant="text" prepend-icon="mdi-check-all" @click="toggleCategory(category)">
                  Toggle
                </v-btn>
              </template>

              <div class="tw-space-y-2">
                <v-checkbox
                  v-for="permission in category.permissions"
                  :key="permission.id"
                  v-model="roleForm.permissions"
                  :value="permission.id"
                  :label="permission.label || permission.name"
                  density="compact"
                  hide-details
                />
              </div>
            </AppCard>
          </div>
        </div>
      </AppModal>

      <AppModal
        v-model="showViewRoleDialog"
        :title="viewingRole?.label || viewingRole?.name || 'Role Details'"
        :subtitle="viewingRole?.description || 'Role details'"
        icon="mdi-eye-outline"
        size="md"
      >
        <template #actions>
          <v-btn variant="outlined" @click="showViewRoleDialog = false">Close</v-btn>
          <v-btn color="primary" variant="flat" @click="viewingRole && editRole(viewingRole)">
            Edit Role
          </v-btn>
        </template>

        <div v-if="viewingRole" class="tw-space-y-4">
          <div class="tw-flex tw-flex-wrap tw-gap-2">
            <AppBadge :label="viewingRole.status || 'active'" :tone="viewingRole.status === 'inactive' ? 'danger' : 'success'" />
            <AppBadge :label="`${countRolePermissions(viewingRole)} permissions`" tone="primary" />
            <AppBadge :label="`${viewingRole.users_count || 0} users`" tone="secondary" />
          </div>

          <AppCard title="Permissions" icon="mdi-key-outline" tone="secondary">
            <div class="tw-flex tw-flex-wrap tw-gap-2">
              <AppBadge
                v-for="permissionId in normalizePermissionIds(viewingRole)"
                :key="permissionId"
                :label="getPermissionName(permissionId)"
                tone="primary"
                size="sm"
              />
            </div>
          </AppCard>
        </div>
      </AppModal>

      <AppConfirmDialog
        v-model="deleteDialog"
        title="Delete role"
        subtitle="This will remove the selected role after backend confirmation."
        :message="deleteDialogMessage"
        warning="Protected roles cannot be deleted from this screen."
        confirm-text="Delete role"
        icon="mdi-delete-alert-outline"
        tone="danger"
        :loading="deleting"
        @cancel="closeDeleteDialog"
        @confirm="confirmDelete"
        @update:model-value="handleDeleteDialogChange"
      />

      <AppConfirmDialog
        v-model="bulkDeleteDialog"
        title="Delete selected roles"
        subtitle="This will call the backend bulk delete endpoint."
        :message="`Delete ${selectedRoles.length} selected role(s)?`"
        warning="Only non-protected roles will be sent for deletion."
        confirm-text="Delete selected roles"
        icon="mdi-delete-sweep-outline"
        tone="danger"
        :loading="deleting"
        @cancel="closeBulkDeleteDialog"
        @confirm="confirmBulkDelete"
        @update:model-value="handleBulkDeleteDialogChange"
      />
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import AdminLayout from '../layout/AdminLayout.vue'
import AppAlert from '../common/AppAlert.vue'
import AppBadge from '../common/AppBadge.vue'
import AppCard from '../common/AppCard.vue'
import AppConfirmDialog from '../common/AppConfirmDialog.vue'
import AppDataTable from '../common/AppDataTable.vue'
import AppEmptyState from '../common/AppEmptyState.vue'
import AppFilterBar from '../common/AppFilterBar.vue'
import AppModal from '../common/AppModal.vue'
import AppStatCard from '../common/AppStatCard.vue'
import AppPageHeader from '../common/AppPageHeader.vue'
import DateDisplay from '../common/DateDisplay.vue'
import { useToast } from '../../composables/useToast'
import { permissionAPI, roleAPI } from '../../utils/api'

const { success, error } = useToast()

const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const searchQuery = ref('')
const debouncer = ref(null)

const showCreateRoleDialog = ref(false)
const showViewRoleDialog = ref(false)
const showPermissionMatrix = ref(false)
const deleteDialog = ref(false)
const bulkDeleteDialog = ref(false)

const editingRole = ref(null)
const viewingRole = ref(null)
const deleteTarget = ref(null)
const selectedRoles = ref([])

const roles = ref([])
const totalRoles = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(15)
const sortBy = ref([{ key: 'created_at', order: 'desc' }])
const allPermissions = ref([])

const statusOptions = ['active', 'inactive']

const roleForm = ref({
  name: '',
  description: '',
  status: 'active',
  permissions: [],
})

const roleHeaders = [
  { title: 'Role', key: 'name', sortable: true },
  { title: 'Description', key: 'description', sortable: false },
  { title: 'Permissions', key: 'permissions_count', sortable: false },
  { title: 'Users', key: 'users_count', sortable: true },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', align: 'end', sortable: false },
]

const permissionCategories = computed(() => {
  const groups = new Map()
  allPermissions.value.forEach((permission) => {
    const category = permission.category || 'General'
    if (!groups.has(category)) groups.set(category, [])
    groups.get(category).push(permission)
  })
  return Array.from(groups.entries()).map(([name, permissions]) => ({ name, permissions }))
})

const totalUsersWithRoles = computed(() => roles.value.reduce((total, role) => total + Number(role.users_count || 0), 0))
const deleteDialogMessage = computed(() => deleteTarget.value ? `Delete role "${deleteTarget.value.label || deleteTarget.value.name}"?` : 'Delete the selected role?')

const normalizePermissionIds = (role) => {
  if (!role?.permissions) return []
  return role.permissions.map((permission) => (typeof permission === 'object' ? permission.id : permission)).filter(Boolean)
}

const countRolePermissions = (role) => normalizePermissionIds(role).length
const getPermissionName = (permissionId) => allPermissions.value.find((permission) => permission.id === permissionId)?.label
  || allPermissions.value.find((permission) => permission.id === permissionId)?.name
  || `#${permissionId}`

const hasPermission = (role, permission) => normalizePermissionIds(role).includes(permission.id)
const isProtectedRole = (role) => String(role?.name || '').toLowerCase() === 'super admin'

const extractCollection = (response) => {
  const data = response?.data?.data ?? response?.data ?? []
  if (Array.isArray(data)) return { items: data, total: data.length }
  if (Array.isArray(data?.data)) return { items: data.data, total: data.meta?.total ?? data.total ?? data.data.length }
  return { items: [], total: 0 }
}

const openCreateDialog = () => {
  editingRole.value = null
  roleForm.value = { name: '', description: '', status: 'active', permissions: [] }
  showCreateRoleDialog.value = true
}

const closeRoleDialog = () => {
  showCreateRoleDialog.value = false
  editingRole.value = null
  roleForm.value = { name: '', description: '', status: 'active', permissions: [] }
}

const viewRole = (role) => {
  viewingRole.value = role
  showViewRoleDialog.value = true
}

const editRole = (role) => {
  editingRole.value = role
  roleForm.value = {
    name: role.name || '',
    description: role.description || '',
    status: role.status || 'active',
    permissions: [...normalizePermissionIds(role)],
  }
  showViewRoleDialog.value = false
  showCreateRoleDialog.value = true
}

const syncPermissionsForRole = async (roleId) => {
  await roleAPI.syncPermissions(roleId, roleForm.value.permissions)
}

const saveRole = async () => {
  if (!roleForm.value.name.trim()) {
    error('Role name is required')
    return
  }

  saving.value = true
  try {
    const payload = {
      name: roleForm.value.name.trim(),
      label: roleForm.value.name.trim(),
      description: roleForm.value.description,
      status: roleForm.value.status,
    }

    let response
    if (editingRole.value) {
      response = await roleAPI.update(editingRole.value.id, payload)
      await syncPermissionsForRole(editingRole.value.id)
      success('Role updated successfully')
    } else {
      response = await roleAPI.create(payload)
      const roleId = response?.data?.data?.id ?? response?.data?.id
      if (roleId) {
        await syncPermissionsForRole(roleId)
      }
      success('Role created successfully')
    }

    closeRoleDialog()
    await loadRoles()
    if (response?.data?.data?.id) {
      viewingRole.value = roles.value.find((role) => role.id === response.data.data.id) || null
    }
  } catch (err) {
    error(err.response?.data?.message || 'Failed to save role')
  } finally {
    saving.value = false
  }
}

const cloneRole = async (role) => {
  try {
    await roleAPI.clone(role.id, {
      name: `${role.name}_copy_${Date.now()}`,
      label: `${role.label || role.name} Copy`,
    })
    success(`Cloned ${role.label || role.name}`)
    await loadRoles()
  } catch (err) {
    error(err.response?.data?.message || 'Failed to clone role')
  }
}

const openDeleteDialog = (role) => {
  if (isProtectedRole(role)) return
  deleteTarget.value = role
  deleteDialog.value = true
}

const closeDeleteDialog = () => {
  deleteDialog.value = false
  deleteTarget.value = null
}

const handleDeleteDialogChange = (value) => {
  deleteDialog.value = value
  if (!value) deleteTarget.value = null
}

const confirmDelete = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await roleAPI.delete(deleteTarget.value.id)
    success('Role deleted successfully')
    closeDeleteDialog()
    selectedRoles.value = selectedRoles.value.filter((id) => id !== deleteTarget.value?.id)
    await loadRoles()
  } catch (err) {
    error(err.response?.data?.message || 'Failed to delete role')
  } finally {
    deleting.value = false
  }
}

const openBulkDeleteDialog = () => {
  const targets = roles.value.filter((role) => selectedRoles.value.includes(role.id) && !isProtectedRole(role))
  if (!targets.length) {
    error('Select at least one non-protected role to delete')
    return
  }
  bulkDeleteDialog.value = true
}

const closeBulkDeleteDialog = () => {
  bulkDeleteDialog.value = false
}

const handleBulkDeleteDialogChange = (value) => {
  bulkDeleteDialog.value = value
}

const confirmBulkDelete = async () => {
  const ids = roles.value
    .filter((role) => selectedRoles.value.includes(role.id) && !isProtectedRole(role))
    .map((role) => role.id)

  if (!ids.length) {
    error('No eligible roles selected for deletion')
    return
  }

  deleting.value = true
  try {
    await roleAPI.bulkDelete({ role_ids: ids })
    success(`Deleted ${ids.length} role(s)`)
    selectedRoles.value = []
    closeBulkDeleteDialog()
    await loadRoles()
  } catch (err) {
    error(err.response?.data?.message || 'Failed to delete selected roles')
  } finally {
    deleting.value = false
  }
}

const toggleCategory = (category) => {
  const selected = new Set(roleForm.value.permissions)
  const ids = category.permissions.map((permission) => permission.id)
  const allSelected = ids.every((id) => selected.has(id))

  if (allSelected) {
    ids.forEach((id) => selected.delete(id))
  } else {
    ids.forEach((id) => selected.add(id))
  }

  roleForm.value.permissions = Array.from(selected)
}

const selectAllPermissions = () => {
  roleForm.value.permissions = allPermissions.value.map((permission) => permission.id)
}

const clearAllPermissions = () => {
  roleForm.value.permissions = []
}

const loadRoles = async () => {
  loading.value = true
  try {
    const params = {
      page: currentPage.value,
      per_page: itemsPerPage.value,
      search: searchQuery.value?.trim() || undefined,
    }

    if (Array.isArray(sortBy.value) && sortBy.value.length) {
      params.sort_by = sortBy.value[0].key
      params.sort_direction = sortBy.value[0].order || 'desc'
    }

    const response = await roleAPI.getAll(params)
    const { items, total } = extractCollection(response)
    roles.value = items
    totalRoles.value = total
    selectedRoles.value = selectedRoles.value.filter((id) => roles.value.some((role) => role.id === id))
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load roles')
    roles.value = []
    totalRoles.value = 0
  } finally {
    loading.value = false
  }
}

const loadPermissions = async () => {
  try {
    const response = await permissionAPI.getAll({ per_page: 1000 })
    const { items } = extractCollection(response)
    allPermissions.value = items
  } catch (err) {
    error(err.response?.data?.message || 'Failed to load permissions')
    allPermissions.value = []
  }
}

const onUpdatePage = (page) => {
  currentPage.value = page
  loadRoles()
}

const onUpdatePerPage = (value) => {
  itemsPerPage.value = value
  currentPage.value = 1
  loadRoles()
}

const onUpdateSort = (value) => {
  sortBy.value = value
  loadRoles()
}

watch(searchQuery, () => {
  clearTimeout(debouncer.value)
  debouncer.value = setTimeout(() => {
    currentPage.value = 1
    loadRoles()
  }, 350)
})

onMounted(async () => {
  await Promise.all([loadRoles(), loadPermissions()])
})
</script>
