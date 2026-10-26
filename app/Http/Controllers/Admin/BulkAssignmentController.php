<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\ApplyBulkAssignment;
use App\Actions\Assignments\PlanBulkAssignment;
use App\Enums\AccountState;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AdmissionPeriod;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BulkAssignmentController extends Controller
{
    public function index(Request $request, PlanBulkAssignment $planner): View
    {
        $data = $request->validate(['period_id' => ['nullable', 'integer', 'exists:admission_periods,id'], 'mode' => ['nullable', Rule::in(['rebalance', 'transfer'])], 'operators' => ['nullable', 'array', 'max:100'], 'operators.*' => ['integer', 'distinct'], 'source_id' => ['nullable', 'integer'], 'page' => ['nullable', 'integer', 'min:1']]);
        $actor = $request->user();
        abort_unless($actor?->isAdministrator(), 403);
        $period = $request->filled('period_id') ? AdmissionPeriod::query()->findOrFail($request->integer('period_id')) : null;
        $ids = array_map(intval(...), $data['operators'] ?? []);
        sort($ids);
        $mode = $data['mode'] ?? 'rebalance';
        $sourceId = $request->filled('source_id') ? $request->integer('source_id') : null;
        $plan = $period !== null && $ids !== [] ? $planner->handle($actor, $period, $mode, $ids, $sourceId) : null;
        $token = $plan !== null ? Crypt::encryptString(json_encode(['actor_id' => $actor->id, 'period_id' => $period->id, 'version' => $period->lock_version, 'mode' => $mode, 'ids' => $ids, 'source_id' => $sourceId, 'hash' => $plan['hash'], 'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR)) : null;
        $page = $request->integer('page', 1);
        $changes = new LengthAwarePaginator(array_slice($plan['changes'] ?? [], ($page - 1) * 50, 50), count($plan['changes'] ?? []), 50, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.bulk-assignments', ['periods' => AdmissionPeriod::query()->where('state', '!=', 'archived')->orderByDesc('id')->get(), 'period' => $period, 'operators' => User::query()->where('role', UserRole::Operator)->orderBy('name')->get()->keyBy('id'), 'activeOperators' => User::query()->where('role', UserRole::Operator)->where('account_state', AccountState::Active)->where('must_change_password', false)->orderBy('name')->get(), 'selected' => $ids, 'mode' => $mode, 'sourceId' => $sourceId, 'plan' => $plan, 'token' => $token, 'changes' => $changes]);
    }

    public function store(Request $request, ApplyBulkAssignment $apply): RedirectResponse
    {
        $data = $request->validate(['preview_token' => ['required', 'string'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'confirmed' => ['accepted']]);
        try {
            $preview = json_decode(Crypt::decryptString($data['preview_token']), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['assignment' => 'Pratinjau tidak sah. Buat pratinjau baru.']);
        }
        $actor = $request->user();
        abort_unless($actor?->isAdministrator(), 403);
        if (! is_array($preview) || ($preview['actor_id'] ?? null) !== $actor->id || ($preview['expires'] ?? 0) <= now()->timestamp) {
            throw ValidationException::withMessages(['assignment' => 'Pratinjau kedaluwarsa atau bukan milik akun ini. Buat pratinjau baru.']);
        }
        $count = $apply->handle($actor, $preview['period_id'], $preview['version'], $preview['mode'], $preview['ids'], $preview['source_id'], $preview['hash'], $data['reason']);

        return redirect()->route('admin.assignments', ['period_id' => $preview['period_id']])->with('status', $count.' penugasan diubah sesuai pratinjau.');
    }
}
