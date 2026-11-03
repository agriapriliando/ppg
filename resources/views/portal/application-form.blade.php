<x-layouts::app title="Formulir Pendaftaran">
@php
    $locked=($application && (!$application->status->editable() || $application->participant->roster_state!=='active' || $application->participant->admin_hold)) || $period->state!==App\Enums\PeriodState::Published;
    $initialStep=1;
    foreach(array_keys($errors->messages()) as $errorKey) { if(isset($fields[$errorKey])) { $initialStep=$fields[$errorKey]['step']; break; } if(in_array($errorKey,['pt_external_id','prodi_external_id','pt_name','prodi_name'])) {$initialStep=4;break;} }
    $labels=[];
    foreach($regions as $region) { $labels[$region->code]=$region->name; }
@endphp
<div class="form-topline"><div><span class="status-pill {{ $application?->status->value ?? 'draf' }}">{{ $application?->status->label() ?? 'Draf pribadi · kepesertaan belum disetujui' }}</span><span class="small text-muted ms-2">{{ $workspace?->saved_at ? 'Disimpan: '.$workspace->saved_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : 'Belum ada draf tersimpan' }}</span></div><strong>{{ $period->name }}</strong></div>
@if($locked)<div class="notice-box info"><i class="bi bi-lock" aria-hidden="true"></i><p class="mb-0">Formulir dapat dibaca. Pengubahan dibuka jika operator meminta perbaikan.</p></div>@endif
@if(!$dataset)<div class="notice-box warning"><i class="bi bi-info-circle" aria-hidden="true"></i><p class="mb-0">Referensi formulir belum diterbitkan pengelola. Anda dapat menyimpan draf sambil menunggu.</p></div>@endif
<section x-effect="if(step===5) refreshSummary()" x-data="{
    step:{{ $initialStep }},
    showConfirmModal: false,
    consentChecked: false,
    summary:[],
    refreshSummary(){
        this.summary=[...this.$refs.applicationForm.querySelectorAll('[data-review-label]')].filter(input=>!(input.name==='kps_number'&&this.kps!=='1')).map(input=>({
            key:input.name,
            label:input.dataset.reviewLabel,
            value:input.type==='hidden'
                ?(document.getElementById('picker-'+input.name)?.value||input.value||'')
                :(input.tagName==='SELECT'?(input.value!==''?input.selectedOptions[0]?.textContent:''):input.value)
        }));
    },
    province:@js((string)\App\Support\FormInput::old('province_code',$data['province_code']??'')),
    provinceName:@js($labels[\App\Support\FormInput::old('province_code',$data['province_code']??'')]??''),
    city:@js((string)\App\Support\FormInput::old('city_code',$data['city_code']??'')),
    cityName:@js($labels[\App\Support\FormInput::old('city_code',$data['city_code']??'')]??''),
    pt:@js(\App\Support\FormInput::old('pt_external_id',$data['pt_external_id']??'')),
    kps:@js((string)\App\Support\FormInput::old('is_kps_recipient',isset($data['is_kps_recipient'])?(int)$data['is_kps_recipient']:''))
}" @reference-selected="
    if($event.detail.field==='district_code'){
        if($event.detail.option){
            city=$event.detail.option.city_code||'';
            cityName=$event.detail.option.city_name||'';
            province=$event.detail.option.province_code||'';
            provinceName=$event.detail.option.province_name||'';
        } else if(!$event.detail.value){
            city=''; cityName=''; province=''; provinceName='';
        }
    }
    if($event.detail.field==='pt_external_id') pt=$event.detail.value;
