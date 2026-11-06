<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Actions\Documents\SetPeriodDocumentVisibility;
use App\Actions\Documents\UploadPeriodDocument;
use App\Enums\PeriodState;
use App\Livewire\Documents\PeriodDocumentManager;
use App\Models\AdmissionPeriod;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

require_once __DIR__.'/../../Fixtures/registration.php';

function skInformationFiles(): array
{
    $c = registrationContext();
    $c['period']->forceFill(['is_public' => true])->save();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $c['participant']->forceFill(['internal_note' => 'CATATAN INTERNAL RAHASIA', 'origin_city_name' => 'Kota Asal SK', 'origin_province_name' => 'Provinsi Asal SK'])->save();
    $files = [];
    foreach (['internal', 'participants', 'public'] as $visibility) {
        $doc = app(UploadPeriodDocument::class)->handle($c['admin'], $c['period'], UploadedFile::fake()->create('panduan-'.$visibility.'.pdf', 10, 'application/pdf'), $visibility);
        if ($visibility !== 'internal') {
            app(SetPeriodDocumentVisibility::class)->handle($c['admin'], $doc, $visibility, 0);
        }
        $files[$visibility] = $doc->fresh();
    }

    return [...$c, 'files' => $files];
}

beforeEach(fn () => Storage::fake('local'));

it('lets participants select among their own periods without mixing SK data or period files', function () {
    $c = skInformationFiles();
    $second = AdmissionPeriod::factory()->published()->create();
    $person = SkParticipant::query()->create(['admission_period_id' => $second->id, 'sk_number' => 'SK-KEDUA', 'sk_date' => '2026-11-06', 'sk_order' => 1, 'name_on_sk' => 'Nama pada SK Kedua', 'normalized_name' => 'nama pada sk kedua', 'official_participant_id' => '000002']);
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], [...claimInput($c), 'period_id' => $second->id, 'name' => $person->name_on_sk, 'identifier' => '000002', 'request_key' => (string) Str::uuid()]);
    app(ApproveClaim::class)->handle($c['admin'], $claim, 0);
    $doc = app(UploadPeriodDocument::class)->handle($c['admin'], $second, UploadedFile::fake()->create('panduan-periode-kedua.pdf', 10, 'application/pdf'), 'second');
    app(SetPeriodDocumentVisibility::class)->handle($c['admin'], $doc, 'participants', 0);
    $this->actingAs($c['applicant'])->get(route('applicant.sk-information', ['period_id' => $second->id]))->assertOk()->assertSee('Nama pada SK Kedua')->assertSee('panduan-periode-kedua.pdf')->assertDontSee('panduan-participants.pdf');
    $this->get(route('applicant.sk-information', ['period_id' => $c['period']->id]))->assertOk()->assertSee($c['participant']->name_on_sk)->assertDontSee('Nama pada SK Kedua')->assertDontSee('panduan-periode-kedua.pdf');
});

