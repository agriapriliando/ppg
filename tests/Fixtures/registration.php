<?php

use App\Actions\Admissions\PublishReferenceDataset;
use App\Actions\Assignments\AssignOperators;
use App\Models\AdmissionPeriod;
use App\Models\ConsentText;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Support\Str;

function registrationContext(): array
{
    $admin = User::factory()->administrator()->create();
    $operator = User::factory()->operator()->create();
    $applicant = User::factory()->create();
    $period = AdmissionPeriod::factory()->published()->create();
    $participant = SkParticipant::create(['admission_period_id' => $period->id, 'sk_number' => 'SK-UJI-001', 'sk_date' => '2026-10-01', 'sk_order' => 1, 'name_on_sk' => $applicant->name, 'normalized_name' => mb_strtolower(Str::squish($applicant->name)), 'official_participant_id' => '000001']);
    app(AssignOperators::class)->handle($admin, $period->id, 0, [$operator->id]);
    $participant->refresh();
    $period->refresh();
    $claimConsent = ConsentText::create(['purpose' => 'claim', 'version' => 'uji-1', 'body' => 'Saya menyetujui pemeriksaan data klaim untuk penerimaan PPG.', 'created_by' => $admin->id, 'published_at' => now()]);
    $submitConsent = ConsentText::create(['purpose' => 'submission', 'version' => 'uji-1', 'body' => 'Saya menyatakan biodata yang disampaikan benar untuk administrasi PPG.', 'created_by' => $admin->id, 'published_at' => now()]);
    $reference = [
        'version' => 'uji-1', 'source' => 'Dataset sintetis pengujian',
        'options' => array_map(fn ($group) => ['group' => $group, 'code' => 'uji', 'label' => 'Pilihan Uji'], ['gender', 'religion', 'nationality', 'residence', 'transport', 'special_need']),
        'regions' => [['code' => '01', 'name' => 'Provinsi Uji', 'level' => 'province', 'parent_code' => null], ['code' => '0101', 'name' => 'Kota Uji', 'level' => 'city', 'parent_code' => '01'], ['code' => '010101', 'name' => 'Kecamatan Uji', 'level' => 'district', 'parent_code' => '0101']],
        'pt' => [['external_id' => 'pt-uji', 'name' => 'Perguruan Tinggi Uji']],
        'prodi' => [['external_id' => 'prodi-uji', 'pt_external_id' => 'pt-uji', 'name' => 'Program Studi Uji']],
    ];
    app(PublishReferenceDataset::class)->handle($admin, $reference);

    return compact('admin', 'operator', 'applicant', 'period', 'participant', 'claimConsent', 'submitConsent', 'reference');
}

function claimInput(array $context): array
{
    return ['period_id' => $context['period']->id, 'name' => $context['applicant']->name, 'identifier' => '000001', 'whatsapp_number' => '081234567890', 'request_key' => (string) Str::uuid(), 'consent' => 1, 'consent_id' => $context['claimConsent']->id];
}

function completeApplicantForm(array $context): array
{
    return ['version' => 0, 'action' => 'submit', 'full_name' => $context['applicant']->name, 'gender_code' => 'uji', 'birth_place' => 'Kota Uji', 'birth_date' => '1990-01-01', 'nik' => '0000000000000001', 'nisn' => '0000000001', 'religion_code' => 'uji', 'nationality_code' => 'uji', 'mother_name' => 'Ibu Uji', 'whatsapp_number' => '+6281234567890', 'street_address' => 'Jalan Uji', 'village_name' => 'Desa Uji', 'province_code' => '01', 'city_code' => '0101', 'district_code' => '010101', 'residence_type_code' => 'uji', 'transport_type_code' => 'uji', 'is_kps_recipient' => '0', 'special_need_code' => 'uji', 'pt_external_id' => 'pt-uji', 'prodi_external_id' => 'prodi-uji', 'degree_level' => 'S1', 'graduation_year' => '2012', 'consent' => 1, 'consent_id' => $context['submitConsent']->id];
}
