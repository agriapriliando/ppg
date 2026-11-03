@props(['name','type','parent'=>'null','value'=>null,'label'=>'','selectedLabel'=>'','locked'=>false])
<div class="position-relative" x-data="{
    value: @js((string) $value), search: @js($selectedLabel), items: [], open: false, loading: false, error: '', page: 1, more: false, parentReady: false, previousParent: null, requestNumber: 0,
    get parentValue() { return {{ $parent }}; },
    get disabled() { return @js($locked) || (@js(in_array($type,['city','prodi'],true)) && !this.parentValue); },
    async load(append=false) {
        if(this.disabled) return;
        const request=++this.requestNumber; this.open=true; this.loading=true; this.error='';
        try {
            const url=new URL(@js(route('references.lookup')),window.location.origin); url.searchParams.set('type',@js($type)); url.searchParams.set('q',this.search); url.searchParams.set('page',String(this.page));
            if(this.parentValue) url.searchParams.set('parent',this.parentValue);
            const response=await fetch(url,{headers:{Accept:'application/json'}});
            if(!response.ok) throw new Error('unavailable');
            const result=await response.json(); if(request!==this.requestNumber) return;
            this.items=append?[...this.items,...result.data]:result.data; this.more=result.has_more;
        } catch(error) { if(request===this.requestNumber) this.error='Referensi belum dapat dimuat. Coba kembali.'; }
        finally { if(request===this.requestNumber) this.loading=false; }
    },
    choose(option) { this.value=option.code; this.search=option.name; this.open=false; this.$dispatch('reference-selected',{field:@js($name),value:option.code,option:option}); this.$refs.search.focus(); }
}" x-effect="if(!parentReady){previousParent=parentValue;parentReady=true;}else if(parentValue!==previousParent){previousParent=parentValue;value='';search='';items=[];open=false;requestNumber++;$dispatch('reference-selected',{field:@js($name),value:'',option:null});}" @click.outside="open=false" @keydown.escape.prevent.stop="open=false">
    <label for="picker-{{ $name }}" class="form-label">{{ $label }} *</label>
    <input data-review-label="{{ $label }}" type="hidden" name="{{ $name }}" :value="value">
    <input id="picker-{{ $name }}" x-ref="search" type="search" class="form-control" x-model="search" :disabled="disabled" autocomplete="off" placeholder="{{ $type === 'district' ? 'Ketik nama kecamatan atau kota/kabupaten…' : 'Cari '.mb_strtolower($label).'…' }}" @focus="page=1;load()" @input.debounce.250ms="value='';$dispatch('reference-selected',{field:@js($name),value:'',option:null});page=1;load()" @keydown.arrow-down.prevent="$refs.results.querySelector('button')?.focus()" :aria-expanded="open" aria-controls="results-{{ $name }}" role="combobox">
    <div id="results-{{ $name }}" x-ref="results" class="position-absolute bg-white border rounded shadow-sm w-100 p-2" style="z-index:30;max-height:280px;overflow:auto" x-cloak x-show="open">
        <div class="small text-muted p-2" x-show="loading" role="status">Memuat referensi…</div><div class="small text-danger p-2" x-text="error" role="alert"></div>
        <template x-for="option in items" :key="option.code">
            <button type="button" class="btn btn-light text-start w-100 mb-1 p-2 border-bottom text-decoration-none" @click="choose(option)">
                <template x-if="option.city_name || option.province_name">
                    <div class="d-flex align-items-start gap-2 w-100">
                        <i class="bi bi-geo-alt text-danger fs-6 mt-1 flex-shrink-0"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-dark text-truncate" x-text="option.name"></div>
                            <div class="small text-muted text-truncate" x-text="(option.city_name || '') + (option.city_name && option.province_name ? ', ' : '') + (option.province_name || '')"></div>
                        </div>
                    </div>
                </template>
                <template x-if="!option.city_name && !option.province_name">
                    <span x-text="option.name"></span>
                </template>
            </button>
        </template>
        <div class="small text-muted p-2" x-show="!loading && !error && items.length===0">Tidak ada hasil. Periksa kata pencarian.</div><button type="button" class="btn btn-sm btn-outline-purple w-100" x-show="more" :disabled="loading" @click="page++;load(true)">Muat Hasil Berikutnya</button>
    </div><x-field-error :name="$name" />
</div>
