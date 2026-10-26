<?php

use App\Models\ApplicationSetting;
use App\Models\User;

it('publishes approved contact information and escapes untrusted notice text', function () {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->put(route('admin.settings.update'), ['helpdesk_name' => 'Helpdesk Resmi', 'helpdesk_whatsapp' => '+628123456789', 'service_hours' => '08.00–16.00', 'notice' => '<script>alert(1)</script>', 'version' => 0])->assertRedirect(route('admin.settings'));
    $this->get(route('home'))->assertSee('Helpdesk Resmi')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    expect(ApplicationSetting::query()->where('key', 'public')->sole()->version)->toBe(1);
});

it('does not overwrite settings using an outdated version', function () {
    $admin = User::factory()->administrator()->create();
    ApplicationSetting::create(['key' => 'public', 'value' => ['helpdesk_name' => 'Versi terkini'], 'version' => 2]);
    $this->actingAs($admin)->put(route('admin.settings.update'), ['helpdesk_name' => 'Versi lama', 'version' => 1])->assertSessionHasErrors('version');
    expect(ApplicationSetting::query()->where('key', 'public')->sole()->value['helpdesk_name'])->toBe('Versi terkini');
});

it('forbids applicants from changing public information', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('admin.settings.update'), ['helpdesk_name' => 'Penyusup', 'version' => 0])->assertForbidden();
    $this->assertDatabaseCount('application_settings', 0);
});
