const number = new Intl.NumberFormat('id-ID');
const percentage = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
const escapeText = (value) => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

export function selectCities(data, province = '', search = '') {
    const scope = data.cities.filter(city => !province || city.province_code === province);
    const query = search.trim().toLocaleLowerCase('id-ID');
    return {
        rows: scope.filter(city => !query || `${city.label} ${city.code} ${city.province_label}`.toLocaleLowerCase('id-ID').includes(query)),
        totalRegions: scope.length,
        denominator: province ? (data.provinces.find(item => item.code === province)?.count ?? 0) : data.summary.total,
    };
}

// Only the latest request may publish a result, even when an aborted fetch resolves.
export class StatisticsRequest {
    constructor(endpoint, fetcher = globalThis.fetch.bind(globalThis)) {
        this.endpoint = endpoint;
        this.fetcher = fetcher;
        this.sequence = 0;
    }
    async load(period, { success, error, pending }) {
        const sequence = ++this.sequence;
        this.controller?.abort();
        this.controller = new AbortController();
        pending(true);
        try {
            const url = new URL(this.endpoint, globalThis.location?.href ?? 'http://localhost');
            if (period !== '') url.searchParams.set('period_id', period);
            const response = await this.fetcher(url, { headers: { Accept: 'application/json' }, cache: 'no-store', signal: this.controller.signal });
            if (!response.ok) throw new Error('Statistik belum dapat dimuat.');
            const data = await response.json();
            if (!data.summary || !data.scope || !['periods', 'provinces', 'cities'].every(key => Array.isArray(data[key]))) throw new Error('Data statistik tidak valid.');
            if (sequence === this.sequence) success(data);
        } catch (exception) {
            if (sequence === this.sequence && exception.name !== 'AbortError') error(exception);
        } finally {
            if (sequence === this.sequence) pending(false);
        }
    }
}

export function chartOptions(title, rows, denominator, { kind, selected = '', onSelect = () => {}, reducedMotion = false, height = 400 } = {}) {
    const rowHeight = kind === 'periods' ? 80 : 38;
    return {
        chart: { type: 'bar', height, backgroundColor: 'transparent', animation: false, scrollablePlotArea: { minHeight: rows.length * rowHeight + 100 } },
        title: { text: title, style: { fontSize: '12px', fontWeight: '500', color: '#67536e' } },
        lang: { decimalPoint: ',', thousandsSep: '.', noData: 'Belum ada data.' },
        xAxis: { categories: rows.map(row => escapeText(row.label)), labels: { step: 1, style: { fontSize: '11px', width: '155px', textOverflow: 'none' } }, title: { text: null } },
        yAxis: { min: 0, maxPadding: .18, allowDecimals: false, title: { text: 'Kepesertaan SK aktif' }, labels: { formatter() { return number.format(this.value); } } },
        legend: { enabled: false },
        credits: { enabled: true },
        accessibility: { enabled: true, description: `${title}. Jumlah adalah kepesertaan SK aktif. Tabel data tersedia setelah grafik.` },
        tooltip: {
            useHTML: false,
            formatter() {
                const point = this.point ?? this;
                const label = point.options.custom.label;
                const percent = denominator > 0 ? (point.y / denominator) * 100 : 0;
                return `<b>${escapeText(label)}</b><br/>${number.format(point.y)} kepesertaan (${percentage.format(percent)}%)`;
            },
        },
        plotOptions: { bar: { borderRadius: 4, maxPointWidth: 20, animation: reducedMotion ? false : { duration: 300 }, dataLabels: { enabled: true, formatter() { return number.format(this.y); } } } },
        series: [{
            name: 'Kepesertaan SK aktif',
            data: rows.map(row => {
                const key = String(kind === 'periods' ? row.id : row.code);
                return { y: row.count, color: selected === key ? '#bb8c18' : '#643078', custom: { key, label: row.label } };
            }),
            cursor: kind === 'cities' ? 'default' : 'pointer',
            point: { events: { click() { if (kind !== 'cities') onSelect(this.options.custom.key); } } },
        }],
        responsive: { rules: [{ condition: { maxWidth: 420 }, chartOptions: { chart: { height: 360 }, xAxis: { labels: { style: { width: '110px', fontSize: '10px' } } } } }] },
    };
}

