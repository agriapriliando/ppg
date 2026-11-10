<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'url', 'description', 'icon', 'button_color', 'target', 'sort_order', 'is_active', 'placement'])]
class PortalQuickLink extends Model
{
    /** @param Builder<PortalQuickLink> $query */
    #[Scope]
    protected function visibleOn(Builder $query, string $page): void
    {
        $query->where('is_active', true)->whereIn('placement', ['both', $page]);
    }

    public function placementLabel(): string
    {
        return ! $this->is_active ? 'Tidak tampil' : match ($this->placement) {
            'dashboard' => 'Dashboard',
            'homepage' => 'Homepage',
            'hidden' => 'Tidak tampil',
            default => 'Semua halaman',
        };
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
