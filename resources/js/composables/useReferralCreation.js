
import { ref } from 'vue';
// 💡 IMPORTANT: Ensure referralAPI is exported from your central API file
import { referralAPI } from '@/js/api/api'; 


export const useReferralCreation = () => {
    const loading = ref(false);
    const error = ref(null);
    // Stores the newly created referral object, including the UTN
    const createdReferral = ref(null);

    const formData = ref({
        enrollee_id: null,
        referring_facility_id: null, 
        receiving_facility_id: null, 
        diagnosis: '',
        clinical_history: '',
        level_of_care_requested: 'Secondary', 
        requested_services: [], // Array of { case_record_id: 1, quantity: 1 }
    });

    const addService = () => {
        formData.value.requested_services.push({
            case_record_id: null,
            quantity: 1,
        });
    };

    const removeService = (index) => {
        formData.value.requested_services.splice(index, 1);
    };

    /**
     * Submits the referral request to the backend.
     */
    const submitReferral = async () => {
        loading.value = true;
        error.value = null;
        createdReferral.value = null;

        try {
            if (formData.value.requested_services.length === 0) {
                 throw new Error("You must request at least one service/tariff item.");
            }

            // Use the real API: POST /api/referrals
            const response = await referralAPI.create(formData.value);
            
            // Assuming data is nested under 'data'
            createdReferral.value = response.data.data;
            
            return createdReferral.value;

        } catch (err) {
            const message = err.response?.data?.message || err.message || 'Failed to create referral request.';
            const validationErrors = err.response?.data?.errors;
            
            if (validationErrors) {
                error.value = 'Validation failed. Please check your form data.';
                // Log/handle validation errors specifically if needed
            } else {
                error.value = message;
            }
            throw message; 
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        createdReferral,
        formData,
        addService,
        removeService,
        submitReferral,
        // Helper data (mocked for this demo, must be replaced by real API calls)
        careLevels: ['Primary', 'Secondary', 'Tertiary'],
    };
};