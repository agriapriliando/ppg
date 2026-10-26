<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$payload = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$cookies = [];
$results = [];

try {
    foreach ($payload['requests'] as $index => $operation) {
        $started = hrtime(true);
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(ConsoleKernel::class)->bootstrap();
        if (config('app.env') !== 'testing' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'pmb_isolated_performance') {
            throw new LogicException('Worker hanya boleh memakai database pmb_isolated_performance dalam testing.');
        }
        if (config('database.connections.mysql.url') || DB::selectOne('SELECT DATABASE() AS name')->name !== 'pmb_isolated_performance') {
            throw new LogicException('DB_URL tidak diizinkan dan database aktual harus pmb_isolated_performance.');
        }
        config(['filesystems.disks.local.root' => storage_path('framework/testing/performance/private')]);
        $queryCount = 0;
        $queryMs = 0;
        DB::listen(function ($query) use (&$queryCount, &$queryMs): void {
            $queryCount++;
            $queryMs += $query->time;
        });
        if ($index === 0) {
            Auth::login(User::findOrFail($payload['actor']));
        }
        memory_reset_peak_usage();
        $request = Request::create('http://localhost'.$operation['path'], $operation['method'], $operation['input'] ?? [], $cookies);
        $kernel = $app->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        if ($response->getStatusCode() !== $operation['expected_status'] || Auth::id() !== $payload['actor'] || session()->has('errors')) {
            throw new RuntimeException('Permintaan '.$operation['name'].' gagal: HTTP '.$response->getStatusCode());
        }
        if (isset($operation['expected_location']) && ! str_ends_with($response->headers->get('Location', ''), $operation['expected_location'])) {
            throw new RuntimeException('Pengiriman draf tidak menuju halaman formulir yang diharapkan.');
        }
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
        $results[] = ['name' => $operation['name'], 'wall_ms' => round((hrtime(true) - $started) / 1e6, 2), 'queries' => $queryCount, 'sql_ms' => round($queryMs, 2), 'peak_heap_mib' => round(memory_get_peak_usage(true) / 1048576, 2), 'status' => $response->getStatusCode()];
        unset($response, $kernel, $request, $app);
        gc_collect_cycles();
    }
    echo json_encode($results, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
