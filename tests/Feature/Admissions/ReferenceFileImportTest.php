<?php

use App\Models\ReferenceDataset;
use App\Models\ReferenceRegion;
use App\Models\User;
use Illuminate\Http\UploadedFile;

require_once __DIR__.'/../../Fixtures/registration.php';

function referenceFile(string $group, array $rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent($group.'.json', json_encode([$group => $rows], JSON_THROW_ON_ERROR));
}

it('detects multiple reference files including GetWilayah and exposes the trimmed regional hierarchy', function () {
    $admin = User::factory()->administrator()->create();
    $files = [
        referenceFile('options', [['group' => 'gender', 'code' => 'L', 'label' => 'Laki-laki']]),
        referenceFile('prodi', [['external_id' => 'prodi-1', 'pt_external_id' => 'pt-1', 'name' => 'Program Uji']]),
        referenceFile('pt', [['external_id' => 'pt-1', 'name' => 'PT Uji']]),
    ];
    $files['regions'] = UploadedFile::fake()->createWithContent('GetWilayah.json', file_get_contents(base_path('docs/GetWilayah.json')));

    $this->actingAs($admin)->post(route('admin.references.store'), ['files' => array_values($files)])
        ->assertRedirect()->assertSessionHasNoErrors();

    $dataset = ReferenceDataset::query()->where('state', 'active')->sole();
    expect(ReferenceRegion::query()->where('dataset_id', $dataset->id)->count())->toBe(7817);
    $this->assertDatabaseHas('reference_regions', ['dataset_id' => $dataset->id, 'code' => '010000', 'parent_code' => null, 'level' => 'province']);
    $this->assertDatabaseMissing('reference_regions', ['code' => '999999']);
    $this->assertDatabaseHas('audit_events', ['subject_id' => $dataset->id, 'action' => 'Menerbitkan referensi formulir']);
    $this->getJson(route('references.lookup', ['type' => 'district', 'parent' => '016200', 'q' => 'Kebon Jeruk']))
        ->assertOk()->assertJsonPath('data.0.code', '016202');
});

