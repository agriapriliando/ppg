<?php

namespace App\Support;

use App\Enums\PeriodState;
use App\Models\AdmissionPeriod;
use App\Models\ReferenceDataset;
use App\Models\ReferenceRegion;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PublicSkParticipantStatistics
{
    /** @return array<string, mixed> */
    public function get(?int $periodId = null): array
    {
        // Read every input before consulting the cache. A content revision also detects
        // bulk updates/deletes that bypass model events, without touching write paths.
        [$rows, $regions, $activeDatasetId] = DB::transaction(function (): array {
            $columns = ['ap.id', 'ap.name', 'ap.code', 'ap.academic_year', 'ap.wave', 'ap.opens_at',
                'sp.origin_reference_dataset_id', 'sp.origin_province_code', 'sp.origin_province_name', 'sp.origin_city_code', 'sp.origin_city_name'];
            // is_public chooses the homepage primary period, not statistics eligibility.
            $rows = AdmissionPeriod::query()->from('admission_periods as ap')
                ->where('ap.state', PeriodState::Published)
                ->leftJoin('sk_participants as sp', function (JoinClause $join): void {
                    $join->on('sp.admission_period_id', '=', 'ap.id')->where('sp.roster_state', 'active');
                })
                ->select($columns)->selectRaw('COUNT(sp.id) as participant_count, MAX(sp.id) as snapshot_id')
                ->groupBy($columns)->orderBy('ap.opens_at')->orderBy('ap.id')
                ->orderBy('sp.origin_reference_dataset_id')->orderBy('sp.origin_province_code')
                ->orderBy('sp.origin_city_code')->orderBy('sp.origin_province_name')->orderBy('sp.origin_city_name')
                ->toBase()->get()->map(fn ($row) => (array) $row)->all();
            $activeDatasetId = ReferenceDataset::query()->where('state', 'active')->latest('id')->value('id');
            $codes = collect($rows)->flatMap(fn ($row) => [trim($row['origin_province_code'] ?? ''), trim($row['origin_city_code'] ?? '')])->filter()->unique()->values();
            $datasetIds = collect($rows)->pluck('origin_reference_dataset_id')->push($activeDatasetId)->filter()->unique()->values();
            $regions = ReferenceRegion::query()->whereIn('dataset_id', $datasetIds)->whereIn('code', $codes)
                ->whereIn('level', ['province', 'city'])->orderBy('dataset_id')->orderBy('code')
                ->get(['dataset_id', 'code', 'name', 'level', 'parent_code'])->toArray();

            return [$rows, $regions, $activeDatasetId];
        });

        abort_if($periodId !== null && ! collect($rows)->contains(fn ($row) => (int) $row['id'] === $periodId), 404);
        $revision = hash('sha256', serialize([$rows, $regions, $activeDatasetId, $periodId]));

        return Cache::remember('public-sk-statistics:v2:'.$revision, max(1, (int) config('pmb.public_statistics_cache_seconds', 300)),
            fn () => $this->aggregate($rows, $regions, $activeDatasetId === null ? null : (int) $activeDatasetId, $periodId));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $regions
     * @return array<string, mixed>
     */
    private function aggregate(array $rows, array $regions, ?int $activeDatasetId, ?int $periodId): array
    {
        $reference = [];
        foreach ($regions as $region) {
            $reference[$region['dataset_id'].':'.$region['code']] = $region;
        }
        $periods = [];
        $provinces = [];
        $cities = [];
        $total = $missingProvince = $missingCity = 0;
        $selectedLabel = 'Semua periode dipublikasikan';
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $count = (int) $row['participant_count'];
            $periodLabel = $row['name'].' · '.$row['academic_year'].' · Gelombang '.$row['wave'].' ('.$row['code'].')';
            $periods[$id] ??= ['id' => $id, 'label' => $periodLabel, 'count' => 0];
            $periods[$id]['count'] += $count;
            if ($periodId === $id) {
                $selectedLabel = $periodLabel;
            }
            if (($periodId !== null && $id !== $periodId) || $count === 0) {
                continue;
            }
            $total += $count;
            $provinceCode = trim($row['origin_province_code'] ?? '');
            $cityCode = trim($row['origin_city_code'] ?? '');
            $datasetId = $row['origin_reference_dataset_id'] ?? $activeDatasetId;
            $province = $reference[$datasetId.':'.$provinceCode] ?? null;
            $city = $reference[$datasetId.':'.$cityCode] ?? null;
            $canonicalProvince = $reference[$activeDatasetId.':'.$provinceCode] ?? null;
            $canonicalCity = $reference[$activeDatasetId.':'.$cityCode] ?? null;
            $provinceLabel = trim(($canonicalProvince['level'] ?? '') === 'province' ? $canonicalProvince['name'] : ($province['name'] ?? ''));
            $provinceLabel = $provinceLabel !== '' ? $provinceLabel : trim($row['origin_province_name'] ?? '');
            $provinceValid = $provinceCode !== '' && ($province['level'] ?? '') === 'province' && $provinceLabel !== '';
            if (! $provinceValid) {
                $missingProvince += $count;
                $missingCity += $count;

                continue;
            }
            $this->addRegion($provinces, $provinceCode, ['code' => $provinceCode, 'label' => $provinceLabel], $count, (int) $row['snapshot_id']);
            $cityValid = $cityCode !== '' && ($city['level'] ?? '') === 'city' && trim($city['parent_code'] ?? '') === $provinceCode;
            $canonicalCityValid = ($canonicalCity['level'] ?? '') === 'city' && trim($canonicalCity['parent_code'] ?? '') === $provinceCode;
            $cityLabel = trim($canonicalCityValid ? $canonicalCity['name'] : ($city['name'] ?? ''));
            $cityLabel = $cityLabel !== '' ? $cityLabel : trim($row['origin_city_name'] ?? '');
            if (! $cityValid || $cityLabel === '') {
                $missingCity += $count;

                continue;
            }
            $this->addRegion($cities, $provinceCode.':'.$cityCode,
                ['code' => $cityCode, 'province_code' => $provinceCode, 'province_label' => $provinceLabel, 'label' => $cityLabel], $count, (int) $row['snapshot_id']);
        }
        $cities = array_map(fn ($city) => array_replace($city, ['province_label' => $provinces[$city['province_code']]['label']]), $cities);

        return [
            'generated_at' => now()->toIso8601String(),
            'scope' => ['period_id' => $periodId, 'label' => $selectedLabel, 'roster_state' => 'active', 'period_state' => 'published'],
            'summary' => ['total' => $total, 'provinces' => count($provinces), 'cities' => count($cities), 'missing_province' => $missingProvince, 'missing_city' => $missingCity],
            'periods' => array_values(array_filter($periods, fn ($period) => $period['count'] > 0)),
            'provinces' => $this->sortedRegions($provinces),
            'cities' => $this->sortedRegions($cities),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @param  array<string, mixed>  $identity
     */
    private function addRegion(array &$groups, string $key, array $identity, int $count, int $snapshotId): void
    {
        $previous = $groups[$key] ?? ['count' => 0, 'snapshot_id' => 0];
        $groups[$key] = ($snapshotId >= $previous['snapshot_id'] ? $identity : $previous)
            + ['snapshot_id' => $snapshotId];
        $groups[$key]['count'] = $previous['count'] + $count;
    }

    /** @param array<string, array<string, mixed>> $groups
     * @return array<int, array<string, mixed>>
     */
    private function sortedRegions(array $groups): array
    {
        $rows = array_values($groups);
        usort($rows, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['label'], $b['label']) ?: strcmp(($a['province_code'] ?? '').':'.$a['code'], ($b['province_code'] ?? '').':'.$b['code']));

        return array_map(function ($row): array {
            unset($row['snapshot_id']);

            return $row;
        }, $rows);
    }
}
