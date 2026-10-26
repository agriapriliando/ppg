<x-layouts::app title="Kepesertaan SK">
    <div class="d-flex justify-content-end mb-4"><a href="{{ route('public.claim') }}" class="btn btn-purple">Ajukan Pencocokan SK</a></div>
    @forelse($claims as $claim)
        <section class="workspace-card mb-3"><div class="panel-body">
            <h2 class="h5">{{ $claim->submitted_name }}</h2>
            <p class="text-muted">{{ $claim->period->name }} · Referensi {{ $claim->public_reference }}</p>
            <span class="badge text-bg-{{ $claim->status==='approved' ? 'success' : 'secondary' }}">{{ ['pending'=>'Menunggu pemeriksaan','approved'=>'Disetujui','needs_information'=>'Perlu informasi tambahan','declined'=>'Klaim belum dapat disetujui','cancelled'=>'Dibatalkan'][$claim->status] ?? $claim->status }}</span>
            @if($claim->public_note)<div class="notice-box info mt-3"><i class="bi bi-chat-square-text" aria-hidden="true"></i><p class="mb-0">{{ $claim->public_note }}</p></div>@endif
            @foreach($claim->responses as $response)
                <div class="border-start ps-3 my-3"><small class="text-muted">Tanggapan Anda · {{ $response->created_at->timezone(config('pmb.business_timezone'))->format('d/m/Y H:i') }} WIB</small><p class="mb-0 text-break" style="white-space: pre-line">{{ $response->message }}</p></div>
            @endforeach
            @if($claim->status==='needs_information' && $claim->period->state !== \App\Enums\PeriodState::Archived)
                <form method="post" action="{{ route('applicant.claims.respond', $claim) }}" class="mt-3">
                    @csrf<input type="hidden" name="version" value="{{ $claim->lock_version }}">
                    <label for="response-{{ $claim->id }}" class="form-label">Informasi tambahan untuk pemeriksa *</label>
                    <textarea id="response-{{ $claim->id }}" name="message" class="form-control" rows="3" minlength="10" maxlength="1000" required></textarea>
                    <div class="form-text mb-3">Jawab catatan pemeriksa. Jangan menulis sandi, kode akses, atau NIK lengkap. Perubahan identitas dan kepemilikan harus diperiksa petugas.</div>
                    <button class="btn btn-outline-purple">Kirim Informasi Tambahan</button>
                </form>
            @endif
            @if(in_array($claim->status, ['pending','needs_information']))
                <details class="mt-4"><summary class="text-muted">Batalkan permohonan ini</summary>
                    <form method="post" action="{{ route('applicant.claims.cancel', $claim) }}" class="mt-3">
                        @csrf<input type="hidden" name="version" value="{{ $claim->lock_version }}">
                        <label class="form-check mb-3"><input type="checkbox" class="form-check-input" name="confirmed" value="1" required><span class="form-check-label">Saya ingin membatalkan permohonan yang belum disetujui ini.</span></label>
                        <button class="btn btn-outline-danger btn-sm">Batalkan Permohonan</button>
                    </form>
                </details>
            @endif
        </div></section>
    @empty
        <div class="workspace-card empty-state"><i class="bi bi-patch-check" aria-hidden="true"></i><h2>Belum ada permohonan klaim</h2><p>Gunakan nama dan pengenal yang tercantum pada SK resmi.</p></div>
    @endforelse
    {{ $claims->links() }}
</x-layouts::app>
