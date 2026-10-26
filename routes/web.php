<?php

use App\Http\Controllers\Admin\AdministrativeCaseController;
use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\BulkAssignmentController;
use App\Http\Controllers\Admin\ConfigurationHistoryController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\OwnershipTransferController;
use App\Http\Controllers\Admin\ReferenceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SkImportController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\WhatsappRecoveryController;
use App\Http\Controllers\ApplicantFormController;
use App\Http\Controllers\ApplicationReviewController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\ClaimFollowupController;
use App\Http\Controllers\ClaimsController;
use App\Http\Controllers\CredentialController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParticipantsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPortalController;
use App\Http\Controllers\ReferenceLookupController;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicPortalController::class)->name('home');
Route::view('/panduan', 'public.guide')->name('guide');
Route::get('/klaim-whatsapp', [ClaimsController::class, 'create'])->name('public.claim');
Route::post('/klaim-whatsapp', [ClaimsController::class, 'store'])->middleware('throttle:public-claims')->name('public.claim.store');
Route::redirect('/pemulihan', '/panduan')->name('recovery');

Route::middleware('guest')->group(function (): void {
    Route::get('/masuk', [SessionController::class, 'create'])->name('login');
    Route::post('/masuk', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('google.redirect');
});
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:30,1')->name('google.callback');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/keluar', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/ruang-kerja', DashboardController::class)->name('dashboard');
    Route::view('/akun', 'portal.account')->name('account');
    Route::put('/akun', [ProfileController::class, 'update'])->name('account.update');
    Route::post('/akun/google', [GoogleController::class, 'link'])->middleware('throttle:5,1')->name('google.link');
    Route::get('/akun/keamanan', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/akun/keamanan', [PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
    Route::get('/referensi/pilihan', ReferenceLookupController::class)->middleware('throttle:120,1')->name('references.lookup');
    Route::middleware('role:operator,administrator')->group(function (): void {
        Route::get('/riwayat', AuditController::class)->name('activity');
        Route::get('/peserta-sk', [ParticipantsController::class, 'index'])->name('participants.index');
        Route::get('/peserta-sk/{participant}', [ParticipantsController::class, 'show'])->name('participants.show');
        Route::get('/klaim', [ClaimsController::class, 'index'])->name('claims.index');
        Route::post('/klaim/{claim}/delegasi', [ClaimFollowupController::class, 'delegate'])->name('claims.delegate');
        Route::get('/klaim/{claim}', [ClaimsController::class, 'show'])->name('claims.show');
        Route::post('/klaim/{claim}/setujui', [ClaimsController::class, 'approve'])->name('claims.approve');
        Route::post('/klaim/{claim}/kandidat', [ClaimsController::class, 'candidate'])->name('claims.candidate');
        Route::post('/klaim/{claim}/catatan', [ClaimsController::class, 'note'])->name('claims.note');
        Route::post('/klaim/{claim}/whatsapp', [ClaimsController::class, 'verifyWhatsapp'])->name('claims.verify-whatsapp');
        Route::post('/klaim/{claim}/kredensial', [CredentialController::class, 'issue'])->name('credentials.issue');
        Route::post('/kredensial/{issuance}/penyerahan', [CredentialController::class, 'deliver'])->name('credentials.deliver');
        Route::get('/antrean', [ApplicationReviewController::class, 'index'])->name('reviews.index');
        Route::get('/pendaftaran/{application}', [ApplicationReviewController::class, 'show'])->name('reviews.show');
        Route::post('/pendaftaran/{application}', [ApplicationReviewController::class, 'update'])->name('reviews.update');
    });
    Route::get('/pendaftar/kepesertaan', [ClaimsController::class, 'own'])->middleware('role:pendaftar_ppg')->name('applicant.claims');
    Route::middleware('role:pendaftar_ppg')->prefix('pendaftar')->name('applicant.')->group(function (): void {
        Route::post('/klaim/{claim}/tanggapan', [ClaimFollowupController::class, 'respond'])->middleware('throttle:10,1')->name('claims.respond');
        Route::post('/klaim/{claim}/batalkan', [ClaimFollowupController::class, 'cancel'])->name('claims.cancel');
        Route::get('/pendaftaran', [ApplicantFormController::class, 'index'])->name('applications');
        Route::get('/formulir/{period}', [ApplicantFormController::class, 'edit'])->name('form.edit');
        Route::post('/formulir/{period}', [ApplicantFormController::class, 'update'])->name('form.update');
        Route::get('/status/{period}', [ApplicantFormController::class, 'status'])->name('application.status');
        Route::get('/bukti/{period}', [ApplicantFormController::class, 'proof'])->name('application.proof');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:administrator')->group(function (): void {
        Route::livewire('/periode', 'pages::admin.periods')->name('periods');
        Route::get('/riwayat-konfigurasi', [ConfigurationHistoryController::class, 'index'])->name('configuration-history');
        Route::get('/pendaftaran/{application}/koreksi-pemilik', [OwnershipTransferController::class, 'edit'])->name('ownership.edit');
        Route::post('/pendaftaran/{application}/koreksi-pemilik', [OwnershipTransferController::class, 'update'])->middleware('throttle:5,1')->name('ownership.update');
        Route::get('/klaim/{claim}/pemulihan-nomor', [WhatsappRecoveryController::class, 'edit'])->name('whatsapp-recovery.edit');
        Route::post('/klaim/{claim}/pemulihan-nomor', [WhatsappRecoveryController::class, 'update'])->middleware('throttle:5,1')->name('whatsapp-recovery.update');
        Route::get('/pengguna', [StaffController::class, 'index'])->name('users');
        Route::post('/pengguna', [StaffController::class, 'store'])->name('users.store');
        Route::patch('/pengguna/{user}/status', [StaffController::class, 'toggle'])->name('users.toggle');
        Route::get('/pengaturan', [SettingsController::class, 'edit'])->name('settings');
        Route::put('/pengaturan', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/audit', AuditController::class)->name('audit');
        Route::post('/peserta/{participant}/kondisi', [ParticipantsController::class, 'condition'])->name('participants.condition');
        Route::get('/ekspor', [ExportController::class, 'index'])->name('exports');
        Route::post('/ekspor', [ExportController::class, 'store'])->name('exports.store');
        Route::get('/ekspor/{export}/unduh', [ExportController::class, 'download'])->name('exports.download');
        Route::get('/kasus', [AdministrativeCaseController::class, 'index'])->name('cases');
        Route::post('/kasus/{case}/selesai', [AdministrativeCaseController::class, 'resolve'])->name('cases.resolve');
        Route::get('/impor', [SkImportController::class, 'index'])->name('imports');
        Route::post('/impor', [SkImportController::class, 'store'])->name('imports.store');
        Route::get('/template-sk', [SkImportController::class, 'template'])->name('imports.template');
        Route::get('/impor/{import}', [SkImportController::class, 'show'])->name('imports.show');
        Route::get('/impor/{import}/kesalahan', [SkImportController::class, 'errors'])->name('imports.errors');
        Route::post('/impor/{import}/konfirmasi', [SkImportController::class, 'commit'])->name('imports.commit');
        Route::get('/penugasan', [AssignmentController::class, 'index'])->name('assignments');
        Route::get('/penugasan-massal', [BulkAssignmentController::class, 'index'])->name('assignments.bulk');
        Route::post('/penugasan-massal', [BulkAssignmentController::class, 'store'])->name('assignments.bulk.store');
        Route::post('/penugasan', [AssignmentController::class, 'store'])->name('assignments.store');
        Route::post('/penugasan/{participant}/pindah', [AssignmentController::class, 'transfer'])->name('assignments.transfer');
        Route::get('/referensi', [ReferenceController::class, 'index'])->name('references');
        Route::post('/referensi', [ReferenceController::class, 'store'])->name('references.store');
        Route::post('/referensi/pernyataan', [ReferenceController::class, 'consent'])->name('references.consent');
    });
});

Route::middleware('auth')->group(function (): void {
    Route::get('/aktivasi-sandi', [ActivationController::class, 'edit'])->name('activation.edit');
    Route::post('/aktivasi-sandi', [ActivationController::class, 'update'])->middleware('throttle:10,1')->name('activation.update');
});
