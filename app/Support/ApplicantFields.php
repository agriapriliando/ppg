<?php

namespace App\Support;

use App\Models\ApplicantWorkspace;
use App\Models\ReferenceDataset;
use App\Models\ReferenceRegion;
use App\Models\SkClaim;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApplicantFields
{
    /** @return array<string, mixed> */
    public static function fromWorkspace(ApplicantWorkspace $workspace): array
    {
        $data = [];
        foreach (self::definitions() as $key => $field) {
            $value = $workspace->biodata?->getAttribute($field['column']);
            $data[$key] = $key === 'birth_date' ? $value?->format('Y-m-d') : $value;
        }
        $education = $workspace->education;
        $data['pt_name'] = $education ? ($education->pt_name_snapshot ?? $education->pt_external_id) : null;
        $data['prodi_name'] = $education ? ($education->prodi_name_snapshot ?? $education->prodi_external_id) : null;
        $data['pt_external_id'] = $education?->pt_external_id;
        $data['prodi_external_id'] = $education?->prodi_external_id;

        if (empty($data['whatsapp_number'])) {
            $claim = SkClaim::query()
                ->where('admission_period_id', $workspace->admission_period_id)
                ->where(function ($query) use ($workspace): void {
                    $query->where('requester_user_id', $workspace->user_id)
                        ->orWhere('review_owner_id', $workspace->user_id);
                    if ($workspace->application?->sk_participant_id !== null) {
                        $query->orWhere('candidate_sk_participant_id', $workspace->application->sk_participant_id);
                    }
                })
                ->orderBy('id')
                ->first();

            if ($claim !== null && ! empty($claim->whatsapp_number)) {
                $data['whatsapp_number'] = $claim->whatsapp_number;
            }
        }

        return $data;
    }

    /** @return array<string, array{label: string, step: int, type: string, required: bool, column: string, reference?: string}> */
    public static function definitions(): array
    {
        return [
            'full_name' => ['label' => 'Nama lengkap biodata', 'step' => 1, 'type' => 'text', 'required' => true, 'column' => 'full_name'],
            'gender_code' => ['label' => 'Jenis kelamin', 'step' => 1, 'type' => 'select', 'required' => true, 'column' => 'gender_code', 'reference' => 'gender'],
            'birth_place' => ['label' => 'Tempat lahir', 'step' => 1, 'type' => 'text', 'required' => true, 'column' => 'birth_place'],
            'birth_date' => ['label' => 'Tanggal lahir', 'step' => 1, 'type' => 'date', 'required' => true, 'column' => 'birth_date'],
            'nik' => ['label' => 'NIK (16 digit)', 'step' => 1, 'type' => 'text', 'required' => true, 'column' => 'nik_encrypted'],
            'nisn' => ['label' => 'NISN (jika tersedia)', 'step' => 1, 'type' => 'text', 'required' => false, 'column' => 'nisn_encrypted'],
            'religion_code' => ['label' => 'Agama', 'step' => 1, 'type' => 'select', 'required' => true, 'column' => 'religion_code', 'reference' => 'religion'],
            'nationality_code' => ['label' => 'Kewarganegaraan', 'step' => 1, 'type' => 'select', 'required' => true, 'column' => 'nationality_code', 'reference' => 'nationality'],
            'mother_name' => ['label' => 'Nama ibu kandung', 'step' => 1, 'type' => 'text', 'required' => true, 'column' => 'mother_name_encrypted'],
            'whatsapp_number' => ['label' => 'Nomor WhatsApp aktif', 'step' => 1, 'type' => 'tel', 'required' => true, 'column' => 'whatsapp_number'],
            'contact_email' => ['label' => 'Email kontak (opsional)', 'step' => 1, 'type' => 'email', 'required' => false, 'column' => 'contact_email'],
            'other_phone' => ['label' => 'Telepon lain (opsional)', 'step' => 1, 'type' => 'tel', 'required' => false, 'column' => 'other_phone'],
            'street_address' => ['label' => 'Jalan dan nomor rumah', 'step' => 2, 'type' => 'text', 'required' => true, 'column' => 'street_address'],
            'hamlet' => ['label' => 'Dusun', 'step' => 2, 'type' => 'text', 'required' => false, 'column' => 'hamlet'],
            'rt' => ['label' => 'RT', 'step' => 2, 'type' => 'text', 'required' => false, 'column' => 'rt'],
            'rw' => ['label' => 'RW', 'step' => 2, 'type' => 'text', 'required' => false, 'column' => 'rw'],
            'village_name' => ['label' => 'Kelurahan / desa', 'step' => 2, 'type' => 'text', 'required' => true, 'column' => 'village_name'],
            'district_code' => ['label' => 'Kecamatan', 'step' => 2, 'type' => 'region', 'required' => true, 'column' => 'district_code', 'reference' => 'district'],
            'city_code' => ['label' => 'Kota / kabupaten', 'step' => 2, 'type' => 'region', 'required' => true, 'column' => 'city_code', 'reference' => 'city'],
            'province_code' => ['label' => 'Provinsi', 'step' => 2, 'type' => 'region', 'required' => true, 'column' => 'province_code', 'reference' => 'province'],
            'postal_code' => ['label' => 'Kode pos', 'step' => 2, 'type' => 'text', 'required' => false, 'column' => 'postal_code'],
            'residence_type_code' => ['label' => 'Jenis tinggal', 'step' => 2, 'type' => 'select', 'required' => true, 'column' => 'residence_type_code', 'reference' => 'residence'],
            'transport_type_code' => ['label' => 'Alat transportasi', 'step' => 2, 'type' => 'select', 'required' => true, 'column' => 'transport_type_code', 'reference' => 'transport'],
            'is_kps_recipient' => ['label' => 'Penerima KPS', 'step' => 3, 'type' => 'boolean', 'required' => true, 'column' => 'is_kps_recipient'],
            'kps_number' => ['label' => 'Nomor KPS (jika penerima)', 'step' => 3, 'type' => 'text', 'required' => false, 'column' => 'kps_number_encrypted'],
            'npwp' => ['label' => 'NPWP (opsional)', 'step' => 3, 'type' => 'text', 'required' => false, 'column' => 'npwp_encrypted'],
            'special_need_code' => ['label' => 'Kebutuhan khusus', 'step' => 3, 'type' => 'select', 'required' => true, 'column' => 'special_need_code', 'reference' => 'special_need'],
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function validate(array $input, bool $final, ?int $datasetId = null): array
    {
        $datasetId ??= ReferenceDataset::query()->where('state', 'active')->value('id');
        $rules = [];
        $labels = [];
        foreach (self::definitions() as $key => $field) {
            $labels[$key] = $field['label'];
            $rules[$key] = [$final && $field['required'] ? 'required' : 'nullable'];
            if ($field['type'] === 'boolean') {
                $rules[$key][] = 'boolean';
            } elseif ($field['type'] === 'date') {
                array_push($rules[$key], 'date_format:Y-m-d', 'before:today');
            } else {
                array_push($rules[$key], 'string', 'max:255');
            }
            if ($field['type'] === 'select') {
                $reference = $field['reference'] ?? throw new \LogicException('Kelompok referensi tidak didefinisikan.');
                $rules[$key][] = Rule::exists('reference_options', 'code')->where('dataset_id', $datasetId)->where('group', $reference);
            }
            if ($field['type'] === 'region') {
                $reference = $field['reference'] ?? throw new \LogicException('Tingkat wilayah tidak didefinisikan.');
                $rules[$key][] = Rule::exists('reference_regions', 'code')->where('dataset_id', $datasetId)->where('level', $reference);
            }
        }
        $rules['nik'][] = 'digits:16';
        $rules['nisn'][] = 'digits:10';
        $rules['contact_email'][] = 'email';
        $rules['rt'][] = 'regex:/^[0-9]{1,5}$/';
        $rules['rw'][] = 'regex:/^[0-9]{1,5}$/';
        $rules['postal_code'][] = 'max:10';
        $rules['npwp'][] = 'max:40';
        $rules['kps_number'][] = 'max:100';
        if ($final && ($input['is_kps_recipient'] ?? null)) {
            $rules['kps_number'][] = 'required';
        }
        if (isset($input['pt_name']) && ! isset($input['pt_external_id'])) {
            $input['pt_external_id'] = $input['pt_name'];
        } elseif (isset($input['pt_external_id']) && ! isset($input['pt_name'])) {
            $input['pt_name'] = $input['pt_external_id'];
        }
        if (isset($input['prodi_name']) && ! isset($input['prodi_external_id'])) {
            $input['prodi_external_id'] = $input['prodi_name'];
        } elseif (isset($input['prodi_external_id']) && ! isset($input['prodi_name'])) {
            $input['prodi_name'] = $input['prodi_external_id'];
        }
        $rules['pt_name'] = [$final ? 'required' : 'nullable', 'string', 'max:255'];
        $rules['prodi_name'] = [$final ? 'required' : 'nullable', 'string', 'max:255'];
        $rules['pt_external_id'] = ['nullable', 'string', 'max:255'];
        $rules['prodi_external_id'] = ['nullable', 'string', 'max:255'];
        $labels['pt_name'] = 'Perguruan tinggi asal';
        $labels['prodi_name'] = 'Program studi asal';
        $labels['pt_external_id'] = 'Perguruan tinggi asal';
        $labels['prodi_external_id'] = 'Program studi asal';
        $data = Validator::make($input, $rules, ['required' => ':attribute wajib diisi.', 'exists' => ':attribute tidak tersedia pada referensi aktif.', 'digits' => ':attribute harus terdiri dari :digits digit.', 'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.'], $labels)->validate();
        if ($final && ! $datasetId) {
            throw ValidationException::withMessages(['references' => 'Referensi formulir belum tersedia. Hubungi pengelola; draf tetap dapat disimpan.']);
        }
        foreach (['city_code' => 'province_code', 'district_code' => 'city_code'] as $child => $parent) {
            if (! empty($data[$child]) && ! ReferenceRegion::query()->where('dataset_id', $datasetId)->where('code', $data[$child])->where('parent_code', $data[$parent] ?? null)->exists()) {
                throw ValidationException::withMessages([$child => 'Pilihan wilayah tidak sesuai induknya. Pilih ulang wilayah.']);
            }
        }
        if (! empty($data['whatsapp_number'])) {
            $data['whatsapp_number'] = WhatsappNumber::normalize($data['whatsapp_number']);
        }
        if (! ($data['is_kps_recipient'] ?? false)) {
            $data['kps_number'] = null;
        }

        return $data;
    }
}
