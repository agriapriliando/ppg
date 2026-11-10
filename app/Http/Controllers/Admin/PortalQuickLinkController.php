<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Actions\Settings\ReorderPortalQuickLink;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PortalQuickLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            'placement' => ['sometimes', 'required', 'in:dashboard,homepage,both,hidden'],
        ]);

        $placement = $data['placement'] ?? ($request->boolean('is_active', true) ? 'both' : 'hidden');
        $maxOrder = (int) PortalQuickLink::query()->max('sort_order');

        $link = PortalQuickLink::create([
            'title' => trim($data['title']),
            'url' => trim($data['url']),
            'description' => ! empty($data['description']) ? trim($data['description']) : null,
            'icon' => ! empty($data['icon']) ? trim($data['icon']) : 'bi-box-arrow-up-right',
            'button_color' => $data['button_color'],
            'target' => $data['target'],
            'sort_order' => $maxOrder + 1,
            'is_active' => $placement !== 'hidden',
            'placement' => $placement,
        ]);

        $audit->handle($actor, 'Menambahkan tautan cepat portal', $link, [
            'title' => $link->title,
            'url' => $link->url,
            'sort_order' => $link->sort_order,
            'placement' => $link->placement,
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
            'placement' => ['sometimes', 'required', 'in:dashboard,homepage,both,hidden'],
        ]);

        $placement = $data['placement'] ?? ($request->boolean('is_active') ? ($quickLink->placement === 'hidden' ? 'both' : $quickLink->placement) : 'hidden');
        $quickLink->update([
            'title' => trim($data['title']),
            'url' => trim($data['url']),
            'description' => ! empty($data['description']) ? trim($data['description']) : null,
            'icon' => ! empty($data['icon']) ? trim($data['icon']) : 'bi-box-arrow-up-right',
            'button_color' => $data['button_color'],
            'target' => $data['target'],
            'is_active' => $placement !== 'hidden',
            'placement' => $placement,
        ]);

        $audit->handle($actor, 'Memperbarui tautan cepat portal', $quickLink, [
            'title' => $quickLink->title,
            'url' => $quickLink->url,
            'is_active' => $quickLink->is_active,
            'placement' => $quickLink->placement,
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

    public function moveUp(Request $request, PortalQuickLink $quickLink, ReorderPortalQuickLink $reorder): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);

        $reorder->handle($quickLink, 'up');

        return redirect()->route('admin.settings')->with('status', 'Urutan tautan cepat diperbarui.');
    }

    public function moveDown(Request $request, PortalQuickLink $quickLink, ReorderPortalQuickLink $reorder): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);

        $reorder->handle($quickLink, 'down');

        return redirect()->route('admin.settings')->with('status', 'Urutan tautan cepat diperbarui.');
    }
}
