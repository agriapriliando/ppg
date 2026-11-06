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
        $data = $request->validate(['type' => ['required', 'in:province,city,city_search,district,pt,prodi'], 'parent' => ['nullable', 'string', 'max:64'], 'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $dataset = ReferenceDataset::query()->where('state', 'active')->value('id');
        $type = $data['type'];
        if (in_array($type, ['city', 'prodi'], true) && empty($data['parent'])) {
            return response()->json(['data' => [], 'has_more' => false]);
        }
        if ($type === 'city_search') {
            $query = ReferenceRegion::from('reference_regions as c')
                ->join('reference_regions as p', function ($join) use ($dataset): void {
                    $join->on('p.code', '=', 'c.parent_code')->where('p.dataset_id', $dataset)->where('p.level', 'province');
                })->where('c.dataset_id', $dataset)->where('c.level', 'city')
                ->select(['c.code', 'c.name', 'p.code as province_code', 'p.name as province_name']);
            if (! empty($data['q'])) {
                $term = '%'.$data['q'].'%';
                $query->where(fn ($query) => $query->where('c.name', 'like', $term)->orWhere('p.name', 'like', $term)->orWhere('c.code', 'like', $term));
            }
            $results = $query->orderBy('c.name')->orderBy('c.id')->simplePaginate(20);

            return response()->json(['data' => $results->items(), 'has_more' => $results->hasMorePages()]);
        }
        if ($type === 'district') {
            $query = ReferenceRegion::from('reference_regions as d')
                ->leftJoin('reference_regions as c', function ($j) use ($dataset): void {
                    $j->on('c.code', '=', 'd.parent_code')->where('c.dataset_id', '=', $dataset);
                })
                ->leftJoin('reference_regions as p', function ($j) use ($dataset): void {
                    $j->on('p.code', '=', 'c.parent_code')->where('p.dataset_id', '=', $dataset);
                })
                ->where('d.dataset_id', $dataset)
                ->where('d.level', 'district')
                ->when(! empty($data['parent']), fn ($q) => $q->where('d.parent_code', $data['parent']))
                ->select([
                    'd.code',
                    'd.name',
                    'c.code as city_code',
                    'c.name as city_name',
                    'p.code as province_code',
                    'p.name as province_name',
                ]);

            if (! empty($data['q'])) {
                $term = '%'.$data['q'].'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('d.name', 'like', $term)
                        ->orWhere('c.name', 'like', $term)
                        ->orWhere('p.name', 'like', $term);
                });
            }

            $results = $query->orderBy('d.name')->orderBy('d.id')->simplePaginate(20);

            return response()->json(['data' => $results->items(), 'has_more' => $results->hasMorePages()]);
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
