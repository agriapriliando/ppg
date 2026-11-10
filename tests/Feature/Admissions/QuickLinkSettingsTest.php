<?php

use App\Livewire\Admin\QuickLinksTable;
use App\Models\PortalQuickLink;
use App\Models\User;
use Livewire\Livewire;

it('reorders quick links in place without a redirect for staff', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $before = PortalQuickLink::query()->orderBy('sort_order')->orderBy('id')->get();
    $last = $before->last();
    $expected = $before->pluck('id')->all();
    $lastIndex = count($expected) - 1;
    [$expected[$lastIndex - 1], $expected[$lastIndex]] = [$expected[$lastIndex], $expected[$lastIndex - 1]];

    $component = Livewire::actingAs($user)->test(QuickLinksTable::class)
        ->call('moveUp', $last->id)->assertNoRedirect()->assertHasNoErrors()->assertSee('Urutan tautan cepat disimpan.');

    expect(PortalQuickLink::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all())->toBe($expected);
    $component->call('moveDown', $last->id)->assertNoRedirect()->assertHasNoErrors();
    expect(PortalQuickLink::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all())->toBe($before->pluck('id')->all());
})->with(['administrator', 'operator']);

it('keeps quick link order unchanged when moving beyond a boundary', function (string $method) {
    $user = User::factory()->administrator()->create();
    $links = PortalQuickLink::query()->orderBy('sort_order')->orderBy('id')->get();

    Livewire::actingAs($user)->test(QuickLinksTable::class)
        ->call($method, $method === 'moveUp' ? $links->first()->id : $links->last()->id)->assertNoRedirect();

    expect(PortalQuickLink::query()->orderBy('sort_order')->orderBy('id')->pluck('id')->all())->toBe($links->pluck('id')->all());
})->with(['moveUp', 'moveDown']);

it('resolves duplicate sort positions when reordering links', function () {
    $user = User::factory()->administrator()->create();
    PortalQuickLink::query()->update(['sort_order' => 1]);
    $before = PortalQuickLink::query()->orderBy('id')->pluck('id')->all();
    $expected = $before;
    [$expected[0], $expected[1]] = [$expected[1], $expected[0]];

    Livewire::actingAs($user)->test(QuickLinksTable::class)->call('moveDown', $before[0])->assertNoRedirect();

    expect(PortalQuickLink::query()->orderBy('sort_order')->pluck('id')->all())->toBe($expected);
    expect(PortalQuickLink::query()->orderBy('sort_order')->pluck('sort_order')->all())->toBe(range(1, count($expected)));
});

it('shows each quick link only in its selected locations', function (string $placement, string $role) {
    $admin = User::factory()->administrator()->create();
    $this->actingAs($admin)->post(route('admin.quick-links.store'), [
        'title' => 'Tombol Lokasi Pengujian', 'url' => 'https://example.test/link', 'button_color' => 'purple',
        'target' => '_blank', 'placement' => $placement,
    ])->assertSessionHasNoErrors();
    $link = PortalQuickLink::query()->where('title', 'Tombol Lokasi Pengujian')->sole();
    $user = $role === 'applicant' ? User::factory()->create() : User::factory()->{$role}()->create();

    $home = $this->get(route('home'))->assertOk();
    $dashboard = $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($home->viewData('quickLinks')->contains('id', $link->id))->toBe(in_array($placement, ['both', 'homepage'], true));
    expect($dashboard->viewData('quickLinks')->contains('id', $link->id))->toBe(in_array($placement, ['both', 'dashboard'], true));
    expect($link->placement)->toBe($placement)->and($link->is_active)->toBe($placement !== 'hidden');
})->with(['both', 'homepage', 'dashboard', 'hidden'])->with(['applicant', 'operator', 'administrator']);

it('can show a hidden link again through its placement setting without an active checkbox', function () {
    $operator = User::factory()->operator()->create();
    $link = PortalQuickLink::create(['title' => 'Tautan Disembunyikan', 'url' => 'https://example.test/link', 'is_active' => false, 'placement' => 'hidden']);

    $this->actingAs($operator)->put(route('admin.quick-links.update', $link), [
        'title' => $link->title, 'url' => $link->url, 'button_color' => 'purple', 'target' => '_blank', 'placement' => 'dashboard',
    ])->assertSessionHasNoErrors();

    expect($link->fresh()->is_active)->toBeTrue()->and($link->fresh()->placement)->toBe('dashboard');
    $this->get(route('admin.settings'))->assertOk()->assertSeeLivewire(QuickLinksTable::class)
        ->assertSee('Tampilkan di')->assertDontSee('Tampilkan di Beranda (Aktif)')->assertDontSee('name="is_active"', false);
});

it('rejects unsupported placements without saving a link', function () {
    $admin = User::factory()->administrator()->create();
    $before = PortalQuickLink::query()->count();

    $this->actingAs($admin)->post(route('admin.quick-links.store'), [
        'title' => 'Tombol Lokasi Salah', 'url' => 'https://example.test/link', 'button_color' => 'purple', 'target' => '_blank', 'placement' => 'unknown',
    ])->assertSessionHasErrors('placement');

    $this->assertDatabaseCount('portal_quick_links', $before);
});

it('denies applicants and inactive operators access to quick link ordering', function (string $role) {
    $user = $role === 'applicant' ? User::factory()->create() : User::factory()->operator()->create(['account_state' => 'disabled']);
    $before = PortalQuickLink::query()->orderBy('sort_order')->pluck('id')->all();

    Livewire::actingAs($user)->test(QuickLinksTable::class)->assertForbidden();

    expect(PortalQuickLink::query()->orderBy('sort_order')->pluck('id')->all())->toBe($before);
})->with(['applicant', 'inactive']);
