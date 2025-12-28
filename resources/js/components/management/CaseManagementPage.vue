<template>
  <AdminLayout>
    <!-- Page Header -->
    <v-card flat class="mb-4">
      <v-card-title class="d-flex align-center justify-space-between bg-grey-lighten-4">
        <div class="d-flex align-center">
          <v-icon size="32" color="primary" class="mr-3">mdi-file-document-multiple-outline</v-icon>
          <div>
            <div class="text-h5 font-weight-bold">Case Management</div>
            <div class="text-caption text-grey-darken-1">Manage case records and service tariffs</div>
          </div>
        </div>
        <v-chip variant="outlined" color="primary">
          <v-icon start>mdi-database</v-icon>
          {{ statistics.total || 0 }} Total Cases
        </v-chip>
      </v-card-title>
    </v-card>

    <!-- Statistics Cards -->
    <v-row class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="1">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-grey">Total Cases</div>
                <div class="text-h5 font-weight-bold">{{ statistics.total || 0 }}</div>
              </div>
              <v-icon size="40" color="primary">mdi-file-document-multiple</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="1">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-grey">Active Cases</div>
                <div class="text-h5 font-weight-bold text-success">{{ statistics.active || 0 }}</div>
              </div>
              <v-icon size="40" color="success">mdi-check-circle</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="1">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-grey">PA Required</div>
                <div class="text-h5 font-weight-bold text-warning">{{ statistics.pa_required || 0 }}</div>
              </div>
              <v-icon size="40" color="warning">mdi-shield-alert</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card elevation="1">
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-caption text-grey">Specialties</div>
                <div class="text-h5 font-weight-bold text-info">{{ statistics.specialties || 0 }}</div>
              </div>
              <v-icon size="40" color="info">mdi-folder-multiple</v-icon>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters and Actions -->
    <v-card elevation="1" class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filters.search"
              label="Search"
              placeholder="Search by code, description, or group"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @input="debouncedSearch"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.level_of_care"
              label="Level of Care"
              :items="levelsOfCare"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchCases"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.detail_type"
              label="Case Type"
              :items="detailTypes"
              item-title="text"
              item-value="value"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchCases"
            ></v-select>
          </v-col>
    
          <v-col cols="12" md="2" class="d-flex gap-2">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="openAddDialog">
              Add Case
            </v-btn>
            <v-btn color="success" variant="outlined" prepend-icon="mdi-upload" @click="openImportDialog">
              Import
            </v-btn>
            <v-btn color="info" variant="outlined" prepend-icon="mdi-download" @click="exportCases">
              Export
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Cases Table -->
    <v-card elevation="1">
      <v-card-text>
        <v-data-table
          :headers="headers"
          :items="cases"
          :loading="loading"
          :items-per-page="pagination.per_page"
          hide-default-footer
          class="elevation-0"
        >
          <template #item.case_name="{ item }">
            <span class="font-weight-medium">{{ item.case_name || 'N/A' }}</span>
          </template>

          <template #item.nicare_code="{ item }">
            <span class="text-caption text-grey">{{ item.nicare_code }}</span>
          </template>

          <template #item.service_description="{ item }">
            <div class="text-truncate" style="max-width: 300px;">{{ item.service_description }}</div>
          </template>

          <template #item.level_of_care="{ item }">
            <v-chip size="small" :color="getLevelColor(item.level_of_care)" variant="outlined">
              {{ item.level_of_care }}
            </v-chip>
          </template>

          <template #item.price="{ item }">
            <span v-if="item.is_bundle" class="font-weight-medium">₦{{ Number(item.bundle_price).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
            <span v-else class="font-weight-medium">₦{{ Number(item.price).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
          </template>

          <template #item.detail_type="{ item }">
            <v-chip
              v-if="item.detail_type"
              size="small"
              :color="getDetailTypeColor(item.detail_type)"
              variant="outlined"
              prepend-icon="mdi-information-outline"
            >
              {{ getDetailTypeLabel(item.detail_type) }}
            </v-chip>
             <v-chip
             v-else 
              size="small"
              variant="outlined"
              prepend-icon="mdi-information-outline"
            >
             <span v-if="!item.is_bundle && !item.detail_type">Case Type</span>
             <span v-else-if="item.is_bundle">Bundle</span>
             <span v-else>{{  item.detail_type }}</span>
            </v-chip>
          </template>

          <template #item.pa_required="{ item }">
            <v-chip size="small" :color="item.pa_required ? 'warning' : 'grey'" variant="flat">
              {{ item.pa_required ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template #item.referable="{ item }">
            <v-chip size="small" :color="item.referable ? 'success' : 'grey'" variant="flat">
              {{ item.referable ? 'Yes' : 'No' }}
            </v-chip>
          </template>

          <template #item.status="{ item }">
            <v-chip size="small" :color="item.status ? 'success' : 'error'" variant="flat">
              {{ item.status ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <v-btn icon="mdi-pencil" size="small" variant="text" color="primary" @click="openEditDialog(item)"></v-btn>
            <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(item)"></v-btn>
          </template>

          <template #bottom>
            <div class="d-flex align-center justify-space-between pa-4">
              <div class="text-caption text-grey">
                Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} to
                {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} of
                {{ pagination.total }} entries
              </div>
              <v-pagination
                v-model="pagination.current_page"
                :length="pagination.last_page"
                :total-visible="7"
                @update:model-value="fetchCases"
              ></v-pagination>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="800px" persistent>
      <v-card>
        <v-card-title class="bg-grey-lighten-4">
          <span class="text-h6">{{ editMode ? 'Edit Case' : 'Add New Case' }}</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="formRef" @submit.prevent="saveCase">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.case_name"
                  label="Case Name *"
                  placeholder="e.g., General Consultation"
                  variant="outlined"
                  density="comfortable"
                  :rules="[v => !!v || 'Case Name is required']"
                  required
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="form.level_of_care"
                  label="Level of Care *"
                  :items="levelsOfCare"
                  variant="outlined"
                  density="comfortable"
                  :rules="[v => !!v || 'Level of Care is required']"
                  required
                ></v-select>
              </v-col>
              <v-col cols="12">
                <v-alert variant="outlined" type="info" density="compact">
                  <div class="text-caption">
                    <strong>NiCare Code will be auto-generated</strong> using format:
                    <code>NGSCHA/[Abbreviation]/[Level Letter]/[Serial]</code>
                    <br>
                    Example: "General Consultation" + "Primary" = <code>NGSCHA/GC/P/0001</code>
                  </div>
                </v-alert>
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="form.service_description"
                  label="Service Description *"
                  placeholder="Enter detailed service description"
                  variant="outlined"
                  density="comfortable"
                  rows="3"
                  :rules="[v => !!v || 'Service Description is required']"
                  required
                ></v-textarea>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="form.price"
                  label="Price (₦) *"
                  type="number"
                  step="0.01"
                  min="0"
                  variant="outlined"
                  density="comfortable"
                  :rules="[v => v >= 0 || 'Price must be positive']"
                  required
                ></v-text-field>
              </v-col>
           
              <v-col cols="12" md="3">
                <v-switch
                  v-model="form.pa_required"
                  label="PA Required"
                  color="warning"
                  hide-details
                ></v-switch>
              </v-col>
              <v-col cols="12" md="3">
                <v-switch
                  v-model="form.referable"
                  label="Referable"
                  color="success"
                  hide-details
                ></v-switch>
              </v-col>
              <v-col cols="12" md="3">
                <v-switch
                  v-model="form.status"
                  label="Active"
                  color="success"
                  hide-details
                ></v-switch>
              </v-col>
              <v-col cols="12" md="3">
                <v-switch
                  v-model="form.is_bundle"
                  label="Is Bundle"
                  color="primary"
                  hide-details
                ></v-switch>
              </v-col>

              <!-- Bundle-specific fields -->
              <template v-if="form.is_bundle">
                <v-col cols="12">
                  <v-divider class="my-2"></v-divider>
                  <div class="text-subtitle-2 text-primary mb-2">
                    <v-icon size="small" class="mr-1">mdi-package-variant</v-icon>
                    Bundle Configuration
                  </div>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model.number="form.bundle_price"
                    label="Bundle Fixed Price (₦) *"
                    type="number"
                    step="0.01"
                    min="0"
                    variant="outlined"
                    density="comfortable"
                    :rules="form.is_bundle ? [v => v >= 0 || 'Bundle price must be positive'] : []"
                    hint="Fixed price for this bundle package"
                    persistent-hint
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.diagnosis_icd10"
                    label="ICD-10 Diagnosis Code"
                    placeholder="e.g., A00.0"
                    variant="outlined"
                    density="comfortable"
                    hint="Optional: Link bundle to specific diagnosis"
                    persistent-hint
                  ></v-text-field>
                </v-col>
              </template>

              <!-- Polymorphic Detail Type Selection -->
              <v-col cols="12">
                <v-divider class="my-2"></v-divider>
                <div class="text-subtitle-2 text-grey-darken-2 mb-2">
                  <v-icon size="small" class="mr-1">mdi-information-outline</v-icon>
                  Additional Details (Optional)
                </div>
              </v-col>
              <v-col cols="12" v-if="!form.is_bundle">
                <v-select
                  v-model="form.detail_type"
                  label="Detail Type"
                  :items="detailTypes"
                  item-title="text"
                  item-value="value"
                  variant="outlined"
                  density="comfortable"
                  clearable
                  hint="Select a detail type to add specialized information"
                  persistent-hint
                  @update:model-value="onDetailTypeChange"
                ></v-select>
              </v-col>

              <!-- Drug Details -->
              <template v-if="form.detail_type === 'drug'">
                <v-col cols="12">
                  <v-alert variant="outlined" type="info" density="compact">
                    <div class="text-caption">
                      <strong>Drug-specific details</strong> - Add pharmaceutical information
                    </div>
                  </v-alert>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.generic_name"
                    label="Generic Name *"
                    variant="outlined"
                    density="comfortable"
                    :rules="form.detail_type === 'drug' ? [v => !!v || 'Generic Name is required'] : []"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.brand_name"
                    label="Brand Name"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.dosage_form"
                    label="Dosage Form"
                    :items="dosageForms"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.strength"
                    label="Strength (e.g., 500mg)"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                      v-model="form.detail_data.pack_description"
                      label="Pack Description"
                      variant="outlined"
                      density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.route_of_administration"
                    label="Route of Administration"
                    :items="routesOfAdministration"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.drug_class"
                    label="Drug Class"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-switch
                    v-model="form.detail_data.prescription_required"
                    label="Prescription Required"
                    color="warning"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="6">
                  <v-switch
                    v-model="form.detail_data.controlled_substance"
                    label="Controlled Substance"
                    color="error"
                    hide-details
                  ></v-switch>
                </v-col>
              </template>

              <!-- Laboratory Details -->
              <template v-if="form.detail_type === 'laboratory'">
                <v-col cols="12">
                  <v-alert variant="outlined" type="info" density="compact">
                    <div class="text-caption">
                      <strong>Laboratory test details</strong> - Add test-specific information
                    </div>
                  </v-alert>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.test_name"
                    label="Test Name *"
                    variant="outlined"
                    density="comfortable"
                    :rules="form.detail_type === 'laboratory' ? [v => !!v || 'Test Name is required'] : []"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.test_code"
                    label="Test Code"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.specimen_type"
                    label="Specimen Type"
                    :items="specimenTypes"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.test_category"
                    label="Test Category"
                    :items="testCategories"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model.number="form.detail_data.turnaround_time"
                    label="Turnaround Time (hours)"
                    type="number"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-switch
                    v-model="form.detail_data.fasting_required"
                    label="Fasting Required"
                    color="warning"
                    hide-details
                  ></v-switch>
                </v-col>
              </template>

              <!-- Professional Service Details -->
              <template v-if="form.detail_type === 'professional_service'">
                <v-col cols="12">
                  <v-alert variant="outlined" type="info" density="compact">
                    <div class="text-caption">
                      <strong>Professional service details</strong> - Add procedure-specific information
                    </div>
                  </v-alert>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.service_name"
                    label="Service Name *"
                    variant="outlined"
                    density="comfortable"
                    :rules="form.detail_type === 'professional_service' ? [v => !!v || 'Service Name is required'] : []"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.service_code"
                    label="Service Code"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.specialty"
                    label="Specialty"
                    :items="specialties"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model.number="form.detail_data.duration_minutes"
                    label="Duration (minutes)"
                    type="number"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.anesthesia_required"
                    label="Anesthesia Required"
                    color="warning"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.admission_required"
                    label="Admission Required"
                    color="info"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.follow_up_required"
                    label="Follow-up Required"
                    color="success"
                    hide-details
                  ></v-switch>
                </v-col>
              </template>

              <!-- Radiology Details -->
              <template v-if="form.detail_type === 'radiology'">
                <v-col cols="12">
                  <v-alert variant="outlined" type="info" density="compact">
                    <div class="text-caption">
                      <strong>Radiology examination details</strong> - Add imaging-specific information
                    </div>
                  </v-alert>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.examination_name"
                    label="Examination Name *"
                    variant="outlined"
                    density="comfortable"
                    :rules="form.detail_type === 'radiology' ? [v => !!v || 'Examination Name is required'] : []"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.examination_code"
                    label="Examination Code"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.modality"
                    label="Modality"
                    :items="['X-Ray', 'CT Scan', 'MRI', 'Ultrasound', 'Mammography', 'Fluoroscopy', 'PET Scan', 'DEXA Scan', 'Angiography']"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.body_part"
                    label="Body Part"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.radiation_dose"
                    label="Radiation Dose"
                    :items="['None', 'Very Low', 'Low', 'Medium', 'High']"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model.number="form.detail_data.duration_minutes"
                    label="Duration (minutes)"
                    type="number"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    v-model="form.detail_data.preparation_instructions"
                    label="Preparation Instructions"
                    variant="outlined"
                    density="comfortable"
                    rows="2"
                  ></v-textarea>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.contrast_required"
                    label="Contrast Required"
                    color="warning"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.pregnancy_safe"
                    label="Pregnancy Safe"
                    color="success"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.sedation_required"
                    label="Sedation Required"
                    color="info"
                    hide-details
                  ></v-switch>
                </v-col>
              </template>

              <!-- Consultation Details -->
              <template v-if="form.detail_type === 'consultation'">
                <v-col cols="12">
                  <v-alert variant="outlined" type="info" density="compact">
                    <div class="text-caption">
                      <strong>Consultation details</strong> - Add consultation-specific information
                    </div>
                  </v-alert>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.consultation_type"
                    label="Consultation Type *"
                    :items="['Initial Consultation', 'Follow-up Consultation', 'Emergency Consultation', 'Second Opinion', 'Pre-operative Assessment', 'Post-operative Review', 'Chronic Disease Management']"
                    variant="outlined"
                    density="comfortable"
                    :rules="form.detail_type === 'consultation' ? [v => !!v || 'Consultation Type is required'] : []"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.specialty"
                    label="Specialty"
                    :items="['General Practice', 'Internal Medicine', 'Pediatrics', 'Surgery', 'Obstetrics & Gynecology', 'Cardiology', 'Neurology', 'Orthopedics', 'Dermatology', 'Psychiatry', 'Ophthalmology', 'ENT', 'Urology', 'Nephrology', 'Endocrinology']"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.provider_level"
                    label="Provider Level"
                    :items="['Consultant', 'Specialist', 'Registrar', 'Medical Officer', 'General Practitioner']"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.consultation_mode"
                    label="Consultation Mode"
                    :items="['In-person', 'Telemedicine', 'Home Visit', 'Ward Round']"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model.number="form.detail_data.duration_minutes"
                    label="Duration (minutes)"
                    type="number"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model.number="form.detail_data.follow_up_interval_days"
                    label="Follow-up Interval (days)"
                    type="number"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    v-model="form.detail_data.scope_of_service"
                    label="Scope of Service"
                    variant="outlined"
                    density="comfortable"
                    rows="2"
                  ></v-textarea>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.prescription_included"
                    label="Prescription Included"
                    color="success"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.follow_up_required"
                    label="Follow-up Required"
                    color="info"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="4">
                  <v-switch
                    v-model="form.detail_data.insurance_accepted"
                    label="Insurance Accepted"
                    color="primary"
                    hide-details
                  ></v-switch>
                </v-col>
              </template>

              <!-- Consumable Details -->
              <template v-if="form.detail_type === 'consumable'">
                <v-col cols="12">
                  <v-alert variant="outlined" type="info" density="compact">
                    <div class="text-caption">
                      <strong>Consumable item details</strong> - Add medical supply information
                    </div>
                  </v-alert>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.item_name"
                    label="Item Name *"
                    variant="outlined"
                    density="comfortable"
                    :rules="form.detail_type === 'consumable' ? [v => !!v || 'Item Name is required'] : []"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.item_code"
                    label="Item Code"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.category"
                    label="Category"
                    :items="['Surgical Supplies', 'Dressings & Bandages', 'Syringes & Needles', 'IV Fluids & Sets', 'Gloves', 'Catheters', 'Sutures', 'Drains & Tubes', 'Specimen Containers', 'Personal Protective Equipment', 'Disinfectants & Antiseptics', 'Oxygen & Respiratory']"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-select
                    v-model="form.detail_data.unit_of_measure"
                    label="Unit of Measure"
                    :items="['Piece', 'Box', 'Pack', 'Bottle', 'Liter', 'Milliliter', 'Kilogram', 'Gram', 'Meter', 'Roll', 'Set']"
                    variant="outlined"
                    density="comfortable"
                  ></v-select>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="form.detail_data.manufacturer"
                    label="Manufacturer"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model.number="form.detail_data.units_per_pack"
                    label="Units per Pack"
                    type="number"
                    variant="outlined"
                    density="comfortable"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    v-model="form.detail_data.specifications"
                    label="Specifications"
                    variant="outlined"
                    density="comfortable"
                    rows="2"
                  ></v-textarea>
                </v-col>
                <v-col cols="12" md="3">
                  <v-switch
                    v-model="form.detail_data.sterile"
                    label="Sterile"
                    color="success"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="3">
                  <v-switch
                    v-model="form.detail_data.single_use"
                    label="Single Use"
                    color="warning"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="3">
                  <v-switch
                    v-model="form.detail_data.latex_free"
                    label="Latex Free"
                    color="info"
                    hide-details
                  ></v-switch>
                </v-col>
                <v-col cols="12" md="3">
                  <v-switch
                    v-model="form.detail_data.hazardous"
                    label="Hazardous"
                    color="error"
                    hide-details
                  ></v-switch>
                </v-col>
              </template>

            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions class="px-6 pb-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn color="primary" variant="flat" :loading="saving" @click="saveCase">
            {{ editMode ? 'Update' : 'Save' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Import Dialog -->
    <v-dialog v-model="importDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="bg-grey-lighten-4">
          <span class="text-h6">Import Cases</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-alert variant="outlined" type="info" class="mb-4">
            <div class="text-body-2">
              <strong>Import Instructions:</strong>
              <ul class="mt-2">
                <li>Select the type of cases you want to import</li>
                <li>Download the appropriate template file</li>
                <li>Fill in the required fields (marked with *)</li>
                <li>Upload the completed file</li>
                <li>Supported formats: .xlsx, .xls, .csv</li>
              </ul>
            </div>
          </v-alert>

          <v-select
            v-model="selectedDetailType"
            :items="detailTypeOptions"
            label="Select Case Type *"
            variant="outlined"
            density="comfortable"
            class="mb-4"
            hint="Choose the type of cases you want to import"
            persistent-hint
          ></v-select>

          <v-btn
            color="primary"
            variant="outlined"
            prepend-icon="mdi-download"
            block
            class="mb-4"
            @click="downloadTemplate"
            :loading="downloadingTemplate"
            :disabled="!selectedDetailType"
          >
            Download {{ selectedDetailType ? detailTypeOptions.find(t => t.value === selectedDetailType)?.title : '' }} Template
          </v-btn>

          <v-file-input
            v-model="importFile"
            label="Select File"
            accept=".xlsx,.xls,.csv"
            variant="outlined"
            density="comfortable"
            append-inner-icon="mdi-file-excel"
            show-size
            clearable
          ></v-file-input>

          <v-alert v-if="importErrors.length > 0" variant="outlined" type="error" class="mt-4">
            <div class="text-body-2">
              <strong>Import Errors:</strong>
              <ul class="mt-2">
                <li v-for="(error, index) in importErrors" :key="index">{{ error }}</li>
              </ul>
            </div>
          </v-alert>

          <v-alert v-if="importSuccess" variant="outlined" type="success" class="mt-4">
            {{ importSuccessMessage }}
          </v-alert>
        </v-card-text>
        <v-card-actions class="px-6 pb-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeImportDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="importing"
            :disabled="!importFile"
            @click="importCases"
          >
            Import
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="500px">
      <v-card>
        <v-card-title class="bg-grey-lighten-4">
          <span class="text-h6">Confirm Delete</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <p>Are you sure you want to delete this case?</p>
          <v-alert variant="outlined" type="warning" class="mt-4">
            <strong>{{ caseToDelete?.nicare_code }}</strong><br>
            {{ caseToDelete?.service_description }}
          </v-alert>
        </v-card-text>
        <v-card-actions class="px-6 pb-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" :loading="deleting" @click="deleteCase">
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue';
import { useToast } from '@/js/composables/useToast';
import api from '@/js/utils/api';
import AdminLayout from '@/js/components/layout/AdminLayout.vue';

const { success: showSuccess, error: showError, info: showInfo } = useToast();

// Data
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const importing = ref(false);
const downloadingTemplate = ref(false);
const loadingGroups = ref(false);
const dialog = ref(false);
const importDialog = ref(false);
const deleteDialog = ref(false);
const editMode = ref(false);
const formRef = ref(null);

const cases = ref([]);
const caseGroups = ref([]);
const caseToDelete = ref(null);
const importFile = ref(null);
const importErrors = ref([]);
const importSuccess = ref(false);
const importSuccessMessage = ref('');
const selectedDetailType = ref('drug');

const detailTypeOptions = [
  { title: 'Drug', value: 'drug' },
  { title: 'Laboratory Tests', value: 'laboratory' },
  { title: 'Radiology Examinations', value: 'radiology' },
  { title: 'Professional Services', value: 'professional_service' },
  { title: 'Consultations', value: 'consultation' },
  { title: 'Consumables', value: 'consumable' },
  { title: 'Bundle Services', value: 'bundle' },
];

const statistics = reactive({
  total: 0,
  active: 0,
  pa_required: 0,
  specialties: 0
});

const filters = reactive({
  search: '',
  level_of_care: null,
  detail_type: null,
  status: null,
  per_page: 15
});

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
});

const form = reactive({
  id: null,
  case_name: '',
  service_description: '',
  level_of_care: '',
  price: 0,
  pa_required: false,
  referable: true,
  status: true,
  is_bundle: true,
  bundle_price: null,
  diagnosis_icd10: null,
  detail_type: null,
  detail_data: {}
});

const headers = [
  { title: 'Case Name', key: 'case_name', sortable: true },
  { title: 'NiCare Code', key: 'nicare_code', sortable: true },
  { title: 'Price', key: 'price', sortable: true },
  { title: 'Detail Type', key: 'detail_type', sortable: false },
  { title: 'PA Required', key: 'pa_required', sortable: true },
  { title: 'Referable', key: 'referable', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
];

const levelsOfCare = ['Primary', 'Secondary', 'Tertiary'];

const statusOptions = [
  { text: 'Active', value: true },
  { text: 'Inactive', value: false }
];

const detailTypes = [
  { text: 'None', value: null },  // disease conditions that can have diagnoses but no specific treatment
  { text: 'Drug', value: 'drug' },
  { text: 'Laboratory Test', value: 'laboratory' },
  { text: 'Professional Service', value: 'professional_service' },
  { text: 'Radiology', value: 'radiology' },
  { text: 'Consumable', value: 'consumable' },
  { text: 'Consultation', value: 'consultation' }
];

// Drug-specific options
const dosageForms = ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Cream', 'Ointment', 'Drops', 'Inhaler'];
const routesOfAdministration = ['Oral', 'Intravenous', 'Intramuscular', 'Subcutaneous', 'Topical', 'Rectal', 'Inhalation'];

// Laboratory-specific options
const specimenTypes = ['Blood', 'Urine', 'Stool', 'Sputum', 'Cerebrospinal Fluid', 'Swab', 'Tissue'];
const testCategories = ['Hematology', 'Chemistry', 'Microbiology', 'Immunology', 'Pathology', 'Radiology'];

// Professional service-specific options
const specialties = [
  'General Practice', 'Cardiology', 'Orthopedics', 'Pediatrics',
  'Obstetrics & Gynecology', 'Surgery', 'Internal Medicine', 'Dermatology'
];

// Methods
const fetchCases = async () => {
  loading.value = true;
  try {
    const params = {
      ...filters,
      page: pagination.current_page,
      per_page: pagination.per_page
    };

    const response = await api.get('/cases', { params });

    if (response.data.success) {
      cases.value = response.data.data;
      pagination.total = response.data.total;
      pagination.current_page = response.data.current_page;
      pagination.last_page = response.data.last_page;
      pagination.per_page = response.data.per_page;
    }
  } catch (error) {
    console.error('Error fetching cases:', error);
    showError('Failed to fetch cases');
  } finally {
    loading.value = false;
  }
};

const fetchStatistics = async () => {
  try {
    const response = await api.get('/cases-statistics');
    if (response.data.success) {
      Object.assign(statistics, response.data.data);
    }
  } catch (error) {
    console.error('Error fetching statistics:', error);
  }
};



const openAddDialog = () => {
  editMode.value = false;
  resetForm();
  dialog.value = true;
};

const openEditDialog = (item) => {
  editMode.value = true;
  form.id = item.id;
  form.case_name = item.case_name;
  form.service_description = item.service_description;
  form.level_of_care = item.level_of_care;
  form.price = item.price;
  form.pa_required = item.pa_required;
  form.referable = item.referable;
  form.status = item.status;
  form.is_bundle = item.is_bundle || false;
  form.bundle_price = item.bundle_price || null;
  form.diagnosis_icd10 = item.diagnosis_icd10 || null;

  // Load polymorphic detail if exists
  if (item.detail_type && item.detail) {
    form.detail_type = item.detail_type.split('\\').pop().toLowerCase().replace('detail', '');
    if (form.detail_type === 'professionalservice') {
      form.detail_type = 'professional_service';
    } else if (form.detail_type === 'consumable') {
      form.detail_type = 'consumable';
    }
    form.detail_data = { ...item.detail };
  } else {
    form.detail_type = null;
    form.detail_data = {};
  }

  dialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  resetForm();
};

const resetForm = () => {
  form.id = null;
  form.case_name = '';
  form.service_description = '';
  form.level_of_care = '';
  form.price = 0;
  form.pa_required = false;
  form.referable = true;
  form.status = true;
  form.is_bundle = true;
  form.bundle_price = null;
  form.diagnosis_icd10 = null;
  form.detail_type = null;
  form.detail_data = {};
};

const onDetailTypeChange = (newType) => {
  // Reset detail_data when type changes
  form.detail_data = {};

  // Set default values based on type
  if (newType === 'drug') {
    form.detail_data = {
      generic_name: '',
      brand_name: '',
      dosage_form: null,
      strength: '',
      route_of_administration: null,
      manufacturer: '',
      drug_class: '',
      indications: '',
      contraindications: '',
      side_effects: '',
      storage_conditions: '',
      pack_description: '',
      prescription_required: true,
      controlled_substance: false,
      nafdac_number: '',
      expiry_date: null
    };
  } else if (newType === 'laboratory') {
    form.detail_data = {
      test_name: '',
      test_code: '',
      specimen_type: null,
      specimen_volume: '',
      collection_method: '',
      test_method: '',
      test_category: null,
      turnaround_time: null,
      preparation_instructions: '',
      reference_range: '',
      reporting_unit: '',
      fasting_required: false,
      urgent_available: false,
      urgent_surcharge: null
    };
  } else if (newType === 'professional_service') {
    form.detail_data = {
      service_name: '',
      service_code: '',
      specialty: null,
      duration_minutes: null,
      provider_type: null,
      equipment_needed: '',
      procedure_description: '',
      indications: '',
      contraindications: '',
      complications: '',
      pre_procedure_requirements: '',
      post_procedure_care: '',
      anesthesia_required: false,
      anesthesia_type: null,
      admission_required: false,
      recovery_time_hours: null,
      follow_up_required: false
    };
  } else if (newType === 'radiology') {
    form.detail_data = {
      examination_name: '',
      examination_code: '',
      modality: null,
      body_part: '',
      view_projection: '',
      contrast_required: false,
      contrast_type: null,
      preparation_instructions: '',
      duration_minutes: null,
      indications: '',
      contraindications: '',
      pregnancy_safe: true,
      radiation_dose: null,
      turnaround_time: null,
      urgent_available: false,
      urgent_surcharge: null,
      special_equipment: '',
      sedation_required: false
    };
  } else if (newType === 'consultation') {
    form.detail_data = {
      consultation_type: '',
      specialty: null,
      provider_level: null,
      duration_minutes: null,
      consultation_mode: null,
      scope_of_service: '',
      diagnostic_tests_included: false,
      included_services: '',
      prescription_included: true,
      medical_report_included: false,
      referral_letter_included: false,
      follow_up_required: false,
      follow_up_interval_days: null,
      emergency_available: false,
      booking_requirements: '',
      insurance_accepted: true
    };
  } else if (newType === 'consumable') {
    form.detail_data = {
      item_name: '',
      item_code: '',
      category: null,
      subcategory: '',
      unit_of_measure: null,
      units_per_pack: null,
      manufacturer: '',
      material_composition: '',
      sterile: false,
      sterilization_method: null,
      single_use: true,
      latex_free: false,
      specifications: '',
      usage_instructions: '',
      storage_conditions: '',
      expiry_date: null,
      regulatory_approval: '',
      requires_cold_chain: false,
      disposal_instructions: '',
      hazardous: false
    };
  }
};

const saveCase = async () => {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  saving.value = true;
  try {
    const payload = { ...form };

    let response;
    if (editMode.value) {
      response = await api.put(`/cases/${form.id}`, payload);
    } else {
      response = await api.post('/cases', payload);
    }

    if (response.data.success) {
      showSuccess(editMode.value ? 'Case updated successfully' : 'Case created successfully');
      closeDialog();
      fetchCases();
      fetchStatistics();
    }
  } catch (error) {
    console.error('Error saving case:', error);
    const message = error.response?.data?.message || 'Failed to save case';
    showError(message);
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (item) => {
  caseToDelete.value = item;
  deleteDialog.value = true;
};

const deleteCase = async () => {
  deleting.value = true;
  try {
    const response = await api.delete(`/cases/${caseToDelete.value.id}`);

    if (response.data.success) {
      showSuccess('Case deleted successfully');
      deleteDialog.value = false;
      caseToDelete.value = null;CaseManagementPage.vue
      fetchCases();
      fetchStatistics();
    }
  } catch (error) {
    console.error('Error deleting case:', error);
    showError('Failed to delete case');
  } finally {
    deleting.value = false;
  }
};

const openImportDialog = () => {
  importFile.value = null;
  importErrors.value = [];
  importSuccess.value = false;
  importSuccessMessage.value = '';
  selectedDetailType.value = 'drug'; // Default to drug
  importDialog.value = true;
};

const closeImportDialog = () => {
  importDialog.value = false;
  importFile.value = null;
  importErrors.value = [];
  importSuccess.value = false;
  importSuccessMessage.value = '';
};

const downloadTemplate = async () => {
  downloadingTemplate.value = true;
  try {
    const response = await api.get('/cases-template', {
      params: {
        detail_type: selectedDetailType.value
      },
      responseType: 'blob'
    });

    // Generate filename based on detail type
    const filenameMap = {
      drug: 'drug_cases_template.xlsx',
      laboratory: 'laboratory_cases_template.xlsx',
      radiology: 'radiology_cases_template.xlsx',
      professional_service: 'professional_service_cases_template.xlsx',
      consultation: 'consultation_cases_template.xlsx',
      consumable: 'consumable_cases_template.xlsx',
      bundle: 'bundle_cases_template.xlsx',
    };
    const filename = filenameMap[selectedDetailType.value] || 'cases_import_template.xlsx';

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    showSuccess('Template downloaded successfully');
  } catch (error) {
    console.error('Error downloading template:', error);
    showError('Failed to download template');
  } finally {
    downloadingTemplate.value = false;
  }
};

const importCases = async () => {
  // Check if file is selected (handle both array and single file)
  const file = Array.isArray(importFile.value) ? importFile.value[0] : importFile.value;

  if (!file) {
    showError('Please select a file to import');
    return;
  }

  importing.value = true;
  importErrors.value = [];
  importSuccess.value = false;
  importSuccessMessage.value = '';

  try {
    const formData = new FormData();
    formData.append('file', file);

    const response = await api.post('/cases/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });

    if (response.data.success) {
      importSuccess.value = true;
      importSuccessMessage.value = response.data.message;

      if (response.data.data?.errors && response.data.data.errors.length > 0) {
        importErrors.value = response.data.data.errors;
      } else {
        // Close dialog after 2 seconds if no errors
        setTimeout(() => {
          closeImportDialog();
          fetchCases();
          fetchStatistics();
        }, 2000);
      }
    }
  } catch (error) {
    console.error('Error importing cases:', error);
    const message = error.response?.data?.message || 'Failed to import cases';
    showError(message);

    if (error.response?.data?.data?.errors) {
      importErrors.value = error.response.data.data.errors;
    }
  } finally {
    importing.value = false;
  }
};

const exportCases = async () => {
  try {
    const params = {
      search: filters.search,
      level_of_care: filters.level_of_care,
      status: filters.status
    };

    const response = await api.get('/cases-export', {
      params,
      responseType: 'blob'
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    const filename = `cases_export_${new Date().toISOString().split('T')[0]}.xlsx`;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    showSuccess('Cases exported successfully');
  } catch (error) {
    console.error('Error exporting cases:', error);
    showError('Failed to export cases');
  }
};

const getLevelColor = (level) => {
  const colors = {
    'Primary': 'success',
    'Secondary': 'warning',
    'Tertiary': 'error'
  };
  return colors[level] || 'grey';
};

const getDetailTypeColor = (detailType) => {
  if (!detailType) return 'grey';

  const type = detailType.split('\\').pop().toLowerCase();
  const colors = {
    'drugdetail': 'purple',
    'laboratorydetail': 'blue',
    'professionalservicedetail': 'teal',
    'radiologydetail': 'orange',
    'consultationdetail': 'green',
    'consumabledetail': 'brown'
  };
  return colors[type] || 'grey';
};

const getDetailTypeLabel = (detailType) => {
  if (!detailType) return 'None';

  const type = detailType.split('\\').pop().toLowerCase();
  const labels = {
    'drugdetail': 'Drug',
    'laboratorydetail': 'Lab Test',
    'professionalservicedetail': 'Service',
    'radiologydetail': 'Radiology',
    'consultationdetail': 'Consultation',
    'consumabledetail': 'Consumable'
  };
  return labels[type] || 'Unknown';
};

// Debounced search
let searchTimeout;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    pagination.current_page = 1;
    fetchCases();
  }, 500);
};

// Watch is_bundle to automatically set pa_required to true when bundle is enabled
watch(() => form.is_bundle, (newValue) => {
  if (newValue) {
    form.pa_required = true;
    form.detail_type = null;
    form.detail_data = {};
  }
});

// Lifecycle
onMounted(() => {
  fetchCases();
  fetchStatistics();
});
</script>

<style scoped>
.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

