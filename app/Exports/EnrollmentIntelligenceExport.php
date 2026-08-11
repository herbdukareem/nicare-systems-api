<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class EnrollmentIntelligenceExport implements WithMultipleSheets
{
    /**
     * @param  array<int, array<int|string, mixed>>  $summaryRows
     * @param  array<int, array<int|string, mixed>>  $verificationRows
     * @param  array<int, array<int|string, mixed>>  $facilityRows
     * @param  array<int, array<int|string, mixed>>  $officerRows
     */
    public function __construct(
        private readonly array $summaryRows,
        private readonly array $verificationRows,
        private readonly array $facilityRows,
        private readonly array $officerRows,
    ) {
    }

    public function sheets(): array
    {
        return [
            $this->makeSheet(
                'Summary',
                ['Section', 'Metric', 'Value'],
                $this->summaryRows
            ),
            $this->makeSheet(
                'Recent Verifications',
                ['Enrollee ID', 'Full Name', 'NIN', 'Status', 'Source', 'Provider', 'Facility', 'LGA', 'Programme', 'Premium Plan', 'Phone', 'Verified By', 'Verified At', 'Failure Note'],
                $this->verificationRows
            ),
            $this->makeSheet(
                'Facility Summary',
                ['Facility', 'LGA', 'Captured', 'Pending Approval', 'Approved', 'Rejected', 'Duplicates', 'NIN Attempts', 'Verified', 'Failed', 'Value'],
                $this->facilityRows
            ),
            $this->makeSheet(
                'Officer Summary',
                ['Enrollment Officer', 'Source', 'Captured', 'Pending Approval', 'Approved', 'Rejected', 'Duplicates', 'NIN Attempts', 'Verified', 'Failed', 'Value'],
                $this->officerRows
            ),
        ];
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int|string, mixed>>  $rows
     */
    private function makeSheet(string $title, array $headings, array $rows): object
    {
        return new class($title, $headings, $rows) implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
        {
            /**
             * @param  array<int, string>  $headings
             * @param  array<int, array<int|string, mixed>>  $rows
             */
            public function __construct(
                private readonly string $title,
                private readonly array $headings,
                private readonly array $rows,
            ) {
            }

            public function array(): array
            {
                return array_map(
                    static fn (array $row): array => array_values($row),
                    $this->rows
                );
            }

            public function headings(): array
            {
                return $this->headings;
            }

            public function title(): string
            {
                return substr($this->title, 0, 31);
            }
        };
    }
}
