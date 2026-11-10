<?php

use App\Models\ApplicationSetting;
use App\Models\SkClaim;

require_once __DIR__.'/../../Fixtures/registration.php';

it('uses the configured Peg ID requirement on the public claim form and server', function (bool $required) {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => $required]]);

    $form = $this->get(route('public.claim'))->assertOk()->assertViewHas('pegIdRequired', $required);
    preg_match('/<input\b[^>]*id="claim-identifier"[^>]*>/s', $form->getContent(), $matches);
    $response = $this->post(route('public.claim.store'), [...claimInput($c), 'identifier' => '']);

    if ($required) {
        expect($matches[0])->toContain('required');
        $form->assertDontSee('kosongkan jika belum menerima surat');
        $response->assertSessionHasErrors(['identifier' => 'Nomor Peg ID wajib diisi.']);
        $this->assertDatabaseCount('sk_claims', 0);
    } else {
        expect($matches[0])->not->toContain('required');
        $response->assertSessionHasNoErrors()->assertRedirect(route('public.claim'));
        expect(SkClaim::query()->sole()->submitted_identifier)->toBeNull();
    }
})->with([true, false]);

it('accepts a Peg ID and preserves its leading zeroes when it is required', function () {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => true]]);

    $this->post(route('public.claim.store'), [...claimInput($c), 'identifier' => '000001'])->assertSessionHasNoErrors();

    expect(SkClaim::query()->sole()->submitted_identifier)->toBe('000001');
});

it('defaults to an optional Peg ID for existing installations without the setting', function () {
    $c = registrationContext();

    $this->get(route('public.claim'))->assertOk()->assertViewHas('pegIdRequired', false);
    $this->post(route('public.claim.store'), [...claimInput($c), 'identifier' => ''])->assertSessionHasNoErrors();

    expect(SkClaim::query()->sole()->submitted_identifier)->toBeNull();
});

it('lets administrators switch Peg ID requirements without losing announcements', function () {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['notice' => 'Pengumuman tetap ada', 'notice_placement' => 'hidden'], 'version' => 0]);
    $this->actingAs($c['admin'])->get(route('admin.settings'))->assertOk()->assertSee('Peg ID pada klaim WhatsApp');

    foreach ([1, 0] as $version => $required) {
        $this->put(route('admin.settings.update'), [
            'version' => $version, 'helpdesk_name' => 'Helpdesk', 'notice' => 'Pengumuman tetap ada', 'claim_peg_id_required' => (string) $required,
        ])->assertSessionHasNoErrors();

        $setting = ApplicationSetting::query()->where('key', 'public')->sole();
        expect($setting->value['claim_peg_id_required'])->toBe((bool) $required);
        expect($setting->value['notice'])->toBe('Pengumuman tetap ada');
        expect($setting->value['notice_placement'])->toBe('hidden');
    }
});

it('preserves the Peg ID setting when an older settings form omits it', function () {
    $c = registrationContext();
    ApplicationSetting::create(['key' => 'public', 'value' => ['claim_peg_id_required' => true], 'version' => 0]);

    $this->actingAs($c['admin'])->put(route('admin.settings.update'), ['version' => 0, 'helpdesk_name' => 'Helpdesk'])->assertSessionHasNoErrors();

    expect(ApplicationSetting::claimPegIdRequired())->toBeTrue();
});
