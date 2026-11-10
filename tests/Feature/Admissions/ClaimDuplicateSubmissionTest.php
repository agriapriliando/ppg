<?php

use App\Models\AdmissionPeriod;
use App\Models\AuditEvent;
use App\Models\SkClaim;
use Illuminate\Support\Str;

require_once __DIR__.'/../../Fixtures/registration.php';

it('rejects repeated public submissions with normalized matching names and WhatsApp numbers', function (string $name, string $number) {
    $c = registrationContext();
    $input = [...claimInput($c), 'name' => 'Siti Aminah'];
    $this->post(route('public.claim.store'), $input)->assertRedirect(route('public.claim'))->assertSessionHasNoErrors();

    $this->post(route('public.claim.store'), [...$input, 'request_key' => (string) Str::uuid(), 'name' => $name, 'whatsapp_number' => $number, 'identifier' => 'IDENTIFIER-BERBEDA'])
        ->assertSessionHasErrors(['claim' => 'Kombinasi nama dan nomor WhatsApp ini sudah terdaftar pada periode yang dipilih. Tidak perlu mengirim pendaftaran akun kembali. Tunggu informasi akun melalui WhatsApp atau hubungi operator untuk bantuan.']);

    $this->assertDatabaseCount('sk_claims', 1);
    $this->assertDatabaseCount('consent_records', 1);
    expect(AuditEvent::query()->where('action', 'Mengajukan klaim SK')->count())->toBe(1);
})->with([
    'same input' => ['Siti Aminah', '081234567890'],
    'upper case' => ['SITI AMINAH', '+6281234567890'],
    'extra spaces' => ['  Siti    Aminah  ', '62 812-3456-7890'],
    'line breaks' => ["Siti\tAminah", '0812 3456 7890'],
]);

it('rejects duplicate name and WhatsApp combinations regardless of existing status', function (string $status) {
    $c = registrationContext();
    $input = claimInput($c);
    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();
    SkClaim::query()->sole()->forceFill(['status' => $status])->save();

    $this->post(route('public.claim.store'), [...$input, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('claim');

    $this->assertDatabaseCount('sk_claims', 1);
})->with(['pending', 'needs_information', 'approved', 'cancelled']);

it('prevents switching from a public submission to a logged in requester to create a duplicate', function () {
    $c = registrationContext();
    $input = claimInput($c);
    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();

    $this->actingAs($c['applicant'])->post(route('public.claim.store'), [...$input, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('claim');

    $this->assertDatabaseCount('sk_claims', 1);
});

it('keeps exact request retries idempotent without creating extra claims or consent records', function () {
    $c = registrationContext();
    $input = claimInput($c);

    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();
    $this->post(route('public.claim.store'), $input)->assertRedirect(route('public.claim'))->assertSessionHasNoErrors();

    $this->assertDatabaseCount('sk_claims', 1);
    $this->assertDatabaseCount('consent_records', 1);
});

it('allows combinations that differ by name or WhatsApp number when Peg IDs are also different', function (array $change) {
    $c = registrationContext();
    $c['participant']->replicate()->fill(['sk_order' => 2, 'official_participant_id' => 'IDENTIFIER-BERBEDA'])->save();
    $input = claimInput($c);
    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();

    $this->post(route('public.claim.store'), [...$input, ...$change, 'identifier' => 'IDENTIFIER-BERBEDA', 'request_key' => (string) Str::uuid()])->assertRedirect(route('public.claim'))->assertSessionHasNoErrors();

    $this->assertDatabaseCount('sk_claims', 2);
})->with([
    'different name' => [['name' => 'Nama Pendaftar Lain']],
    'different WhatsApp' => [['whatsapp_number' => '081299999999']],
]);

it('allows the same combination in a different period', function () {
    $c = registrationContext();
    $input = claimInput($c);
    $this->post(route('public.claim.store'), $input)->assertSessionHasNoErrors();
    $period = AdmissionPeriod::factory()->published()->create();
    $c['participant']->replicate()->fill(['admission_period_id' => $period->id])->save();

    $this->post(route('public.claim.store'), [...$input, 'period_id' => $period->id, 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('sk_claims', 2);
});
