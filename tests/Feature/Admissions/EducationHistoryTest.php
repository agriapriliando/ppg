<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\SubmitSkClaim;
use App\Models\ApplicationSubmission;
use App\Models\OriginEducation;
use App\Support\ApplicantFields;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once __DIR__.'/../../Fixtures/registration.php';

function educationHistoryInput(string $highest): array
{
    $history = [];
    foreach (['S1' => 2008, 'S2' => 2012, 'S3' => 2016] as $level => $year) {
        $history[$level] = ['pt_name' => 'Universitas '.$level, 'prodi_name' => 'Program Studi '.$level, 'graduation_year' => $year];
        if ($level === $highest) {
            break;
        }
    }

    return $history;
}

it('saves and submits all education levels through the highest qualification', function (string $highest) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $history = educationHistoryInput($highest);
    $input = array_merge(completeApplicantForm($c), ['action' => 'submit', 'version' => 0, 'degree_level' => $highest, 'education_history' => $history]);

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), $input)
        ->assertRedirect(route('applicant.application.status', $c['period']))->assertSessionHasNoErrors();

    $education = $application->workspace->fresh()->education;
    expect($education->education_history)->toBe($history);
    expect($education->degree_level)->toBe($highest);
    expect($education->pt_name_snapshot)->toBe('Universitas '.$highest);
    expect($education->graduation_year)->toBe($history[$highest]['graduation_year']);
    $snapshot = json_decode(ApplicationSubmission::query()->sole()->encrypted_snapshot, true);
    expect($snapshot['education_history'])->toBe($history);
    $this->get(route('applicant.form.edit', $c['period']))->assertOk()->assertSee('Universitas S1');
    $this->get(route('applicant.application.proof', $c['period']))->assertOk()->assertSee('Program Studi '.$highest);
    $this->actingAs($c['operator'])->get(route('reviews.show', $application))->assertOk()->assertSee('Universitas S1')->assertSee('Universitas '.$highest);
    $this->get(route('reviews.print', $application))->assertOk()->assertSee('Tahun Lulus '.$highest)->assertSee('Program Studi '.$highest);
})->with(['S1', 'S2', 'S3']);

it('requires each lower qualification before submitting a higher degree', function (string $missingLevel) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $history = educationHistoryInput('S3');
    unset($history[$missingLevel]);

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), array_merge(completeApplicantForm($c), [
        'version' => 0, 'action' => 'submit', 'degree_level' => 'S3', 'education_history' => $history,
    ]))->assertSessionHasErrors('education_history.'.$missingLevel);

    $this->assertDatabaseCount('application_submissions', 0);
})->with(['S1', 'S2', 'S3']);

it('requires campus study program and graduation year for every included qualification', function (string $field) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $history = educationHistoryInput('S2');
    $history['S2'][$field] = '';

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), array_merge(completeApplicantForm($c), [
        'version' => 0, 'action' => 'submit', 'degree_level' => 'S2', 'education_history' => $history,
    ]))->assertSessionHasErrors('education_history.S2.'.$field);
})->with(['pt_name', 'prodi_name', 'graduation_year']);

it('allows incomplete drafts and removes education above a lowered highest degree', function () {
    $c = registrationContext();
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        'version' => 0, 'action' => 'draft', 'degree_level' => 'S3', 'education_history' => ['S1' => ['pt_name' => 'Kampus Draf']],
    ])->assertSessionHasNoErrors();
    $education = OriginEducation::query()->sole();
    expect($education->education_history['S1']['pt_name'])->toBe('Kampus Draf');

    $this->post(route('applicant.form.update', $c['period']), [
        'version' => 1, 'action' => 'draft', 'degree_level' => 'S1', 'education_history' => educationHistoryInput('S3'),
    ])->assertSessionHasNoErrors();
    expect(array_keys($education->fresh()->education_history))->toBe(['S1']);
});

it('rejects invalid qualifications and graduation years', function (string $case) {
    $c = registrationContext();
    $input = ['version' => 0, 'action' => 'draft', 'degree_level' => 'S2', 'education_history' => educationHistoryInput('S2')];
    if ($case === 'degree') {
        $input['degree_level'] = 'S4';
        $error = 'degree_level';
    } else {
        $input['education_history']['S2']['graduation_year'] = match ($case) {
            'future' => now()->year + 1, 'too_old' => 1899, 'decimal' => 2020.5, 'order' => 2007,
        };
        $error = 'education_history.S2.graduation_year';
    }

    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), $input)->assertSessionHasErrors($error);
    $this->assertDatabaseCount('origin_education', 0);
})->with(['degree', 'future', 'too_old', 'decimal', 'order']);

it('prefills old S1 campus and program data without inventing a degree or graduation year', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $application = app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    OriginEducation::create(['workspace_id' => $application->workspace_id, 'pt_name_snapshot' => 'Kampus Lama', 'prodi_name_snapshot' => 'Prodi Lama']);

    $data = ApplicantFields::fromWorkspace($application->workspace->fresh());

    expect($data['education_history']['S1']['pt_name'])->toBe('Kampus Lama');
    expect($data['education_history']['S1']['prodi_name'])->toBe('Prodi Lama');
    expect($data['degree_level'])->toBeNull();
    expect($data['education_history']['S1']['graduation_year'])->toBeNull();
});

it('exports separate campus program and graduation year columns for all qualifications', function () {
    Storage::fake('local');
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    app(ApproveClaim::class)->handle($c['operator'], $claim, 0);
    $this->actingAs($c['applicant'])->post(route('applicant.form.update', $c['period']), [
        'version' => 0, 'action' => 'draft', 'degree_level' => 'S3', 'education_history' => educationHistoryInput('S3'),
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($c['admin'])->get(route('participants.export'))->assertOk();
    Storage::disk('local')->put('education-export.xlsx', $response->streamedContent());
    $book = IOFactory::load(Storage::disk('local')->path('education-export.xlsx'));
    $rows = $book->getActiveSheet()->toArray();
    foreach (educationHistoryInput('S3') as $level => $item) {
        expect($rows[1][array_search('Perguruan Tinggi '.$level, $rows[0], true)])->toBe($item['pt_name']);
        expect($rows[1][array_search('Program Studi '.$level, $rows[0], true)])->toBe($item['prodi_name']);
        expect((int) $rows[1][array_search('Tahun Lulus '.$level, $rows[0], true)])->toBe($item['graduation_year']);
    }
    $book->disconnectWorksheets();
});
