// src/composables/useClaimSubmission.js

import { ref, computed } from 'vue';
// 💡 IMPORTANT: Adjust this path to your actual central API file
import { claimAPI, referralAPI, paCodeAPI } from '@/js/api/api'; 


export const useClaimSubmission = () => {
    const loading = ref(false);
    const error = ref(null);
    const utnKey = ref('');
    // authData stores approved { referral, paCodes }
    const authData = ref(null); 

    const initialFormData = {
        enrollee_id: null,
        facility_id: null, 
        claim_date: new Date().toISOString().split('T')[0],
        claim_type: 'HYBRID', 
        clinical_summary: '',
        claim_lines: [], 
    };
    
    const formData = ref({ ...initialFormData });

    // Computed total from line items 
    const totalClaimAmount = computed(() => {
        const total = formData.value.claim_lines.reduce((sum, line) => {
            // Ensure line properties are present before multiplying
            const lineTotal = (line.quantity || 0) * (line.unit_price || 0);
            return sum + lineTotal;
        }, 0);
        return parseFloat(total).toFixed(2); 
    });

    /**
     * Fetches and sets the authorization context (Referral & PA Codes) based on UTN.
     * Replaces the mockApi.referral.getByUtn and mockApi.paCode.getApprovedByReferral calls.
     */
    const fetchAuthorization = async () => {
        if (!utnKey.value) {
            error.value = 'UTN is required.';
            return;
        }

        loading.value = true;
        authData.value = null;
        error.value = null;

        try {
            // 1. Get the Referral by UTN
            const referralResponse = await referralAPI.getByUtn(utnKey.value);
            // Assuming data is nested: response.data.data
            const referral = referralResponse.data.data; 
            
            if (!referral || referral.status !== 'APPROVED') {
                throw new Error('UTN is invalid or referral is not approved.');
            }
            
            // 2. Get all approved PA codes linked to this Referral ID
            const paCodeResponse = await paCodeAPI.getApprovedByReferral(referral.id);
            // Assuming data is nested: response.data.data
            const paCodes = paCodeResponse.data.data; 
            
            if (paCodes.length === 0) {
                throw new Error('No approved Pre-Authorization services found for this UTN.');
            }

            authData.value = { referral, paCodes };
            
            // Auto-populate claim header fields from the referral
            formData.value.enrollee_id = referral.enrollee_id;
            // The ClaimController uses facility_id linked to the receiving facility
            formData.value.facility_id = referral.receiving_facility_id; 
            
            error.value = null;
            
        } catch (err) {
            // Standardized API error handling
            const message = err.response?.data?.message || err.message || 'Authorization lookup failed.';
            error.value = message;
            authData.value = null; 
            // Re-throw the error to be handled by the component
            throw message; 
        } finally {
            loading.value = false;
        }
    };


    /**
     * Submits the final claim payload to the backend using ClaimController@store.
     * Replaces the mockApi.claim.submit call.
     */
    const createClaim = async () => {
        if (!authData.value) {
            throw new Error('Authorization required. Please validate UTN first.');
        }

        loading.value = true;
        error.value = null;

        try {
            const payload = {
                // UTN must be included in the payload for validation in ClaimController@store
                utn_key: utnKey.value, 
                ...formData.value,
                // The backend expects total_amount for validation
                total_amount: totalClaimAmount.value, 
            };
            
            // Use the real API (ClaimController@store)
            const response = await claimAPI.create(payload); 
            
            // Assuming the backend returns the created claim object nested under 'data'
            return response.data.data; 

        } catch (err) {
            // Standardized API error handling
            const message = err.response?.data?.message || err.message || 'Failed to submit claim due to server error.';
            const validationErrors = err.response?.data?.errors;
            
            if (validationErrors) {
                // Log and throw specific validation errors from Laravel
                error.value = 'Validation failed. Please check your form data.';
                console.error('Validation Errors:', validationErrors);
                throw { message: error.value, errors: validationErrors };
            }

            error.value = message;
            throw message;
        } finally {
            loading.value = false;
        }
    };
    
    // Updates the line total whenever quantity or price changes
    const updateLineItemAmount = (line) => {
        const total = (line.quantity || 0) * (line.unit_price || 0);
        line.amount = parseFloat(total).toFixed(2);
    };

    return {
        loading,
        error,
        utnKey,
        authData,
        formData,
        totalClaimAmount,
        fetchAuthorization,
        createClaim,
        updateLineItemAmount,
    };
};