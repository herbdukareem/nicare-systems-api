<?php

namespace App\Services\Legacy;

use App\Models\Enrollee;
use Illuminate\Support\Str;

class LegacyEnrolleePhotoResolver
{
    /**
     * @return array{bytes: string, mime: string, extension: string, source: string}|null
     */
    public function resolve(Enrollee $enrollee, string $legacyRoot): ?array
    {
        $fromCurrentValue = $this->decodeImagePayload((string) ($enrollee->image_url ?? ''));
        if ($fromCurrentValue !== null) {
            return [
                ...$fromCurrentValue,
                'source' => 'enrollees.image_url',
            ];
        }

        foreach ($this->candidateFiles($enrollee, $legacyRoot) as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $resolved = str_ends_with(strtolower($file), '.json')
                ? $this->decodeJsonFile($file)
                : $this->decodeBinaryFile($file);

            if ($resolved !== null) {
                return [
                    ...$resolved,
                    'source' => $file,
                ];
            }
        }

        return null;
    }

    public function shouldSkipWithoutOverwrite(?string $imageUrl): bool
    {
        $value = trim((string) $imageUrl);
        if ($value === '') {
            return false;
        }

        if ($this->decodeImagePayload($value) !== null) {
            return false;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return true;
        }

        $normalized = ltrim(str_replace('\\', '/', $value), '/');

        return Str::startsWith($normalized, [
            'storage/',
            'enrollees/',
            'mobile-enrollments/',
        ]);
    }

    /**
     * @return list<string>
     */
    private function candidateFiles(Enrollee $enrollee, string $legacyRoot): array
    {
        $legacyId = (string) $enrollee->legacy_id;
        $legacyNumber = trim((string) $enrollee->legacy_enrollee_id);
        $sourceTable = strtolower((string) $enrollee->legacy_source_table);
        $isFormal = str_contains($sourceTable, 'formal');

        $orderedJsonFolders = $isFormal
            ? ['passports_formal', 'passports', 'pictures']
            : ['passports', 'pictures', 'passports_formal'];

        $files = [];
        foreach ($orderedJsonFolders as $folder) {
            $files[] = $this->join($legacyRoot, $folder, "{$legacyId}.json");
        }

        $files[] = $this->join($legacyRoot, 'enrollees_data', 'pictures', "{$legacyId}.json");
        $files[] = $this->join($legacyRoot, 'apps', 'enrollees_data', 'pictures', "{$legacyId}.json");

        foreach ($this->relativeImagePathCandidates((string) $enrollee->image_url) as $relativePath) {
            $files[] = $this->join($legacyRoot, $relativePath);
            $files[] = $this->join($legacyRoot, 'apps', $relativePath);
        }

        foreach (array_filter([$legacyId, strtolower($legacyNumber)]) as $name) {
            foreach (['jpg', 'jpeg', 'png'] as $extension) {
                $files[] = $this->join($legacyRoot, 'img_data', 'enrollees', "{$name}.{$extension}");
                $files[] = $this->join($legacyRoot, 'apps', 'img_data', 'enrollees', "{$name}.{$extension}");
                $files[] = $this->join($legacyRoot, 'img_data', "{$name}.{$extension}");
                $files[] = $this->join($legacyRoot, 'apps', 'img_data', "{$name}.{$extension}");
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @return list<string>
     */
    private function relativeImagePathCandidates(string $imageUrl): array
    {
        $value = trim($imageUrl);
        if ($value === '' || preg_match('#^(data:|https?://)#i', $value) === 1) {
            return [];
        }

        return [ltrim(str_replace('\\', '/', $value), '/')];
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    private function decodeJsonFile(string $file): ?array
    {
        $contents = @file_get_contents($file);
        if ($contents === false || trim($contents) === '') {
            return null;
        }

        $json = json_decode($contents, true);
        if (!is_array($json)) {
            return null;
        }

        foreach (['passport', 'photo', 'image', 'enrolee_image_link'] as $key) {
            if (!empty($json[$key]) && is_string($json[$key])) {
                $decoded = $this->decodeImagePayload($json[$key]);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    private function decodeBinaryFile(string $file): ?array
    {
        $bytes = @file_get_contents($file);
        if ($bytes === false || strlen($bytes) < 50) {
            return null;
        }

        $mime = $this->detectMime($bytes);
        if ($mime === null) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => $this->extensionForMime($mime),
        ];
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}|null
     */
    private function decodeImagePayload(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $mime = null;
        if (preg_match('#^data:(?<mime>image/[-\w.+]+);base64,(?<payload>.+)$#is', $value, $matches) === 1) {
            $mime = strtolower($matches['mime']);
            $value = $matches['payload'];
        }

        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value) !== 1 || strlen($value) < 50) {
            return null;
        }

        $bytes = base64_decode($value, true);
        if ($bytes === false || strlen($bytes) < 50) {
            return null;
        }

        $mime ??= $this->detectMime($bytes);
        if ($mime === null) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'extension' => $this->extensionForMime($mime),
        ];
    }

    private function detectMime(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($bytes, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }

        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'image/gif';
        }

        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        $info = @getimagesizefromstring($bytes);

        return is_array($info) && isset($info['mime']) && str_starts_with($info['mime'], 'image/')
            ? strtolower($info['mime'])
            : null;
    }

    private function extensionForMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    private function join(string ...$segments): string
    {
        $path = '';
        foreach ($segments as $index => $segment) {
            $segment = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $segment);
            $segment = $index === 0
                ? rtrim($segment, DIRECTORY_SEPARATOR)
                : trim($segment, DIRECTORY_SEPARATOR);

            if ($segment === '') {
                continue;
            }

            $path = $path === '' ? $segment : $path . DIRECTORY_SEPARATOR . $segment;
        }

        return $path;
    }
}
