<?php

use App\Models\AdmissionPeriod;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Livewire::flushState();
    config(['seo.url' => 'https://ppg.iaknpky.ac.id', 'seo.indexable' => true, 'seo.redirect_canonical' => false]);
});

it('renders public metadata using the configured canonical domain without query parameters', function () {
    $response = $this->get('/?utm_source=whatsapp')->assertOk();

    $response->assertSee('<title>PPG IAKN Palangka Raya | Pendaftaran dan Informasi Resmi</title>', false)
        ->assertSee('<link rel="canonical" href="https://ppg.iaknpky.ac.id/">', false)
        ->assertSee('content="index, follow, max-image-preview:large', false)
        ->assertSee('https://ppg.iaknpky.ac.id/assets/ppg%20iaknpky%20seo.jpg', false)
        ->assertDontSee('utm_source', false)->assertDontSee('livewire.js', false);
    $response->assertHeaderMissing('X-Robots-Tag');
});

it('keeps account and registration pages out of search while retaining their interactive scripts', function (string $route) {
    $this->get(route($route))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('livewire.js', false);
})->with(['login', 'public.claim']);

it('keeps the authenticated workspace out of search', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, follow')->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('publishes a valid sitemap containing only public published periods and informational pages', function () {
    $public = AdmissionPeriod::factory()->published()->create(['is_public' => true]);
    $private = AdmissionPeriod::factory()->published()->create(['is_public' => false]);
    $draft = AdmissionPeriod::factory()->create(['is_public' => true]);
    $archived = AdmissionPeriod::factory()->create(['state' => 'archived', 'is_public' => true]);

    $response = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $xml = simplexml_load_string($response->getContent());
    expect($xml)->not->toBeFalse();
    $urls = array_map(fn ($url) => (string) $url->loc, iterator_to_array($xml->url, false));
    expect($urls)->toBe(['https://ppg.iaknpky.ac.id/', 'https://ppg.iaknpky.ac.id/panduan', 'https://ppg.iaknpky.ac.id/periode/'.$public->id.'/berkas']);
    foreach ([$private, $draft, $archived] as $period) {
        $response->assertDontSee('/periode/'.$period->id.'/berkas', false);
    }
});

it('provides robots instructions and disables indexing for local or staging environments', function () {
    $this->get('/robots.txt')->assertOk()->assertSee("User-agent: *\nAllow: /\n", false)
        ->assertSee('Sitemap: https://ppg.iaknpky.ac.id/sitemap.xml', false);
    config(['seo.indexable' => false]);
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false);
    $this->get('/')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('indexes only published public period pages and gives each period its own metadata', function () {
    $period = AdmissionPeriod::factory()->published()->create(['is_public' => true, 'name' => 'PPG Gelombang Dua']);
    $this->get(route('public.period-documents.index', $period))->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')->assertSee('PPG Gelombang Dua | Jadwal dan Pengumuman PPG IAKN Palangka Raya')
        ->assertSee('Panduan Pendaftaran')->assertDontSee('livewire.js', false);
    $period->forceFill(['is_public' => false])->save();
    $this->get(route('public.period-documents.index', $period))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    $period->forceFill(['is_public' => true, 'state' => 'archived'])->save();
    $this->get(route('public.period-documents.index', $period))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
});

it('redirects public informational pages to the configured HTTPS domain without looping', function () {
    config(['seo.redirect_canonical' => true]);
    $this->get('http://alternate.example/panduan?utm_source=wa')->assertStatus(301)
        ->assertRedirect('https://ppg.iaknpky.ac.id/panduan?utm_source=wa');
    $this->get('https://ppg.iaknpky.ac.id/panduan')->assertOk();
    $this->get('http://alternate.example/masuk')->assertOk();
});