export function mountStatistics(root) {
    if (root.dataset.statisticsMounted) return;
    root.dataset.statisticsMounted = 'true';
    let data = JSON.parse(root.querySelector('[data-statistics-payload]').textContent);
    const find = selector => root.querySelector(selector);
    const period = find('[data-statistics-period]');
    const province = find('[data-statistics-province]');
    const search = find('[data-statistics-search]');
    const status = find('[data-statistics-status]');
    const retry = find('[data-statistics-retry]');
    const charts = new Map();
    const chartRevisions = new Map();
    const request = new StatisticsRequest(root.dataset.endpoint);
    const motion = globalThis.matchMedia?.('(prefers-reduced-motion: reduce)');
    let Highcharts;
    let chartPromise;
    let chartFailure = false;
    let requestFailure = false;
    let requestedPeriod = '';
    let loading = false;

    function announce(message = '') {
        status.textContent = message;
        status.hidden = !message;
    }
    function options(select, rows, allLabel, selected = '') {
        select.replaceChildren(new Option(allLabel, ''));
        for (const row of rows) select.add(new Option(row.label, String(row.id ?? row.code)));
        select.value = selected;
    }
    function table(kind, rows) {
        const body = find(`[data-statistics-table="${kind}"] tbody`);
        const fragment = document.createDocumentFragment();
        for (const row of rows) {
            const tr = document.createElement('tr');
            const label = document.createElement('th');
            label.scope = 'row';
            label.textContent = row.label;
            tr.append(label);
            if (kind === 'cities') {
                const cell = document.createElement('td');
                cell.textContent = row.province_label;
                tr.append(cell);
            }
            const count = document.createElement('td');
            count.className = 'text-end';
            count.textContent = number.format(row.count);
            tr.append(count);
            fragment.append(tr);
        }
        body.replaceChildren(fragment);
    }
    function chartUnavailable() {
        Highcharts = null;
        charts.forEach(chart => { try { chart.destroy(); } catch { /* A partially created chart may already be destroyed. */ } });
        charts.clear();
        chartRevisions.clear();
        root.querySelectorAll('[data-statistics-chart]').forEach(element => { element.hidden = true; });
        root.querySelectorAll('[data-statistics-table]').forEach(element => { element.open = true; });
        chartFailure = true;
        announce('Grafik belum dapat ditampilkan. Seluruh angka tetap tersedia pada tabel data.');
        retry.hidden = false;
    }
    function draw(kind, rows, denominator, title, selected, onSelect) {
        const element = find(`[data-statistics-chart="${kind}"]`);
        find(`[data-statistics-chart-empty="${kind}"]`).hidden = rows.length > 0;
        if (!Highcharts || rows.length === 0) {
            charts.get(kind)?.destroy();
            charts.delete(kind);
            chartRevisions.delete(kind);
            element.hidden = true;
            return;
        }
        element.hidden = false;
        const revision = JSON.stringify([rows, denominator, selected, motion?.matches]);
        if (chartRevisions.get(kind) === revision) return;
        const config = chartOptions(title, rows, denominator, { kind, selected, onSelect, reducedMotion: motion?.matches || charts.has(kind), height: element.clientWidth < 420 ? 360 : 400 });
        const previous = charts.get(kind);
        charts.delete(kind);
        chartRevisions.delete(kind);
        previous?.destroy();
        charts.set(kind, Highcharts.chart(element, config));
        chartRevisions.set(kind, revision);
    }
    function render() {
        const city = selectCities(data, province.value, search.value);
        for (const key of ['total', 'provinces', 'cities']) find(`[data-statistics-metric="${key}"]`).textContent = number.format(data.summary[key]);
        find('[data-statistics-scope]').textContent = data.scope.label;
        const time = find('[data-statistics-time]');
        time.dateTime = data.generated_at;
        time.textContent = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta' }).format(new Date(data.generated_at)) + ' WIB';
        find('[data-missing-province]').textContent = number.format(data.summary.missing_province);
        find('[data-missing-city]').textContent = number.format(data.summary.missing_city);
        find('[data-statistics-missing]').hidden = data.summary.missing_province + data.summary.missing_city === 0;
        find('[data-statistics-empty]').hidden = data.summary.total > 0;
        find('[data-statistics-city-caption]').textContent = `Menampilkan ${number.format(city.rows.length)} dari ${number.format(city.totalRegions)} wilayah. ${province.value ? province.selectedOptions[0].textContent : 'Semua provinsi'}.`;
        find('[data-statistics-clear-search]').hidden = search.value === '';
        table('provinces', data.provinces);
        table('cities', city.rows);
        table('periods', data.periods);
        try {
            draw('provinces', data.provinces, data.summary.total, 'Sebaran provinsi', province.value, value => {
                province.value = value;
                search.value = '';
                render();
            });
            draw('cities', city.rows, city.denominator, 'Sebaran kabupaten/kota', '', () => {});
            draw('periods', data.periods, data.periods.reduce((sum, item) => sum + item.count, 0), 'Perbandingan seluruh periode publik', String(data.scope.period_id ?? ''), loadPeriod);
        } catch {
            chartUnavailable();
        }
    }
    async function ensureCharts() {
        if (Highcharts) return;
        if (chartPromise) return chartPromise;
        chartPromise = (async () => {
            try {
                const core = await import('highcharts/esm/highcharts.js');
                await import('highcharts/esm/modules/accessibility.js');
                Highcharts = core.default;
                render();
                if (!Highcharts) return;
                chartFailure = false;
                root.querySelectorAll('[data-statistics-table]').forEach(element => { element.open = false; });
                if (!loading && !requestFailure) announce();
                retry.hidden = !requestFailure;
            } catch {
                chartUnavailable();
            } finally {
                chartPromise = null;
            }
        })();
        return chartPromise;
    }
    function loadPeriod(value) {
        requestedPeriod = String(value);
        const target = period.querySelector(`option[value="${requestedPeriod}"]`)?.textContent ?? 'periode pilihan';
        // Keep the visible selection aligned with the displayed snapshot until success.
        period.value = String(data.scope.period_id ?? '');
        requestFailure = false;
        retry.hidden = true;
        return request.load(requestedPeriod, {
            pending(value) {
                loading = value;
                root.setAttribute('aria-busy', String(value));
                if (value) announce(`Memuat statistik ${target}…`);
            },
            success(next) {
                data = next;
                options(period, data.periods, 'Semua periode publik', String(data.scope.period_id ?? ''));
                if (data.scope.period_id !== null && period.value === '') {
                    period.add(new Option(data.scope.label, String(data.scope.period_id), true, true));
                }
                options(province, data.provinces, 'Semua provinsi');
                search.value = '';
                render();
                announce(chartFailure ? 'Grafik belum dapat ditampilkan. Data terbaru tersedia pada tabel.' : 'Statistik telah diperbarui.');
                retry.hidden = !chartFailure;
            },
            error() {
                requestFailure = true;
                period.value = String(data.scope.period_id ?? '');
                announce('Statistik belum dapat dimuat. Data sebelumnya belum diperbarui; cakupan yang tampil tetap mengikuti snapshot sebelumnya. Silakan coba lagi.');
                retry.hidden = false;
            },
        });
    }
    root.querySelectorAll('[data-statistics-controls]').forEach(element => { element.hidden = false; });
    period.addEventListener('change', () => loadPeriod(period.value));
    province.addEventListener('change', () => { search.value = ''; render(); });
    search.addEventListener('input', render);
    find('[data-statistics-clear-search]').addEventListener('click', () => { search.value = ''; render(); search.focus(); });
    find('[data-statistics-reset]').addEventListener('click', () => loadPeriod(''));
    retry.addEventListener('click', () => requestFailure ? loadPeriod(requestedPeriod) : ensureCharts());
    motion?.addEventListener('change', render);
    render();
    if ('IntersectionObserver' in globalThis) {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) { observer.disconnect(); ensureCharts(); }
        }, { rootMargin: '250px' });
        observer.observe(root);
    } else ensureCharts();
}

if (typeof document !== 'undefined') {
    document.querySelectorAll('[data-sk-statistics]').forEach(root => mountStatistics(root));
}
