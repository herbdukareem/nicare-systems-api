<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload file to AWS S3/Wasabi
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string|null $filename
     * @return array
     */
    public function uploadFile(UploadedFile $file, string $folder = 'documents', ?string $filename = null): array
    {
        try {
            // Validate file
            $this->validateFile($file);

            // Generate filename if not provided
            if (!$filename) {
                $filename = $this->generateFilename($file);
            }

            // Create full path
            $path = $folder . '/' . $filename;

            // Upload to S3/Wasabi
            $uploaded = Storage::disk('s3')->put($path, file_get_contents($file), 'public');

            if (!$uploaded) {
                throw new \Exception('Failed to upload file to storage');
            }

            // Get the URL
            $url = Storage::disk('s3')->url($path);

            return [
                'success' => true,
                'path' => $path,
                'url' => $url,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload multiple files
     *
     * @param array $files
     * @param string $folder
     * @return array
     */
    public function uploadMultipleFiles(array $files, string $folder = 'documents'): array
    {
        $results = [];
        
        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $results[$key] = $this->uploadFile($file, $folder);
            }
        }

        return $results;
    }

    /**
     * Delete file from storage
     *
     * @param string $path
     * @return bool
     */
    public function deleteFile(string $path): bool
    {
        try {
            return Storage::disk('s3')->delete($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file URL
     *
     * @param string $path
     * @return string|null
     */
    public function getFileUrl(string $path): ?string
    {
        try {
            if (Storage::disk('s3')->exists($path)) {
                return Storage::disk('s3')->url($path);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate uploaded file
     *
     * @param UploadedFile $file
     * @throws \Exception
     */
    private function validateFile(UploadedFile $file): void
    {
        // Check if file is valid
        if (!$file->isValid()) {
            throw new \Exception('Invalid file upload');
        }

        // Check file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds maximum limit of 10MB');
        }

        // Check allowed file types
        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \Exception('File type not allowed. Allowed types: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX');
        }
    }

    /**
     * Generate unique filename
     *
     * @param UploadedFile $file
     * @return string
     */
    private function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $random = Str::random(8);
        
        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Upload PAS document (referral-specific)
     *
     * @param UploadedFile $file
     * @param string $referralCode
     * @param string $documentType
     * @return array
     */
    public function uploadPASDocument(UploadedFile $file, string $referralCode, string $documentType): array
    {
        $folder = "pas/{$referralCode}/{$documentType}";
        $filename = $this->generatePASFilename($file, $documentType);
        
        return $this->uploadFile($file, $folder, $filename);
    }

    /**
     * Generate PAS-specific filename
     *
     * @param UploadedFile $file
     * @param string $documentType
     * @return string
     */
    private function generatePASFilename(UploadedFile $file, string $documentType): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        return "{$documentType}_{$timestamp}.{$extension}";
    }

    /**
     * Get file size in human readable format
     *
     * @param int $bytes
     * @return string
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf("%.2f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    /**
     * Check if file exists in storage
     *
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            return Storage::disk('s3')->exists($path);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get file metadata
     *
     * @param string $path
     * @return array|null
     */
    public function getFileMetadata(string $path): ?array
    {
        try {
            if (!$this->fileExists($path)) {
                return null;
            }

            return [
                'size' => Storage::disk('s3')->size($path),
                'last_modified' => Storage::disk('s3')->lastModified($path),
                'url' => Storage::disk('s3')->url($path),
                'exists' => true
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
