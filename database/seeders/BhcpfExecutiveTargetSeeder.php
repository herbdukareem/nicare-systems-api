<?php

namespace Database\Seeders;

use App\Models\BhcpfExecutiveTarget;
use App\Models\Benefactor;
use App\Models\EnrollmentPhase;
use App\Models\Lga;
use Illuminate\Database\Seeder;
use RuntimeException;

class BhcpfExecutiveTargetSeeder extends Seeder
{
    public function run(): void
    {
        $phase = $this->bhcpfEnrollmentPhase();

        foreach ($this->rows() as $row) {
            $normalizedLga = $this->normalizeLgaName($row['lga']);
            $lga = Lga::query()
                ->whereRaw('UPPER(name) = ?', [$normalizedLga])
                ->first();

            if (!$lga) {
                throw new RuntimeException("Unable to match BHCPF target row to LGA [{$row['lga']}].");
            }

            BhcpfExecutiveTarget::updateOrCreate(
                ['enrollment_phase_id' => $phase->id, 'lga_id' => $lga->id],
                [
                    'ward_count' => $row['ward_count'],
                    'current_enrollee_count' => $row['current_enrollee_count'],
                    'poverty_index' => $row['poverty_index'],
                    'proposed_enrolments' => $row['proposed_enrolments'],
                    'final_target' => $row['final_target'],
                    'plwd_target' => $row['plwd_target'],
                    'under_5_target' => $row['under_5_target'],
                    'female_reproductive_target' => $row['female_reproductive_target'],
                    'elderly_target' => $row['elderly_target'],
                    'others_target' => $row['others_target'],
                    'proposed_per_ward' => $row['proposed_per_ward'],
                    'plwd_per_ward' => $row['plwd_per_ward'],
                    'under_5_per_ward' => $row['under_5_per_ward'],
                    'female_reproductive_per_ward' => $row['female_reproductive_per_ward'],
                    'elderly_per_ward' => $row['elderly_per_ward'],
                    'others_per_ward' => $row['others_per_ward'],
                ]
            );
        }
    }

