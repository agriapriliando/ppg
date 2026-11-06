<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\SkParticipant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicantSkInformationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null && $user->role === UserRole::Applicant, 403);
        $data = $request->validate(['period_id' => ['nullable', 'integer', 'min:1']]);
        $participants = SkParticipant::query()->where('claimed_user_id', $user->id)->with(['period', 'assignedOperator'])->orderByDesc('admission_period_id')->get();
        $participant = isset($data['period_id']) ? $participants->firstWhere('admission_period_id', (int) $data['period_id']) : $participants->first();
        abort_if(isset($data['period_id']) && $participant === null, 404);
        $period = $participant?->period;

        return response()->view('portal.sk-information', [
            'participants' => $participants, 'participant' => $participant, 'period' => $period,
            'documents' => $period?->periodDocuments()->sharedWithParticipants()->orderBy('display_name')->orderBy('id')->get() ?? collect(),
        ], headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'same-origin']);
    }
}
