<?php

use App\Models\SkClaim;
use App\Models\SkParticipant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $claimId;

    #[Locked]
    public ?int $selectedId = null;

    public string $search = '';
    public bool $changingCandidate = false;

    public function mount(int $claimId): void
    {
        $this->claimId = $claimId;
        $claim = $this->authorizedClaim();
        $oldId = \App\Support\FormInput::old('candidate_id', $claim->candidate_sk_participant_id);
        $this->selectedId = $this->eligibleCandidates()->whereKey($oldId)->value('id');
    }

    private function authorizedClaim(): SkClaim
    {
        $claim = SkClaim::query()->findOrFail($this->claimId);
        Gate::authorize('review', $claim);

        return $claim;
    }

    private function eligibleCandidates(): \Illuminate\Database\Eloquent\Builder
    {
        $claim = $this->authorizedClaim();

        return SkParticipant::query()->where('admission_period_id', $claim->admission_period_id)
            ->whereNull('claimed_user_id')->where('roster_state', 'active')->where('admin_hold', false);
    }

    public function selectCandidate(int $id): void
    {
        $this->selectedId = $this->eligibleCandidates()->findOrFail($id)->id;
        $this->search = '';
        $this->changingCandidate = false;
    }

    public function changeCandidate(): void
    {
        $this->authorizedClaim();
        $this->search = '';
        $this->changingCandidate = true;
    }

    public function cancelChange(): void
    {
        $this->authorizedClaim();
        $this->reset('changingCandidate', 'search');
    }

    public function with(): array
    {
        $query = $this->eligibleCandidates();
        $search = trim(mb_substr($this->search, 0, 100));

        return [
            'selected' => $this->selectedId ? (clone $query)->find($this->selectedId) : null,
            'results' => mb_strlen($search) >= 2
                ? $query->where(fn ($q) => $q->where('name_on_sk', 'like', '%'.$search.'%')
                    ->orWhere('official_participant_id', 'like', '%'.$search.'%')
                    ->orWhere('sk_number', 'like', '%'.$search.'%'))
                    ->orderBy('name_on_sk')->orderBy('id')->limit(10)->get()
                : collect(),
        ];
    }
};
?>

<div class="mb-3" x-data="{ open: false, cursor: -1, move(step) { const buttons = [...$refs.results.querySelectorAll('button')]; if (!buttons.length) return; this.cursor = (this.cursor + step + buttons.length) % buttons.length; buttons[this.cursor].focus(); } }"
    @keydown.escape="open = false" @click.outside="open = false">
    <input type="hidden" name="candidate_id" value="{{ $selected?->id }}">
    <label for="claim-candidate-search" class="form-label fw-semibold">Kandidat resmi pada periode ini</label>
    @if($selected)
        <div class="border rounded-3 bg-light p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="text-break">
                <strong>{{ $selected->name_on_sk }}</strong>
                <small class="d-block text-muted">Peg ID: {{ $selected->official_participant_id ?? 'Tidak tersedia' }}</small>
                <small class="d-block text-muted">{{ $selected->sk_number }} &middot; Urut {{ $selected->sk_order }}</small>
            </div>
            @if($changingCandidate)
                <button type="button" class="btn btn-sm btn-outline-purple" wire:click="cancelChange" wire:loading.attr="disabled" @click="open = false">Batal ganti</button>
            @else
                <button type="button" class="btn btn-sm btn-outline-purple" wire:click="changeCandidate" wire:loading.attr="disabled" @click="open = true">Ganti kandidat</button>
            @endif
        </div>
    @endif
    @if(!$selected || $changingCandidate)
        @if($selected)
            <p class="small text-muted mt-3 mb-2">Kandidat di atas tetap dipilih sampai Anda memilih penggantinya.</p>
        @endif
        <input id="claim-candidate-search" type="search" class="form-control" maxlength="100"
            placeholder="Ketik minimal 2 karakter nama, Peg ID, atau nomor SK"
            wire:model.live.debounce.300ms="search" autocomplete="off" aria-controls="claim-candidate-results"
            @input="open = true; cursor = -1" @focus="open = true" @keydown.arrow-down.prevent="open = true; move(1)"
            @keydown.enter.prevent>
        <div class="form-text">Cari lalu pilih peserta yang sesuai dengan identitas pemohon.</div>
    @endif
    <div wire:loading.delay wire:target="search,selectCandidate,changeCandidate,cancelChange" class="small text-muted mt-2" role="status">Memuat kandidat…</div>
    <div id="claim-candidate-results" x-ref="results" x-show="open" class="mt-2" aria-live="polite"
        @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" style="max-height: 320px; overflow-y: auto;">
        @if(!$selected || $changingCandidate)
            <div class="list-group">
                @foreach($results as $result)
                    <button type="button" class="list-group-item list-group-item-action text-break py-3" wire:key="candidate-result-{{ $result->id }}"
                        wire:click="selectCandidate({{ $result->id }})" wire:loading.attr="disabled" @click="open = false">
                        <span class="fw-semibold">{{ $result->name_on_sk }}</span>
                        <small class="d-block text-muted">Peg ID: {{ $result->official_participant_id ?? 'Tidak tersedia' }}</small>
                        <small class="d-block text-muted">{{ $result->sk_number }} &middot; Urut {{ $result->sk_order }}</small>
                    </button>
                @endforeach
            </div>
            @if(mb_strlen(trim($search)) >= 2 && $results->isEmpty())
                <p class="small text-muted mb-0">Kandidat tidak ditemukan. Coba nama yang lebih pendek, Peg ID, atau nomor SK.</p>
            @elseif($results->count() === 10)
                <p class="small text-muted mt-2 mb-0">Menampilkan 10 hasil awal. Perjelas pencarian untuk mempersempit hasil.</p>
            @endif
        @endif
    </div>
</div>
