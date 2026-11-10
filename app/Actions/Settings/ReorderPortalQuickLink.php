<?php

namespace App\Actions\Settings;

use App\Models\PortalQuickLink;
use Illuminate\Support\Facades\DB;

class ReorderPortalQuickLink
{
    public function handle(PortalQuickLink $link, string $direction): void
    {
        DB::transaction(function () use ($link, $direction): void {
            $links = PortalQuickLink::query()->orderBy('id')->lockForUpdate()->get()
                ->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
            $index = $links->search(fn (PortalQuickLink $item): bool => $item->id === $link->id);
            if ($index === false) {
                return;
            }
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($target < 0 || $target >= $links->count()) {
                return;
            }
            $other = $links->get($target);
            $current = $links->get($index);
            if ($other === null || $current === null) {
                return;
            }
            $links->put($target, $current);
            $links->put($index, $other);
            foreach ($links as $order => $item) {
                if ($item->sort_order !== $order + 1) {
                    $item->sort_order = $order + 1;
                    $item->save();
                }
            }
        });
    }
}
