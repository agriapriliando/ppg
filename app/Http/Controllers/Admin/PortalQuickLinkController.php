<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PortalQuickLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalQuickLinkController extends Controller
{
    public function store(Request $request, RecordAudit $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'button_color' => ['required', 'string', 'in:purple,gold,blue,green,teal,dark,outline-purple'],
            'target' => ['required', 'string', 'in:_blank,_self'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $maxOrder = (int) PortalQuickLink::query()->max('sort_order');

        $link = PortalQuickLink::create([
            'title' => trim($data['title']),
            'url' => trim($data['url']),
            'description' => ! empty($data['description']) ? trim($data['description']) : null,
            'icon' => ! empty($data['icon']) ? trim($data['icon']) : 'bi-box-arrow-up-right',
            'button_color' => $data['button_color'],
            'target' => $data['target'],
            'sort_order' => $maxOrder + 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $audit->handle($actor, 'Menambahkan tautan cepat portal', $link, [
            'title' => $link->title,
            'url' => $link->url,
            'sort_order' => $link->sort_order,
        ]);

        return redirect()->route('admin.settings')->with('status', 'Tautan cepat berhasil ditambahkan.');
    }

    public function update(Request $request, PortalQuickLink $quickLink, RecordAudit $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:50'],
            'button_color' => ['required', 'string', 'in:purple,gold,blue,green,teal,dark,outline-purple'],
            'target' => ['required', 'string', 'in:_blank,_self'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $quickLink->update([
            'title' => trim($data['title']),
            'url' => trim($data['url']),
            'description' => ! empty($data['description']) ? trim($data['description']) : null,
            'icon' => ! empty($data['icon']) ? trim($data['icon']) : 'bi-box-arrow-up-right',
            'button_color' => $data['button_color'],
            'target' => $data['target'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $audit->handle($actor, 'Memperbarui tautan cepat portal', $quickLink, [
            'title' => $quickLink->title,
            'url' => $quickLink->url,
            'is_active' => $quickLink->is_active,
        ]);

        return redirect()->route('admin.settings')->with('status', 'Tautan cepat berhasil diperbarui.');
    }

    public function destroy(Request $request, PortalQuickLink $quickLink, RecordAudit $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);

        $title = $quickLink->title;
        $audit->handle($actor, 'Menghapus tautan cepat portal', $quickLink, ['title' => $title]);
        $quickLink->delete();

        return redirect()->route('admin.settings')->with('status', 'Tautan cepat "'.$title.'" berhasil dihapus.');
    }

    public function moveUp(Request $request, PortalQuickLink $quickLink): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);

        $this->reorder($quickLink, 'up');

        return redirect()->route('admin.settings')->with('status', 'Urutan tautan cepat diperbarui.');
    }

    public function moveDown(Request $request, PortalQuickLink $quickLink): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);

        $this->reorder($quickLink, 'down');

        return redirect()->route('admin.settings')->with('status', 'Urutan tautan cepat diperbarui.');
    }

    private function reorder(PortalQuickLink $quickLink, string $direction): void
    {
        DB::transaction(function () use ($quickLink, $direction): void {
            $links = PortalQuickLink::query()->orderBy('sort_order')->orderBy('id')->get();
            $index = $links->search(fn (PortalQuickLink $item): bool => $item->id === $quickLink->id);

            if ($index === false) {
                return;
            }

            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
            if ($targetIndex < 0 || $targetIndex >= $links->count()) {
                return;
            }

            $targetLink = $links->get($targetIndex);
            $currentLink = $links->get($index);

            if ($targetLink === null || $currentLink === null) {
                return;
            }

            $temp = $currentLink->sort_order;
            if ($temp === $targetLink->sort_order) {
                $currentLink->sort_order = $direction === 'up' ? $targetLink->sort_order - 1 : $targetLink->sort_order + 1;
            } else {
                $currentLink->sort_order = $targetLink->sort_order;
                $targetLink->sort_order = $temp;
                $targetLink->save();
            }
            $currentLink->save();

            $all = PortalQuickLink::query()->orderBy('sort_order')->orderBy('id')->get();
            foreach ($all as $order => $item) {
                if ($item->sort_order !== $order + 1) {
                    $item->sort_order = $order + 1;
                    $item->save();
                }
            }
        });
    }
}
