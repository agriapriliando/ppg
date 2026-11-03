<?php

namespace App\Http\Controllers;

use App\Actions\RecordAudit;
use App\Enums\AccountState;
use App\Enums\PeriodState;
use App\Enums\UserRole;
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\PeriodOperator;
use App\Models\ReferenceOption;
use App\Models\ReferenceRegion;
use App\Models\SkParticipant;
use App\Models\User;
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

    public function updateSk(Request $request, SkParticipant $participant, RecordAudit $audit): RedirectResponse
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
            'assigned_operator_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($actor, $participant, $data, $audit): void {
            $period = AdmissionPeriod::query()->lockForUpdate()->findOrFail($participant->admission_period_id);
            $locked = SkParticipant::query()->lockForUpdate()->findOrFail($participant->id);

            if ($period->state === PeriodState::Archived || $locked->lock_version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Data kepesertaan telah berubah atau periode diarsipkan. Muat ulang halaman sebelum menyimpan.']);
            }

            $operatorId = ! empty($data['assigned_operator_id']) ? (int) $data['assigned_operator_id'] : null;
            if ($operatorId !== null) {
                $isOperatorInPeriod = PeriodOperator::query()
                    ->where('admission_period_id', $period->id)
                    ->where('user_id', $operatorId)
                    ->where('is_active', true)
                    ->exists();

                if (! $isOperatorInPeriod) {
                    throw ValidationException::withMessages(['assigned_operator_id' => 'Operator yang dipilih tidak terdaftar aktif pada periode ini.']);
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

            $locked->forceFill([
                'name_on_sk' => $cleanName,
                'normalized_name' => mb_strtolower(Str::squish($cleanName)),
                'sk_number' => $changes['sk_number'],
                'sk_date' => $changes['sk_date'],
                'sk_order' => $changes['sk_order'],
                'official_participant_id' => $changes['official_participant_id'],
                'assigned_operator_id' => $changes['assigned_operator_id'],
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $audit->handle($actor, 'Memperbarui data resmi SK peserta', $locked, $changes, $data['reason']);
        });

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
            'period_id' => ['nullable', 'integer', 'exists:admission_periods,id'],
            'operator_id' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $data['search'] ?? '';
        $operatorId = $data['operator_id'] ?? '';

        $participants = SkParticipant::query()
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
                'application.workspace.verifications',
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
            'Nomor Pengenal SK',
            'Nama Sesuai SK',
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
            'Status WhatsApp',
            'Nama Lengkap (Biodata)',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'NIK',
            'NISN',
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
            'Jenis Tinggal',
            'Alat Transportasi',
            'Penerima KPS',
            'Nomor KPS',
            'NPWP',
            'Kebutuhan Khusus',
            'Perguruan Tinggi Asal',
            'Program Studi Asal',
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

            $number = $bio?->whatsapp_number;
            $isWaVerified = $number !== null && $ws->verifications->contains(fn ($v) => $v->number === $number && $v->invalidated_at === null);
            $waStatus = $isWaVerified ? 'Terverifikasi' : ($number ? 'Belum Terverifikasi' : 'Belum Diisi');

            $birthDate = $bio?->birth_date ? $bio->birth_date->format('d/m/Y') : '';
            $submittedAt = $app?->submitted_at ? $app->submitted_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : '';

            $district = $bio !== null && $bio->district_code !== null ? ($regions->get($bio->district_code) ?? $bio->district_code) : '';
            $city = $bio !== null && $bio->city_code !== null ? ($regions->get($bio->city_code) ?? $bio->city_code) : '';
            $province = $bio !== null && $bio->province_code !== null ? ($regions->get($bio->province_code) ?? $bio->province_code) : '';

            $row = [
                $index + 1,
                $participant->period->name,
                (string) $participant->sk_number,
                $participant->sk_date->format('d/m/Y'),
                (string) $participant->sk_order,
                (string) ($participant->official_participant_id ?? ''),
                $participant->name_on_sk,
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
                $waStatus,
                $bio !== null ? ($bio->full_name ?? '') : '',
                $resolveOption('gender', $bio?->gender_code),
                $bio !== null ? ($bio->birth_place ?? '') : '',
                $birthDate,
                $bio !== null ? (string) ($bio->nik_encrypted ?? '') : '',
                $bio !== null ? (string) ($bio->nisn_encrypted ?? '') : '',
                $resolveOption('religion', $bio?->religion_code),
                $resolveOption('nationality', $bio?->nationality_code),
                $bio !== null ? (string) ($bio->mother_name_encrypted ?? '') : '',
                $bio !== null ? (string) ($bio->whatsapp_number ?? '') : '',
                $bio !== null ? ($bio->contact_email ?? '') : '',
                $bio !== null ? (string) ($bio->other_phone ?? '') : '',
                $bio !== null ? ($bio->street_address ?? '') : '',
                $bio !== null ? ($bio->hamlet ?? '') : '',
                $bio !== null ? (string) ($bio->rt ?? '') : '',
                $bio !== null ? (string) ($bio->rw ?? '') : '',
                $bio !== null ? ($bio->village_name ?? '') : '',
                $district,
                $city,
                $province,
                $bio !== null ? (string) ($bio->postal_code ?? '') : '',
                $resolveOption('residence', $bio?->residence_type_code),
                $resolveOption('transport', $bio?->transport_type_code),
                $bio !== null ? ($bio->is_kps_recipient ? 'Ya' : 'Tidak') : '',
                $bio !== null ? (string) ($bio->kps_number_encrypted ?? '') : '',
                $bio !== null ? (string) ($bio->npwp_encrypted ?? '') : '',
                $resolveOption('special_need', $bio?->special_need_code),
                $edu !== null ? ($edu->pt_name_snapshot ?? '') : '',
                $edu !== null ? ($edu->prodi_name_snapshot ?? '') : '',
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

        $biodata = $workspace?->biodata;
        $education = $workspace?->education;
        $documents = $workspace ? $workspace->availableDocuments : collect();

        return view('portal.participant', [
            'participant' => $participant,
            'workspace' => $workspace,
            'biodata' => $biodata,
            'education' => $education,
            'documents' => $documents,
            'operators' => User::query()
                ->where('role', 'operator')
                ->where('account_state', 'active')
                ->whereIn('id', PeriodOperator::query()->where('admission_period_id', $participant->admission_period_id)->where('is_active', true)->select('user_id'))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
