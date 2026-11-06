<x-layouts::app title="Informasi SK">
    @if($participant)
        <section class="workspace-card mb-4"><div class="panel-body">
            <form method="get" action="{{ route('applicant.sk-information') }}" class="row g-3 align-items-end">
                <div class="col-sm-8"><label for="sk-period" class="form-label">Periode kepesertaan</label><select id="sk-period" name="period_id" class="form-select">@foreach($participants as $item)<option value="{{ $item->admission_period_id }}" @selected($participant->id === $item->id)>{{ $item->period->name }} · {{ $item->period->academic_year }}</option>@endforeach</select></div>
                <div class="col-sm-4"><button class="btn btn-purple w-100">Tampilkan Informasi</button></div>
            </form>
        </div></section>
        <div class="row g-4">
            <div class="col-lg-6"><section class="workspace-card h-100"><div class="panel-heading"><div><h2 class="h5 mb-1">Data Pribadi Sesuai SK</h2><p class="mb-0">Sesuai data SK yang telah dihubungkan ke akun Anda.</p></div></div><div class="panel-body">
                <dl class="row g-2 mb-0">
                    <dt class="col-sm-5">Nama sesuai SK</dt><dd class="col-sm-7">{{ $participant->name_on_sk }}</dd>
                    <dt class="col-sm-5">Peg ID</dt><dd class="col-sm-7">{{ $participant->official_participant_id ?? 'Belum tersedia' }}</dd>
                    <dt class="col-sm-5">Kota/Kabupaten Asal</dt><dd class="col-sm-7">{{ $participant->origin_city_name ?? 'Belum tersedia' }}</dd>
                    <dt class="col-sm-5">Provinsi Asal</dt><dd class="col-sm-7">{{ $participant->origin_province_name ?? 'Belum tersedia' }}</dd>
                    <dt class="col-sm-5">Nomor SK</dt><dd class="col-sm-7">{{ $participant->sk_number }}</dd>
                    <dt class="col-sm-5">Tanggal SK</dt><dd class="col-sm-7">{{ $participant->sk_date->translatedFormat('d F Y') }}</dd>
                    <dt class="col-sm-5">Nomor urut SK</dt><dd class="col-sm-7">{{ $participant->sk_order }}</dd>
                    <dt class="col-sm-5">Kepesertaan</dt><dd class="col-sm-7">{{ $participant->roster_state === 'active' ? 'Aktif' : 'Ditarik' }}{{ $participant->admin_hold ? ' · Memerlukan pemeriksaan administratif' : '' }}</dd>
                    <dt class="col-sm-5">Operator pendamping</dt><dd class="col-sm-7">{{ $participant->assignedOperator?->name ?? 'Belum ditugaskan' }}</dd>
                </dl><p class="small text-muted mt-3 mb-0">Hubungi pengelola jika data sesuai SK perlu dikoreksi.</p>
            </div></section></div>
            <div class="col-lg-6"><section class="workspace-card h-100"><div class="panel-heading"><h2 class="h5 mb-0">Informasi Periode</h2></div><div class="panel-body"><x-period-information :period="$period" /></div></section></div>
        </div>
        <section class="workspace-card mt-4"><div class="panel-heading"><div><h2 class="h5 mb-1">Berkas Periode</h2><p class="mb-0">Panduan dan dokumen yang dibagikan pengelola untuk periode {{ $period->name }}.</p></div></div><div class="panel-body"><x-period-file-list :period="$period" :documents="$documents" audience="applicant" /></div></section>
    @else
        <section class="workspace-card"><div class="panel-body text-center py-5"><i class="bi bi-person-vcard fs-1 text-purple" aria-hidden="true"></i><h2 class="h5 mt-3">Kepesertaan SK belum terhubung</h2><p class="text-muted">Informasi pribadi sesuai SK akan tersedia setelah pencocokan kepesertaan disetujui.</p><a href="{{ route('applicant.claims') }}" class="btn btn-outline-purple">Lihat Permohonan Saya</a></div></section>
    @endif
</x-layouts::app>
