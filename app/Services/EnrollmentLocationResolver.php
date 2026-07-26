<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\Ward;

class EnrollmentLocationResolver
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function resolve(array $data): array
    {
        if (!empty($data['facility_id'])) {
            $facility = Facility::query()
                ->select(['id', 'lga_id', 'ward_id'])
                ->find($data['facility_id']);

            if ($facility) {
                if (!empty($facility->lga_id)) {
                    $data['lga_id'] = (int) $facility->lga_id;
                }

                if (!empty($facility->ward_id)) {
                    $data['ward_id'] = (int) $facility->ward_id;
                }
            }

            return $data;
        }

        if (!empty($data['ward_id'])) {
            $ward = Ward::query()
                ->select(['id', 'lga_id'])
                ->find($data['ward_id']);

            if ($ward && !empty($ward->lga_id)) {
                $data['lga_id'] = (int) $ward->lga_id;
            }
        }

        return $data;
    }
}
