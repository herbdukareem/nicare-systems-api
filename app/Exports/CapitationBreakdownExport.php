<?php

namespace App\Exports;

use App\Models\Capitation;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class CapitationBreakdownExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly Capitation $capitation,
        private readonly Collection $details,
        private readonly string $fundingTypeLabel,
    ) {
    }

    public function collection(): Collection
    {
        return $this->details;
    }

    public function headings(): array
    {
        return [
            'Capitation Period',
            'Capitation Month',
            'Funding Type',
            'Facility',
            'Enrollee Count',
            'Rate (NGN)',
            'Amount (NGN)',
            'Account Number',
            'Account Name',
            'Bank',
            'Status',
        ];
    }

    public function map($detail): array
    {
        $account = $detail->facility?->accountDetail;

        return [
            $this->capitation->name,
            $this->capitation->capitation_month,
            $detail->fundingType?->name ?? $this->fundingTypeLabel,
            $detail->facility?->name ?? 'N/A',
            (int) ($detail->total_enrollees ?? $detail->total_enrolled ?? 0),
            (float) ($detail->capitation_rate ?? $detail->rate ?? 0),
            (float) ($detail->total_amount ?? $detail->amount ?? 0),
            $account?->account_number,
            $account?->account_name,
            $account?->bank?->name,
            $this->statusLabel($detail),
        ];
    }

    public function title(): string
    {
        return 'Capitation Breakdown';
    }

    private function statusLabel($detail): string
    {
        if ($detail->paid_at) return 'Paid';
        if ($detail->approved_at) return 'Approved';
        if ($detail->reviewed_at) return 'Reviewed';

        return 'Generated';
    }
}
