<x-layouts::public :title="'Berkas Periode · '.$period->name">
    <section class="section-light py-5"><div class="container">
        <a href="{{ route('home') }}" class="btn btn-outline-purple mb-4"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Kembali ke Beranda</a>
        <div class="workspace-card mb-4"><div class="panel-heading"><div><span class="section-badge mb-2">INFORMASI PENERIMAAN</span><h1 class="h4 mb-1">{{ $period->name }}</h1><p class="mb-0">Informasi dan dokumen periode yang dipublikasikan pengelola.</p></div></div><div class="panel-body"><x-period-information :period="$period" /></div></div>
        <section class="workspace-card"><div class="panel-heading"><h2 class="h5 mb-0">Berkas Periode</h2></div><div class="panel-body"><x-period-file-list :period="$period" :documents="$documents" /></div></section>
    </div></section>
</x-layouts::public>
