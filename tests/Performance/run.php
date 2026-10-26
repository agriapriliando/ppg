<?php

use App\Actions\Assignments\AssignOperators;
use App\Actions\Imports\CommitSkImport;
use App\Actions\Imports\ValidateSkImport;
use App\Actions\RecordAudit;
use App\Enums\UserRole;
use App\Jobs\GenerateParticipantExportJob;
use App\Models\ApplicantWorkspace;
use App\Models\ExportRun;
use App\Models\SkImport;
use App\Models\SkParticipant;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): never {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage().PHP_EOL);
    exit(1);
});
if (config('app.env') !== 'testing' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'pmb_isolated_performance') {
    throw new LogicException('Benchmark memerlukan APP_ENV=testing dan database khusus pmb_isolated_performance. Database ini dibangun ulang; jangan gunakan database aplikasi.');
}
if (config('database.connections.mysql.url') || DB::selectOne('SELECT DATABASE() AS name')->name !== 'pmb_isolated_performance') {
    throw new LogicException('DB_URL tidak diizinkan dan database aktual harus pmb_isolated_performance.');
}
require_once __DIR__.'/../Fixtures/registration.php';
$label = $argv[1] ?? 'baseline';
if (! preg_match('/^[a-z0-9_-]{1,40}$/', $label)) {
    throw new LogicException('Nama laporan harus huruf kecil, angka, garis bawah atau tanda hubung.');
}
$directory = storage_path('framework/testing/performance');
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
config(['filesystems.disks.local.root' => $directory.'/private']);
Storage::forgetDisk('local');
$queryCounters = ['queries' => 0, 'sql_ms' => 0];
DB::listen(function ($query) use (&$queryCounters): void {
    $queryCounters['queries']++;
    $queryCounters['sql_ms'] += $query->time;
});

function measureWork(callable $work): array
{
    global $queryCounters;
    $before = $queryCounters;
    memory_reset_peak_usage();
    $start = hrtime(true);
    $work();

    return ['wall_ms' => round((hrtime(true) - $start) / 1e6, 2), 'queries' => $queryCounters['queries'] - $before['queries'], 'sql_ms' => round($queryCounters['sql_ms'] - $before['sql_ms'], 2), 'peak_heap_mib' => round(memory_get_peak_usage(true) / 1048576, 2)];
}

function runRequestPool(array $actors, int $periodId, string $directory): array
{
    $waiting = [];
    foreach ($actors as $index => $actor) {
        $staff = $actor->role !== UserRole::Applicant;
        $requests = [['name' => $staff ? 'staff.dashboard' : 'applicant.dashboard', 'path' => '/ruang-kerja?period_id='.$periodId, 'method' => 'GET', 'expected_status' => 200]];
        if ($staff) {
            $requests[] = ['name' => 'staff.participants', 'path' => '/peserta-sk?period_id='.$periodId, 'method' => 'GET', 'expected_status' => 200];
            $requests[] = ['name' => 'staff.queue', 'path' => '/antrean?period_id='.$periodId.'&status=terkirim', 'method' => 'GET', 'expected_status' => 200];
        } else {
            $path = '/pendaftar/formulir/'.$periodId;
            $requests[] = ['name' => 'applicant.form', 'path' => $path, 'method' => 'GET', 'expected_status' => 200];
            $requests[] = ['name' => 'applicant.draft', 'path' => $path, 'method' => 'POST', 'input' => ['version' => 0, 'action' => 'draft', 'full_name' => $actor->name], 'expected_status' => 302, 'expected_location' => $path];
        }
        $path = $directory.'/request-'.$index.'.json';
        file_put_contents($path, json_encode(['actor' => $actor->id, 'requests' => $requests], JSON_THROW_ON_ERROR));
        $waiting[] = $path;
    }
    $running = [];
    $results = [];
    $workerEnvironment = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => 'pmb_isolated_performance', 'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password'), 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BCRYPT_ROUNDS' => '4'];
    try {
        while ($waiting !== [] || $running !== []) {
            while (count($running) < 8 && $waiting !== []) {
                $inputPath = array_shift($waiting);
                $viewDirectory = $directory.'/views-'.pathinfo($inputPath, PATHINFO_FILENAME);
                if (! is_dir($viewDirectory)) {
                    mkdir($viewDirectory, 0777, true);
                }
                $process = new Process([PHP_BINARY, base_path('tests/Performance/request-worker.php'), $inputPath], base_path(), [...$workerEnvironment, 'VIEW_COMPILED_PATH' => $viewDirectory]);
                $process->setTimeout(60)->start();
                $running[] = $process;
            }
            foreach ($running as $key => $process) {
                $process->checkTimeout();
                if (! $process->isRunning()) {
                    if (! $process->isSuccessful()) {
                        throw new RuntimeException($process->getErrorOutput());
                    }
                    array_push($results, ...json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
                    unset($running[$key]);
                }
            }
            usleep(10000);
        }
    } finally {
        foreach ($running as $process) {
            $process->stop();
        }
    }
    $groups = [];
    foreach ($results as $row) {
        $groups[$row['name']][] = $row;
    }
    $summary = [];
    foreach ($groups as $name => $rows) {
        $times = array_column($rows, 'wall_ms');
        sort($times);
        $summary[$name] = ['requests' => count($rows), 'p50_ms' => $times[(int) ceil(count($times) * .5) - 1], 'p95_ms' => $times[(int) ceil(count($times) * .95) - 1], 'max_queries' => max(array_column($rows, 'queries')), 'max_sql_ms' => max(array_column($rows, 'sql_ms')), 'max_heap_mib' => max(array_column($rows, 'peak_heap_mib'))];
    }
    ksort($summary);

    return $summary;
}

