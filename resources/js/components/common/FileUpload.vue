<template>
  <div class="file-upload-component">
    <v-card 
      :class="[
        'tw-border-2 tw-border-dashed tw-transition-all tw-duration-200',
        isDragOver ? 'tw-border-blue-400 tw-bg-blue-50' : 'tw-border-gray-300',
        error ? 'tw-border-red-400 tw-bg-red-50' : ''
      ]"
      @dragover.prevent="handleDragOver"
      @dragleave.prevent="handleDragLeave"
      @drop.prevent="handleDrop"
      @click="triggerFileInput"
    >
      <v-card-text class="tw-text-center tw-py-8">
        <!-- Upload Icon -->
        <div class="tw-mb-4">
          <v-icon 
            :color="error ? 'red' : isDragOver ? 'blue' : 'grey'" 
            size="48"
          >
            {{ uploadIcon }}
          </v-icon>
        </div>

        <!-- Upload Text -->
        <div class="tw-mb-4">
          <h3 class="tw-text-lg tw-font-medium tw-text-gray-700 tw-mb-2">
            {{ uploadText }}
          </h3>
          <p class="tw-text-sm tw-text-gray-500">
            {{ uploadSubtext }}
          </p>
        </div>

        <!-- File Input -->
        <input
          ref="fileInput"
          type="file"
          :accept="accept"
          :multiple="multiple"
          @change="handleFileSelect"
          class="tw-hidden"
        />

        <!-- Upload Button -->
        <v-btn
          v-if="!files.length"
          color="blue"
          variant="outlined"
          @click.stop="triggerFileInput"
        >
          <v-icon class="tw-mr-2">mdi-upload</v-icon>
          Choose {{ multiple ? 'Files' : 'File' }}
        </v-btn>

        <!-- Error Message -->
        <v-alert
          v-if="error"
          type="error"
          variant="text"
          class="tw-mt-4"
        >
          {{ error }}
        </v-alert>
      </v-card-text>
    </v-card>

    <!-- Selected Files -->
    <div v-if="files.length" class="tw-mt-4">
      <h4 class="tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-2">
        Selected {{ multiple ? 'Files' : 'File' }}:
      </h4>
      
      <div class="tw-space-y-2">
        <v-card
          v-for="(file, index) in files"
          :key="index"
          variant="outlined"
          class="tw-p-3"
        >
          <div class="tw-flex tw-items-center tw-justify-between">
            <div class="tw-flex tw-items-center tw-space-x-3">
              <!-- File Icon -->
              <v-icon :color="getFileIconColor(file.type)">
                {{ getFileIcon(file.type) }}
              </v-icon>
              
              <!-- File Info -->
              <div>
                <p class="tw-text-sm tw-font-medium tw-text-gray-900">
                  {{ file.name }}
                </p>
                <p class="tw-text-xs tw-text-gray-500">
                  {{ formatFileSize(file.size) }} • {{ file.type }}
                </p>
              </div>
            </div>

            <!-- Remove Button -->
            <v-btn
              icon
              size="small"
              variant="text"
              color="red"
              @click="removeFile(index)"
            >
              <v-icon size="16">mdi-close</v-icon>
            </v-btn>
          </div>

          <!-- Upload Progress -->
          <v-progress-linear
            v-if="uploadProgress[index] !== undefined"
            :model-value="uploadProgress[index]"
            color="blue"
            class="tw-mt-2"
          />
        </v-card>
      </div>

      <!-- Action Buttons -->
      <div class="tw-flex tw-justify-between tw-mt-4">
        <v-btn
          variant="text"
          color="grey"
          @click="clearFiles"
        >
          Clear All
        </v-btn>
        
        <v-btn
          variant="text"
          color="blue"
          @click="triggerFileInput"
        >
          <v-icon class="tw-mr-2">mdi-plus</v-icon>
          Add More
        </v-btn>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  accept: {
    type: String,
    default: '*/*'
  },
  multiple: {
    type: Boolean,
    default: false
  },
  maxSize: {
    type: Number,
    default: 10 * 1024 * 1024 // 10MB
  },
  maxFiles: {
    type: Number,
    default: 5
  },
  uploadProgress: {
    type: Object,
    default: () => ({})
  }
});

