<?php

namespace App\Livewire\Documents;

use App\Actions\Documents\DeletePeriodDocument;
use App\Actions\Documents\RenamePeriodDocument;
use App\Actions\Documents\UploadPeriodDocument;
use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\DocumentPolicyVersion;
use App\Models\PeriodDocument;
use App\Models\User;
use App\Policies\PeriodDocumentPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class PeriodDocumentManager extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $periodId;

    #[Locked]
    public ?string $uploadRequestId = null;

    #[Locked]
    public ?string $selectedId = null;

    #[Locked]
    public int $version = 0;

    #[Locked]
    public string $extension = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploadedFiles = [];

    public string $dialog = '';

    public string $name = '';

    public bool $deleteConfirmed = false;

    public string $message = '';

    /** @var list<string> */
    public array $uploadErrors = [];

    private UploadPeriodDocument $uploader;

    public function boot(UploadPeriodDocument $uploader): void
    {
        $this->uploader = $uploader;
    }

    public function mount(int $periodId): void
    {
        $this->periodId = $periodId;
        $this->period();
    }

    private function period(): AdmissionPeriod
    {
        Gate::authorize('viewAny', AdmissionPeriod::class);

        return AdmissionPeriod::findOrFail($this->periodId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user !== null, 403);

        return $user;
    }

    public function beginUpload(PeriodDocumentPolicy $access): void
    {
        abort_unless($access->upload($this->actor(), $this->period()), 403);
        $this->uploadRequestId = (string) Str::uuid();
        $this->resetValidation();
        $this->uploadErrors = [];
        $this->message = '';
    }

    public function updatedUploadedFiles(): void
    {
        $this->upload();
    }

    public function upload(): void
    {
        $period = $this->period();
        abort_unless($this->uploadRequestId !== null, 422, 'Pilih ulang berkas untuk memulai unggahan.');
        $this->validate(['uploadedFiles' => ['required', 'array', 'min:1']]);
        $this->uploadErrors = [];
        $saved = 0;
        foreach ($this->uploadedFiles as $file) {
            try {
                $this->uploader->handle($this->actor(), $period, $file, hash('sha256', $this->uploadRequestId.':'.$file->getFilename()));
                $saved++;
            } catch (ValidationException $exception) {
                $this->uploadErrors[] = $file->getClientOriginalName().': '.implode(' ', array_merge(...array_values($exception->errors())));
            } catch (Throwable $exception) {
                report($exception);
                $this->uploadErrors[] = $file->getClientOriginalName().': Belum dapat diunggah. Pilih ulang file ini dan coba kembali.';
            } finally {
                try {
                    $file->delete();
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
        }
        $this->reset('uploadedFiles', 'uploadRequestId');
        $this->message = $saved > 0 ? $saved.' berkas periode berhasil diunggah.' : '';
        $this->dispatch('period-documents-updated');
    }

    private function document(string $id): PeriodDocument
    {
        $this->period();

        return PeriodDocument::query()->where('admission_period_id', $this->periodId)->findOrFail($id);
    }

    public function openRename(string $id): void
    {
        $doc = $this->document($id);
        Gate::authorize('update', $doc);
        $this->select($doc, 'rename');
    }

    public function confirmDelete(string $id): void
    {
        $doc = $this->document($id);
        Gate::authorize('delete', $doc);
        $this->select($doc, 'delete');
    }

    private function select(PeriodDocument $doc, string $dialog): void
    {
        $this->selectedId = $doc->id;
        $this->version = $doc->lock_version;
        $this->extension = $doc->extension;
        $this->name = $doc->display_name;
        $this->deleteConfirmed = false;
        $this->dialog = $dialog;
        $this->resetValidation();
    }

    public function saveRename(RenamePeriodDocument $rename): void
    {
        abort_unless($this->dialog === 'rename' && $this->selectedId !== null, 422);
        $rename->handle($this->actor(), $this->document($this->selectedId), $this->name, $this->version);
        $this->dialog = '';
        $this->message = 'Nama berkas periode berhasil diperbarui.';
    }

    public function executeDelete(DeletePeriodDocument $delete): void
    {
        abort_unless($this->dialog === 'delete' && $this->selectedId !== null, 422);
        $this->validate(['deleteConfirmed' => ['accepted']], ['deleteConfirmed.accepted' => 'Centang konfirmasi penghapusan terlebih dahulu.']);
        $done = $delete->handle($this->actor(), $this->document($this->selectedId), $this->version);
        $this->dialog = '';
        $this->message = $done ? 'Berkas periode telah dihapus permanen.' : 'Penghapusan file belum selesai. Sistem akan mencoba ulang otomatis; Anda juga dapat memilih Coba Ulang Hapus.';
        $this->dispatch('period-documents-updated');
    }

    public function render(): View
    {
        $period = $this->period();

        return view('livewire.documents.period-document-manager', [
            'period' => $period, 'policy' => DocumentPolicyVersion::resolveForPeriod($period),
            'canModify' => $period->state !== PeriodState::Archived,
            'documents' => $period->periodDocuments()->with('uploader')->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }
}
