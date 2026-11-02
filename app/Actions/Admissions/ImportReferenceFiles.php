<?php

namespace App\Actions\Admissions;

use App\Models\ApplicationSetting;
use App\Models\ReferenceDataset;
use App\Models\ReferenceOption;
use App\Models\ReferenceProdi;
use App\Models\ReferencePt;
use App\Models\ReferenceRegion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportReferenceFiles
{
    private const GROUPS = [
        'options' => [ReferenceOption::class, ['group', 'code', 'label']],
        'regions' => [ReferenceRegion::class, ['code', 'parent_code', 'level', 'name']],
        'pt' => [ReferencePt::class, ['external_id', 'name']],
        'prodi' => [ReferenceProdi::class, ['external_id', 'pt_external_id', 'name']],
    ];

    public function __construct(private PublishReferenceDataset $publish) {}

    /** @param array<int|string, UploadedFile> $files */
    public function handle(User $actor, string $version, string $source, array $files): ReferenceDataset
    {
        abort_unless($actor->isAdministrator(), 403);
        $replacements = [];
        $optionGroups = [];
        foreach ($files as $key => $file) {
            $parts = isset(self::GROUPS[$key]) ? [$key => $this->read($file, $key)] : $this->detect($file, 'files.'.$key);
            foreach ($parts as $group => $rows) {
                if ($rows === []) {
                    throw ValidationException::withMessages(['files.'.$key => 'File referensi tidak boleh kosong.']);
                }
                if ($group === 'options') {
                    Validator::make(['options' => $rows], ['options.*' => ['array'], 'options.*.group' => ['required', 'string']])->validate();
                    $groups = array_unique(array_column($rows, 'group'));
                    if (array_intersect($optionGroups, $groups) !== []) {
                        throw ValidationException::withMessages(['files.'.$key => 'Kelompok pilihan yang sama terdapat pada beberapa file. Unggah satu file untuk setiap kelompok.']);
                    }
                    $optionGroups = array_merge($optionGroups, $groups);
                    $replacements['options'] = array_merge($replacements['options'] ?? [], $rows);
                } elseif (isset($replacements[$group])) {
                    throw ValidationException::withMessages(['files.'.$key => 'Jenis referensi yang sama terdapat pada beberapa file.']);
                } else {
                    $replacements[$group] = $rows;
                }
            }
        }
        if ($replacements === []) {
            throw ValidationException::withMessages(['files' => 'Pilih minimal satu file referensi.']);
        }

        return DB::transaction(function () use ($actor, $version, $source, $replacements): ReferenceDataset {
            ApplicationSetting::query()->firstOrCreate(['key' => 'reference-control'], ['value' => []]);
            ApplicationSetting::query()->where('key', 'reference-control')->lockForUpdate()->firstOrFail();
            $activeId = ReferenceDataset::query()->where('state', 'active')->value('id');
            $input = ['version' => $version, 'source' => $source];
            foreach (self::GROUPS as $group => [$model, $columns]) {
                if (array_key_exists($group, $replacements)) {
                    $input[$group] = $replacements[$group];
                } elseif ($activeId !== null) {
                    $input[$group] = $model::query()->where('dataset_id', $activeId)->get($columns)->toArray();
                } else {
                    $input[$group] = [];
                }
            }
            if (isset($replacements['options']) && $activeId !== null) {
                $groups = array_unique(array_column($replacements['options'], 'group'));
                $previous = ReferenceOption::query()->where('dataset_id', $activeId)->whereNotIn('group', $groups)->get(['group', 'code', 'label'])->toArray();
                $input['options'] = array_merge($previous, $replacements['options']);
            }

            return $this->publish->handle($actor, $input, allowIncomplete: true);
        });
    }

    /** @return array<string, array<mixed>> */
    private function detect(UploadedFile $file, string $field): array
    {
        try {
            $data = json_decode($file->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([$field => 'Berkas referensi harus berupa JSON yang sah.']);
        }
        if (! is_array($data)) {
            throw ValidationException::withMessages([$field => 'Format referensi tidak dikenali.']);
        }
        if (array_key_exists('data', $data)) {
            $first = is_array($data['data']) ? ($data['data'][0] ?? []) : [];
            if (is_array($first) && array_key_exists('id_level_wilayah', $first)) {
                return ['regions' => $this->wilayah($data, $field)];
            }
            foreach (['transport' => ['id_alat_transportasi', 'nama_alat_transportasi'], 'residence' => ['id_jenis_tinggal', 'nama_jenis_tinggal']] as $group => [$id, $name]) {
                if (is_array($first) && array_key_exists($id, $first)) {
                    $validator = Validator::make($data, [
                        'error_code' => ['required', 'integer', 'in:0'],
                        'data' => ['required', 'array', 'min:1', 'max:500'],
                        'data.*.'.$id => ['required', 'string', 'max:20'],
                        'data.*.'.$name => ['required', 'string', 'max:255'],
                    ]);
                    if ($validator->fails()) {
                        throw ValidationException::withMessages([$field => 'Respons referensi tidak valid: '.$validator->errors()->first()]);
                    }

                    return ['options' => array_map(fn ($row) => ['group' => $group, 'code' => trim($row[$id]), 'label' => trim($row[$name])], $data['data'])];
                }
            }
            throw ValidationException::withMessages([$field => 'Format respons referensi tidak dikenali atau data kosong.']);
        }
        $parts = [];
        foreach (self::GROUPS as $group => $_) {
            if (array_key_exists($group, $data)) {
                if (! is_array($data[$group]) || ! array_is_list($data[$group])) {
                    throw ValidationException::withMessages([$field => 'Data '.$group.' harus berupa daftar JSON.']);
                }
                $parts[$group] = $data[$group];
            }
        }
        if ($parts !== []) {
            return $parts;
        }
        $first = $data[0] ?? null;
        if (array_is_list($data) && is_array($first)) {
            $group = match (true) {
                isset($first['group']) => 'options',
                isset($first['level']) => 'regions',
                isset($first['pt_external_id']) => 'prodi',
                isset($first['external_id']) => 'pt',
                default => null,
            };
            if ($group !== null) {
                return [$group => $data];
            }
        }
        throw ValidationException::withMessages([$field => 'Format referensi tidak dikenali. Gunakan options, regions, pt, prodi atau respons GetWilayah, GetAlatTransportasi, GetJenisTinggal.']);
    }

    /** @return array<mixed> */
    private function read(UploadedFile $file, string $group): array
    {
        $field = 'files.'.$group;
        try {
            $data = json_decode($file->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([$field => 'Berkas referensi harus berupa JSON yang sah.']);
        }
        if (! is_array($data)) {
            throw ValidationException::withMessages([$field => 'File harus berisi daftar data referensi.']);
        }
        if ($group === 'regions' && array_key_exists('data', $data)) {
            return $this->wilayah($data, $field);
        }
        $rows = $data[$group] ?? $data;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw ValidationException::withMessages([$field => 'Gunakan daftar JSON atau objek dengan kunci '.$group.'.']);
        }

        return $rows;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function wilayah(array $data, string $field): array
    {
        // Validate the response envelope before selecting Indonesian administrative levels.
        $validated = Validator::make($data, [
            'error_code' => ['required', 'integer', 'in:0'],
            'data' => ['required', 'array', 'min:1', 'max:10000'],
            'data.*.id_level_wilayah' => ['required', 'integer', 'between:0,4'],
            'data.*.id_negara' => ['required', 'string'],
            'data.*.id_wilayah' => ['required', 'string', 'max:20'],
            'data.*.nama_wilayah' => ['required', 'string', 'max:255'],
            'data.*.id_induk_wilayah' => ['present', 'nullable', 'string', 'max:20'],
        ]);
        if ($validated->fails()) {
            throw ValidationException::withMessages([$field => 'Respons GetWilayah tidak valid: '.$validated->errors()->first()]);
        }
        $levels = [1 => 'province', 2 => 'city', 3 => 'district'];
        $rows = [];
        foreach ($data['data'] as $row) {
            $level = (int) $row['id_level_wilayah'];
            $code = trim($row['id_wilayah']);
            if ($row['id_negara'] !== 'ID' || ! isset($levels[$level]) || $code === '999999') {
                continue;
            }
            $rows[] = [
                'code' => $code,
                'parent_code' => $level === 1 ? null : trim($row['id_induk_wilayah'] ?? ''),
                'level' => $levels[$level],
                'name' => trim($row['nama_wilayah']),
            ];
        }

        return $rows;
    }
}
