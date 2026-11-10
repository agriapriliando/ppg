<?php

namespace App\Livewire\Admin;

use App\Actions\Settings\ReorderPortalQuickLink;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Models\PortalQuickLink;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class QuickLinksTable extends Component
{
    public string $message = '';

    public function moveUp(int $id, ReorderPortalQuickLink $reorder): void
    {
        $this->authorizeManagement();
        $reorder->handle(PortalQuickLink::findOrFail($id), 'up');
        $this->message = 'Urutan tautan cepat disimpan.';
    }

    public function moveDown(int $id, ReorderPortalQuickLink $reorder): void
    {
        $this->authorizeManagement();
        $reorder->handle(PortalQuickLink::findOrFail($id), 'down');
        $this->message = 'Urutan tautan cepat disimpan.';
    }

    private function authorizeManagement(): void
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->account_state === AccountState::Active && ! $user->must_change_password
            && in_array($user->role, [UserRole::Administrator, UserRole::Operator], true), 403);
    }

    public function render(): View
    {
        $this->authorizeManagement();

        return view('livewire.admin.quick-links-table', ['links' => PortalQuickLink::query()->orderBy('sort_order')->orderBy('id')->get()]);
    }
}
