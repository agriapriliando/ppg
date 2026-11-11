<?php

// Local browser smoke setup/cleanup. Never run against production.
use App\Models\AdmissionPeriod;
use App\Models\ApplicantWorkspace;
use App\Models\DocumentPolicyVersion;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abort_if(app()->isProduction(), 403);
$contextPath = $_SERVER['PPG_UPLOAD_SMOKE_CONTEXT'] ?? throw new RuntimeException('Missing test context path.');
if (($_SERVER['PPG_UPLOAD_SMOKE_CLEANUP'] ?? '') === '1') {
    $data = json_decode(file_get_contents($contextPath), true, flags: JSON_THROW_ON_ERROR);
    $period = AdmissionPeriod::findOrFail($data['period_id']);
    $user = User::findOrFail($data['user_id']);
    if ($period->code !== $data['tag'] || $user->email !== strtolower($data['tag']).'@example.invalid' || ! str_starts_with($data['tag'], 'UPLOAD-QA-')) {
        throw new RuntimeException('Unexpected test data; cleanup refused.');
    }
    ApplicantWorkspace::query()->whereKey($data['workspace_id'])->where('admission_period_id', $period->id)->where('user_id', $data['owner_id'])->firstOrFail();
    $owner = User::findOrFail($data['owner_id']);
    if ($owner->id !== $user->id && $owner->email !== strtolower($data['tag']).'-owner@example.invalid') {
        throw new RuntimeException('Unexpected workspace owner; cleanup refused.');
    }
    foreach (DB::table('period_documents')->where('admission_period_id', $data['period_id'])->get() as $document) {
        if (! str_starts_with($document->object_key, 'period-documents/'.$data['period_id'].'/')) {
            throw new RuntimeException('Unexpected period test file; cleanup refused.');
        }
        Storage::disk($document->disk)->delete($document->object_key);
    }
    foreach (DB::table('applicant_documents')->where('workspace_id', $data['workspace_id'])->get() as $document) {
        if (! str_starts_with($document->object_key, 'applicant-documents/'.$data['workspace_id'].'/')) {
            throw new RuntimeException('Unexpected test file; cleanup refused.');
        }
        Storage::disk($document->disk)->delete($document->object_key);
    }
    DB::transaction(function () use ($data): void {
        DB::table('period_documents')->where('admission_period_id', $data['period_id'])->delete();
        DB::table('applicant_upload_batches')->where('workspace_id', $data['workspace_id'])->delete();
        DB::table('applicant_documents')->where('workspace_id', $data['workspace_id'])->delete();
        DB::table('applicant_workspaces')->where('id', $data['workspace_id'])->where('user_id', $data['owner_id'])->delete();
        DB::table('document_policy_versions')->where('admission_period_id', $data['period_id'])->delete();
        DB::table('admission_periods')->where('id', $data['period_id'])->where('code', $data['tag'])->delete();
        DB::table('audit_events')->where('actor_id', $data['user_id'])->delete();
        DB::table('sessions')->where('user_id', $data['user_id'])->delete();
        if ($data['owner_id'] !== $data['user_id']) {
            DB::table('users')->where('id', $data['owner_id'])->where('email', strtolower($data['tag']).'-owner@example.invalid')->delete();
        }
        DB::table('users')->where('id', $data['user_id'])->delete();
    });
    echo "Local upload test data cleaned.\n";

    return;
}
if (is_file($contextPath)) {
    throw new RuntimeException('Refusing to overwrite an existing test context.');
}
$tag = 'UPLOAD-QA-'.Str::lower(Str::random(12));
[$user, $period, $workspace, $owner] = DB::transaction(function () use ($tag): array {
    $workflow = $_SERVER['PPG_UPLOAD_SMOKE_WORKFLOW'] ?? 'applicant';
    $factory = match ($workflow) {
        'staff' => User::factory()->administrator(),
        'period' => User::factory()->operator(),
        default => User::factory(),
    };
    $user = $factory->create(['name' => 'QA Unggah Berkas', 'email' => strtolower($tag).'@example.invalid', 'phone_number' => '6281234567890']);
    $period = AdmissionPeriod::factory()->published()->create(['code' => $tag, 'name' => 'QA Validasi Unggahan', 'is_public' => false]);
    DocumentPolicyVersion::create(['admission_period_id' => $period->id, 'version' => 1, 'allowed_extensions' => ['pdf', 'mp4', 'mov', 'webm'], 'max_file_size_bytes' => 100 * 1024, 'workspace_quota_bytes' => 1024 * 1024, 'max_video_files' => 3]);
    $owner = $workflow === 'applicant' ? $user : User::factory()->create(['email' => strtolower($tag).'-owner@example.invalid', 'phone_number' => '6281234567891']);
    $workspace = ApplicantWorkspace::create(['user_id' => $owner->id, 'admission_period_id' => $period->id]);

    return [$user, $period, $workspace, $owner];
});
Auth::login($user);
session()->save();
$cookieName = config('session.cookie');
$cookie = Crypt::encryptString(CookieValuePrefix::create($cookieName, app('encrypter')->getKey()).session()->getId());
file_put_contents($contextPath, json_encode(['tag' => $tag, 'user_id' => $user->id, 'owner_id' => $owner->id, 'period_id' => $period->id, 'workspace_id' => $workspace->id, 'cookie_name' => $cookieName, 'cookie_value' => $cookie], JSON_THROW_ON_ERROR));
echo "Local upload browser test prepared.\n";