">
<nav class="form-steps" aria-label="Tahapan formulir">@foreach(['Identitas','Alamat','Tambahan','Pendidikan','Tinjau & kirim'] as $stepName)<button type="button" @click="step={{ $loop->iteration }}" :class="{'active':step==={{ $loop->iteration }}}" :aria-current="step==={{ $loop->iteration }}?'step':null"><span>{{ $loop->iteration }}</span><b>{{ $stepName }}</b></button>@endforeach</nav>
<form method="post" action="{{ route('applicant.form.update',$period) }}" class="workspace-card" x-ref="applicationForm" novalidate>@csrf<input type="hidden" name="version" value="{{ \App\Support\FormInput::old('version',$workspace?->lock_version??0) }}"><div class="panel-body form-panel">
@foreach([1=>'Identitas & kontak',2=>'Alamat tempat tinggal',3=>'Informasi tambahan'] as $stepNumber=>$heading)<div x-show="step==={{ $stepNumber }}" @if($stepNumber!==$initialStep)x-cloak @endif><div class="section-heading"><h2>{{ $heading }}</h2><p>Kolom bertanda * wajib saat mengirim formulir.</p></div>@if($stepNumber===1 && $application)<div class="mb-3"><label for="sk-name" class="form-label">Nama sesuai SK</label><input id="sk-name" class="form-control bg-light" value="{{ $application->participant->name_on_sk }}" readonly></div>@endif<div class="row g-3">
@foreach($fields as $key=>$field)@continue($field['step']!==$stepNumber)<div class="col-md-6" @if($key==='kps_number') x-show="kps==='1'" @endif>
@if($field['type']==='region')
    @if($key === 'district_code')
        <x-reference-picker :name="$key" type="district" parent="null" :value="\App\Support\FormInput::old($key,$data[$key]??'')" :selected-label="$labels[\App\Support\FormInput::old($key,$data[$key]??'')]??''" :label="$field['label']" :locked="$locked" />
        <div class="form-text text-muted small mt-1"><i class="bi bi-info-circle me-1"></i>Pilih kecamatan Anda. Kota/kabupaten dan provinsi akan terisi secara otomatis.</div>
    @elseif($key === 'city_code')
        <label for="picker-city_code" class="form-label d-flex align-items-center justify-content-between">
            <span>{{ $field['label'] }} @if($field['required'])<span class="text-danger">*</span>@endif</span>
            <span class="badge bg-light text-secondary border small"><i class="bi bi-magic me-1"></i>Otomatis</span>
        </label>
        <input type="hidden" name="city_code" :value="city" data-review-label="{{ $field['label'] }}">
        <div class="input-group">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-building"></i></span>
            <input id="picker-city_code" type="text" class="form-control bg-light border-start-0 text-dark fw-medium"
                   :value="cityName" placeholder="Terisi otomatis setelah memilih kecamatan" readonly disabled>
        </div>
        <div class="form-text text-muted small mt-1">Terisi otomatis dari pilihan kecamatan.</div>
        <x-field-error name="city_code" />
    @elseif($key === 'province_code')
        <label for="picker-province_code" class="form-label d-flex align-items-center justify-content-between">
            <span>{{ $field['label'] }} @if($field['required'])<span class="text-danger">*</span>@endif</span>
            <span class="badge bg-light text-secondary border small"><i class="bi bi-magic me-1"></i>Otomatis</span>
        </label>
        <input type="hidden" name="province_code" :value="province" data-review-label="{{ $field['label'] }}">
        <div class="input-group">
            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-map"></i></span>
            <input id="picker-province_code" type="text" class="form-control bg-light border-start-0 text-dark fw-medium"
                   :value="provinceName" placeholder="Terisi otomatis setelah memilih kecamatan" readonly disabled>
        </div>
        <div class="form-text text-muted small mt-1">Terisi otomatis dari pilihan kecamatan.</div>
        <x-field-error name="province_code" />
    @endif
