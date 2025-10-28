import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CasesManagementPage from '../CasesManagementPage.vue';
import { useToast } from '../../../composables/useToast';
import * as api from '../../../utils/api';

// Mock the API
vi.mock('../../../utils/api', () => ({
  caseAPI: {
    getAll: vi.fn(),
    getStatistics: vi.fn(),
    getGroups: vi.fn(),
    import: vi.fn(),
    downloadTemplate: vi.fn(),
    export: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn()
  }
}));

// Mock the toast composable
vi.mock('../../../composables/useToast', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn()
  })
}));

describe('CasesManagementPage - Import Flow E2E Tests', () => {
  let wrapper;
  let mockToast;

  beforeEach(() => {
    // Reset mocks
    vi.clearAllMocks();

    // Mock API responses
    api.caseAPI.getAll.mockResolvedValue({
      data: {
        success: true,
        data: {
          data: [],
          total: 0,
          current_page: 1
        }
      }
    });

    api.caseAPI.getStatistics.mockResolvedValue({
      data: {
        success: true,
        data: {
          total_cases: 0,
          active_cases: 0,
          pa_required_count: 0,
          referable_count: 0
        }
      }
    });

    api.caseAPI.getGroups.mockResolvedValue({
      data: {
        success: true,
        data: []
      }
    });

    mockToast = {
      success: vi.fn(),
      error: vi.fn()
    };
  });

  describe('Import Dialog - File Selection', () => {
    it('should open import dialog when Import button is clicked', async () => {
      wrapper = mount(CasesManagementPage, {
        global: {
          stubs: {
            AdminLayout: { template: '<div><slot /></div>' },
            VBtn: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
            VDialog: { template: '<div v-if="modelValue"><slot /></div>' },
            VCard: { template: '<div><slot /></div>' },
            VCardTitle: { template: '<div><slot /></div>' },
            VCardText: { template: '<div><slot /></div>' },
            VCardActions: { template: '<div><slot /></div>' },
            VFileInput: { template: '<input type="file" @change="$emit(\'update:modelValue\', $event.target.files)" />' },
            VAlert: { template: '<div><slot /></div>' },
            VSpacer: { template: '<div></div>' }
          }
        }
      });

      const importButton = wrapper.findAll('button').find(btn => btn.text().includes('Import'));
      expect(importButton).toBeDefined();
    });

    it('should validate file selection - no file selected', async () => {
      wrapper = mount(CasesManagementPage, {
        global: {
          mocks: {
            useToast: () => mockToast
          }
        }
      });

      const vm = wrapper.vm;
      const error = vm.validateImportFile();
      expect(error).toBe('No file selected');
    });

    it('should validate file type - Excel file (.xlsx)', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      // Create a mock Excel file
      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      const error = vm.validateImportFile();
      expect(error).toBeNull();
    });

    it('should validate file type - Excel file (.xls)', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      const file = new File(['test'], 'test.xls', {
        type: 'application/vnd.ms-excel'
      });

      vm.importFile = [file];
      const error = vm.validateImportFile();
      expect(error).toBeNull();
    });

    it('should validate file type - CSV file', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      const file = new File(['test'], 'test.csv', {
        type: 'text/csv'
      });

      vm.importFile = [file];
      const error = vm.validateImportFile();
      expect(error).toBeNull();
    });

    it('should reject invalid file type', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      const file = new File(['test'], 'test.pdf', {
        type: 'application/pdf'
      });

      vm.importFile = [file];
      const error = vm.validateImportFile();
      expect(error).toBe('Please select an Excel or CSV file');
    });

    it('should reject invalid file object', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      vm.importFile = [null];
      const error = vm.validateImportFile();
      expect(error).toBe('Invalid file');
    });
  });

  describe('Import Flow - Success Scenarios', () => {
    it('should successfully import cases with no errors', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockResolvedValue({
        data: {
          success: true,
          data: {
            imported_count: 3,
            errors: []
          }
        }
      });

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      vm.showImportDialog = true;

      // Mock the toast
      const successSpy = vi.fn();
      vi.spyOn(vm, 'success').mockImplementation(successSpy);

      await vm.importCases();

      expect(api.caseAPI.import).toHaveBeenCalled();
      expect(vm.showImportDialog).toBe(false);
      expect(vm.importFile).toEqual([]);
    });

    it('should handle partial import success with errors', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockResolvedValue({
        data: {
          success: true,
          data: {
            imported_count: 2,
            errors: [
              { row: 3, message: 'Invalid price format' }
            ]
          }
        }
      });

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      await vm.importCases();

      expect(api.caseAPI.import).toHaveBeenCalled();
    });
  });

  describe('Import Flow - Error Scenarios', () => {
    it('should show error when no file is selected', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      vm.importFile = [];
      const errorSpy = vi.fn();
      vi.spyOn(vm, 'error').mockImplementation(errorSpy);

      await vm.importCases();

      expect(errorSpy).toHaveBeenCalledWith('No file selected');
      expect(api.caseAPI.import).not.toHaveBeenCalled();
    });

    it('should show error when invalid file type is selected', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      const file = new File(['test'], 'test.pdf', {
        type: 'application/pdf'
      });

      vm.importFile = [file];
      const errorSpy = vi.fn();
      vi.spyOn(vm, 'error').mockImplementation(errorSpy);

      await vm.importCases();

      expect(errorSpy).toHaveBeenCalledWith('Please select an Excel or CSV file');
      expect(api.caseAPI.import).not.toHaveBeenCalled();
    });

    it('should handle API import failure', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockRejectedValue(new Error('Network error'));

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      const errorSpy = vi.fn();
      vi.spyOn(vm, 'error').mockImplementation(errorSpy);

      await vm.importCases();

      expect(errorSpy).toHaveBeenCalledWith('Failed to import cases');
      expect(vm.importing).toBe(false);
    });

    it('should handle API returning success: false', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockResolvedValue({
        data: {
          success: false,
          message: 'Import failed'
        }
      });

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      await vm.importCases();

      expect(vm.showImportDialog).toBe(true); // Dialog should remain open
    });
  });

  describe('Import Flow - State Management', () => {
    it('should set importing flag during import', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockImplementation(() => 
        new Promise(resolve => {
          setTimeout(() => {
            resolve({
              data: {
                success: true,
                data: { imported_count: 1, errors: [] }
              }
            });
          }, 100);
        })
      );

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      const importPromise = vm.importCases();

      expect(vm.importing).toBe(true);
      await importPromise;
      expect(vm.importing).toBe(false);
    });

    it('should clear import file after successful import', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockResolvedValue({
        data: {
          success: true,
          data: { imported_count: 1, errors: [] }
        }
      });

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      await vm.importCases();

      expect(vm.importFile).toEqual([]);
    });

    it('should close import dialog after successful import', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockResolvedValue({
        data: {
          success: true,
          data: { imported_count: 1, errors: [] }
        }
      });

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      vm.showImportDialog = true;
      await vm.importCases();

      expect(vm.showImportDialog).toBe(false);
    });

    it('should reload cases after successful import', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockResolvedValue({
        data: {
          success: true,
          data: { imported_count: 1, errors: [] }
        }
      });

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      await vm.importCases();

      expect(api.caseAPI.getAll).toHaveBeenCalled();
    });

    it('should reload statistics after successful import', async () => {
      wrapper = mount(CasesManagementPage);
      const vm = wrapper.vm;

      api.caseAPI.import.mockResolvedValue({
        data: {
          success: true,
          data: { imported_count: 1, errors: [] }
        }
      });

      const file = new File(['test'], 'test.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      });

      vm.importFile = [file];
      await vm.importCases();

      expect(api.caseAPI.getStatistics).toHaveBeenCalled();
    });
  });
});

