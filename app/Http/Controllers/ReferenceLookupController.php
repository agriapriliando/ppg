<?php

namespace App\Http\Controllers;

use App\Models\ReferenceDataset;
use App\Models\ReferenceProdi;
use App\Models\ReferencePt;
use App\Models\ReferenceRegion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['type' => ['required', 'in:province,city,district,pt,prodi'], 'parent' => ['nullable', 'string', 'max:64'], 'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $dataset = ReferenceDataset::query()->where('state', 'active')->value('id');
        $type = $data['type'];
        if (in_array($type, ['city', 'district', 'prodi'], true) && empty($data['parent'])) {
            return response()->json(['data' => [], 'has_more' => false]);
        }
        if ($type === 'pt') {
            $query = ReferencePt::query()->where('dataset_id', $dataset)->select(['external_id as code', 'name']);
        } elseif ($type === 'prodi') {
            $query = ReferenceProdi::query()->where('dataset_id', $dataset)->where('pt_external_id', $data['parent'])->select(['external_id as code', 'name']);
        } else {
            $query = ReferenceRegion::query()->where('dataset_id', $dataset)->where('level', $type)->when($type !== 'province', fn ($query) => $query->where('parent_code', $data['parent']))->select(['code', 'name']);
        }
        $results = $query->when(! empty($data['q']), fn ($query) => $query->where('name', 'like', '%'.$data['q'].'%'))->orderBy('name')->orderBy('id')->simplePaginate(20);

        return response()->json(['data' => $results->items(), 'has_more' => $results->hasMorePages()]);
    }
}
