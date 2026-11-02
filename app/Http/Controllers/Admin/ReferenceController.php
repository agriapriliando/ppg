<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admissions\ImportReferenceFiles;
use App\Actions\Admissions\PublishReferenceDataset;
use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use App\Models\ConsentText;
use App\Models\ReferenceDataset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReferenceController extends Controller
{
    public function index(): View
    {
        return view('admin.references', ['datasets' => ReferenceDataset::query()->orderByDesc('id')->paginate(10), 'consents' => ConsentText::query()->orderByDesc('id')->get()]);
    }

    public function store(Request $request, PublishReferenceDataset $publish, ImportReferenceFiles $import): RedirectResponse
    {
        if (! $request->hasFile('file')) {
            $data = $request->validate([
                'version' => ['sometimes', 'string', 'max:64'],
                'source' => ['sometimes', 'string', 'max:255'],
                'files' => ['required', 'array', 'min:1', 'max:10'],
                'files.*' => ['file', 'extensions:json', 'max:10240'],
            ]);
            $actor = $request->user();
            abort_unless($actor !== null, 403);
            $files = [];
            foreach ($data['files'] as $key => $uploaded) {
                if ($uploaded instanceof UploadedFile) {
                    $files[$key] = $uploaded;
                }
            }
            $source = Str::limit(implode(', ', array_map(fn (UploadedFile $file) => $file->getClientOriginalName(), $files)), 255, '');
            $import->handle($actor, $data['version'] ?? 'impor-'.Str::uuid(), $data['source'] ?? $source, $files);

            return back()->with('status', 'Referensi berhasil diterbitkan. Jenis yang tidak diunggah dipertahankan dari dataset aktif sebelumnya.');
        }
        $request->validate(['file' => ['required', 'file', 'extensions:json', 'max:10240'], 'files' => ['prohibited'], 'approved' => ['accepted']]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        try {
            $data = json_decode($file->getContent(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['file' => 'Berkas referensi harus berupa JSON yang sah.']);
        }
        if (! is_array($data)) {
            throw ValidationException::withMessages(['file' => 'Struktur dataset harus berupa objek JSON.']);
        }
        $actor = $request->user();
        abort_unless($actor !== null, 403);
        $publish->handle($actor, $data);

        return back()->with('status', 'Referensi berhasil diterbitkan. Dataset lama tetap tersimpan untuk riwayat.');
    }

    public function consent(Request $request, RecordAudit $audit): RedirectResponse
    {
        $data = $request->validate(['purpose' => ['required', Rule::in(['claim', 'submission'])], 'version' => ['required', 'string', 'max:40'], 'body' => ['required', 'string', 'min:20', 'max:5000'], 'approved' => ['accepted']]);
        if (ConsentText::query()->where('purpose', $data['purpose'])->where('version', $data['version'])->exists()) {
            throw ValidationException::withMessages(['version' => 'Versi pernyataan sudah digunakan. Terbitkan versi baru untuk perubahan.']);
        }
        $consent = ConsentText::create(['purpose' => $data['purpose'], 'version' => $data['version'], 'body' => $data['body'], 'published_at' => now(), 'created_by' => $request->user()?->id]);
        $audit->handle($request->user(), 'Menerbitkan naskah pernyataan', $consent, ['purpose' => $consent->purpose, 'version' => $consent->version]);

        return back()->with('status', 'Pernyataan diterbitkan sebagai versi baru. Versi lama tetap utuh.');
    }
}