it('shows applicants only their SK details and shared period files through the dashboard menu', function () {
    $c = skInformationFiles();
    $this->actingAs($c['applicant'])->get(route('dashboard'))->assertOk()->assertSee('Informasi SK')->assertSee(route('applicant.sk-information'));
    $this->get(route('applicant.sk-information'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee($c['participant']->name_on_sk)->assertSee($c['participant']->official_participant_id)
        ->assertSee('Kota Asal SK')->assertSee('Provinsi Asal SK')->assertSee($c['period']->academic_year)
        ->assertSee('panduan-participants.pdf')->assertSee('panduan-public.pdf')->assertDontSee('panduan-internal.pdf')->assertDontSee('CATATAN INTERNAL RAHASIA');
    $this->get(route('applicant.period-documents.download', [$c['period'], $c['files']['participants']]))->assertOk()->assertDownload('panduan-participants.pdf');
    $this->get(route('applicant.period-documents.download', [$c['period'], $c['files']['internal']]))->assertForbidden();
    $otherPeriod = AdmissionPeriod::factory()->create();
    $this->get(route('applicant.sk-information', ['period_id' => $otherPeriod->id]))->assertNotFound();
    $this->get(route('applicant.period-documents.download', [$otherPeriod, $c['files']['public']]))->assertNotFound();
});

it('protects other participants and handles applicants without linked SK records', function () {
    $c = skInformationFiles();
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('applicant.sk-information'))->assertOk()->assertSee('Kepesertaan SK belum terhubung')->assertDontSee($c['participant']->name_on_sk);
    $this->get(route('applicant.sk-information', ['period_id' => $c['period']->id]))->assertNotFound();
    $this->get(route('applicant.period-documents.download', [$c['period'], $c['files']['participants']]))->assertForbidden();
    $this->get(route('applicant.period-documents.download', [$c['period'], $c['files']['public']]))->assertForbidden();
});

it('shows a homepage button and only published files to guests without exposing SK data', function () {
    $c = skInformationFiles();
    $this->get(route('home'))->assertOk()->assertSee('Lihat Berkas Periode')->assertSee(route('public.period-documents.index', $c['period']));
    $this->get(route('public.period-documents.index', $c['period']))->assertOk()->assertSee($c['period']->name)
        ->assertSee('panduan-public.pdf')->assertDontSee('panduan-participants.pdf')->assertDontSee('panduan-internal.pdf')
        ->assertDontSee($c['participant']->name_on_sk)->assertDontSee('CATATAN INTERNAL RAHASIA');
    $this->get(route('public.period-documents.download', [$c['period'], $c['files']['public']]))->assertOk()->assertDownload('panduan-public.pdf');
    $this->get(route('public.period-documents.preview', [$c['period'], $c['files']['public']]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    foreach (['internal', 'participants'] as $hidden) {
        $this->get(route('public.period-documents.download', [$c['period'], $c['files'][$hidden]]))->assertNotFound();
    }
});

it('allows staff to publish and revoke files and rejects stale publication changes', function () {
    $c = skInformationFiles();
    $doc = $c['files']['internal'];
    Livewire::actingAs($c['admin'])->test(PeriodDocumentManager::class, ['periodId' => $c['period']->id])
        ->call('setVisibility', $doc->id, 'public', 0)->assertHasNoErrors()->assertSee('Visibilitas berkas berhasil diperbarui.')
        ->call('setVisibility', $doc->id, 'internal', 0)->assertHasErrors('visibility');
    $this->get(route('public.period-documents.download', [$c['period'], $doc]))->assertOk();
    Livewire::actingAs($c['admin'])->test(PeriodDocumentManager::class, ['periodId' => $c['period']->id])
        ->call('setVisibility', $doc->id, 'internal', 1)->assertHasNoErrors();
    // Even a signed-in administrator cannot bypass public URL visibility rules.
    $this->get(route('public.period-documents.download', [$c['period'], $doc]))->assertNotFound();
});

it('hides draft nonpublic archived and deleting files from public links while retaining historical participant access', function () {
    $c = skInformationFiles();
    $period = $c['period'];
    $doc = $c['files']['public'];
    $period->forceFill(['manual_closed_at' => now()])->save();
    $this->get(route('public.period-documents.index', $period))->assertOk()->assertSee('Pendaftaran ditutup');
    foreach ([['state' => PeriodState::Draft], ['state' => PeriodState::Published, 'is_public' => false], ['state' => PeriodState::Archived, 'is_public' => true]] as $change) {
        $period->forceFill($change)->save();
        $this->get(route('public.period-documents.index', $period))->assertNotFound();
        $this->get(route('public.period-documents.download', [$period, $doc]))->assertNotFound();
    }
    $this->actingAs($c['applicant'])->get(route('applicant.sk-information'))->assertOk()->assertSee('panduan-participants.pdf');
    $this->get(route('applicant.period-documents.download', [$period, $c['files']['participants']]))->assertOk();
    $doc->forceFill(['status' => 'deleting'])->save();
    $this->get(route('applicant.period-documents.download', [$period, $doc]))->assertForbidden();
});
