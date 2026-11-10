<?php

namespace App\Http\Controllers;

use App\Actions\Admissions\SynchronizeApplicantPhone;
use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\OriginEducation;
use App\Models\ReferenceDataset;
use App\Models\ReferenceOption;
use App\Models\ReferenceRegion;
use App\Models\SkParticipant;
use App\Models\User;
use App\Support\ApplicantFields;
use App\Support\ParticipantOrigin;
use App\Support\ParticipantPhone;
use App\Support\SubmittedRegistrationData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipantsController extends Controller
{
    public function condition(Request $request, SkParticipant $participant, RecordAudit $audit): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'roster_state' => ['required', Rule::in(['active', 'withdrawn'])], 'admin_hold' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:20', 'max:1000']]);
        DB::transaction(function () use ($request, $participant, $data, $audit): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($participant->admission_period_id);
            $locked = SkParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            if ($period->state === PeriodState::Archived || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['condition' => 'Kepesertaan telah berubah atau periode diarsipkan. Muat ulang sebelum melanjutkan.']);
            }
            $locked->forceFill(['roster_state' => $data['roster_state'], 'admin_hold' => (bool) $data['admin_hold'], 'hold_reason' => $data['reason'], 'lock_version' => $locked->lock_version + 1])->save();
            $period->lock_version++;
            $period->save();
            $audit->handle($request->user(), 'Memperbarui kondisi kepesertaan SK', $locked, ['roster_state' => $locked->roster_state, 'admin_hold' => $locked->admin_hold], $data['reason']);
        });

        return back()->with('status', 'Kondisi kepesertaan diperbarui. Riwayat pendaftaran tetap disimpan.');
    }

    public function updateSk(Request $request, SkParticipant $participant, RecordAudit $audit, SynchronizeApplicantPhone $syncPhone): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        abort_unless($actor->isAdministrator() || $actor->role === UserRole::Operator, 403);

        $data = $request->validate([
            'name_on_sk' => ['required', 'string', 'min:3', 'max:255'],
            'sk_number' => ['required', 'string', 'max:100'],
            'sk_date' => ['required', 'date'],
            'sk_order' => ['required', 'integer', 'min:1', 'max:999999'],
            'official_participant_id' => ['nullable', 'string', 'max:100'],
            'phone_number' => ParticipantPhone::rules(),
            'origin_city_code' => ['nullable', 'string', 'max:64'],
            'assigned_operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($actor, $participant, $data, $audit, $syncPhone): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($participant->admission_period_id);
            $locked = SkParticipant::query()->lockForUpdate()->findOrFail($participant->id);

            if ($period->state === PeriodState::Archived || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Data kepesertaan telah berubah atau periode diarsipkan. Muat ulang halaman sebelum menyimpan.']);
            }

            $operatorId = ! empty($data['assigned_operator_id']) ? (int) $data['assigned_operator_id'] : null;
            if ($operatorId !== null) {
                $operator = User::query()
                    ->whereKey($operatorId)
                    ->where('role', UserRole::Operator)
                    ->where('account_state', AccountState::Active)
                    ->lockForUpdate()->first();

                if ($operator === null) {
                    throw ValidationException::withMessages(['assigned_operator_id' => 'Pilih akun Operator yang aktif.']);
                }
            }

            $cleanName = trim($data['name_on_sk']);
            $changes = [
                'name_on_sk' => $cleanName,
                'sk_number' => trim($data['sk_number']),
                'sk_date' => $data['sk_date'],
                'sk_order' => (int) $data['sk_order'],
                'official_participant_id' => ! empty($data['official_participant_id']) ? trim($data['official_participant_id']) : null,
                'assigned_operator_id' => $operatorId,
            ];
            $origin = [];
            if (array_key_exists('origin_city_code', $data)) {
                $origin = $data['origin_city_code'] === $locked->origin_city_code
                    ? $locked->only(ParticipantOrigin::FIELDS)
                    : ParticipantOrigin::resolve($data['origin_city_code']);
            }
            $changes = array_merge($changes, $origin);
            if (array_key_exists('phone_number', $data)) {
                $changes['phone_number'] = $syncPhone->participantNumber($locked, $data['phone_number'] ?? null);
            }

            $locked->forceFill([
                ...$changes,
                'normalized_name' => mb_strtolower(Str::squish($cleanName)),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            if (array_key_exists('phone_number', $changes) && $locked->claimedUser !== null && $changes['phone_number'] !== null) {
                $syncPhone->handle($locked->claimedUser, $changes['phone_number']);
            }
            $auditReason = ! empty($data['reason']) ? $data['reason'] : 'Pembaruan data resmi SK peserta';
            $audit->handle($actor, 'Memperbarui data resmi SK peserta', $locked, $changes, $auditReason);
            $period->increment('lock_version');
        }, attempts: 3);

        return back()->with('status', 'Data resmi kepesertaan bersumber dari SK berhasil diperbarui.');
    }

    public function index(Request $request): View
    {
        $data = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:admission_periods,id'],
            'operator_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $search = $data['search'] ?? '';
        $operatorId = $data['operator_id'] ?? '';

        $query = SkParticipant::query()
            ->when(! empty($data['period_id']), fn ($q) => $q->where('admission_period_id', $data['period_id']))
            ->when($operatorId === 'unassigned', fn ($q) => $q->whereNull('assigned_operator_id'))
            ->when(! empty($operatorId) && $operatorId !== 'unassigned', fn ($q) => $q->where('assigned_operator_id', (int) $operatorId))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub->where('name_on_sk', 'like', '%'.$search.'%')->orWhere('official_participant_id', 'like', '%'.$search.'%')));

        return view('portal.participants', [
            'participants' => $query->with(['assignedOperator', 'period', 'application', 'claimedUser'])->orderBy('sk_number')->orderBy('sk_order')->orderBy('id')->paginate(25)->withQueryString(),
            'periods' => AdmissionPeriod::query()->orderByDesc('id')->get(),
            'operators' => User::query()->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->orderBy('name')->get(),
            'search' => $search,
            'periodId' => $data['period_id'] ?? '',
            'operatorId' => $operatorId,
        ]);
    }

    public function export(Request $request, RecordAudit $audit): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor !== null, 403);

        $data = $request->validate([
            'origin_city' => ['nullable', 'string', 'max:64'],
            'period_id' => ['nullable', 'integer', 'exists:admission_periods,id'],
            'operator_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $data['search'] ?? '';
        $operatorId = $data['operator_id'] ?? '';

        $participants = SkParticipant::query()
            ->originCity($data['origin_city'] ?? '')
            ->when(! empty($data['period_id']), fn ($q) => $q->where('admission_period_id', $data['period_id']))
            ->when($operatorId === 'unassigned', fn ($q) => $q->whereNull('assigned_operator_id'))
            ->when(! empty($operatorId) && $operatorId !== 'unassigned', fn ($q) => $q->where('assigned_operator_id', (int) $operatorId))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub->where('name_on_sk', 'like', '%'.$search.'%')->orWhere('official_participant_id', 'like', '%'.$search.'%')))
            ->with([
                'assignedOperator',
                'period',
                'claimedUser',
                'application.workspace.biodata',
                'application.workspace.education',
            ])
            ->orderBy('sk_number')
            ->orderBy('sk_order')
            ->orderBy('id')
            ->get();

        $options = ReferenceOption::query()->get()->groupBy('group');
        $regions = ReferenceRegion::query()->pluck('name', 'code');

        $resolveOption = function (?string $group, ?string $code) use ($options): string {
            if (! $code) {
                return '';
            }

            $found = $options->get($group ?? '')?->firstWhere('code', $code);

            return $found instanceof ReferenceOption ? $found->label : $code;
        };

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Peserta & Biodata');

        $headers = [
            'No',
            'Periode',
            'Nomor SK',
            'Tanggal SK',
            'Urut SK',
            'Peg ID',
            'Kota/Kabupaten Asal (SK)',
            'Provinsi Asal (SK)',
            'Nama Sesuai SK',
            'Nomor HP (SK)',
            'Kondisi Roster SK',
            'Status Penahanan SK',
            'Operator Penugasan',
            'Status Akun Portal',
            'Username / Login Akun',
            'Email Akun',
            'Status Antrean Pendaftaran',
            'Nomor PMB',
            'Tanggal Kirim Pertama',
            'Revisi Pengajuan',
            'Nama Lengkap (Biodata)',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'NIK',
            'NISN',
            'Nomor Peg ID (Biodata)',
            'NUPTK',
            'Agama',
            'Kewarganegaraan',
            'Nama Ibu Kandung',
            'Nomor WhatsApp Aktif',
            'Email Kontak',
            'Telepon Lain',
            'Alamat Jalan & No',
            'Dusun',
            'RT',
            'RW',
            'Kelurahan / Desa',
            'Kecamatan',
            'Kota / Kabupaten',
            'Provinsi',
            'Kode Pos',
            'Kategori Wilayah Tempat Tinggal',
            'Jenis Tinggal',
            'Alat Transportasi',
            'Penerima KPS',
            'Nomor KPS',
            'NPWP',
            'Kebutuhan Khusus',
            'Perguruan Tinggi Asal',
            'Program Studi Asal',
            'Pendidikan Terakhir',
            'Tahun Lulus',
            'Perguruan Tinggi S1', 'Program Studi S1', 'Tahun Lulus S1',
            'Perguruan Tinggi S2', 'Program Studi S2', 'Tahun Lulus S2',
            'Perguruan Tinggi S3', 'Program Studi S3', 'Tahun Lulus S3',
            'Sumber Data Biodata',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $headerEndCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$headerEndCol}1";

        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4A154B');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->freezePane('A2');

        $rowIndex = 2;
        foreach ($participants as $index => $participant) {
            $app = $participant->application;
            $ws = $app?->workspace;
            $bio = $ws?->biodata;
            $edu = $ws?->education;
            $ws?->setRelation('application', $app);
            $effectiveData = $ws ? ApplicantFields::fromWorkspace($ws) : [];
            $educationHistory = $effectiveData['education_history'] ?? ($edu->education_history ?? []);
            if ($educationHistory === [] && ($edu?->pt_name_snapshot || $edu?->prodi_name_snapshot)) {
                $educationHistory[$edu->degree_level ?? 'S1'] = [
                    'pt_name' => $edu->pt_name_snapshot, 'prodi_name' => $edu->prodi_name_snapshot,
                    'graduation_year' => $edu->graduation_year ?? null,
                ];
            }

            if ($participant->claimed_user_id) {
                $accountState = $participant->claimedUser?->account_state;
                if ($accountState === AccountState::Disabled) {
                    $accountStatus = 'Nonaktif';
                } elseif ($accountState === AccountState::PendingActivation) {
                    $accountStatus = 'Menunggu Aktivasi';
                } else {
                    $accountStatus = 'Akun Terhubung';
                }
            } else {
                $accountStatus = 'Belum Ada Akun';
            }

            $queueStatus = $app !== null ? $app->status->label() : 'Belum Masuk Antrean';

            $birthDate = (string) ($effectiveData['birth_date'] ?? '');
            $submittedAt = $app?->submitted_at ? $app->submitted_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : '';

            $district = ! empty($effectiveData['district_code']) ? ($regions->get($effectiveData['district_code']) ?? $effectiveData['district_code']) : '';
            $city = ! empty($effectiveData['city_code']) ? ($regions->get($effectiveData['city_code']) ?? $effectiveData['city_code']) : '';
            $province = ! empty($effectiveData['province_code']) ? ($regions->get($effectiveData['province_code']) ?? $effectiveData['province_code']) : '';

            $row = [
                $index + 1,
                $participant->period->name,
                (string) $participant->sk_number,
                $participant->sk_date->format('d/m/Y'),
                (string) $participant->sk_order,
                (string) ($participant->official_participant_id ?? ''),
                (string) ($participant->origin_city_name ?? ''),
                (string) ($participant->origin_province_name ?? ''),
                $participant->name_on_sk,
                (string) ($participant->phone_number ?? ''),
                $participant->roster_state === 'active' ? 'Aktif' : 'Mundur',
                $participant->admin_hold ? 'Ditahan' : 'Normal',
                $participant->assignedOperator !== null ? $participant->assignedOperator->name : 'Belum Ditugaskan',
                $accountStatus,
                $participant->claimedUser !== null ? $participant->claimedUser->login_id : '',
                $participant->claimedUser !== null ? ($participant->claimedUser->email ?? '') : '',
                $queueStatus,
                $app !== null ? $app->application_number : '',
                $submittedAt,
                $app !== null ? (string) $app->submission_revision : '',
                $effectiveData['full_name'] ?? '',
                $resolveOption('gender', $effectiveData['gender_code'] ?? null),
                $effectiveData['birth_place'] ?? '',
                $birthDate,
                (string) ($effectiveData['nik'] ?? ''),
                (string) ($effectiveData['nisn'] ?? ''),
                (string) ($effectiveData['peg_id'] ?? ''),
                (string) ($effectiveData['nuptk'] ?? ''),
                $resolveOption('religion', $effectiveData['religion_code'] ?? null),
                $resolveOption('nationality', $effectiveData['nationality_code'] ?? null),
                (string) ($effectiveData['mother_name'] ?? ''),
                (string) ($effectiveData['whatsapp_number'] ?? ''),
                $effectiveData['contact_email'] ?? '',
                (string) ($effectiveData['other_phone'] ?? ''),
                $effectiveData['street_address'] ?? '',
                $effectiveData['hamlet'] ?? '',
                (string) ($effectiveData['rt'] ?? ''),
                (string) ($effectiveData['rw'] ?? ''),
                $effectiveData['village_name'] ?? '',
                $district,
                $city,
                $province,
                (string) ($effectiveData['postal_code'] ?? ''),
                (ApplicantFields::definitions()['residence_region']['options'] ?? [])[$effectiveData['residence_region'] ?? ''] ?? '',
                $resolveOption('residence', $effectiveData['residence_type_code'] ?? null),
                $resolveOption('transport', $effectiveData['transport_type_code'] ?? null),
                isset($effectiveData['is_kps_recipient']) ? ($effectiveData['is_kps_recipient'] ? 'Ya' : 'Tidak') : '',
                (string) ($effectiveData['kps_number'] ?? ''),
                (string) ($effectiveData['npwp'] ?? ''),
                $resolveOption('special_need', $effectiveData['special_need_code'] ?? null),
                $effectiveData['education_history'][$effectiveData['degree_level'] ?? '']['pt_name'] ?? $effectiveData['pt_name'] ?? '',
                $effectiveData['education_history'][$effectiveData['degree_level'] ?? '']['prodi_name'] ?? $effectiveData['prodi_name'] ?? '',
                $effectiveData['degree_level'] ?? '',
                $effectiveData['education_history'][$effectiveData['degree_level'] ?? '']['graduation_year'] ?? $effectiveData['graduation_year'] ?? '',
                $educationHistory['S1']['pt_name'] ?? '',
                $educationHistory['S1']['prodi_name'] ?? '',
                $educationHistory['S1']['graduation_year'] ?? '',
                $educationHistory['S2']['pt_name'] ?? '',
                $educationHistory['S2']['prodi_name'] ?? '',
                $educationHistory['S2']['graduation_year'] ?? '',
                $educationHistory['S3']['pt_name'] ?? '',
                $educationHistory['S3']['prodi_name'] ?? '',
                $educationHistory['S3']['graduation_year'] ?? '',
                $ws?->draft_payload !== null && $app->status->editable() ? 'Draf otomatis belum dikirim' : 'Data tersimpan',
            ];

            foreach ($row as $colIdx => $val) {
                $sheet->setCellValueExplicit([$colIdx + 1, $rowIndex], (string) $val, DataType::TYPE_STRING);
            }

            $rowIndex++;
        }

        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $audit->handle($actor, 'Mengekspor data peserta SK dan biodata ke Excel', $actor, ['count' => $participants->count()]);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'peserta-sk-biodata-'.date('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store',
        ]);
    }

    public function show(SkParticipant $participant): View
    {
        abort_unless(Gate::allows('view', $participant), 404);

        $participant->load([
            'assignedOperator',
            'period',
            'application.workspace.biodata',
            'application.workspace.education',
            'application.workspace.availableDocuments.uploader',
            'application.submissions.configVersion',
            'claimedUser',
        ]);

        $workspace = $participant->application?->workspace;
        if ($workspace === null && $participant->claimed_user_id) {
            $workspace = ApplicantWorkspace::query()
                ->where('user_id', $participant->claimed_user_id)
                ->where('admission_period_id', $participant->admission_period_id)
                ->with(['biodata', 'education', 'availableDocuments.uploader'])
                ->first();
        }

        $application = $participant->application;
        $submitted = $application?->hasCurrentOwnerSubmission() ?? false;
        $data = [];
        if ($submitted) {
            $data = SubmittedRegistrationData::forDisplay($application);
            $workspace = $application->workspace;
        } elseif ($workspace !== null) {
            $data = ApplicantFields::fromWorkspace($workspace);
        }
        $biodata = $workspace?->biodata;
        $education = $workspace?->education;
        if (! $submitted && $workspace?->draft_payload !== null) {
            $education = $education !== null ? clone $education : new OriginEducation;
            $education->fill(['degree_level' => $data['degree_level'] ?? null, 'education_history' => $data['education_history'] ?? null, 'graduation_year' => $data['graduation_year'] ?? null]);
        }
        $submission = $application?->submissions->where('ownership_epoch', $application->ownership_epoch)->sortByDesc('revision')->first();
        $datasetId = $submission?->configVersion?->reference_version_ids['biodata'] ?? $education->reference_dataset_id
            ?? ReferenceDataset::query()->where('state', 'active')->latest('id')->value('id');
        $options = ReferenceOption::query()->where('dataset_id', $datasetId)->get()->groupBy('group');
        $regions = ReferenceRegion::query()->where('dataset_id', $datasetId)->whereIn('code', array_filter([$data['district_code'] ?? null, $data['city_code'] ?? null, $data['province_code'] ?? null]))->pluck('name', 'code');
        $display = [];
        foreach (ApplicantFields::definitions() as $key => $field) {
            $value = $data[$key] ?? null;
            $display[$key] = match ($field['type']) {
                'choice' => ($field['options'] ?? [])[$value ?? ''] ?? ($value ?: 'Belum diisi'),
                'select' => $options->get($field['reference'] ?? '')?->firstWhere('code', $value)->label ?? ($value ?: 'Belum diisi'),
                'region' => $regions->get($value) ?? ($value ?: 'Belum diisi'),
                'boolean' => $value === null || $value === '' ? 'Belum diisi' : ((bool) $value ? 'Ya' : 'Tidak'),
                default => $value === null || $value === '' ? 'Belum diisi' : $value,
            };
        }
        $documents = $workspace ? $workspace->availableDocuments : collect();

        return view('portal.participant', [
            'participant' => $participant,
            'workspace' => $workspace,
            'biodata' => $biodata,
            'education' => $education,
            'documents' => $documents,
            'data' => $data, 'display' => $display, 'fields' => ApplicantFields::definitions(),
            'biodataSource' => $submitted ? 'Data pada pengiriman terakhir peserta.' : 'Draf peserta yang belum dikirim.',
            'operators' => User::query()
                ->where('role', 'operator')
                ->where('account_state', 'active')
                ->orderBy('name')
                ->get(),
        ]);
    }
}
