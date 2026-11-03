<?php

use App\Models\ExportRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../../Fixtures/registration.php';

it('exports all matching rows with formula protection and without private identity fields', function () {
    Storage::fake('local');
    $c = registrationContext();
    $c['participant']->name_on_sk = '=FORMULA(UJI)';
    $c['participant']->save();
    $this->actingAs($c['admin'])->post(route('admin.exports.store'), ['period_id' => $c['period']->id])->assertRedirect();
    $run = ExportRun::query()->sole();
    expect($run->state)->toBe('ready');
    expect($run->row_count)->toBe(1);
    $contents = Storage::disk('local')->get($run->private_path);
    expect($contents)->toContain("'=FORMULA(UJI)")->toContain("'000001")->not->toContain('nik_encrypted')->not->toContain('mother_name');
    $this->get(route('admin.exports.download', $run))->assertOk()->assertDownload('peserta-ppg-'.$run->id.'.csv');
});

it('refuses download after assignment changes and after expiry', function () {
    Storage::fake('local');
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.exports.store'), ['period_id' => $c['period']->id])->assertRedirect();
    $run = ExportRun::query()->sole();
    $c['participant']->lock_version++;
    $c['participant']->save();
    $this->get(route('admin.exports.download', $run))->assertSessionHasErrors('export');
    $this->travel(25)->hours();
    $this->get(route('admin.exports.download', $run))->assertSessionHasErrors('export');
    $this->artisan('pmb:expire-exports')->assertSuccessful();
    expect($run->fresh()->state)->toBe('expired');
    Storage::disk('local')->assertMissing($run->private_path);
});

it('forbids operators from downloading another users export and forbids applicants from exporting', function () {
    Storage::fake('local');
    $c = registrationContext();
    $this->actingAs($c['admin'])->post(route('admin.exports.store'), [])->assertRedirect();
    $run = ExportRun::query()->sole();
    $this->actingAs($c['operator'])->get(route('admin.exports.download', $run))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('admin.exports.store'), [])->assertForbidden();
    $this->actingAs($c['operator'])->post(route('admin.exports.store'), [])->assertRedirect();
});