const emit = defineEmits(['update:modelValue', 'files-selected', 'file-removed']);

// Reactive data
const fileInput = ref(null);
const files = ref([...(props.modelValue || [])]);
const isDragOver = ref(false);
const error = ref('');

// Computed properties
const uploadIcon = computed(() => {
  if (error.value) return 'mdi-alert-circle';
  if (isDragOver.value) return 'mdi-cloud-upload';
  return 'mdi-cloud-upload-outline';
});

const uploadText = computed(() => {
  if (files.value.length) return 'Files Selected';
  if (isDragOver.value) return 'Drop files here';
  return 'Upload Documents';
});

const uploadSubtext = computed(() => {
  if (files.value.length) return `${files.value.length} file(s) ready to upload`;
  return `Drag and drop files here, or click to browse. Max size: ${formatFileSize(props.maxSize)}`;
});

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  files.value = [...(newValue || [])];
});

// Methods
const triggerFileInput = () => {
  fileInput.value?.click();
};

const handleFileSelect = (event) => {
  const selectedFiles = Array.from(event.target.files);
  addFiles(selectedFiles);
  // Clear input to allow selecting the same file again
  event.target.value = '';
};

const handleDragOver = (event) => {
  event.preventDefault();
  isDragOver.value = true;
};

const handleDragLeave = (event) => {
  event.preventDefault();
  isDragOver.value = false;
};

const handleDrop = (event) => {
  event.preventDefault();
  isDragOver.value = false;
  
  const droppedFiles = Array.from(event.dataTransfer.files);
  addFiles(droppedFiles);
};

const addFiles = (newFiles) => {
  error.value = '';
  
  // Validate files
  for (const file of newFiles) {
    if (!validateFile(file)) {
      return;
    }
  }

  // Check total file count
  if (!props.multiple && newFiles.length > 1) {
    error.value = 'Only one file is allowed';
    return;
  }

  if (files.value.length + newFiles.length > props.maxFiles) {
    error.value = `Maximum ${props.maxFiles} files allowed`;
    return;
  }

  // Add files
  if (props.multiple) {
    files.value.push(...newFiles);
  } else {
    files.value = [newFiles[0]];
  }

  updateModelValue();
  emit('files-selected', newFiles);
};

const validateFile = (file) => {
  // Check file size
  if (file.size > props.maxSize) {
    error.value = `File "${file.name}" exceeds maximum size of ${formatFileSize(props.maxSize)}`;
    return false;
  }

  // Check file type if accept is specified
  if (props.accept !== '*/*') {
    const acceptedTypes = props.accept.split(',').map(type => type.trim());
    const fileType = file.type;
    const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
    
    const isAccepted = acceptedTypes.some(type => {
      if (type.startsWith('.')) {
        return type === fileExtension;
      }
      return fileType.match(type.replace('*', '.*'));
    });

    if (!isAccepted) {
      error.value = `File type "${fileType}" is not allowed`;
      return false;
    }
  }

  return true;
};

const removeFile = (index) => {
  const removedFile = files.value[index];
  files.value.splice(index, 1);
  updateModelValue();
  emit('file-removed', removedFile, index);
};

const clearFiles = () => {
  files.value = [];
  updateModelValue();
};

const updateModelValue = () => {
  emit('update:modelValue', [...files.value]);
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

const getFileIcon = (mimeType) => {
  if (mimeType.startsWith('image/')) return 'mdi-file-image';
  if (mimeType.includes('pdf')) return 'mdi-file-pdf-box';
  if (mimeType.includes('word')) return 'mdi-file-word';
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'mdi-file-excel';
  return 'mdi-file-document';
};

const getFileIconColor = (mimeType) => {
  if (mimeType.startsWith('image/')) return 'green';
  if (mimeType.includes('pdf')) return 'red';
  if (mimeType.includes('word')) return 'blue';
  if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'green';
  return 'grey';
};
</script>

<style scoped>
.file-upload-component {
  width: 100%;
}

.v-card {
  cursor: pointer;
  transition: all 0.2s ease;
}

.v-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>
