<?php

namespace App\Console\Commands;

use App\Models\Enrollee;
use App\Services\Legacy\LegacyEnrolleePhotoResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateLegacyEnrolleePhotosCommand extends Command
{
    protected $signature = 'legacy:migrate-enrollee-photos
        {amount=100 : Number of enrollees to migrate, or "all"}
        {--disk= : Target filesystem disk. Defaults to ENROLLEE_PASSPORT_DISK}
        {--legacy-root= : Legacy photo root containing passports, passports_formal, and pictures}
        {--path=enrollees/passports/legacy : Target storage folder}
        {--chunk=200 : Number of enrollees to read per chunk}
        {--from-id= : Start from a new-system enrollee id}
        {--overwrite : Replace existing non-legacy-looking image_url values}
        {--dry-run : Show what would be migrated without uploading or updating}';

    protected $description = 'Migrate legacy enrollee passport images to the configured filesystem disk and update enrollees.image_url';

    public function handle(LegacyEnrolleePhotoResolver $photos): int
    {
        $amount = $this->parseAmount((string) $this->argument('amount'));
        if ($amount === false) {
            $this->error('The amount must be a positive number or "all".');

            return self::FAILURE;
        }

        $disk = (string) ($this->option('disk') ?: config('filesystems.enrollee_passport_disk', 'public'));
        $legacyRoot = rtrim((string) ($this->option('legacy-root') ?: config('filesystems.legacy_enrollee_photo_root', '/home/ngshia5')), "/\\");
        $targetRoot = trim((string) $this->option('path'), "/\\");
        $chunk = max(1, (int) $this->option('chunk'));
        $fromId = $this->option('from-id') !== null ? max(1, (int) $this->option('from-id')) : null;
        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');

        if ($legacyRoot === '') {
            $this->error('A legacy root path is required. Use --legacy-root=... or LEGACY_ENROLLEE_PHOTO_ROOT.');

            return self::FAILURE;
        }

        if (!$dryRun && !array_key_exists($disk, (array) config('filesystems.disks', []))) {
            $this->error("Filesystem disk [{$disk}] is not configured.");

            return self::FAILURE;
        }

        $query = Enrollee::query()
            ->select(['id', 'enrollee_id', 'legacy_id', 'legacy_source_table', 'legacy_enrollee_id', 'nin', 'image_url'])
            ->whereNotNull('legacy_id')
            ->when($fromId !== null, fn (Builder $builder) => $builder->where('id', '>=', $fromId))
            ->orderBy('id');

        $matchingRows = (clone $query)->count();
        $total = $amount === null ? $matchingRows : min($matchingRows, $amount);
        if ($total === 0) {
            $this->info('No legacy enrollees found for the selected criteria.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%s %s legacy enrollee photo%s from [%s] to disk [%s].',
            $dryRun ? 'Dry-running' : 'Migrating',
            number_format($total),
            $total === 1 ? '' : 's',
            $legacyRoot,
            $disk
        ));

        $stats = [
            'checked' => 0,
            'migrated' => 0,
            'skipped_existing' => 0,
            'missing_photo' => 0,
            'failed' => 0,
        ];

        $remaining = $amount;

        $query->chunkById($chunk, function ($enrollees) use ($photos, $legacyRoot, $targetRoot, $disk, $overwrite, $dryRun, &$stats, &$remaining): bool {
            foreach ($enrollees as $enrollee) {
                if ($remaining !== null && $remaining <= 0) {
                    return false;
                }

                $stats['checked']++;
                if ($remaining !== null) {
                    $remaining--;
                }

                if (!$overwrite && $photos->shouldSkipWithoutOverwrite($enrollee->image_url)) {
                    $stats['skipped_existing']++;
                    continue;
                }

                $resolved = $photos->resolve($enrollee, $legacyRoot);
                if ($resolved === null) {
                    $stats['missing_photo']++;
                    continue;
                }

                $path = $this->storagePath($targetRoot, $enrollee, $resolved['extension']);

                if ($dryRun) {
                    $stats['migrated']++;
                    $this->line(sprintf(
                        '[dry-run] %s legacy:%s -> %s (%s)',
                        $enrollee->enrollee_id ?: "enrollee#{$enrollee->id}",
                        $enrollee->legacy_id,
                        $path,
                        $resolved['source']
                    ));
                    continue;
                }

                try {
                    Storage::disk($disk)->put($path, $resolved['bytes'], [
                        'visibility' => 'public',
                        'ContentType' => $resolved['mime'],
                    ]);

                    $enrollee->forceFill([
                        'image_url' => Storage::disk($disk)->url($path),
                    ])->save();

                    $stats['migrated']++;
                } catch (\Throwable $exception) {
                    $stats['failed']++;
                    $this->error(sprintf(
                        '[%s legacy:%s] failed: %s',
                        $enrollee->enrollee_id ?: "enrollee#{$enrollee->id}",
                        $enrollee->legacy_id,
                        $exception->getMessage()
                    ));
                }
            }

            return $remaining === null || $remaining > 0;
        }, 'id');

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['checked', $stats['checked']],
                [$dryRun ? 'would migrate' : 'migrated', $stats['migrated']],
                ['skipped existing app/S3 URL', $stats['skipped_existing']],
                ['missing legacy photo', $stats['missing_photo']],
                ['failed', $stats['failed']],
            ]
        );

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function parseAmount(string $amount): int|null|false
    {
        $amount = strtolower(trim($amount));
        if ($amount === 'all') {
            return null;
        }

        if (!ctype_digit($amount) || (int) $amount < 1) {
            return false;
        }

        return (int) $amount;
    }

    private function storagePath(string $targetRoot, Enrollee $enrollee, string $extension): string
    {
        $source = str_contains(strtolower((string) $enrollee->legacy_source_table), 'formal')
            ? 'formal'
            : 'informal';

        $name = sprintf(
            '%s-%s.%s',
            $enrollee->legacy_id,
            Str::slug((string) ($enrollee->legacy_enrollee_id ?: $enrollee->enrollee_id ?: $enrollee->id)),
            $extension
        );

        return trim($targetRoot, "/\\") . '/' . $source . '/' . $name;
    }
}
