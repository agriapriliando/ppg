<?php

namespace App\Http\Controllers;

use App\Support\PublicSkParticipantStatistics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSkParticipantStatisticsController extends Controller
{
    public function __invoke(Request $request, PublicSkParticipantStatistics $statistics): JsonResponse
    {
        $value = $request->query('period_id');
        $periodId = null;
        if ($value !== null && $value !== '') {
            abort_unless(is_string($value) && filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false, 404);
            $periodId = (int) $value;
        }

        return response()->json($statistics->get($periodId))->header('Cache-Control', 'no-store, private');
    }
}
