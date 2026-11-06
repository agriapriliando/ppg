<?php

namespace App\Support;

use App\Models\ReferenceDataset;
use App\Models\ReferenceRegion;
use Illuminate\Validation\ValidationException;

class ParticipantOrigin
{
    public const FIELDS = ['origin_reference_dataset_id', 'origin_city_code', 'origin_city_name', 'origin_province_code', 'origin_province_name'];

    /** @return array{origin_reference_dataset_id: int|null, origin_city_code: string|null, origin_city_name: string|null, origin_province_code: string|null, origin_province_name: string|null} */
    public static function resolve(?string $code, string $errorKey = 'origin_city_code', ?int $datasetId = null): array
    {
        if ($code === null || trim($code) === '') {
            return ['origin_reference_dataset_id' => null, 'origin_city_code' => null, 'origin_city_name' => null, 'origin_province_code' => null, 'origin_province_name' => null];
        }
        $datasetId ??= ReferenceDataset::query()->where('state', 'active')->value('id');
        $city = ReferenceRegion::query()->where('dataset_id', $datasetId)->where('level', 'city')->where('code', trim($code))->first();
        $province = $city === null ? null : ReferenceRegion::query()->where('dataset_id', $datasetId)->where('level', 'province')->where('code', $city->parent_code)->first();
        if ($city === null || $province === null) {
            throw ValidationException::withMessages([$errorKey => 'Kota/kabupaten asal tidak ditemukan pada referensi aktif atau belum memiliki provinsi. Pilih ulang dari hasil pencarian.']);
        }

        return ['origin_reference_dataset_id' => $city->dataset_id, 'origin_city_code' => $city->code, 'origin_city_name' => $city->name, 'origin_province_code' => $province->code, 'origin_province_name' => $province->name];
    }
}
