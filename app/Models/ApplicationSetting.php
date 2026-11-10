<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $value
 */
#[Fillable(['key', 'value', 'version', 'updated_by'])]
class ApplicationSetting extends Model
{
    public static function claimPegIdRequired(): bool
    {
        $settings = static::query()->where('key', 'public')->first()->value ?? [];

        return (bool) ($settings['claim_peg_id_required'] ?? false);
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return ['value' => 'array', 'version' => 'integer'];
    }
}
