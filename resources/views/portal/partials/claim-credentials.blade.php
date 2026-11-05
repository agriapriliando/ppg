    @php
        $digits = preg_replace('/[^0-9]/', '', $issuance?->target_number ?? '');
        if (str_starts_with($digits, '0')) {
            $waNumber = '62' . substr($digits, 1);
        } else {
            $waNumber = $digits;
        }

        $waMessage = "Halo Bapak/Ibu {$user->name}, Berikut informasi akun Portal PMB PPG IAKN Palangka Raya:\n"
            . "ID Login: {$user->login_id}\n"
            . ($secret !== null ? "Kata Sandi: {$secret}\n" : "")
            . "Tautan Masuk: " . route('login') . "\n"
            . "Segera login untuk melengkapi Formulir Biodata dan Unggah Berkas\n"
            . "Terima Kasih.";

        $waUrl = !empty($waNumber) ? 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($waMessage) : null;
    @endphp

    <section class="workspace-card mb-4">
        <div class="panel-heading d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2>Akun Peserta</h2>
                <p>Serahkan informasi akses kepada pemilik akun melalui nomor WhatsApp yang sesuai.</p>
            </div>

        </div>
        <div class="panel-body">
            <dl class="detail-list">
                <dt>ID login</dt><dd><code>{{ $user->login_id }}</code></dd>
                <dt>Status akun</dt><dd>{{ $user->account_state->label() }}</dd>
                <dt>Kata sandi</dt><dd>@if($secret !== null)<span class="d-inline-block px-3 py-2 rounded border border-warning-subtle bg-warning-subtle text-dark fw-bold fs-5 text-break">{{ $secret }}</span>@else<span class="text-muted">Kata sandi belum tersedia atau sudah tidak berlaku. Gunakan tombol di bawah untuk membuat kata sandi.</span>@endif</dd>
                @if($issuance)
                    @if($user->account_state === App\Enums\AccountState::PendingActivation)<dt>Batas masuk pertama</dt><dd>{{ $issuance->expires_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd>@endif
                @endif
                <dt>Alamat masuk</dt><dd>{{ route('login') }}</dd>
            </dl>

            @if($issuance && $secret !== null && $waUrl)
                <div class="p-3 mb-4 rounded-3 border bg-light d-flex align-items-center justify-content-between flex-wrap gap-3" style="border-left: 4px solid #25D366 !important;">
                    <div>
                        <strong class="d-block text-dark mb-1">
                            <i class="bi bi-whatsapp text-success me-1 fs-5"></i> Kirim Akun ke Peserta
                        </strong>
                        <p class="small text-muted mb-0">
                            Klik tombol di samping untuk membuka WhatsApp dan mengirim pesan kredensial otomatis (ID login & kata sandi) ke nomor <strong>{{ $issuance->target_number }}</strong>.
                        </p>
                    </div>
                    <div>
                        @if($canDeliver)
                            <form method="post" action="{{ route('credentials.deliver', $issuance) }}" target="_blank" rel="noopener noreferrer">
                                @csrf
                                <input type="hidden" name="delivered" value="1">
                                <input type="hidden" name="open_whatsapp" value="1">
                                <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-2 px-3 py-2 shadow-sm">
                                    <i class="bi bi-whatsapp fs-5" aria-hidden="true"></i>
                                    <span class="fw-semibold">Kirim Akun ke WhatsApp</span>
                                </button>
                            </form>
                        @else
                            <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-success d-inline-flex align-items-center gap-2 px-3 py-2 shadow-sm">
                            <i class="bi bi-whatsapp fs-5"></i>
                            <span class="fw-semibold">Kirim Akun ke WhatsApp</span>
                        </a>
                        @endif
                    </div>
                </div>
            @endif


            @if(!$issuance)
                <form method="post" action="{{ route('credentials.issue', $claim) }}" class="mt-3">
                    @csrf
                    <input type="hidden" name="return_to_claim" value="1">
                    <button class="btn btn-purple">Siapkan Akun Peserta</button>
                </form>
            @endif
            <form method="post" action="{{ route('credentials.regenerate', $claim) }}" class="mt-3">
                @csrf
                <input type="hidden" name="return_to_claim" value="1">
                <button class="btn btn-outline-danger">Generate Ulang Password</button>
            </form>
        </div>
    </section>

