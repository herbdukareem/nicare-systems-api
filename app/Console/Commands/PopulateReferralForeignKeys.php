<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Referral;
use App\Models\Facility;
use App\Models\Enrollee;
use Illuminate\Support\Facades\DB;

class PopulateReferralForeignKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'referrals:populate-foreign-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate foreign key fields for existing referrals';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to populate foreign keys for existing referrals...');

        $referrals = Referral::whereNull('referring_facility_id')
            ->orWhereNull('receiving_facility_id')
            ->orWhereNull('enrollee_id')
            ->get();

        $this->info("Found {$referrals->count()} referrals to update");

        $updated = 0;
        $errors = 0;

        foreach ($referrals as $referral) {
            try {
                DB::beginTransaction();

                // Find referring facility by NiCare code
                if (!$referral->referring_facility_id && $referral->referring_nicare_code) {
                    $referringFacility = Facility::where('hcp_code', $referral->referring_nicare_code)->first();
                    if ($referringFacility) {
                        $referral->referring_facility_id = $referringFacility->id;
                    }
                }

                // Find receiving facility by NiCare code
                if (!$referral->receiving_facility_id && $referral->receiving_nicare_code) {
                    $receivingFacility = Facility::where('hcp_code', $referral->receiving_nicare_code)->first();
                    if ($receivingFacility) {
                        $referral->receiving_facility_id = $receivingFacility->id;
                    }
                }

                // Find enrollee by NiCare number
                if (!$referral->enrollee_id && $referral->nicare_number) {
                    $enrollee = Enrollee::where('enrollee_id', $referral->nicare_number)->first();
                    if ($enrollee) {
                        $referral->enrollee_id = $enrollee->id;
                    }
                }

                $referral->save();
                DB::commit();

                $updated++;
                $this->info("Updated referral {$referral->referral_code}");

            } catch (\Exception $e) {
                DB::rollBack();
                $errors++;
                $this->error("Failed to update referral {$referral->referral_code}: " . $e->getMessage());
            }
        }

        $this->info("Completed! Updated: {$updated}, Errors: {$errors}");

        return Command::SUCCESS;
    }
}
