@props(['period'])
<dl class="row g-2 mb-0">
    <dt class="col-sm-4">Nama periode</dt><dd class="col-sm-8">{{ $period->name }}</dd>
    <dt class="col-sm-4">Kode periode</dt><dd class="col-sm-8">{{ $period->code }}</dd>
    <dt class="col-sm-4">Tahun akademik</dt><dd class="col-sm-8">{{ $period->academic_year }}</dd>
    <dt class="col-sm-4">Status pendaftaran</dt><dd class="col-sm-8"><span class="badge text-bg-{{ $period->acceptsApplications() ? 'success' : 'secondary' }}">{{ $period->availabilityLabel() }}</span></dd>
    <dt class="col-sm-4">Pendaftaran dibuka</dt><dd class="col-sm-8">{{ $period->opens_at ? $period->opens_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i').' WIB' : 'Belum ditentukan' }}</dd>
    <dt class="col-sm-4">Pendaftaran ditutup</dt><dd class="col-sm-8">{{ $period->closes_at ? $period->closes_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i').' WIB' : 'Belum ditentukan' }}</dd>
    @if($period->allow_correction_after_close && $period->correction_closes_at)
        <dt class="col-sm-4">Batas perbaikan</dt><dd class="col-sm-8">{{ $period->correction_closes_at->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</dd>
    @endif
</dl>
