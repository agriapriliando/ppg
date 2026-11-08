<?php

namespace App\Actions\Admissions;

use App\Actions\RecordAudit;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteUnlinkedApplicantAccount
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(User $actor, int $userId): void
    {
        abort_unless($actor->isAdministrator(), 403);
        DB::transaction(function () use ($actor, $userId): void {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            if ($user->role !== UserRole::Applicant) {
                throw ValidationException::withMessages(['deletion' => 'Akun yang terhubung bukan akun Pendaftar. Seluruh penghapusan dibatalkan.']);
            }

            // Preserve data outside the participant being deleted, including other periods.
            $references = [
                'sk_participants' => ['claimed_user_id', 'assigned_operator_id'],
                'applications' => ['user_id', 'accepted_by'],
                'applicant_workspaces' => ['user_id'],
                'applicant_documents' => ['owner_user_id', 'uploaded_by'],
                'sk_claims' => ['requester_user_id', 'review_owner_id', 'reviewed_by'],
                'application_ownership_transfers' => ['old_user_id', 'new_user_id', 'actor_id'],
                'application_reviews' => ['reviewer_id'],
                'application_submissions' => ['submitted_by'],
                'administrative_cases' => ['opened_by', 'resolved_by'],
                'admission_config_versions' => ['created_by', 'approved_by'],
                'application_settings' => ['updated_by'],
                'assignment_events' => ['old_operator_id', 'new_operator_id', 'actor_id'],
                'consent_texts' => ['created_by'],
                'document_policy_versions' => ['created_by'],
                'export_runs' => ['requested_by'],
                'period_documents' => ['uploaded_by'],
                'period_operators' => ['user_id'],
                'reference_datasets' => ['created_by'],
                'sk_imports' => ['uploaded_by'],
                'whatsapp_verifications' => ['verified_by'],
            ];
            foreach ($references as $table => $columns) {
                if (DB::table($table)->where(function ($query) use ($columns, $userId): void {
                    foreach ($columns as $column) {
                        $query->orWhere($column, $userId);
                    }
                })->exists()) {
                    throw ValidationException::withMessages(['deletion' => 'Akun '.$user->name.' masih terhubung dengan kepesertaan, pendaftaran, permohonan, atau data lain. Seluruh penghapusan dibatalkan.']);
                }
            }

            DB::table('credential_issuances')->where('user_id', $userId)->delete();
            if (DB::table('credential_issuances')->where(fn ($query) => $query->where('issued_by', $userId)->orWhere('delivered_by', $userId))->exists()) {
                throw ValidationException::withMessages(['deletion' => 'Akun masih terhubung dengan penerbitan akses peserta lain. Seluruh penghapusan dibatalkan.']);
            }
            DB::table('consent_records')->where('user_id', $userId)->delete();
            DB::table('claim_responses')->where('user_id', $userId)->delete();
            DB::table('social_accounts')->where('user_id', $userId)->delete();
            DB::table('applicant_upload_batches')->where('user_id', $userId)->delete();
            DB::table('sessions')->where('user_id', $userId)->delete();
            DB::table('document_deletion_tasks')->where('requested_by', $userId)->update(['requested_by' => null]);
            if ($user->email !== null) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            }
            // Historical audit entries retain their role snapshot without a dangling user FK.
            DB::table('audit_events')->where('actor_id', $userId)->update(['actor_id' => null]);
            $this->audit->handle($actor, 'Menghapus permanen akun peserta', $user, ['login_id' => $user->login_id, 'name' => $user->name]);
            $user->delete();
        });
    }
}
