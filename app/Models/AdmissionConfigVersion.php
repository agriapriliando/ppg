<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property array<string, scalar|null> $pmb_config
 * @property array<string, int> $reference_version_ids
 */
#[Fillable(['admission_period_id', 'version', 'pmb_config', 'reference_version_ids', 'consent_text_id', 'consent_version', 'content_digest'])]
class AdmissionConfigVersion extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Versi konfigurasi tersimpan tidak dapat diubah. Buat versi baru.');
        });
        static::deleting(function (): never {
            throw new LogicException('Versi konfigurasi harus dipertahankan sebagai riwayat.');
        });
    }

    protected function casts(): array
    {
        return ['pmb_config' => 'array', 'reference_version_ids' => 'array', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<AdmissionPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /** @return BelongsTo<ConsentText, $this> */
    public function consentText(): BelongsTo
    {
        return $this->belongsTo(ConsentText::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'config_version_id');
    }

    /** @return HasMany<ApplicationSubmission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(ApplicationSubmission::class, 'config_version_id');
    }
}
