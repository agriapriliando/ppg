import test from 'node:test';
import assert from 'node:assert/strict';
import { chartOptions, selectCities, StatisticsRequest } from '../../resources/js/public-sk-statistics.js';

const data = {
    summary: { total: 12 },
    scope: { period_id: null },
    periods: [{ id: 1, label: 'Periode I', count: 12 }],
    provinces: [{ code: '01', label: 'Provinsi Pertama', count: 8 }, { code: '02', label: 'Provinsi Kedua', count: 4 }],
    cities: [
        { code: '0101', province_code: '01', province_label: 'Provinsi Pertama', label: 'Kabupaten Sama', count: 3 },
        { code: '0102', province_code: '01', province_label: 'Provinsi Pertama', label: 'Kota Kedua', count: 4 },
        { code: '0201', province_code: '02', province_label: 'Provinsi Kedua', label: 'Kabupaten Sama', count: 4 },
    ],
};
const ok = (payload = data) => ({ ok: true, json: async () => payload });
const deferred = () => { let resolve; let reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no; }); return { promise, resolve, reject }; };

test('city filtering uses province codes, preserves all rows, and does not change the summary or percentage denominator', () => {
    const before = structuredClone(data);
    assert.deepEqual(selectCities(data).rows, data.cities);
    const selected = selectCities(data, '01', '  KABUPATEN SAMA  ');
    assert.deepEqual(selected.rows.map(row => row.code), ['0101']);
    assert.equal(selected.totalRegions, 2);
    assert.equal(selected.denominator, 8); // Includes the member with missing city information.
    assert.equal(selectCities(data, '', 'tidak-ada').denominator, 12);
    assert.deepEqual(selectCities(data, '', '0201').rows.map(row => row.code), ['0201']);
    assert.deepEqual(selectCities(data, '01', 'tidak-ada').rows, []);
    assert.deepEqual(data, before);
});

test('charts include every positive region in the payload and support scrolling, reduced motion, and period selection', () => {
    const rows = Array.from({ length: 71 }, (_, i) => ({ code: `01${i}`, label: `Wilayah ${i}`, count: i + 1 }));
    const config = chartOptions('Kabupaten/kota', rows, 100, { kind: 'cities', reducedMotion: true });
    assert.equal(config.series[0].data.length, 71);
    assert.ok(config.chart.scrollablePlotArea.minHeight >= 71 * 38);
    assert.equal(config.plotOptions.bar.animation, false);
    assert.equal(config.yAxis.min, 0);
    assert.equal(config.yAxis.allowDecimals, false);
    let selected;
    const comparison = chartOptions('Periode', data.periods, 12, { kind: 'periods', selected: '1', onSelect: value => { selected = value; } });
    const point = comparison.series[0].data[0];
    assert.equal(point.color, '#bb8c18');
    comparison.series[0].point.events.click.call({ options: point });
    assert.equal(selected, '1');
});

test('tooltip labels cannot inject markup and a zero denominator never creates NaN or Infinity', () => {
    const config = chartOptions('Provinsi', [{ code: '01', label: '<img src=x onerror=alert(1)>', count: 2 }], 0, { kind: 'provinces' });
    const tooltip = config.tooltip.formatter.call({ y: 2, options: config.series[0].data[0] });
    assert.ok(tooltip.includes('&lt;img'));
    assert.ok(!tooltip.includes('<img'));
    assert.ok(tooltip.includes('(0%)'));
    assert.ok(!tooltip.includes('NaN') && !tooltip.includes('Infinity'));
});

test('a slower older response cannot replace the latest selected period, even if abort is ignored', async () => {
    const first = deferred();
    const second = deferred();
    const inputs = [];
    const request = new StatisticsRequest('http://ppg.test/statistik-peserta-sk', (url, options) => {
        inputs.push({ url, options });
        return inputs.length === 1 ? first.promise : second.promise;
    });
    const commits = [];
    const busy = [];
    const hooks = { success: value => commits.push(value.scope.period_id), error: assert.fail, pending: value => busy.push(value) };
    const one = request.load('1', hooks);
    const two = request.load('2', hooks);
    assert.equal(inputs[0].options.signal.aborted, true);
    assert.equal(inputs[1].url.searchParams.get('period_id'), '2');
    assert.equal(inputs[1].options.cache, 'no-store');
    second.resolve(ok({ ...data, scope: { period_id: 2 } }));
    await two;
    first.resolve(ok({ ...data, scope: { period_id: 1 } }));
    await one;
    assert.deepEqual(commits, [2]);
    assert.deepEqual(busy, [true, true, false]);
});

test('reset requests all public periods and failed requests do not publish partial results', async () => {
    let url;
    let status;
    let commits = 0;
    let failures = 0;
    const request = new StatisticsRequest('http://ppg.test/statistik-peserta-sk', async value => { url = value; return { ok: false, status: 404 }; });
    await request.load('', { success: () => { commits++; }, error: () => { failures++; }, pending: value => { status = value; } });
    assert.equal(url.searchParams.has('period_id'), false);
    assert.equal(status, false);
    assert.equal(commits, 0);
    assert.equal(failures, 1);
});

test('invalid JSON shapes and superseded errors cannot overwrite a successful snapshot', async () => {
    let failures = 0;
    const request = new StatisticsRequest('http://ppg.test/statistik-peserta-sk', async () => ok({ summary: {} }));
    await request.load('1', { success: assert.fail, error: () => { failures++; }, pending: () => {} });
    assert.equal(failures, 1);
    const first = deferred();
    let calls = 0;
    let successes = 0;
    const race = new StatisticsRequest('http://ppg.test/statistik-peserta-sk', () => ++calls === 1 ? first.promise : Promise.resolve(ok()));
    const hooks = { success: () => { successes++; }, error: assert.fail, pending: () => {} };
    const old = race.load('1', hooks);
    await race.load('', hooks);
    first.reject(new Error('Koneksi lama terputus'));
    await old;
    assert.equal(successes, 1);
});