it('replaces one reference group while preserving the other groups and historical rows', function () {
    $c = registrationContext();
    $oldId = ReferenceDataset::query()->where('state', 'active')->sole()->id;
    $regions = $c['reference']['regions'];
    $regions[0]['name'] = 'Provinsi Baru';

    $this->actingAs($c['admin'])->post(route('admin.references.store'), [
        'version' => 'split-2', 'source' => 'Pembaruan wilayah', 'approved' => 1,
        'files' => ['regions' => referenceFile('regions', $regions)],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $newId = ReferenceDataset::query()->where('state', 'active')->sole()->id;
    $this->assertDatabaseHas('reference_regions', ['dataset_id' => $newId, 'name' => 'Provinsi Baru']);
    $this->assertDatabaseHas('reference_regions', ['dataset_id' => $oldId, 'name' => 'Provinsi Uji']);
    $this->assertDatabaseHas('reference_datasets', ['id' => $oldId, 'state' => 'retired']);
    $this->assertDatabaseHas('reference_prodi', ['dataset_id' => $newId, 'external_id' => 'prodi-uji', 'pt_external_id' => 'pt-uji']);
    $this->assertDatabaseHas('reference_options', ['dataset_id' => $newId, 'group' => 'gender', 'code' => 'uji']);
});

it('accepts a single file as the first import and adds programs in a later upload', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->post(route('admin.references.store'), [
        'files' => [referenceFile('pt', [['external_id' => 'pt', 'name' => 'PT Uji']])],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $first = ReferenceDataset::query()->where('state', 'active')->sole();
    expect($first->version)->toStartWith('impor-');
    expect($first->source)->toBe('pt.json');
    $this->assertDatabaseCount('reference_regions', 0);
    $this->post(route('admin.references.store'), [
        'files' => [referenceFile('prodi', [['external_id' => 'program', 'pt_external_id' => 'pt', 'name' => 'Program Uji']])],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $active = ReferenceDataset::query()->where('state', 'active')->sole();
    $this->assertDatabaseHas('reference_pt', ['dataset_id' => $active->id, 'external_id' => 'pt']);
    $this->assertDatabaseHas('reference_prodi', ['dataset_id' => $active->id, 'external_id' => 'program']);
    $this->assertDatabaseHas('reference_datasets', ['id' => $first->id, 'state' => 'retired']);
});

it('rejects a partial update that would orphan existing programs without retiring the active version', function () {
    $c = registrationContext();

    $this->actingAs($c['admin'])->post(route('admin.references.store'), [
        'version' => 'orphan', 'source' => 'Uji', 'approved' => 1,
        'files' => ['pt' => referenceFile('pt', [['external_id' => 'other-pt', 'name' => 'PT Baru']])],
    ])->assertInvalid(['dataset' => 'Setiap program studi harus memiliki perguruan tinggi'])
        ->assertInvalid(['dataset' => 'Kode PT yang belum tersedia: pt-uji']);

    expect(ReferenceDataset::query()->where('state', 'active')->sole()->version)->toBe('uji-1');
    $this->assertDatabaseCount('reference_datasets', 1);
});

it('rejects malformed separate files without changing active data', function (string $content, string $message) {
    $c = registrationContext();

    $this->actingAs($c['admin'])->post(route('admin.references.store'), [
        'version' => 'invalid', 'source' => 'Uji', 'approved' => 1,
        'files' => ['regions' => UploadedFile::fake()->createWithContent('wilayah.json', $content)],
    ])->assertInvalid(['files.regions' => $message]);

    expect(ReferenceDataset::query()->where('state', 'active')->sole()->version)->toBe('uji-1');
    $this->assertDatabaseCount('reference_datasets', 1);
})->with([
    ['{invalid', 'JSON yang sah'],
    ['{"pt":[]}', 'kunci regions'],
    ['{"error_code":1,"data":[]}', 'Respons GetWilayah tidak valid'],
]);

it('rejects broken regional parents after converting a GetWilayah response', function () {
    $c = registrationContext();
    $data = ['error_code' => 0, 'data' => [
        ['id_negara' => 'ID', 'id_level_wilayah' => 1, 'id_wilayah' => '01  ', 'nama_wilayah' => 'Provinsi', 'id_induk_wilayah' => '000000'],
        ['id_negara' => 'ID', 'id_level_wilayah' => 2, 'id_wilayah' => '0101 ', 'nama_wilayah' => 'Kota', 'id_induk_wilayah' => '01 '],
        ['id_negara' => 'ID', 'id_level_wilayah' => 3, 'id_wilayah' => '010101 ', 'nama_wilayah' => 'Kecamatan', 'id_induk_wilayah' => 'missing'],
    ]];

    $this->actingAs($c['admin'])->post(route('admin.references.store'), [
        'version' => 'broken-parent', 'source' => 'Uji', 'approved' => 1,
        'files' => ['regions' => UploadedFile::fake()->createWithContent('GetWilayah.json', json_encode($data))],
    ])->assertInvalid(['dataset' => 'Hubungan induk wilayah tidak sah']);

    $this->assertDatabaseCount('reference_datasets', 1);
});

it('keeps the combined JSON import available', function () {
    $c = registrationContext();
    $data = $c['reference'];
    $data['version'] = 'combined-2';

    $this->actingAs($c['admin'])->post(route('admin.references.store'), [
        'approved' => 1, 'file' => UploadedFile::fake()->createWithContent('gabungan.json', json_encode($data)),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(ReferenceDataset::query()->where('state', 'active')->sole()->version)->toBe('combined-2');
});

it('accepts a plain JSON list for a single group update', function () {
    $c = registrationContext();
    $rows = [['external_id' => 'prodi-baru', 'pt_external_id' => 'pt-uji', 'name' => 'Program Baru']];

    $this->actingAs($c['admin'])->post(route('admin.references.store'), [
        'version' => 'plain-list', 'source' => 'Uji', 'approved' => 1,
        'files' => ['prodi' => UploadedFile::fake()->createWithContent('prodi.json', json_encode($rows))],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $id = ReferenceDataset::query()->where('state', 'active')->sole()->id;
    $this->assertDatabaseHas('reference_prodi', ['dataset_id' => $id, 'external_id' => 'prodi-baru']);
    $this->assertDatabaseMissing('reference_prodi', ['dataset_id' => $id, 'external_id' => 'prodi-uji']);
});

it('rejects duplicate versions and duplicate region codes without changing active data', function () {
    $c = registrationContext();
    $payload = ['version' => 'uji-1', 'source' => 'Uji', 'approved' => 1, 'files' => ['regions' => referenceFile('regions', $c['reference']['regions'])]];
    $this->actingAs($c['admin'])->post(route('admin.references.store'), $payload)->assertInvalid(['version']);

    $regions = $c['reference']['regions'];
    $regions[] = $regions[0];
    $payload['version'] = 'duplicate-codes';
    $payload['files'] = ['regions' => referenceFile('regions', $regions)];
    $this->post(route('admin.references.store'), $payload)->assertInvalid(['regions.0.code']);

    expect(ReferenceDataset::query()->where('state', 'active')->sole()->version)->toBe('uji-1');
    $this->assertDatabaseCount('reference_datasets', 1);
});

it('requires only files and limits reference imports to administrators', function () {
    $admin = User::factory()->administrator()->create();
    $this->post(route('admin.references.store'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->operator()->create())->post(route('admin.references.store'))->assertForbidden();

    $this->actingAs($admin)->post(route('admin.references.store'), ['version' => 'empty', 'source' => 'Uji'])
        ->assertInvalid(['files']);

    $this->assertDatabaseCount('reference_datasets', 0);
    $this->get(route('admin.references'))->assertOk()->assertSee('name="files[]"', false)->assertSee('multiple', false)
        ->assertDontSee('reference-version')->assertDontSee('reference-source')->assertDontSee('name="file"', false)->assertSee('GetWilayah.json');
});

it('imports raw transport and residence responses together and retains other option groups', function () {
    $c = registrationContext();
    $files = [];
    foreach (['GetAlatTransportasi.json', 'GetJenisTinggal.json'] as $name) {
        $files[] = UploadedFile::fake()->createWithContent($name, file_get_contents(base_path('docs/'.$name)));
    }

    $this->actingAs($c['admin'])->post(route('admin.references.store'), ['files' => $files])->assertRedirect()->assertSessionHasNoErrors();

    $id = ReferenceDataset::query()->where('state', 'active')->sole()->id;
    $this->assertDatabaseHas('reference_options', ['dataset_id' => $id, 'group' => 'transport', 'code' => '13', 'label' => 'Sepeda motor']);
    $this->assertDatabaseHas('reference_options', ['dataset_id' => $id, 'group' => 'residence', 'code' => '3', 'label' => 'Kost']);
    $this->assertDatabaseHas('reference_options', ['dataset_id' => $id, 'group' => 'gender', 'code' => 'uji']);
    $this->assertDatabaseMissing('reference_options', ['dataset_id' => $id, 'group' => 'transport', 'code' => 'uji']);
    $this->assertDatabaseHas('reference_regions', ['dataset_id' => $id, 'code' => '01']);
});

it('allows an initial import of only one option group', function () {
    $admin = User::factory()->administrator()->create();

    $this->actingAs($admin)->post(route('admin.references.store'), [
        'files' => [referenceFile('options', [['group' => 'gender', 'code' => 'L', 'label' => 'Laki-laki']])],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('reference_options', ['group' => 'gender', 'code' => 'L']);
    $this->assertDatabaseCount('reference_datasets', 1);
});

it('rejects unknown and conflicting multi uploads atomically', function (string $content, string $message) {
    $c = registrationContext();

    $this->actingAs($c['admin'])->post(route('admin.references.store'), ['files' => [
        referenceFile('pt', $c['reference']['pt']),
        UploadedFile::fake()->createWithContent('lain.json', $content),
    ]])->assertInvalid(['files.1' => $message]);

    expect(ReferenceDataset::query()->where('state', 'active')->sole()->version)->toBe('uji-1');
    $this->assertDatabaseCount('reference_datasets', 1);
})->with([
    ['{"unknown":[]}', 'Format referensi tidak dikenali'],
    ['{"pt":[{"external_id":"lain","name":"PT Lain"}]}', 'Jenis referensi yang sama'],
    ['{"pt":[]}', 'tidak boleh kosong'],
]);
