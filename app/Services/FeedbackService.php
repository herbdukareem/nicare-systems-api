<?php

namespace App\Services;

use App\Models\FeedbackRecord;
use App\Models\Referral;
use App\Models\PACode;
use App\Models\Admission;
use App\Models\Claim;
use Illuminate\Support\Facades\Auth;

class FeedbackService
{
    // Event type constants
    const EVENT_UTN_VALIDATED = 'UTN_VALIDATED';
    const EVENT_FUP_REQUESTED = 'FUP_REQUESTED';
    const EVENT_FUP_APPROVED = 'FUP_APPROVED';
    const EVENT_ADMISSION = 'ADMISSION';
    const EVENT_DISCHARGE = 'DISCHARGE';
    const EVENT_CLAIM_SUBMITTED = 'CLAIM_SUBMITTED';
    const EVENT_CLAIM_APPROVED = 'CLAIM_APPROVED';
    const EVENT_CLAIM_REJECTED = 'CLAIM_REJECTED';

    /**
     * Create automatic feedback when UTN is validated
     */
    public function createUTNValidatedFeedback(Referral $referral): FeedbackRecord
    {
        return $this->createSystemFeedback(
            $referral,
            self::EVENT_UTN_VALIDATED,
            'Enrollee has visited the facility.',
            'UTN validated - Enrollee confirmed at receiving facility.'
        );
    }

    /**
     * Create automatic feedback when FUP code is requested
     */
    public function createFUPRequestedFeedback(PACode $paCode): FeedbackRecord
    {
        $referral = $paCode->referral;
        
        return $this->createSystemFeedback(
            $referral,
            self::EVENT_FUP_REQUESTED,
            "Follow-up PA Code requested: {$paCode->pa_code}",
            "PA Code {$paCode->pa_code} has been requested for additional services.",
            $paCode->id
        );
    }

    /**
     * Create automatic feedback when FUP code is approved
     */
    public function createFUPApprovedFeedback(PACode $paCode): FeedbackRecord
    {
        $referral = $paCode->referral;
        
        return $this->createSystemFeedback(
            $referral,
            self::EVENT_FUP_APPROVED,
            "Follow-up PA Code approved: {$paCode->pa_code}",
            "PA Code {$paCode->pa_code} has been approved for services.",
            $paCode->id
        );
    }

    /**
     * Create automatic feedback when patient is admitted
     */
    public function createAdmissionFeedback(Admission $admission): FeedbackRecord
    {
        $referral = $admission->referral;
        
        return $this->createSystemFeedback(
            $referral,
            self::EVENT_ADMISSION,
            "Patient admitted: {$admission->admission_code}",
            "Patient has been admitted to the facility. Ward: {$admission->ward}"
        );
    }

    /**
     * Create automatic feedback when patient is discharged
     */
    public function createDischargeFeedback(Admission $admission): FeedbackRecord
    {
        $referral = $admission->referral;
        
        return $this->createSystemFeedback(
            $referral,
            self::EVENT_DISCHARGE,
            "Patient discharged: {$admission->admission_code}",
            "Patient has been discharged from the facility. Outcome: {$admission->discharge_status}"
        );
    }

    /**
     * Create automatic feedback when claim is submitted
     */
    public function createClaimSubmittedFeedback(Claim $claim): FeedbackRecord
    {
        $referral = $claim->referral;
        
        return $this->createSystemFeedback(
            $referral,
            self::EVENT_CLAIM_SUBMITTED,
            "Claim submitted: {$claim->claim_number}",
            "Claim has been submitted. Total amount: ₦" . number_format($claim->total_amount, 2)
        );
    }

    /**
     * Create a system-generated feedback record
     */
    protected function createSystemFeedback(
        Referral $referral,
        string $eventType,
        string $comments,
        string $observations,
        ?int $paCodeId = null
    ): FeedbackRecord {
        return FeedbackRecord::create([
            'enrollee_id' => $referral->enrollee_id,
            'referral_id' => $referral->id,
            'pa_code_id' => $paCodeId,
            'feedback_officer_id' => Auth::id() ?? 1, // System user fallback
            'feedback_type' => 'referral',
            'event_type' => $eventType,
            'is_system_generated' => true,
            'status' => 'completed',
            'priority' => 'low',
            'feedback_comments' => $comments,
            'officer_observations' => $observations,
            'feedback_date' => now(),
            'completed_at' => now(),
            'created_by' => Auth::id() ?? 1,
        ]);
    }
}

