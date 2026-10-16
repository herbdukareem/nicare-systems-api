<?php

namespace App\Services;

use App\Models\Enrollee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EnrollmentSlipCollectionListService
{
    public const MAX_ROWS = 2000;

    public function __construct(private EnrollmentSlipPrintingService $printing)
    {
    }

    public function rows(array $filters, int $userId): Collection
    {
        if (!empty($filters['batch_id'])) {
            $batch = $this->printing->batch($filters['batch_id'], $userId);
            abort_unless($batch->status === 'completed', 409, 'Collection lists are available for completed printing exercises only.');
            $query = Enrollee::query()->whereIn('id', $batch->items()->select('enrollee_id'));
        } else {
            // This read-only report always includes generated slips, regardless of
            // the printing-queue default or an individual identifier lookup.
            $query = $this->printing->query(array_merge($filters, ['printing_status' => 'generated']));
        }
        $query->whereNotNull('slip_first_generated_at');
        if (!empty($filters['facility_lga_id'])) {
            $query->whereHas('facility', fn ($q) => $q->where('lga_id', $filters['facility_lga_id']));
        }
        if (!empty($filters['enrollee_ids'])) {
            $query->whereKey($filters['enrollee_ids']);
        }
        $count = (clone $query)->count();
        if (!empty($filters['enrollee_ids']) && $count !== count($filters['enrollee_ids'])) {
            throw ValidationException::withMessages(['enrollee_ids' => 'Select only enrolments whose slips have already been generated and match the current filters.']);
        }
        abort_if($count === 0, 404, 'No previously generated slips match this collection list.');
        if ($count > self::MAX_ROWS) {
            throw ValidationException::withMessages(['facility_id' => 'A collection list can contain up to '.self::MAX_ROWS.' enrolments. Choose a facility, narrow the filters, or select fewer enrolments.']);
        }
        return $query->select([
            'id', 'facility_id', 'enrollee_id', 'first_name', 'middle_name', 'last_name',
            'nin', 'phone', 'address',
        ])->with(['facility.lga:id,name', 'facility.ward:id,name'])
            ->orderBy('facility_id')->orderBy('last_name')->orderBy('first_name')->orderBy('id')->get();
    }

    public function pdf(Collection $enrollees, ?string $batchId = null): string
    {
        $groups = $enrollees->groupBy(fn (Enrollee $enrollee) => $enrollee->facility_id ?? 'unassigned');
        $pdf = Pdf::setOptions(['isRemoteEnabled' => false])->loadView('pdf.enrollment-slip-collection-list', [
            'groups' => $groups, 'printedAt' => now('Africa/Lagos'), 'batchId' => $batchId,
        ])->setPaper('a4', 'landscape');
        $pdf->render();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $pdf->getDomPDF()->getCanvas()->page_text(735, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.3, 0.3, 0.3]);
        return $pdf->output();
    }
}