    private function bhcpfEnrollmentPhase(): EnrollmentPhase
    {
        $benefactor = Benefactor::query()
            ->whereRaw('UPPER(name) = ?', ['BHCPF'])
            ->firstOrFail();

        return EnrollmentPhase::query()->firstOrCreate(
            ['name' => '65K BHCPF Enrollment'],
            [
                'benefactor_id' => $benefactor->id,
                'status' => 1,
                'is_current' => true,
                'start_date' => '2026-08-03',
                'end_date' => '2026-12-31',
            ]
        );
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function rows(): array
    {
        return [
            ['lga' => 'AGAIE', 'ward_count' => 11, 'current_enrollee_count' => 3092, 'poverty_index' => 34, 'proposed_enrolments' => 2966, 'final_target' => 6058, 'plwd_target' => 297, 'under_5_target' => 653, 'female_reproductive_target' => 860, 'elderly_target' => 208, 'others_target' => 949, 'proposed_per_ward' => 269, 'plwd_per_ward' => 27, 'under_5_per_ward' => 59, 'female_reproductive_per_ward' => 78, 'elderly_per_ward' => 19, 'others_per_ward' => 86],
            ['lga' => 'AGWARA', 'ward_count' => 10, 'current_enrollee_count' => 3272, 'poverty_index' => 41, 'proposed_enrolments' => 3577, 'final_target' => 6849, 'plwd_target' => 358, 'under_5_target' => 787, 'female_reproductive_target' => 1037, 'elderly_target' => 250, 'others_target' => 1145, 'proposed_per_ward' => 358, 'plwd_per_ward' => 36, 'under_5_per_ward' => 79, 'female_reproductive_per_ward' => 104, 'elderly_per_ward' => 25, 'others_per_ward' => 114],
            ['lga' => 'BIDA', 'ward_count' => 14, 'current_enrollee_count' => 5462, 'poverty_index' => 18, 'proposed_enrolments' => 1570, 'final_target' => 7032, 'plwd_target' => 157, 'under_5_target' => 345, 'female_reproductive_target' => 455, 'elderly_target' => 110, 'others_target' => 502, 'proposed_per_ward' => 112, 'plwd_per_ward' => 11, 'under_5_per_ward' => 25, 'female_reproductive_per_ward' => 33, 'elderly_per_ward' => 8, 'others_per_ward' => 36],
            ['lga' => 'BORGU', 'ward_count' => 10, 'current_enrollee_count' => 2211, 'poverty_index' => 32, 'proposed_enrolments' => 2792, 'final_target' => 5003, 'plwd_target' => 279, 'under_5_target' => 614, 'female_reproductive_target' => 810, 'elderly_target' => 195, 'others_target' => 893, 'proposed_per_ward' => 279, 'plwd_per_ward' => 28, 'under_5_per_ward' => 61, 'female_reproductive_per_ward' => 81, 'elderly_per_ward' => 20, 'others_per_ward' => 89],
            ['lga' => 'BOSSO', 'ward_count' => 10, 'current_enrollee_count' => 4224, 'poverty_index' => 25, 'proposed_enrolments' => 2181, 'final_target' => 6405, 'plwd_target' => 218, 'under_5_target' => 480, 'female_reproductive_target' => 632, 'elderly_target' => 153, 'others_target' => 698, 'proposed_per_ward' => 218, 'plwd_per_ward' => 22, 'under_5_per_ward' => 48, 'female_reproductive_per_ward' => 63, 'elderly_per_ward' => 15, 'others_per_ward' => 70],
            ['lga' => 'CHANCHAGA', 'ward_count' => 11, 'current_enrollee_count' => 6119, 'poverty_index' => 11, 'proposed_enrolments' => 960, 'final_target' => 7079, 'plwd_target' => 96, 'under_5_target' => 211, 'female_reproductive_target' => 278, 'elderly_target' => 67, 'others_target' => 307, 'proposed_per_ward' => 87, 'plwd_per_ward' => 9, 'under_5_per_ward' => 19, 'female_reproductive_per_ward' => 25, 'elderly_per_ward' => 6, 'others_per_ward' => 28],
            ['lga' => 'EDATI', 'ward_count' => 10, 'current_enrollee_count' => 3022, 'poverty_index' => 32, 'proposed_enrolments' => 2792, 'final_target' => 5814, 'plwd_target' => 279, 'under_5_target' => 614, 'female_reproductive_target' => 810, 'elderly_target' => 195, 'others_target' => 893, 'proposed_per_ward' => 279, 'plwd_per_ward' => 28, 'under_5_per_ward' => 61, 'female_reproductive_per_ward' => 81, 'elderly_per_ward' => 20, 'others_per_ward' => 89],
            ['lga' => 'GBAKO', 'ward_count' => 10, 'current_enrollee_count' => 4261, 'poverty_index' => 37, 'proposed_enrolments' => 3228, 'final_target' => 7489, 'plwd_target' => 323, 'under_5_target' => 710, 'female_reproductive_target' => 936, 'elderly_target' => 226, 'others_target' => 1033, 'proposed_per_ward' => 323, 'plwd_per_ward' => 32, 'under_5_per_ward' => 71, 'female_reproductive_per_ward' => 94, 'elderly_per_ward' => 23, 'others_per_ward' => 103],
            ['lga' => 'GURARA', 'ward_count' => 10, 'current_enrollee_count' => 2389, 'poverty_index' => 30, 'proposed_enrolments' => 2617, 'final_target' => 5006, 'plwd_target' => 262, 'under_5_target' => 576, 'female_reproductive_target' => 759, 'elderly_target' => 183, 'others_target' => 837, 'proposed_per_ward' => 262, 'plwd_per_ward' => 26, 'under_5_per_ward' => 58, 'female_reproductive_per_ward' => 76, 'elderly_per_ward' => 18, 'others_per_ward' => 84],
            ['lga' => 'KATCHA', 'ward_count' => 10, 'current_enrollee_count' => 1885, 'poverty_index' => 35, 'proposed_enrolments' => 3054, 'final_target' => 4939, 'plwd_target' => 305, 'under_5_target' => 672, 'female_reproductive_target' => 886, 'elderly_target' => 214, 'others_target' => 977, 'proposed_per_ward' => 305, 'plwd_per_ward' => 31, 'under_5_per_ward' => 67, 'female_reproductive_per_ward' => 89, 'elderly_per_ward' => 21, 'others_per_ward' => 98],
            ['lga' => 'KONTAGORA', 'ward_count' => 13, 'current_enrollee_count' => 2171, 'poverty_index' => 18, 'proposed_enrolments' => 1570, 'final_target' => 3741, 'plwd_target' => 157, 'under_5_target' => 345, 'female_reproductive_target' => 455, 'elderly_target' => 110, 'others_target' => 502, 'proposed_per_ward' => 120, 'plwd_per_ward' => 12, 'under_5_per_ward' => 26, 'female_reproductive_per_ward' => 35, 'elderly_per_ward' => 8, 'others_per_ward' => 38],
            ['lga' => 'LAPAI', 'ward_count' => 10, 'current_enrollee_count' => 4494, 'poverty_index' => 25, 'proposed_enrolments' => 2181, 'final_target' => 6675, 'plwd_target' => 218, 'under_5_target' => 480, 'female_reproductive_target' => 632, 'elderly_target' => 153, 'others_target' => 698, 'proposed_per_ward' => 218, 'plwd_per_ward' => 22, 'under_5_per_ward' => 48, 'female_reproductive_per_ward' => 63, 'elderly_per_ward' => 15, 'others_per_ward' => 70],
            ['lga' => 'LAVUN', 'ward_count' => 12, 'current_enrollee_count' => 3622, 'poverty_index' => 35, 'proposed_enrolments' => 3054, 'final_target' => 6676, 'plwd_target' => 305, 'under_5_target' => 672, 'female_reproductive_target' => 886, 'elderly_target' => 214, 'others_target' => 977, 'proposed_per_ward' => 250, 'plwd_per_ward' => 25, 'under_5_per_ward' => 55, 'female_reproductive_per_ward' => 73, 'elderly_per_ward' => 18, 'others_per_ward' => 80],
            ['lga' => 'MAGAMA', 'ward_count' => 11, 'current_enrollee_count' => 4501, 'poverty_index' => 33, 'proposed_enrolments' => 2880, 'final_target' => 7381, 'plwd_target' => 288, 'under_5_target' => 634, 'female_reproductive_target' => 835, 'elderly_target' => 202, 'others_target' => 922, 'proposed_per_ward' => 261, 'plwd_per_ward' => 26, 'under_5_per_ward' => 57, 'female_reproductive_per_ward' => 76, 'elderly_per_ward' => 18, 'others_per_ward' => 84],
            ['lga' => 'MARIGA', 'ward_count' => 11, 'current_enrollee_count' => 1895, 'poverty_index' => 35, 'proposed_enrolments' => 3054, 'final_target' => 4949, 'plwd_target' => 305, 'under_5_target' => 672, 'female_reproductive_target' => 886, 'elderly_target' => 214, 'others_target' => 977, 'proposed_per_ward' => 277, 'plwd_per_ward' => 28, 'under_5_per_ward' => 61, 'female_reproductive_per_ward' => 80, 'elderly_per_ward' => 19, 'others_per_ward' => 89],
            ['lga' => 'MASHEGU', 'ward_count' => 10, 'current_enrollee_count' => 4285, 'poverty_index' => 29, 'proposed_enrolments' => 2530, 'final_target' => 6815, 'plwd_target' => 253, 'under_5_target' => 557, 'female_reproductive_target' => 734, 'elderly_target' => 177, 'others_target' => 810, 'proposed_per_ward' => 253, 'plwd_per_ward' => 25, 'under_5_per_ward' => 56, 'female_reproductive_per_ward' => 73, 'elderly_per_ward' => 18, 'others_per_ward' => 81],
            ['lga' => 'MOKWA', 'ward_count' => 11, 'current_enrollee_count' => 2174, 'poverty_index' => 27, 'proposed_enrolments' => 2356, 'final_target' => 4530, 'plwd_target' => 236, 'under_5_target' => 518, 'female_reproductive_target' => 683, 'elderly_target' => 165, 'others_target' => 754, 'proposed_per_ward' => 214, 'plwd_per_ward' => 21, 'under_5_per_ward' => 47, 'female_reproductive_per_ward' => 62, 'elderly_per_ward' => 15, 'others_per_ward' => 69],
            ['lga' => 'MUNYA', 'ward_count' => 11, 'current_enrollee_count' => 1785, 'poverty_index' => 36, 'proposed_enrolments' => 3141, 'final_target' => 4926, 'plwd_target' => 314, 'under_5_target' => 691, 'female_reproductive_target' => 911, 'elderly_target' => 220, 'others_target' => 1005, 'proposed_per_ward' => 285, 'plwd_per_ward' => 29, 'under_5_per_ward' => 63, 'female_reproductive_per_ward' => 83, 'elderly_per_ward' => 20, 'others_per_ward' => 91],
            ['lga' => 'PAIKORO', 'ward_count' => 11, 'current_enrollee_count' => 2761, 'poverty_index' => 34, 'proposed_enrolments' => 2966, 'final_target' => 5727, 'plwd_target' => 297, 'under_5_target' => 653, 'female_reproductive_target' => 860, 'elderly_target' => 208, 'others_target' => 949, 'proposed_per_ward' => 212, 'plwd_per_ward' => 21, 'under_5_per_ward' => 47, 'female_reproductive_per_ward' => 61, 'elderly_per_ward' => 15, 'others_per_ward' => 68],
            ['lga' => 'RAFI', 'ward_count' => 11, 'current_enrollee_count' => 944, 'poverty_index' => 28, 'proposed_enrolments' => 2443, 'final_target' => 3387, 'plwd_target' => 244, 'under_5_target' => 537, 'female_reproductive_target' => 708, 'elderly_target' => 171, 'others_target' => 782, 'proposed_per_ward' => 222, 'plwd_per_ward' => 22, 'under_5_per_ward' => 49, 'female_reproductive_per_ward' => 64, 'elderly_per_ward' => 16, 'others_per_ward' => 71],
            ['lga' => 'RIJAU', 'ward_count' => 11, 'current_enrollee_count' => 3811, 'poverty_index' => 36, 'proposed_enrolments' => 3141, 'final_target' => 6952, 'plwd_target' => 314, 'under_5_target' => 691, 'female_reproductive_target' => 911, 'elderly_target' => 220, 'others_target' => 1005, 'proposed_per_ward' => 285, 'plwd_per_ward' => 29, 'under_5_per_ward' => 63, 'female_reproductive_per_ward' => 83, 'elderly_per_ward' => 20, 'others_per_ward' => 91],
            ['lga' => 'SHIRORO', 'ward_count' => 15, 'current_enrollee_count' => 1470, 'poverty_index' => 37, 'proposed_enrolments' => 3228, 'final_target' => 4698, 'plwd_target' => 323, 'under_5_target' => 710, 'female_reproductive_target' => 936, 'elderly_target' => 226, 'others_target' => 1033, 'proposed_per_ward' => 215, 'plwd_per_ward' => 22, 'under_5_per_ward' => 47, 'female_reproductive_per_ward' => 62, 'elderly_per_ward' => 15, 'others_per_ward' => 69],
            ['lga' => 'SULEJA', 'ward_count' => 10, 'current_enrollee_count' => 3940, 'poverty_index' => 23, 'proposed_enrolments' => 2007, 'final_target' => 5947, 'plwd_target' => 201, 'under_5_target' => 442, 'female_reproductive_target' => 582, 'elderly_target' => 140, 'others_target' => 642, 'proposed_per_ward' => 200, 'plwd_per_ward' => 20, 'under_5_per_ward' => 44, 'female_reproductive_per_ward' => 58, 'elderly_per_ward' => 14, 'others_per_ward' => 64],
            ['lga' => 'TAFA', 'ward_count' => 10, 'current_enrollee_count' => 2625, 'poverty_index' => 22, 'proposed_enrolments' => 1919, 'final_target' => 4544, 'plwd_target' => 192, 'under_5_target' => 422, 'female_reproductive_target' => 557, 'elderly_target' => 134, 'others_target' => 614, 'proposed_per_ward' => 191, 'plwd_per_ward' => 19, 'under_5_per_ward' => 42, 'female_reproductive_per_ward' => 55, 'elderly_per_ward' => 13, 'others_per_ward' => 61],
            ['lga' => 'WUSHISHI', 'ward_count' => 11, 'current_enrollee_count' => 2037, 'poverty_index' => 32, 'proposed_enrolments' => 2792, 'final_target' => 4829, 'plwd_target' => 279, 'under_5_target' => 614, 'female_reproductive_target' => 810, 'elderly_target' => 195, 'others_target' => 893, 'proposed_per_ward' => 254, 'plwd_per_ward' => 25, 'under_5_per_ward' => 56, 'female_reproductive_per_ward' => 74, 'elderly_per_ward' => 18, 'others_per_ward' => 81],
        ];
    }

    private function normalizeLgaName(string $name): string
    {
        $normalized = strtoupper(trim($name));

        return [
            'MUNYA' => 'MUYA',
        ][$normalized] ?? $normalized;
    }
}
