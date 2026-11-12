<?php

namespace App\Support;

use App\Enums\AccountState;
use App\Enums\ApplicationStatus;
use App\Enums\ParticipantAccountStatus;
use App\Models\SkParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SkParticipantFilters
{
    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function validated(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $input[$key] = trim($value);
            }
        }

        $validated = Validator::make($input, [
            'search' => ['nullable', 'string', 'max:100'],
            'period_id' => ['nullable', 'integer', 'exists:admission_periods,id'],
            'operator_id' => ['nullable', 'string', 'regex:/^(?:unassigned|[1-9][0-9]*)$/D'],
            'origin_city' => ['nullable', 'string', 'max:64'],
            'account_status' => ['nullable', 'string', Rule::in(array_column(ParticipantAccountStatus::cases(), 'value'))],
            'queue_status' => ['nullable', 'string', Rule::in(['not_started', ...array_column(ApplicationStatus::cases(), 'value')])],
        ], [
            'account_status.in' => 'Status akun tidak dikenal. Pilih salah satu status akun yang tersedia atau reset filter.',
            'account_status.string' => 'Status akun harus berupa satu pilihan yang tersedia.',
            'queue_status.in' => 'Status antrean tidak dikenal. Pilih salah satu status antrean yang tersedia atau reset filter.',
            'queue_status.string' => 'Status antrean harus berupa satu pilihan yang tersedia.',
        ])->validate();

        return array_map(fn ($value) => $value ?? '', $validated);
    }

    /** @param Builder<SkParticipant> $query
     * @param  array<string, mixed>  $filters  Validated filter values.
     * @return Builder<SkParticipant>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $search = $filters['search'] ?? '';
        $operatorId = $filters['operator_id'] ?? '';
        $query->originCity($filters['origin_city'] ?? '')
            ->when(! empty($filters['period_id']), fn ($q) => $q->where('admission_period_id', (int) $filters['period_id']))
            ->when($operatorId === 'unassigned', fn ($q) => $q->whereNull('assigned_operator_id'))
            ->when(! empty($operatorId) && $operatorId !== 'unassigned', fn ($q) => $q->where('assigned_operator_id', (int) $operatorId))
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) => $sub->where('name_on_sk', 'like', '%'.$search.'%')->orWhere('official_participant_id', 'like', '%'.$search.'%')));

        match ($filters['account_status'] ?? '') {
            ParticipantAccountStatus::NoAccount->value => $query->whereNull('claimed_user_id'),
            ParticipantAccountStatus::PendingActivation->value => $query->whereHas('claimedUser', fn ($q) => $q->where('account_state', AccountState::PendingActivation)),
            ParticipantAccountStatus::Disabled->value => $query->whereHas('claimedUser', fn ($q) => $q->where('account_state', AccountState::Disabled)),
            ParticipantAccountStatus::Connected->value => $query->whereNotNull('claimed_user_id')
                ->whereDoesntHave('claimedUser', fn ($q) => $q->whereIn('account_state', [AccountState::PendingActivation, AccountState::Disabled])),
            default => null,
        };

        $queueStatus = $filters['queue_status'] ?? '';
        if ($queueStatus === 'not_started') {
            $query->whereDoesntHave('application');
        } elseif ($queueStatus !== '') {
            $query->whereHas('application', fn ($q) => $q->where('status', $queueStatus));
        }

        return $query;
    }
}
