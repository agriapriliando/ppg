<?php

use App\Actions\Admissions\SubmitSkClaim;
use App\Models\AdmissionPeriod;
use App\Models\SkClaim;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('blocks repeat registration using the same Peg ID even after names or phone numbers change', function (string $status) {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $claim->update(['status' => $status]);
    $input = [...claimInput($c), 'request_key' => (string) Str::uuid(), 'name' => 'Nama Pemohon Diubah', 'whatsapp_number' => '081355556666', 'identifier' => ' 000001 '];

    $page = $this->from(route('public.claim'))->followingRedirects()->post(route('public.claim.store'), $input)->assertOk();

    $page->assertSee('Pendaftaran akun belum dapat dikirim')->assertSee('Peg ID ini sudah terdaftar')
        ->assertSee('hubungi operator')->assertSee('is-invalid')->assertSee('Nama Pemohon Diubah');
    $this->assertDatabaseCount('sk_claims', 1);
    $this->assertDatabaseCount('consent_records', 1);
})->with(['pending', 'needs_information', 'approved', 'declined', 'cancelled']);

it('keeps duplicate request retries idempotent and rejects changed Peg IDs on a used request key', function () {
    $c = registrationContext();
    $input = claimInput($c);

    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();
    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();
    $this->post(route('public.claim.store'), [...$input, 'identifier' => '000002'])->assertSessionHasErrors('claim');

    $this->assertDatabaseCount('sk_claims', 1);
    $this->assertDatabaseCount('consent_records', 1);
});

it('allows the same Peg ID to register for a different admission period', function () {
    $c = registrationContext();
    app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $otherPeriod = AdmissionPeriod::factory()->published()->create();

    $this->post(route('public.claim.store'), [
        ...claimInput($c), 'period_id' => $otherPeriod->id, 'request_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('sk_claims', 2);
});

it('still rejects duplicate name and WhatsApp combinations without a Peg ID', function () {
    $c = registrationContext();
    app(SubmitSkClaim::class)->handle(null, [...claimInput($c), 'identifier' => '']);

    $this->post(route('public.claim.store'), [
        ...claimInput($c), 'request_key' => (string) Str::uuid(), 'identifier' => '', 'name' => mb_strtoupper($c['applicant']->name),
    ])->assertSessionHasErrors('claim');

    $this->assertDatabaseCount('sk_claims', 1);
});

it('allows a Peg ID to be used again after the previous claim has been removed by staff', function () {
    $c = registrationContext();
    $claim = app(SubmitSkClaim::class)->handle(null, claimInput($c));
    $claim->delete();

    $this->post(route('public.claim.store'), [...claimInput($c), 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();

    expect(SkClaim::query()->count())->toBe(1);
    expect(SkClaim::withTrashed()->count())->toBe(2);
});

it('shows clear field names instead of untranslated validation keys on the public form', function () {
    $c = registrationContext();
    $page = $this->from(route('public.claim'))->followingRedirects()->post(route('public.claim.store'), [
        ...claimInput($c), 'name' => '', 'period_id' => '', 'whatsapp_number' => '',
    ])->assertOk();

    $page->assertSee('Pendaftaran akun belum dapat dikirim')
        ->assertSee('Nama lengkap wajib diisi.')->assertSee('Periode pendaftaran wajib diisi.')->assertSee('Nomor WhatsApp wajib diisi.')
        ->assertDontSee('validation.required');
    $this->assertDatabaseCount('sk_claims', 0);
});