$report = ['label' => $label, 'captured_at' => now()->toIso8601String(), 'php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'database' => DB::selectOne('SELECT VERSION() AS version')->version, 'sessions' => 50, 'max_workers' => 8, 'view_cache' => 'isolated per worker to avoid Windows rename contention', 'transport' => 'Laravel HTTP kernel; no network/TLS/browser; not a production acceptance result', 'datasets' => []];
foreach ([400, 4000] as $count) {
    $app->make(Kernel::class)->call('migrate:fresh', ['--force' => true]);
    $c = registrationContext();
    $operators = collect([$c['operator'], ...User::factory()->operator()->count(3)->create()])->sortBy('id')->values();
    app(AssignOperators::class)->handle($c['admin'], $c['period']->id, $c['period']->fresh()->lock_version, $operators->pluck('id')->all());
    $c['participant']->forceFill(['sk_number' => 'SK-BENCH', 'sk_order' => 1, 'sk_date' => '2026-10-01', 'official_participant_id' => '0000000001', 'name_on_sk' => 'Peserta Beban 00001', 'normalized_name' => 'peserta beban 00001'])->save();
    $book = new Spreadsheet;
    $book->getActiveSheet()->setTitle('Peserta_SK');
    $rows = [ValidateSkImport::HEADERS];
    for ($index = 1; $index <= $count; $index++) {
        $rows[] = ['SK-BENCH', '2026-10-01', $c['period']->code, $index, 'Peserta Beban '.sprintf('%05d', $index), sprintf('%010d', $index), $operators[($index - 1) % 4]->email, null];
    }
    $book->getActiveSheet()->fromArray($rows);
    $privatePath = 'imports/'.$label.'-'.$count.'.xlsx';
    Storage::disk('local')->makeDirectory('imports');
    (new Xlsx($book))->save(Storage::disk('local')->path($privatePath));
    $book->disconnectWorksheets();
    unset($book, $rows);
    $import = SkImport::create(['admission_period_id' => $c['period']->id, 'uploaded_by' => $c['admin']->id, 'original_filename' => 'synthetic-'.$count.'.xlsx', 'private_path' => $privatePath, 'file_sha256' => hash_file('sha256', Storage::disk('local')->path($privatePath))]);
    $validation = measureWork(fn () => app(ValidateSkImport::class)->handle($import));
    if ($import->refresh()->state !== 'ready') {
        throw new RuntimeException('Workbook sintetis tidak valid.');
    }
    $commit = measureWork(fn () => app(CommitSkImport::class)->handle($c['admin'], $import, $import->preview_hash));
    $reimport = SkImport::create(['admission_period_id' => $c['period']->id, 'uploaded_by' => $c['admin']->id, 'original_filename' => 'synthetic-repeat.xlsx', 'private_path' => $privatePath, 'file_sha256' => $import->file_sha256]);
    $revalidation = measureWork(fn () => app(ValidateSkImport::class)->handle($reimport));
    if ($reimport->refresh()->counts['identical'] !== $count || SkParticipant::query()->count() !== $count) {
        throw new RuntimeException('Impor ulang tidak identik atau jumlah roster tidak cocok.');
    }
    $users = [];
    $hash = Hash::make('SyntheticLoad2026!');
    for ($index = 2; $index <= $count; $index++) {
        $users[] = ['name' => 'Akun Beban '.sprintf('%05d', $index), 'login_id' => 'bench-'.sprintf('%05d', $index), 'email' => 'bench'.$index.'@example.test', 'password' => $hash, 'role' => 'pendaftar_ppg', 'account_state' => 'active', 'must_change_password' => false, 'created_at' => now(), 'updated_at' => now()];
    }
    foreach (array_chunk($users, 100) as $chunk) {
        User::insert($chunk);
    }
    $applicants = User::query()->where('role', 'pendaftar_ppg')->orderBy('id')->get();
    $workspaceRows = $applicants->map(fn ($user) => ['user_id' => $user->id, 'admission_period_id' => $c['period']->id, 'created_at' => now(), 'updated_at' => now()])->all();
    foreach (array_chunk($workspaceRows, 100) as $chunk) {
        ApplicantWorkspace::insert($chunk);
    }
    $workspaces = ApplicantWorkspace::query()->orderBy('id')->get()->keyBy('user_id');
    $participants = SkParticipant::query()->orderBy('sk_order')->get();
    $applicationRows = [];
    $biodataRows = [];
    foreach ($applicants as $index => $user) {
        $workspace = $workspaces[$user->id];
        $biodataRows[] = ['workspace_id' => $workspace->id, 'full_name' => $user->name];
        $applicationRows[] = ['user_id' => $user->id, 'sk_participant_id' => $participants[$index]->id, 'admission_period_id' => $c['period']->id, 'workspace_id' => $workspace->id, 'application_number' => 'BENCH-'.sprintf('%06d', $index + 1), 'status' => $index % 2 === 0 ? 'draf' : 'terkirim', 'submitted_at' => $index % 2 === 0 ? null : now(), 'created_at' => now(), 'updated_at' => now()];
    }
    foreach (array_chunk($biodataRows, 100) as $chunk) {
        DB::table('applicant_biodata')->insert($chunk);
    }
    foreach (array_chunk($applicationRows, 100) as $chunk) {
        DB::table('applications')->insert($chunk);
    }
    DB::table('sk_participants')->join('applications', 'applications.sk_participant_id', '=', 'sk_participants.id')->update(['sk_participants.claimed_user_id' => DB::raw('applications.user_id')]);
    $actors = [$c['admin'], ...$operators->all(), ...$applicants->filter(fn ($user, $index) => $index % 2 === 0)->take(45)->all()];
    $requests = runRequestPool($actors, $c['period']->id, $directory);
    $run = ExportRun::create(['requested_by' => $c['admin']->id, 'scope_snapshot' => ['period_id' => $c['period']->id]]);
    $export = measureWork(fn () => (new GenerateParticipantExportJob($run->id))->handle(app(RecordAudit::class)));
    if ($run->refresh()->row_count !== $count) {
        throw new RuntimeException('Jumlah ekspor tidak sesuai roster.');
    }
    $report['datasets'][$count] = ['validation' => $validation, 'commit' => $commit, 'identical_revalidation' => $revalidation, 'export' => $export, 'http_kernel' => $requests, 'stored_sessions' => DB::table('sessions')->count()];
    file_put_contents($directory.'/'.$label.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['dataset' => $count, 'validation' => $validation, 'commit' => $commit, 'revalidation' => $revalidation, 'requests' => $requests], JSON_THROW_ON_ERROR).PHP_EOL;
}
echo 'Laporan: '.$directory.'/'.$label.'.json'.PHP_EOL;
