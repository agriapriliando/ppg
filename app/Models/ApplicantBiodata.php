<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * @property CarbonImmutable|null $birth_date
 * @property string|null $whatsapp_number
 */
#[Hidden(['nik_encrypted', 'nisn_encrypted', 'mother_name_encrypted', 'kps_number_encrypted', 'npwp_encrypted'])]
#[Fillable(['workspace_id', 'full_name', 'birth_place', 'gender_code', 'religion_code', 'nationality_code', 'whatsapp_number', 'contact_email', 'other_phone', 'street_address', 'hamlet', 'rt', 'rw', 'village_name', 'province_code', 'city_code', 'district_code', 'postal_code', 'residence_type_code', 'transport_type_code', 'special_need_code', 'nik_encrypted', 'nisn_encrypted', 'mother_name_encrypted', 'kps_number_encrypted', 'npwp_encrypted', 'birth_date', 'is_kps_recipient'])]
class ApplicantBiodata extends Model
{
    protected $table = 'applicant_biodata';

    protected function casts(): array
    {
        return ['nik_encrypted' => 'encrypted', 'nisn_encrypted' => 'encrypted', 'mother_name_encrypted' => 'encrypted', 'kps_number_encrypted' => 'encrypted', 'npwp_encrypted' => 'encrypted', 'birth_date' => 'immutable_date', 'is_kps_recipient' => 'boolean'];
    }
}
