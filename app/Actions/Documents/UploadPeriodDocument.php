<?php

namespace App\Actions\Documents;

use App\Actions\RecordAudit;
use App\Models\AdmissionPeriod;
use App\Models\DocumentPolicyVersion;
use App\Models\PeriodDocument;
use App\Models\User;
use App\Policies\PeriodDocumentPolicy;
use App\Support\DocumentUploadRules;
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

    /** @param array<string, mixed> $clientMetadata */
    public function handle(User $actor, AdmissionPeriod $period, UploadedFile $file, string $requestKey, ?int $expectedPolicyId = null, array $clientMetadata = []): PeriodDocument
    {
        abort_unless($this->access->upload($actor, $period), 403);
        Validator::make(['file' => $file, 'request_key' => $requestKey], ['file' => ['required', 'file'], 'request_key' => ['required', 'string', 'max:100']])->validate();
        $existing = PeriodDocument::query()->where('admission_period_id', $period->id)->where('uploaded_by', $actor->id)->where('request_key', $requestKey)->first();
        if ($existing !== null) {
            if (! $existing->isAvailable()) {
                throw ValidationException::withMessages(['upload' => 'Berkas dari unggahan ini sudah dihapus. Pilih ulang berkas untuk unggahan baru.']);
            }

            return $existing;
        }
        $id = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();
        $size = (int) $file->getSize();
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $period, $file, $requestKey, $id, $extension, $mime, $size, &$path, $expectedPolicyId, $clientMetadata): PeriodDocument {
                $locked = AdmissionPeriod::query()->lockForUpdate()->findOrFail($period->id);
                abort_unless($this->access->upload($actor, $locked), 403);
                $existing = PeriodDocument::query()->where('admission_period_id', $locked->id)->where('uploaded_by', $actor->id)->where('request_key', $requestKey)->first();
                if ($existing !== null) {
                    if (! $existing->isAvailable()) {
                        throw ValidationException::withMessages(['upload' => 'Berkas dari unggahan ini sudah dihapus. Pilih ulang berkas untuk unggahan baru.']);
                    }

                    return $existing;
                }
                $policy = DocumentPolicyVersion::resolveForPeriod($locked);
                DocumentUploadRules::validate($policy, $extension, $mime, $size, $expectedPolicyId);
                $metadata = DocumentUploadRules::isVideo($extension) ? DocumentUploadRules::metadata($clientMetadata, $policy->id) : null;
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
                    'object_key' => $path, 'status' => PeriodDocument::STATUS_AVAILABLE, 'lock_version' => 0, 'request_key' => $requestKey, 'client_media_metadata' => $metadata, 'sha256' => hash_file('sha256', $file->getRealPath()) ?: null]);
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
