<x-layouts::app title="Formulir Pendaftaran">
@php
    $locked=($application && (!$application->status->editable() || $application->participant->roster_state!=='active' || $application->participant->admin_hold)) || $period->state!==App\Enums\PeriodState::Published;
    $initialStep=max(1, min(5, request()->integer('section', 1)));
    foreach(array_keys($errors->messages()) as $errorKey) { if(isset($fields[$errorKey])) { $initialStep=$fields[$errorKey]['step']; break; } if(str_starts_with($errorKey,'education_history.') || in_array($errorKey,['pt_external_id','prodi_external_id','pt_name','prodi_name','degree_level','graduation_year','education_history'])) {$initialStep=4;break;} }
    $labels=[];
    foreach($regions as $region) { $labels[$region->code]=$region->name; }
@endphp
<x-registration-progress :active="1" :period="$period" />
<div class="form-topline"><div><span class="status-pill {{ $application?->status->value ?? 'draf' }}">{{ $application?->status->label() ?? 'Draf pribadi · kepesertaan belum disetujui' }}</span><span class="small text-muted ms-2">{{ $workspace?->saved_at ? 'Disimpan: '.$workspace->saved_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : 'Belum ada draf tersimpan' }}</span></div><strong>{{ $period->name }}</strong></div>
@if($locked)<div class="notice-box info"><i class="bi bi-lock" aria-hidden="true"></i><p class="mb-0">Formulir dapat dibaca. Pengubahan dibuka jika operator meminta perbaikan.</p></div>@endif
@if(!$dataset)<div class="notice-box warning"><i class="bi bi-info-circle" aria-hidden="true"></i><p class="mb-0">Referensi formulir belum diterbitkan pengelola. Anda dapat menyimpan draf sambil menunggu.</p></div>@endif
@if(!config('pmb.autosave_enabled'))<div class="alert alert-warning">Mode simpan manual aktif. Gunakan Simpan Draf atau Simpan &amp; Lanjut ke Berkas sebelum meninggalkan halaman.</div>@endif
<script src="{{ asset('js/applicant-autosave.js') }}?v={{ filemtime(public_path('js/applicant-autosave.js')) }}"></script>
<section x-effect="if(step===5) refreshSummary()" x-data="{
    ...applicantAutosave({
        url: @js(route('applicant.form.autosave', $period)), documentsUrl: @js(route('applicant.documents.index', $period)),
        csrf: @js(csrf_token()), version: {{ $workspace?->lock_version ?? 0 }}, locked: @js($locked || !config('pmb.autosave_enabled')),
        fields: @js([...array_keys($fields), 'degree_level', 'education_history', 'pt_name', 'prodi_name', 'pt_external_id', 'prodi_external_id', 'graduation_year'])
    }),
    step:{{ $initialStep }},
    showConfirmModal: false,
    consentChecked: false,
    summary:[],
    refreshSummary(){
        this.summary=[...this.$refs.applicationForm.querySelectorAll('[data-review-label]')].filter(input=>!input.matches(':disabled') && !(input.name==='kps_number'&&this.kps!=='1')).map(input=>({
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
    $nextTick(() => captureChanges(true));
">
<nav class="form-steps" aria-label="Tahapan formulir">@foreach(['Identitas','Alamat','Tambahan','Pendidikan','Ringkasan'] as $stepName)<button type="button" @click="moveStep({{ $loop->iteration }})" :class="{'active':step==={{ $loop->iteration }}}" :aria-current="step==={{ $loop->iteration }}?'step':null"><span>{{ $loop->iteration }}</span><b>{{ $stepName }}</b></button>@endforeach</nav>
<form method="post" action="{{ route('applicant.form.update',$period) }}" class="workspace-card" x-ref="applicationForm" novalidate @if(config('pmb.autosave_enabled')) @input="captureChanges()" @change="captureChanges(true)" @focusout="captureChanges(true)" @submit.prevent="captureChanges(true)" @endif>@csrf<input type="hidden" name="version" value="{{ \App\Support\FormInput::old('version',$workspace?->lock_version??0) }}"><div class="panel-body form-panel">
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
@elseif($field['type']==='choice')<select id="form-{{ $key }}" data-review-label="{{ $field['label'] }}" name="{{ $key }}" class="form-select" @disabled($locked)><option value="">Pilih kategori wilayah tempat tinggal</option>@foreach($field['options'] as $value => $label)<option value="{{ $value }}" @selected((string)\App\Support\FormInput::old($key,$data[$key]??'')===$value)>{{ $label }}</option>@endforeach</select>
@elseif($field['type']==='boolean')<select id="form-{{ $key }}" data-review-label="{{ $field['label'] }}" name="{{ $key }}" class="form-select" x-model="kps" @disabled($locked)><option value="">Pilih</option><option value="0">Tidak</option><option value="1">Ya</option></select>
@else<input id="form-{{ $key }}" data-review-label="{{ $field['label'] }}" name="{{ $key }}" type="{{ $field['type'] }}" @if(in_array($key, ['contact_email', 'nisn'])) required aria-required="true" @endif @if($key === 'contact_email') autocomplete="email" @endif class="form-control" value="{{ \App\Support\FormInput::old($key,$data[$key]??'') }}" @disabled($locked) maxlength="{{ $key==='nik'?16:($key==='nuptk'?16:($key==='peg_id'?100:($key==='npwp'?40:255))) }}" @if(in_array($key,['nik','nuptk','rt','rw','npwp'])) inputmode="numeric" @endif @if($key==='npwp') pattern="[0-9]*" @input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0,40)" @endif @if($key==='nuptk') @input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0,16)" @endif @if($key==='kps_number') :disabled="kps!=='1'||@js($locked)" @endif>@endif @if($key==='nuptk')<div class="form-text">Isi 16 angka sesuai NUPTK, jika tersedia.</div>@elseif($key==='nisn')<div class="form-text">Lihat di Ijazah S1 atau SMA.<br><a href="https://nisn.data.kemendikdasmen.go.id/" target="_blank" rel="noopener noreferrer">Cek di Kemendikdasmen <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a></div>@elseif($key==='peg_id')<div class="form-text">Nomor Peg ID wajib diisi sebelum mengirim pendaftaran.</div>@endif @if($key==='whatsapp_number')<div class="form-text text-muted mt-1 small"><i class="bi bi-whatsapp text-success me-1"></i>Terhubung langsung dengan nomor WhatsApp akun saat pertama kali mendaftar. Pengubahan nomor di sini akan otomatis memperbarui nomor WhatsApp akun.</div>@endif<x-field-error :name="$key" />@endif
</div>@endforeach</div></div>@endforeach
<div x-show="step===4" @if($initialStep!==4)x-cloak @endif x-data="{ degreeLevel: @js(\App\Support\FormInput::old('degree_level', $data['degree_level'] ?? '')) }">
    <div class="section-heading">
        <h2>Riwayat pendidikan</h2>
        <p>Pilih pendidikan tertinggi yang sudah lulus, lalu lengkapi semua jenjang hingga pendidikan terakhir sesuai ijazah.</p>
    </div>
    <div class="mb-4">
        <label for="form-degree_level" class="form-label fw-semibold">Pendidikan Terakhir <span class="text-danger">*</span></label>
        <select id="form-degree_level" name="degree_level" class="form-select" data-review-label="Pendidikan terakhir" x-model="degreeLevel" @disabled($locked)>
            <option value="">Pilih pendidikan terakhir</option>
            @foreach(['S1' => 'S1 (Sarjana)', 'S2' => 'S2 (Magister)', 'S3' => 'S3 (Doktor)'] as $level => $label)
                <option value="{{ $level }}" @selected(\App\Support\FormInput::old('degree_level', $data['degree_level'] ?? '') === $level)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="form-text">S1: isi S1. S2: isi S1 dan S2. S3: isi S1, S2, dan S3. Jangan masukkan jenjang yang masih ditempuh.</div>
        <x-field-error name="degree_level" /><x-field-error name="education_history" />
    </div>
    @foreach(['S1' => 'Sarjana', 'S2' => 'Magister', 'S3' => 'Doktor'] as $level => $label)
        <fieldset class="border rounded-3 p-3 p-md-4 mb-3 bg-light"
            x-show="['S1','S2','S3'].indexOf(degreeLevel) >= {{ array_search($level, ['S1', 'S2', 'S3']) }}"
            :disabled="{{ $locked ? 'true' : 'false' }} || ['S1','S2','S3'].indexOf(degreeLevel) < {{ array_search($level, ['S1', 'S2', 'S3']) }}">
            <legend class="float-none w-auto px-2 fs-6 fw-bold text-purple">{{ $level }} &mdash; {{ $label }}</legend>
            <div class="row g-3">
                <div class="col-12">
                    <label for="education-{{ $level }}-pt" class="form-label">Nama Perguruan Tinggi {{ $level }} <span class="text-danger">*</span></label>
                    <input id="education-{{ $level }}-pt" name="education_history[{{ $level }}][pt_name]" type="text" class="form-control"
                        data-review-label="Perguruan tinggi {{ $level }}" maxlength="255"
                        value="{{ \App\Support\FormInput::old('education_history.'.$level.'.pt_name', $data['education_history'][$level]['pt_name'] ?? '') }}"
                        placeholder="Nama lengkap perguruan tinggi sesuai ijazah" @disabled($locked)>
                    <x-field-error :name="'education_history.'.$level.'.pt_name'" />
                </div>
                <div class="col-md-8">
                    <label for="education-{{ $level }}-prodi" class="form-label">Nama Program Studi {{ $level }} <span class="text-danger">*</span></label>
                    <input id="education-{{ $level }}-prodi" name="education_history[{{ $level }}][prodi_name]" type="text" class="form-control"
                        data-review-label="Program studi {{ $level }}" maxlength="255"
                        value="{{ \App\Support\FormInput::old('education_history.'.$level.'.prodi_name', $data['education_history'][$level]['prodi_name'] ?? '') }}"
                        placeholder="Nama program studi sesuai ijazah" @disabled($locked)>
                    <x-field-error :name="'education_history.'.$level.'.prodi_name'" />
                </div>
                <div class="col-md-4">
                    <label for="education-{{ $level }}-year" class="form-label">Tahun Lulus {{ $level }} <span class="text-danger">*</span></label>
                    <input id="education-{{ $level }}-year" name="education_history[{{ $level }}][graduation_year]" type="text" class="form-control"
                        data-review-label="Tahun lulus {{ $level }}" maxlength="4" pattern="[0-9]{4}" inputmode="numeric"
                        @input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0, 4)"
                        value="{{ \App\Support\FormInput::old('education_history.'.$level.'.graduation_year', $data['education_history'][$level]['graduation_year'] ?? '') }}"
                        placeholder="Contoh: 2020" @disabled($locked)>
                    <x-field-error :name="'education_history.'.$level.'.graduation_year'" />
                </div>
            </div>
            <x-field-error :name="'education_history.'.$level" />
        </fieldset>
    @endforeach
    <p class="small text-muted">Semua kolom pada jenjang yang ditampilkan wajib dilengkapi sebelum pendaftaran dikirim. Anda dapat menyimpan draf terlebih dahulu.</p>
</div>
<div x-show="step===5" @if($initialStep!==5)x-cloak @endif>
    <div class="section-heading"><h2>Ringkasan biodata</h2><p>Periksa isian Anda, lalu lanjutkan ke halaman berkas untuk mengirim pendaftaran.</p></div>
    <div class="review-grid mb-4"><template x-for="item in summary" :key="item.key"><div class="review-block"><h3 x-text="item.label"></h3><p class="mb-0" x-text="item.value || 'Belum diisi'"></p></div></template></div>
    <div class="notice-box info"><i class="bi bi-info-circle" aria-hidden="true"></i><p class="mb-0">Biodata tersimpan sebagai draf. Pendaftaran baru terkirim setelah Anda menyetujui pernyataan dan menekan Kirim Pendaftaran di halaman berkas.</p></div>
</div>
</div>
<div class="p-3 border-top" role="status" aria-live="polite" @if($locked || !config('pmb.autosave_enabled')) hidden @endif>
    <span x-show="saveState === 'saved'" class="text-success"><i class="bi bi-check-circle me-1"></i>Draf tersimpan <span x-text="savedTime"></span></span>
    <span x-show="saveState === 'pending'" class="text-warning-emphasis">Ada perubahan belum tersimpan.</span>
    <span x-show="saveState === 'saving'" class="text-muted">Menyimpan?</span>
    <span x-show="['failed','conflict','expired','blocked'].includes(saveState)" class="text-danger" x-text="saveMessage"></span>
    <button type="button" class="btn btn-sm btn-outline-purple ms-2" x-show="saveState === 'failed'" @click="flushDraft()">Coba simpan lagi</button>
    <p class="small text-muted mb-0 mt-1">Isian yang belum lengkap tetap disimpan sebagai draf. Periksa kelengkapannya sebelum mengirim.</p>
</div>
<div class="form-actions">
    <button type="button" class="btn btn-light border" @click="moveStep(step - 1)" :disabled="step === 1">Kembali</button>
    <div class="d-flex flex-wrap gap-2">
        @if(!$locked)
            @if(config('pmb.autosave_enabled'))<button type="button" class="btn btn-outline-purple" @click="captureChanges(true)" :disabled="sending || stopped">Simpan sekarang</button>
            @else<input type="hidden" name="action" value="draft"><button type="submit" class="btn btn-outline-purple">Simpan Draf</button>@endif
        @endif
        <button type="button" class="btn btn-purple" @click="moveStep(step + 1)" x-show="step < 5">Selanjutnya</button>
        @if(!config('pmb.autosave_enabled') && !$locked)
            <button type="submit" name="continue_documents" value="1" class="btn btn-gold" x-show="step === 5">Simpan &amp; Lanjut ke Berkas</button>
        @else
            <button type="button" class="btn btn-gold" x-show="step === 5" @click="goToDocuments()" :disabled="navigating || stopped">Lanjut ke Berkas</button>
        @endif
    </div>
</div>
</form>
</section>
</x-layouts::app>
