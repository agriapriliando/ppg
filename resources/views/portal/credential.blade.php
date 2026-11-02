<x-layouts::app title="Akses Akun">
    <section class="workspace-card">
        <div class="panel-heading"><h2>Akses akun</h2><p>Serahkan informasi akses kepada pemilik akun melalui nomor WhatsApp yang sesuai.</p></div>
        <div class="panel-body">
            <dl class="detail-list">
                <dt>Nama</dt><dd>{{ $user->name }}</dd>
                <dt>ID login</dt><dd><code>{{ $user->login_id }}</code></dd>
                <dt>Status akun</dt><dd>{{ $user->account_state->label() }}</dd>
                <dt>Kata sandi</dt><dd>@if($secret !== null)<code>{{ $secret }}</code>@else<span class="text-muted">Kata sandi yang ada tidak dapat ditampilkan kembali.</span>@endif</dd>
                @if($issuance)
                    <dt>Nomor tujuan</dt><dd>{{ $issuance->target_number }}</dd>
                    @if($user->account_state === App\Enums\AccountState::PendingActivation)<dt>Batas masuk pertama</dt><dd>{{ $issuance->expires_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd>@endif
                @endif
                <dt>Alamat masuk</dt><dd>{{ route('login') }}</dd>
            </dl>
            @if($canDeliver)
                <form method="post" action="{{ route('credentials.deliver', $issuance) }}">
                    @csrf
                    <label class="consent-box mb-3"><input name="delivered" value="1" type="checkbox" class="form-check-input" required><span>Saya telah menyerahkan akses melalui nomor WhatsApp yang ditampilkan.</span></label>
                    <button class="btn btn-purple">Catat Sudah Diserahkan</button>
                </form>
            @endif
        </div>
    </section>
</x-layouts::app>
