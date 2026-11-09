<?php

namespace App\Exports;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use SplFileObject;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FacilitiesExport
{
    public const HEADINGS = [
        'S/N',
        'Facility Name',
        'NHIA Provider Code',
        'Programme',
        'Level of Care',
        'Ownership',
        'Account Name',
        'Bank Name',
        'Account Number',
        'Local Government Area',
        'Facility Address',
        'Contact Person',
        'Telephone Number',
        'Verification Status',
        'Date Verified',
        'Remarks',
    ];

    public function __construct(private readonly array $filters = []) {}

    public function query(): Builder
    {
        $query = Facility::query()
            ->select([
                'facilities.id',
                'facilities.name',
                'facilities.hcp_code',
                'facilities.type',
                'facilities.ownership',
                'facilities.address',
                'facilities.phone',
                'facility_account_details.account_name',
                'facility_account_details.account_number',
                'facility_account_details.is_verified as account_is_verified',
                'facility_account_details.verified_at as account_verified_at',
                'facility_banks.name as bank_name',
                'facility_lgas.name as lga_name',
            ])
            ->leftJoin('account_details as facility_account_details', 'facility_account_details.id', '=', 'facilities.account_detail_id')
            ->leftJoin('banks as facility_banks', 'facility_banks.id', '=', 'facility_account_details.bank_id')
            ->leftJoin('lgas as facility_lgas', 'facility_lgas.id', '=', 'facilities.lga_id');

        $search = trim((string) ($this->filters['search'] ?? ''));
        $query->when($search !== '', function (Builder $query) use ($search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('facilities.name', 'like', "%{$search}%")
                    ->orWhere('facilities.hcp_code', 'like', "%{$search}%")
                    ->orWhere('facilities.address', 'like', "%{$search}%")
                    ->orWhere('facilities.phone', 'like', "%{$search}%");
            });
        });

        foreach (['type', 'ownership', 'lga_id', 'ward_id', 'accreditation_status'] as $filter) {
            if (($this->filters[$filter] ?? null) !== null && $this->filters[$filter] !== '') {
                $query->where("facilities.{$filter}", $this->filters[$filter]);
            }
        }

        if (($this->filters['status'] ?? null) !== null && $this->filters['status'] !== '') {
            $query->where('facilities.status', $this->filters['status']);
        }

        return $query->orderBy('facilities.name')->orderBy('facilities.id');
    }

    public function download(): BinaryFileResponse
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(240);
        }

        $tempPath = $this->temporaryFilePath();
        $file = new SplFileObject($tempPath, 'w+b');
        $query = $this->query();
        $total = (clone $query)->toBase()->count();

        $this->write($file, "\xEF\xBB\xBF".$this->header());

        $serial = 1;
        foreach ($query->toBase()->cursor() as $facility) {
            $values = $this->map($facility, $serial++);
            $html = '<tr>';
            foreach ($values as $column => $value) {
                $class = in_array($column, [2, 8, 12], true) ? ' class="text"' : '';
                if ($column === 13) {
                    $class = ' class="verification"';
                }
                $html .= '<td'.$class.'>'.e((string) ($value ?? '')).'</td>';
            }
            $this->write($file, $html.'</tr>');
        }

        if ($total === 0) {
            $this->write($file, '<tr><td colspan="16" class="empty">No facilities found</td></tr>');
        }

        $this->write($file, '</table></body></html>');
        if (! $file->fflush()) {
            throw new RuntimeException('Unable to finish writing the facilities export file.');
        }
        $file = null;

        return (new BinaryFileResponse($tempPath, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Cache-Control' => 'no-store, private',
        ], false))
            ->setContentDisposition('attachment', 'Facilities_Account_Details_'.now()->format('Y-m-d').'.xls')
            ->deleteFileAfterSend(true);
    }

    private function map(object $facility, int $serial): array
    {
        return [
            $serial,
            $facility->name,
            $facility->hcp_code,
            'BHCPF',
            $facility->type,
            $facility->ownership,
            $facility->account_name,
            $facility->bank_name,
            $facility->account_number,
            $facility->lga_name,
            $facility->address,
            '',
            $facility->phone,
            ((int) $facility->account_is_verified === 1) ? 'Verified' : 'Pending verification',
            $this->formatDate($facility->account_verified_at),
            '',
        ];
    }

    private function header(): string
    {
        $title = 'ACCREDITED HEALTHCARE PROVIDERS – ACCOUNT DETAILS COLLECTION';
        $headings = implode('', array_map(fn (string $heading): string => '<th>'.e($heading).'</th>', self::HEADINGS));

        return '<html><head><meta charset="UTF-8"><style>'
            .'body{font-family:Arial,sans-serif;font-size:10pt}'
            .'table{border-collapse:collapse;width:100%}'
            .'td,th{border:1px solid #000;padding:4px 6px;vertical-align:middle}'
            .'th{background:#1f4e78;color:#fff;font-weight:bold;text-align:center;white-space:normal}'
            .'td{background:#fffdf2}'
            .'td.verification{background:#fff2cc;color:#7f6000}'
            .'td.text{mso-number-format:"\\@"}'
            .'.title{background:#1f4e78;color:#fff;font-size:14pt;font-weight:bold;text-align:center;border:0;height:28px}'
            .'.empty{text-align:center;font-style:italic}'
            .'</style></head><body><table>'
            .'<tr><td class="title" colspan="16">'.e($title).'</td></tr>'
            .'<tr>'.$headings.'</tr>';
    }

    private function formatDate(?string $date): string
    {
        return $date && substr($date, 0, 10) !== '0000-00-00'
            ? substr($date, 8, 2).'/'.substr($date, 5, 2).'/'.substr($date, 0, 4)
            : '';
    }

    private function temporaryFilePath(): string
    {
        $directory = storage_path('app/temp');

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the report temporary directory.');
        }

        $path = tempnam($directory, 'facilities_');

        if ($path === false) {
            throw new RuntimeException('Unable to create the facilities export temporary file.');
        }

        return $path;
    }

    private function write(SplFileObject $file, string $contents): void
    {
        $length = strlen($contents);
        $written = 0;
        while ($written < $length) {
            $bytes = $file->fwrite(substr($contents, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Unable to write the facilities export file. Check available disk space.');
            }
            $written += $bytes;
        }
    }
}
