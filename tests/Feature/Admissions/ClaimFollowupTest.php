<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Enums\PeriodState;
use App\Models\PeriodOperator;
use App\Models\User;

require_once __DIR__.'/../../Fixtures/registration.php';

it('returns an applicant response to the queue once without changing claimed identity', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $this->actingAs($c['operator'])->post(route('claims.note', $claim), ['version' => 0, 'status' => 'needs_information', 'public_note' => 'Mohon jelaskan perbedaan penulisan nama pada SK.'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($c['applicant'])->get(route('applicant.claims'))->assertOk()->assertSee('Kirim Informasi Tambahan');
    $payload = ['version' => 1, 'message' => '<script>alert("uji")</script> Nama SK menggunakan gelar lama.', 'candidate_sk_participant_id' => 999, 'status' => 'approved'];
    $this->post(route('applicant.claims.respond', $claim), $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect($claim->refresh()->status)->toBe('pending')->and($claim->candidate_sk_participant_id)->toBe($c['participant']->id)->and($claim->lock_version)->toBe(2);
    $this->post(route('applicant.claims.respond', $claim), $payload)->assertSessionHasErrors('claim');
    $this->assertDatabaseCount('claim_responses', 1);
    $this->actingAs($c['operator'])->get(route('claims.show', $claim))->assertOk()->assertSee($payload['message'])->assertDontSee($payload['message'], false);
    $this->assertDatabaseCount('applications', 0);
});

it('protects applicant responses from other accounts and archived or unchanged claims', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $payload = ['version' => 0, 'message' => 'Informasi tambahan peserta sesuai permintaan.'];
    $this->actingAs(User::factory()->create())->post(route('applicant.claims.respond', $claim), $payload)->assertNotFound();
    $this->post(route('applicant.claims.cancel', $claim), ['version' => 0, 'confirmed' => 1])->assertNotFound();
    $this->actingAs($c['applicant'])->post(route('applicant.claims.respond', $claim), $payload)->assertSessionHasErrors('claim');
    $claim->status = 'needs_information';
    $claim->save();
    $c['period']->state = PeriodState::Archived;
    $c['period']->save();
    $this->post(route('applicant.claims.respond', $claim), $payload)->assertSessionHasErrors('claim');
    $this->assertDatabaseCount('claim_responses', 0);
});

it('cancels only an unapproved own claim with a current version and explicit confirmation', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], claimInput($c));
    $this->actingAs($c['applicant'])->post(route('applicant.claims.cancel', $claim), ['version' => 0])->assertSessionHasErrors('confirmed');
    $this->post(route('applicant.claims.cancel', $claim), ['version' => 0, 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect($claim->refresh()->status)->toBe('cancelled');
    $this->actingAs($c['operator'])->post(route('claims.approve', $claim), ['version' => 1, 'identity_checked' => 1, 'sk_checked' => 1])->assertSessionHasErrors('claim');
    $this->post(route('claims.note', $claim), ['version' => 1, 'status' => 'needs_information', 'public_note' => 'Catatan yang tidak boleh membuka klaim batal.'])->assertSessionHasErrors('claim');
    $this->assertDatabaseCount('applications', 0);
});

it('delegates ambiguous claims to an active period operator and removes scope after membership is disabled', function () {
    $c = registrationContext();
    $input = claimInput($c);
    $input['name'] = 'Nama Ambigu yang Belum Cocok';
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], $input);
    $this->actingAs($c['admin'])->get(route('claims.show', $claim))->assertOk()->assertSee('Penanggung jawab klaim');
    $this->post(route('claims.delegate', $claim), ['version' => 0, 'operator_id' => $c['operator']->id, 'reason' => 'Operator ditunjuk untuk memeriksa sumber SK.'])->assertRedirect()->assertSessionHasNoErrors();
    expect($claim->refresh()->review_owner_id)->toBe($c['operator']->id);
    $this->actingAs($c['operator'])->get(route('claims.index'))->assertOk()->assertSee($input['name']);
    $this->get(route('claims.show', $claim))->assertOk();
    $this->post(route('claims.delegate', $claim), ['version' => 1, 'operator_id' => $c['operator']->id, 'reason' => 'Operator memperbarui penanggung jawab klaim.'])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($c['applicant'])->post(route('claims.delegate', $claim), ['version' => 2, 'operator_id' => $c['operator']->id, 'reason' => 'Pendaftar tidak berhak mendelegasikan.'])->assertForbidden();
    $this->actingAs($c['operator']);
    PeriodOperator::query()->where('admission_period_id', $c['period']->id)->where('user_id', $c['operator']->id)->update(['is_active' => false]);
    $this->get(route('claims.index'))->assertOk()->assertDontSee($input['name']);
    $this->get(route('claims.show', $claim))->assertNotFound();
});

it('rejects delegation to outsiders and stale or already matched claims', function () {
    $c = registrationContext();
    $input = claimInput($c);
    $input['name'] = 'Nama Belum Cocok';
    $claim = app(SubmitSkClaim::class)->handle($c['applicant'], $input);
    $payload = ['version' => 0, 'operator_id' => User::factory()->operator()->create()->id, 'reason' => 'Penugasan pemeriksaan tambahan sumber SK.'];
    $this->actingAs($c['admin'])->post(route('claims.delegate', $claim), $payload)->assertSessionHasErrors('operator_id');
    $payload['operator_id'] = $c['operator']->id;
    $payload['version'] = 1;
    $this->post(route('claims.delegate', $claim), $payload)->assertSessionHasErrors('claim');
    $payload['version'] = 0;
    $claim->candidate_sk_participant_id = $c['participant']->id;
    $claim->save();
    $this->post(route('claims.delegate', $claim), $payload)->assertSessionHasErrors('claim');
    expect($claim->refresh()->review_owner_id)->toBeNull();
});
