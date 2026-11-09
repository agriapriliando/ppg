<dl class="detail-list">
    <dt>Nama Sesuai SK</dt>
    <dd class="fw-bold text-dark fs-6">{{ $participant->name_on_sk }}</dd>

    <dt>Nomor SK & Tanggal</dt>
    <dd>{{ $participant->sk_number }} · {{ $participant->sk_date->format('d/m/Y') }}</dd>

    <dt>Nomor Urut SK</dt>
    <dd>{{ $participant->sk_order }}</dd>

    <dt>Peg ID</dt>
    <dd>{{ $participant->official_participant_id ?? 'Tidak tersedia' }}</dd>
    <dt>Nomor HP (SK)</dt>
    <dd>{{ $participant->phone_number ?? 'Belum diisi' }}</dd>
    <dt>Kota/Kabupaten Asal</dt><dd>{{ $participant->origin_city_name ?? 'Belum diisi' }}</dd>
    <dt>Provinsi Asal</dt><dd>{{ $participant->origin_province_name ?? 'Belum diisi' }}</dd>

    <dt>Periode Penerimaan</dt>
    <dd>{{ $participant->period->name }}</dd>

    <dt>Operator Penanggung Jawab</dt>
    <dd>
        @if($participant->assignedOperator)
            <span class="badge text-bg-light border text-purple fs-6">{{ $participant->assignedOperator->name }}</span>
        @else
            <span class="text-muted">Belum ditugaskan</span>
        @endif
    </dd>

    <dt>Status Daftar Resmi</dt>
    <dd>
        @if($participant->roster_state === 'active')
            <span class="status-pill diterima_lengkap">Aktif</span>
        @else
            <span class="status-pill declined">Ditarik dari Kepesertaan</span>
        @endif
    </dd>

    <dt>Status Akun Portal</dt>
    <dd>
        @if($participant->claimedUser)
            @if($participant->claimedUser->account_state === App\Enums\AccountState::Disabled)
                <span class="status-pill declined">Akun Dinonaktifkan</span>
            @elseif($participant->claimedUser->account_state === App\Enums\AccountState::PendingActivation)
                <span class="status-pill perbaikan">Menunggu Aktivasi</span>
            @else
                <span class="status-pill diterima_lengkap">Akun Terhubung</span>
            @endif
            <span class="text-muted ms-2">({{ $participant->claimedUser->name }} · {{ $participant->claimedUser->login_id }})</span>
        @else
            <span class="status-pill draf">Belum Mengklaim Akun</span>
        @endif
    </dd>

    <dt>Status Antrean Pendaftaran</dt>
    <dd>
        @if($participant->application)
            <span class="status-pill {{ $participant->application->status->value }}">
                {{ $participant->application->status->label() }}
            </span>
            <span class="text-muted ms-2">({{ $participant->application->application_number }})</span>
        @else
            <span class="status-pill draf">Belum Masuk Antrean Pendaftaran</span>
        @endif
    </dd>
</dl>
