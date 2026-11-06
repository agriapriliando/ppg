<?php

namespace App\Models\Concerns;

trait HasDocumentFileDetails
{
    public function getFileNameAttribute(): string
    {
        $ext = ltrim($this->extension, '.');

        return $ext !== '' ? "{$this->display_name}.{$ext}" : $this->display_name;
    }

    public function getSanitizedDownloadNameAttribute(): string
    {
        $safeBase = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $this->display_name) ?: 'dokumen';
        $safeBase = trim(preg_replace('/\s+/', ' ', $safeBase));
        $ext = ltrim($this->extension, '.');

        return $ext !== '' ? "{$safeBase}.{$ext}" : $safeBase;
    }

    public function isPreviewable(): bool
    {
        $previewable = config('document_policy.previewable_mimes', []);

        return in_array($this->mime_type, $previewable, true);
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    public function isDeleting(): bool
    {
        return $this->status === self::STATUS_DELETING;
    }

    public function formattedSize(): string
    {
        $bytes = $this->byte_size;
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }

    public function iconClass(): string
    {
        $ext = strtolower($this->extension);

        return match ($ext) {
            'pdf' => 'bi-file-earmark-pdf text-danger',
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'bi-file-earmark-image text-primary',
            'doc', 'docx' => 'bi-file-earmark-word text-info',
            'xls', 'xlsx' => 'bi-file-earmark-excel text-success',
            default => 'bi-file-earmark-text text-secondary',
        };
    }
}
