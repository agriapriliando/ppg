<?php

namespace App\Http\Controllers;

use App\Actions\Admissions\SaveApplicantDraftPatch;
use App\Models\AdmissionPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicantDraftController extends Controller
{
    public function __invoke(Request $request, AdmissionPeriod $period, SaveApplicantDraftPatch $save): JsonResponse
    {
        abort_unless(config('pmb.autosave_enabled'), 503, 'Penyimpanan otomatis sedang dinonaktifkan. Muat ulang untuk memakai Simpan Draf.');
        $data = $request->validate(['version' => ['required', 'integer', 'min:0'], 'batch_id' => ['required', 'uuid'], 'changes' => ['required', 'array', 'max:100']]);
        abort_if(strlen($request->getContent()) > 70000, 413);
        $workspace = $save->handle($request->user(), $period->id, (int) $data['version'], $data['batch_id'], $data['changes']);

        return response()->json(['batch_id' => $data['batch_id'], 'version' => $workspace->lock_version, 'saved_at' => $workspace->saved_at?->timezone('Asia/Jakarta')->format('H:i:s')], headers: ['Cache-Control' => 'private, no-store']);
    }
}
