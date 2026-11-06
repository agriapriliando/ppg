<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\DocumentPolicyVersion;
use App\Models\PeriodDocument;
use App\Models\User;
use App\Policies\PeriodDocumentPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UploadPeriodDocument
{
    public function __construct(private PeriodDocumentPolicy $access, private RecordAudit $audit) {}

    public function handle(User $actor, AdmissionPeriod $period, UploadedFile $file, string $requestKey): PeriodDocument
    {
        abort_unless($this->access->upload($actor, $period), 403);
        Validator::make(['file' => $file, 'request_key' => $requestKey], ['file' => ['required', 'file'], 'request_key' => ['required', 'string', 'max:100']])->validate();
        $existing = PeriodDocument::query()->where('admission_period_id', $period->id)->where('uploaded_by', $actor->id)->where('request_key', $requestKey)->first();
        if ($existing !== null) {
            return $existing;
        }
        $id = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $period, $file, $requestKey, $id, $extension, $mime, $size, &$path): PeriodDocument {
                $locked = AdmissionPeriod::query()->lockForUpdate()->findOrFail($period->id);
                abort_unless($this->access->upload($actor, $locked), 403);
                $existing = PeriodDocument::query()->where('admission_period_id', $locked->id)->where('uploaded_by', $actor->id)->where('request_key', $requestKey)->first();
                if ($existing !== null) {
                    return $existing;
                }
                $policy = DocumentPolicyVersion::resolveForPeriod($locked);
                if ($size < 1 || $size > $policy->max_file_size_bytes) {
                    throw ValidationException::withMessages(['file' => 'File kosong atau melebihi batas '.$policy->formattedMaxFileSize().'.']);
                }
                if (! in_array($extension, $policy->allowedExtensions(), true) || ! in_array($mime, $policy->allowedMimeTypes(), true)) {
                    throw ValidationException::withMessages(['file' => 'Format atau isi file tidak sesuai aturan berkas periode. Gunakan '.$policy->allowedExtensionLabels().'.']);
                }
                // Validate MIME against the selected extension as well as the overall allowlist.
                if (! in_array($mime, config('document_policy.supported_types.'.$extension.'.mime_types', []), true)) {
                    throw ValidationException::withMessages(['file' => 'Isi file tidak cocok dengan ekstensinya.']);
                }
                $path = Storage::disk('local')->putFileAs('period-documents/'.$locked->id, $file, $id.'.'.$extension);
                if (! is_string($path)) {
                    throw new \RuntimeException('Berkas belum dapat disimpan. Coba unggah kembali.');
                }
                $base = Str::limit(Str::squish(preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))), 180, '');
                $document = new PeriodDocument;
                $document->id = $id;
                $document->fill(['admission_period_id' => $locked->id, 'uploaded_by' => $actor->id,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'display_name' => $base ?: 'dokumen',
                    'extension' => $extension, 'mime_type' => $mime, 'byte_size' => $size, 'disk' => 'local',
                    'object_key' => $path, 'status' => PeriodDocument::STATUS_AVAILABLE, 'lock_version' => 0, 'request_key' => $requestKey]);
                $document->save();
                $this->audit->handle($actor, 'Mengunggah berkas periode', $document, ['period_id' => $locked->id]);

                return $document;
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
