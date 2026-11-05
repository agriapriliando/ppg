<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $peg_id
 * @property string|null $nuptk_encrypted
 * @property string|null $residence_region
 * @property int $workspace_id
 * @property string|null $full_name
 * @property string|null $birth_place
 * @property string|null $gender_code
 * @property string|null $religion_code
 * @property string|null $nationality_code
 * @property string|null $whatsapp_number
 * @property string|null $contact_email
 * @property string|null $other_phone
 * @property string|null $street_address
 * @property string|null $hamlet
 * @property string|null $rt
 * @property string|null $rw
 * @property string|null $village_name
 * @property string|null $province_code
 * @property string|null $city_code
 * @property string|null $district_code
 * @property string|null $postal_code
 * @property string|null $residence_type_code
 * @property string|null $transport_type_code
 * @property string|null $special_need_code
 * @property string|null $nik_encrypted
 * @property string|null $nisn_encrypted
 * @property string|null $mother_name_encrypted
 * @property string|null $kps_number_encrypted
 * @property string|null $npwp_encrypted
 * @property CarbonImmutable|null $birth_date
 * @property bool|null $is_kps_recipient
 */
#[Hidden(['nuptk_encrypted', 'nik_encrypted', 'nisn_encrypted', 'mother_name_encrypted', 'kps_number_encrypted', 'npwp_encrypted'])]
#[Fillable(['peg_id', 'nuptk_encrypted', 'residence_region', 'workspace_id', 'full_name', 'birth_place', 'gender_code', 'religion_code', 'nationality_code', 'whatsapp_number', 'contact_email', 'other_phone', 'street_address', 'hamlet', 'rt', 'rw', 'village_name', 'province_code', 'city_code', 'district_code', 'postal_code', 'residence_type_code', 'transport_type_code', 'special_need_code', 'nik_encrypted', 'nisn_encrypted', 'mother_name_encrypted', 'kps_number_encrypted', 'npwp_encrypted', 'birth_date', 'is_kps_recipient'])]
class ApplicantBiodata extends Model
{
    protected $table = 'applicant_biodata';

    protected function casts(): array
    {
        return ['nuptk_encrypted' => 'encrypted', 'nik_encrypted' => 'encrypted', 'nisn_encrypted' => 'encrypted', 'mother_name_encrypted' => 'encrypted', 'kps_number_encrypted' => 'encrypted', 'npwp_encrypted' => 'encrypted', 'birth_date' => 'immutable_date', 'is_kps_recipient' => 'boolean'];
    }
}
