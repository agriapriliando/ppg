<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Models\ApplicationSetting;
use App\Models\ReferenceDataset;
use App\Models\ReferenceOption;
use App\Models\ReferenceProdi;
use App\Models\ReferencePt;
use App\Models\ReferenceRegion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublishReferenceDataset
{
    public function __construct(private RecordAudit $audit) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, array $input): ReferenceDataset
    {
        abort_unless($actor->isAdministrator(), 403);
        $data = Validator::make($input, [
            'options.*' => ['array:group,code,label'],
            'regions.*' => ['array:code,parent_code,level,name'],
            'pt.*' => ['array:external_id,name'],
            'prodi.*' => ['array:external_id,pt_external_id,name'],
            'version' => ['required', 'string', 'max:64', 'unique:reference_datasets,version'], 'source' => ['required', 'string', 'max:255'],
            'options' => ['required', 'array', 'min:6', 'max:500'], 'options.*.group' => ['required', Rule::in(['gender', 'religion', 'nationality', 'residence', 'transport', 'special_need'])], 'options.*.code' => ['required', 'string', 'max:20'], 'options.*.label' => ['required', 'string', 'max:255'],
            'regions' => ['required', 'array', 'min:3', 'max:10000'], 'regions.*.code' => ['required', 'string', 'max:20', 'distinct'], 'regions.*.parent_code' => ['nullable', 'string', 'max:20'], 'regions.*.level' => ['required', Rule::in(['province', 'city', 'district'])], 'regions.*.name' => ['required', 'string', 'max:255'],
            'pt' => ['required', 'array', 'min:1', 'max:10000'], 'pt.*.external_id' => ['required', 'string', 'max:64', 'distinct'], 'pt.*.name' => ['required', 'string', 'max:255'],
            'prodi' => ['required', 'array', 'min:1', 'max:30000'], 'prodi.*.external_id' => ['required', 'string', 'max:64', 'distinct'], 'prodi.*.pt_external_id' => ['required', 'string', 'max:64'], 'prodi.*.name' => ['required', 'string', 'max:255'],
        ])->validate();
        $groups = array_unique(array_column($data['options'], 'group'));
        if (count($groups) !== 6) {
            throw ValidationException::withMessages(['dataset' => 'Seluruh enam kelompok pilihan biodata wajib tersedia.']);
        }
        $optionKeys = [];
        foreach ($data['options'] as $option) {
            $key = $option['group'].'|'.$option['code'];
            if (isset($optionKeys[$key])) {
                throw ValidationException::withMessages(['dataset' => 'Kode pilihan berulang dalam kelompok yang sama.']);
            }
            $optionKeys[$key] = true;
        }
        $regions = array_column($data['regions'], null, 'code');
        foreach ($data['regions'] as $region) {
            $parent = $regions[$region['parent_code'] ?? ''] ?? null;
            if (($region['level'] === 'province' && ! empty($region['parent_code'])) || ($region['level'] === 'city' && ($parent['level'] ?? null) !== 'province') || ($region['level'] === 'district' && ($parent['level'] ?? null) !== 'city')) {
                throw ValidationException::withMessages(['dataset' => 'Hubungan induk wilayah tidak sah.']);
            }
        }
        $ptIds = array_column($data['pt'], 'external_id');
        foreach ($data['prodi'] as $prodi) {
            if (! in_array($prodi['pt_external_id'], $ptIds, true)) {
                throw ValidationException::withMessages(['dataset' => 'Setiap program studi harus memiliki perguruan tinggi pada dataset yang sama.']);
            }
        }

        return DB::transaction(function () use ($actor, $data): ReferenceDataset {
            ApplicationSetting::query()->firstOrCreate(['key' => 'reference-control'], ['value' => []]);
            ApplicationSetting::query()->where('key', 'reference-control')->lockForUpdate()->firstOrFail();
            $dataset = ReferenceDataset::create(['version' => $data['version'], 'source' => $data['source'], 'state' => 'staging', 'created_by' => $actor->id, 'activated_at' => now()]);
            foreach (['options' => ReferenceOption::class, 'regions' => ReferenceRegion::class, 'pt' => ReferencePt::class, 'prodi' => ReferenceProdi::class] as $group => $model) {
                foreach ($data[$group] as $row) {
                    $model::create(['dataset_id' => $dataset->id, ...$row]);
                }
            }
            ReferenceDataset::query()->where('state', 'active')->update(['state' => 'retired']);
            $dataset->state = 'active';
            $dataset->save();
            $this->audit->handle($actor, 'Menerbitkan referensi formulir', $dataset, ['version' => $dataset->version]);

            return $dataset;
        });
    }
}
