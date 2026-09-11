<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\Enrollee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class BulkEnrolleeNinUpdateService
{
    public const MAX_ROWS = 5000;

    public function __construct(
        private readonly EnrolleeNinLock $ninLock,
        private readonly EnrolleeDuplicateDetectionService $duplicateDetection,
        private readonly EnrolleeDuplicateNinService $duplicateNins,
    ) {}

    public function update(UploadedFile $file, User $user): array
    {
        $rows = $this->readRows($file);
        $headers = array_map(fn ($value) => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', trim((string) $value)), '_')), array_shift($rows));
        $idColumns = array_keys(array_filter($headers, fn ($header) => in_array($header, ['nicare_id', 'enrollee_id'], true)));
        $ninColumns = array_keys($headers, 'nin', true);
        if (count($idColumns) !== 1 || count($ninColumns) !== 1) {
            throw ValidationException::withMessages(['file' => 'Include exactly one NICARE ID (or Enrollee ID) column and one NIN column in the first row.']);
        }

        $idColumn = $idColumns[0];
        $ninColumn = $ninColumns[0];
        $idCounts = [];
        foreach ($rows as $row) {
            $id = strtoupper(trim((string) ($row[$idColumn] ?? '')));
            $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
        }

        $result = ['total' => 0, 'updated' => 0, 'cleared' => 0, 'unchanged' => 0, 'skipped' => 0, 'rows' => []];
        foreach ($rows as $index => $row) {
            if (count(array_filter($row, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $id = strtoupper(trim((string) ($row[$idColumn] ?? '')));
            $nin = trim((string) ($row[$ninColumn] ?? ''));
            $nin = $nin === '' ? null : $nin;

            try {
                if ($id === '' || strlen($id) > 255) {
                    throw new RuntimeException('A valid NICARE ID is required.');
                }
                if ($idCounts[$id] > 1) {
                    throw new RuntimeException('NICARE ID occurs more than once in this upload. Keep one row per enrollee.');
                }
                if (! array_key_exists($ninColumn, $row)) {
                    throw new RuntimeException('NIN cell is missing. Use an empty cell to clear the NIN.');
                }
                if ($nin !== null && ! preg_match('/^[0-9]{11}$/D', $nin)) {
                    throw new RuntimeException('NIN must contain exactly 11 digits, or be blank to clear it.');
                }

                $enrollee = Enrollee::query()->where('enrollee_id', $id)->first();
                if (! $enrollee) {
                    throw new RuntimeException('No enrollee found with this NICARE ID.');
                }

                $outcome = $this->ninLock->run($enrollee->id, fn () => $this->duplicateDetection->withinSubmissionLock(['nin' => $nin], fn () => DB::transaction(function () use ($enrollee, $nin, $user): array {
                    $current = Enrollee::query()->whereKey($enrollee->id)->lockForUpdate()->firstOrFail();
                    if ($current->nin_verification_status === Enrollee::NIN_VERIFICATION_VERIFIED) {
                        throw new RuntimeException('Verified NINs cannot be updated or cleared.');
                    }

                    $oldNin = $current->nin;
                    if ($oldNin === $nin) {
                        return ['status' => 'unchanged', 'message' => 'NIN already matches the uploaded value.'];
                    }
                    if ($nin !== null && Enrollee::query()->where('nin', $nin)->whereKeyNot($current->id)->exists()) {
                        throw new RuntimeException('This NIN already belongs to another enrollee.');
                    }

                    $oldStatus = $current->nin_verification_status;
                    $current->forceFill([
                        'nin' => $nin,
                        'nin_verification_status' => $nin === null ? Enrollee::NIN_VERIFICATION_NOT_PROVIDED : Enrollee::NIN_VERIFICATION_NOT_STARTED,
                        'nin_verified_at' => null,
                        'nin_verified_by' => null,
                        'nin_verification_provider' => null,
                        'nin_verification_data' => null,
                        'nin_verification_meta' => null,
                        'has_duplicate_nin' => false,
                    ])->save();
                    $this->duplicateNins->refreshForNins([$oldNin, $nin]);

                    AuditTrail::create([
                        'auditable_type' => Enrollee::class,
                        'auditable_id' => $current->id,
                        'action' => 'bulk_nin_updated',
                        'description' => $nin === null ? 'NIN cleared by bulk upload.' : 'NIN changed by bulk upload.',
                        'user_id' => $user->id,
                        'old_values' => ['nin' => $oldNin, 'nin_verification_status' => $oldStatus],
                        'new_values' => ['nin' => $nin, 'nin_verification_status' => $current->nin_verification_status],
                    ]);

                    return ['status' => $nin === null ? 'cleared' : 'updated', 'message' => $nin === null ? 'NIN cleared.' : 'NIN updated; verification required.'];
                })
                )
                );
            } catch (RuntimeException $exception) {
                // Infrastructure/database errors must not be presented as row validation errors.
                if (get_class($exception) !== RuntimeException::class) {
                    throw $exception;
                }
                $outcome = ['status' => 'skipped', 'message' => $exception->getMessage()];
            }

            $result['total']++;
            $result[$outcome['status']]++;
            $result['rows'][] = ['row' => $index + 2, 'enrollee_id' => $id, ...$outcome];
        }

        if ($result['total'] === 0) {
            throw ValidationException::withMessages(['file' => 'The upload contains no enrollee rows.']);
        }

        return $result;
    }

    private function readRows(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            $handle = fopen($file->getRealPath(), 'rb');
            try {
                $rows = [];
                while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    $rows[] = $row;
                    if (count($rows) > self::MAX_ROWS + 1 || count($row) > 50) {
                        $this->rejectSize();
                    }
                }
                if (isset($rows[0][0])) {
                    $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
                }
            } finally {
                fclose($handle);
            }
        } else {
            try {
                $reader = IOFactory::createReaderForFile($file->getRealPath());
                $sheets = $reader->listWorksheetInfo($file->getRealPath());
                if (count($sheets) !== 1) {
                    throw ValidationException::withMessages(['file' => 'Upload a workbook containing one worksheet.']);
                }
                $sheet = $sheets[0];
                if ($sheet['totalRows'] > self::MAX_ROWS + 1 || $sheet['totalColumns'] > 50) {
                    $this->rejectSize();
                }
                $workbook = $reader->load($file->getRealPath());
                try {
                    // Do not calculate formulas. Preserve text IDs and explicitly blank NIN cells.
                    $rows = $workbook->getSheet(0)->rangeToArray('A1:'.$sheet['lastColumnLetter'].max(1, $sheet['totalRows']), null, false, false);
                } finally {
                    $workbook->disconnectWorksheets();
                }
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages(['file' => 'The spreadsheet could not be read. Upload a valid CSV, XLSX, or XLS file.']);
            }
        }

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'Include a header row and at least one enrollee.']);
        }

        return $rows;
    }

    private function rejectSize(): never
    {
        throw ValidationException::withMessages(['file' => 'Upload at most 5,000 enrollees and 50 columns per file.']);
    }
}
