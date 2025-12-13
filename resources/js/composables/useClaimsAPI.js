import { ref } from 'vue';
import { useClaimsStore } from '../stores/claimsStore';
import api from '../utils/api';

export const useClaimsAPI = () => {
  const claimsStore = useClaimsStore();
  const loading = ref(false);
  const error = ref(null);

  // Referral API calls
  const fetchReferrals = async (filters = {}) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.get('/referrals', { params: filters });
      claimsStore.setReferrals(response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      claimsStore.setError(err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const createReferral = async (data) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.post('/referrals', data);
      claimsStore.addReferral(response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      claimsStore.setError(err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const getReferral = async (id) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.get(`/referrals/${id}`);
      claimsStore.setCurrentReferral(response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      throw err;
    } finally {
      loading.value = false;
    }
  };

  // Claim API calls
  const fetchClaims = async (filters = {}) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.get('/claims', { params: filters });
      claimsStore.setClaims(response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      claimsStore.setError(err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const createClaim = async (data) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.post('/claims', data);
      claimsStore.addClaim(response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      claimsStore.setError(err.message);
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const getClaim = async (id) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.get(`/claims/${id}`);
      claimsStore.setCurrentClaim(response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const updateClaim = async (id, data) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.put(`/claims/${id}`, data);
      claimsStore.updateClaim(id, response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      throw err;
    } finally {
      loading.value = false;
    }
  };

  // Admission API calls
  const fetchAdmissions = async (filters = {}) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.get('/claims-automation/admissions', { params: filters });
      claimsStore.setAdmissions(response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      throw err;
    } finally {
      loading.value = false;
    }
  };

  const createAdmission = async (data) => {
    loading.value = true;
    error.value = null;
    try {
      const response = await api.post('/claims-automation/admissions', data);
      claimsStore.addAdmission(response.data.data || response.data);
      return response.data;
    } catch (err) {
      error.value = err.message;
      throw err;
    } finally {
      loading.value = false;
    }
  };

  return {
    loading,
    error,
    fetchReferrals,
    createReferral,
    getReferral,
    fetchClaims,
    createClaim,
    getClaim,
    updateClaim,
    fetchAdmissions,
    createAdmission,
  };
};
