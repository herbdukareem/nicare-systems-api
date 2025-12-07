import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useClaimsStore = defineStore('claims', () => {
  // State
  const claims = ref([]);
  const referrals = ref([]);
  const admissions = ref([]);
  const currentClaim = ref(null);
  const currentReferral = ref(null);
  const currentAdmission = ref(null);
  const loading = ref(false);
  const error = ref(null);
  const filters = ref({
    status: null,
    facility_id: null,
    date_from: null,
    date_to: null,
  });

  // Computed
  const approvedClaims = computed(() => 
    claims.value.filter(c => c.status === 'APPROVED')
  );

  const pendingClaims = computed(() => 
    claims.value.filter(c => c.status === 'DRAFT' || c.status === 'SUBMITTED')
  );

  const rejectedClaims = computed(() => 
    claims.value.filter(c => c.status === 'REJECTED')
  );

  const approvedReferrals = computed(() => 
    referrals.value.filter(r => r.status === 'approved')
  );

  const pendingReferrals = computed(() => 
    referrals.value.filter(r => r.status === 'pending')
  );

  // Actions
  const setClaims = (data) => {
    claims.value = data;
  };

  const setReferrals = (data) => {
    referrals.value = data;
  };

  const setAdmissions = (data) => {
    admissions.value = data;
  };

  const setCurrentClaim = (claim) => {
    currentClaim.value = claim;
  };

  const setCurrentReferral = (referral) => {
    currentReferral.value = referral;
  };

  const setCurrentAdmission = (admission) => {
    currentAdmission.value = admission;
  };

  const setLoading = (state) => {
    loading.value = state;
  };

  const setError = (err) => {
    error.value = err;
  };

  const setFilters = (newFilters) => {
    filters.value = { ...filters.value, ...newFilters };
  };

  const addClaim = (claim) => {
    claims.value.push(claim);
  };

  const updateClaim = (id, updatedClaim) => {
    const index = claims.value.findIndex(c => c.id === id);
    if (index !== -1) {
      claims.value[index] = { ...claims.value[index], ...updatedClaim };
    }
  };

  const removeClaim = (id) => {
    claims.value = claims.value.filter(c => c.id !== id);
  };

  const addReferral = (referral) => {
    referrals.value.push(referral);
  };

  const updateReferral = (id, updatedReferral) => {
    const index = referrals.value.findIndex(r => r.id === id);
    if (index !== -1) {
      referrals.value[index] = { ...referrals.value[index], ...updatedReferral };
    }
  };

  const addAdmission = (admission) => {
    admissions.value.push(admission);
  };

  const updateAdmission = (id, updatedAdmission) => {
    const index = admissions.value.findIndex(a => a.id === id);
    if (index !== -1) {
      admissions.value[index] = { ...admissions.value[index], ...updatedAdmission };
    }
  };

  const clearCurrentClaim = () => {
    currentClaim.value = null;
  };

  const clearCurrentReferral = () => {
    currentReferral.value = null;
  };

  const clearCurrentAdmission = () => {
    currentAdmission.value = null;
  };

  const resetFilters = () => {
    filters.value = {
      status: null,
      facility_id: null,
      date_from: null,
      date_to: null,
    };
  };

  return {
    // State
    claims,
    referrals,
    admissions,
    currentClaim,
    currentReferral,
    currentAdmission,
    loading,
    error,
    filters,
    // Computed
    approvedClaims,
    pendingClaims,
    rejectedClaims,
    approvedReferrals,
    pendingReferrals,
    // Actions
    setClaims,
    setReferrals,
    setAdmissions,
    setCurrentClaim,
    setCurrentReferral,
    setCurrentAdmission,
    setLoading,
    setError,
    setFilters,
    addClaim,
    updateClaim,
    removeClaim,
    addReferral,
    updateReferral,
    addAdmission,
    updateAdmission,
    clearCurrentClaim,
    clearCurrentReferral,
    clearCurrentAdmission,
    resetFilters,
  };
});