@else<label for="form-{{ $key }}" class="form-label">{{ $field['label'] }} @if($field['required'])<span class="text-danger">*</span>@endif</label>
@if($field['type']==='select')<select id="form-{{ $key }}" data-review-label="{{ $field['label'] }}" name="{{ $key }}" class="form-select" @disabled($locked)><option value="">Pilih {{ mb_strtolower($field['label']) }}</option>@foreach($options[$field['reference']]??[] as $option)<option value="{{ $option->code }}" @selected((string)\App\Support\FormInput::old($key,$data[$key]??'')===$option->code)>{{ $option->label }}</option>@endforeach</select>
@elseif($field['type']==='boolean')<select id="form-{{ $key }}" data-review-label="{{ $field['label'] }}" name="{{ $key }}" class="form-select" x-model="kps" @disabled($locked)><option value="">Pilih</option><option value="0">Tidak</option><option value="1">Ya</option></select>
@else<input id="form-{{ $key }}" data-review-label="{{ $field['label'] }}" name="{{ $key }}" type="{{ $field['type'] }}" class="form-control" value="{{ \App\Support\FormInput::old($key,$data[$key]??'') }}" @disabled($locked) maxlength="{{ $key==='nik'?16:($key==='nisn'?10:255) }}" @if(in_array($key,['nik','nisn','rt','rw'])) inputmode="numeric" @endif @if($key==='kps_number') :disabled="kps!=='1'||@js($locked)" @endif>@endif @if($key==='whatsapp_number')<div class="form-text text-muted mt-1 small"><i class="bi bi-whatsapp text-success me-1"></i>Terhubung langsung dengan nomor WhatsApp akun saat pertama kali mendaftar. Pengubahan nomor di sini akan otomatis memperbarui nomor WhatsApp akun.</div>@endif<x-field-error :name="$key" />@endif
</div>@endforeach</div></div>@endforeach
<div x-show="step===4" @if($initialStep!==4)x-cloak @endif><div class="section-heading"><h2>Pendidikan asal</h2><p>Ketik nama perguruan tinggi dan program studi asal secara manual.</p></div><div class="row g-3"><div class="col-12"><label for="form-pt_name" class="form-label">Perguruan tinggi asal <span class="text-danger">*</span></label><input id="form-pt_name" data-review-label="Perguruan tinggi asal" name="pt_name" type="text" class="form-control" value="{{ \App\Support\FormInput::old('pt_name', $data['pt_name'] ?? ($data['pt_external_id'] ?? '')) }}" @disabled($locked) maxlength="255" placeholder="Contoh: Institut Agama Kristen Negeri Palangka Raya"><div class="form-text text-muted small mt-1">Ketik nama lengkap perguruan tinggi asal Anda.</div><x-field-error name="pt_name" /><x-field-error name="pt_external_id" /></div><div class="col-12"><label for="form-prodi_name" class="form-label">Program studi asal <span class="text-danger">*</span></label><input id="form-prodi_name" data-review-label="Program studi asal" name="prodi_name" type="text" class="form-control" value="{{ \App\Support\FormInput::old('prodi_name', $data['prodi_name'] ?? ($data['prodi_external_id'] ?? '')) }}" @disabled($locked) maxlength="255" placeholder="Contoh: Pendidikan Agama Kristen"><div class="form-text text-muted small mt-1">Ketik nama lengkap program studi asal Anda.</div><x-field-error name="prodi_name" /><x-field-error name="prodi_external_id" /></div></div></div>
<div x-show="step===5" @if($initialStep!==5)x-cloak @endif><div class="section-heading"><h2>Tinjau & kirim</h2><p>Periksa seluruh isian pada tahap sebelumnya. Tanggal daftar ditetapkan sekali pada pengiriman pertama.</p></div><div class="review-grid mb-4"><template x-for="item in summary" :key="item.key"><div class="review-block"><h3 x-text="item.label"></h3><p class="mb-0" x-text="item.value||'—'"></p></div></template></div><div class="notice-box info mb-3"><i class="bi bi-calendar-check" aria-hidden="true"></i><p class="mb-0">Perbaikan dan kirim ulang mempertahankan tanggal pendaftaran pertama. Setelah dikirim, formulir dikunci selama pemeriksaan.</p></div>@if(!$application)<div class="notice-box warning mb-3"><i class="bi bi-patch-check" aria-hidden="true"></i><p class="mb-0">Kepesertaan SK belum disetujui. Simpan draf dan <a href="{{ route('applicant.claims') }}">pantau pencocokan SK</a> sebelum mengirim.</p></div>@endif @if($consent && !$locked)<div class="notice-box info mb-3"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>Konfirmasi Pernyataan</strong><p class="mb-0">Klik tombol <strong>Kirim Pendaftaran</strong> di bawah untuk membaca dan menyetujui pernyataan simulasi pendaftaran pada jendela konfirmasi.</p></div></div>@elseif(!$consent)<p class="text-muted">Naskah pernyataan belum diterbitkan pengelola; pengiriman belum tersedia.</p>@endif</div>
</div>@if($consent && !$locked)<input type="hidden" name="consent_id" value="{{ $consent->id }}"><div x-show="showConfirmModal" class="ui-modal-backdrop" @keydown.escape.window="showConfirmModal = false" @click.self="showConfirmModal = false" style="display: none;" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" role="dialog" aria-modal="true" aria-labelledby="modal-confirm-submit-title"><div class="ui-modal" x-show="showConfirmModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"><div class="ui-modal-header d-flex align-items-center justify-content-between"><div class="d-flex align-items-center gap-2"><span class="dayak-shield-badge" style="width: 32px; height: 32px; font-size: 0.85rem;"><i class="bi bi-shield-check" aria-hidden="true"></i></span><h2 id="modal-confirm-submit-title" class="h6 mb-0 fw-bold text-purple">Konfirmasi Pengiriman Pendaftaran</h2></div><button type="button" class="btn-close" @click="showConfirmModal = false" aria-label="Tutup"></button></div><div class="ui-modal-body"><p class="text-muted mb-3">Pastikan seluruh data yang Anda masukkan sudah sesuai dan benar. Setelah formulir dikirim, data pendaftaran Anda akan <strong>dikunci</strong> untuk proses pemeriksaan administrasi.</p><div class="p-3 mb-3 rounded-3 border bg-light" style="border-left: 4px solid var(--purple, #4a1d6d) !important;"><label class="form-check d-flex align-items-start gap-2 mb-0 cursor-pointer"><input type="checkbox" class="form-check-input mt-1 flex-shrink-0" name="consent" value="1" x-model="consentChecked" required><span class="text-dark small lh-base">{{ $consent->body }}@if(!empty($consent->version))<small class="d-block text-muted mt-1">Pernyataan versi {{ $consent->version }}</small>@endif</span></label></div><div class="small text-muted mb-3" x-show="!consentChecked"><i class="bi bi-info-circle text-warning me-1"></i>Centang kotak pernyataan di atas untuk mengaktifkan tombol kirim.</div><div class="modal-actions d-flex align-items-center justify-content-end gap-2 pt-2 border-top"><button type="button" class="btn btn-light border px-3" @click="showConfirmModal = false">Batal</button><button type="submit" name="action" value="submit" class="btn btn-gold px-4 d-inline-flex align-items-center gap-2" :disabled="!consentChecked"><i class="bi bi-send-check" aria-hidden="true"></i><span>Kirim Pendaftaran</span></button></div></div></div></div>@endif<div class="form-actions"><button type="button" class="btn btn-light border" @click="step=Math.max(1,step-1)" :disabled="step===1">Kembali</button><div class="d-flex flex-wrap gap-2">@if(!$locked)<button name="action" value="draft" class="btn btn-outline-purple" formnovalidate>Simpan Draf</button>@endif<button type="button" class="btn btn-purple" @click="step=Math.min(5,step+1)" x-show="step<5">Selanjutnya</button>@if(!$locked)<button type="button" class="btn btn-gold" x-show="step===5" @click="showConfirmModal = true" @disabled(!$application || !$consent || !$dataset)>Kirim Pendaftaran</button>@endif</div></div></form></section>
</x-layouts::app>
