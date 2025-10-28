<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CaseRecord;
use App\Models\CaseGroup;
use Illuminate\Support\Facades\DB;

class UpdateCaseGroupIdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * This seeder updates the case_group_id column in the cases table
     * by matching the group column value with the name in case_groups table.
     */
    public function run(): void
    {
        $this->command->info('Starting to update case_group_id for all cases...');

        // Get all case groups
        $caseGroups = CaseGroup::all()->keyBy('name');
        
        if ($caseGroups->isEmpty()) {
            $this->command->error('No case groups found! Please run CaseGroupSeeder first.');
            return;
        }

        $this->command->info('Found ' . $caseGroups->count() . ' case groups.');

        // Get all cases
        $cases = CaseRecord::all();
        
        if ($cases->isEmpty()) {
            $this->command->warn('No cases found to update.');
            return;
        }

        $this->command->info('Found ' . $cases->count() . ' cases to process.');

        $updated = 0;
        $notFound = 0;
        $alreadySet = 0;

        DB::beginTransaction();

        try {
            foreach ($cases as $case) {
                // Skip if case_group_id is already set
                if ($case->case_group_id) {
                    $alreadySet++;
                    continue;
                }

                // Get the group name from the case
                $groupName = trim($case->group);

                if (empty($groupName)) {
                    $this->command->warn("Case ID {$case->id} has no group name.");
                    $notFound++;
                    continue;
                }

                // Find matching case group
                $caseGroup = $caseGroups->get($groupName);

                if ($caseGroup) {
                    $case->update(['case_group_id' => $caseGroup->id]);
                    $updated++;
                    
                    if ($updated % 100 === 0) {
                        $this->command->info("Updated {$updated} cases...");
                    }
                } else {
                    $this->command->warn("No case group found for: '{$groupName}' (Case ID: {$case->id})");
                    $notFound++;
                }
            }

            DB::commit();

            $this->command->info('');
            $this->command->info('=== Update Summary ===');
            $this->command->info("Total cases processed: {$cases->count()}");
            $this->command->info("Successfully updated: {$updated}");
            $this->command->info("Already had case_group_id: {$alreadySet}");
            $this->command->info("Not found/No group: {$notFound}");
            $this->command->info('');
            $this->command->info('Case group update completed successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error updating cases: ' . $e->getMessage());
            throw $e;
        }
    }
}

