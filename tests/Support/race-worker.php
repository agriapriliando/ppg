<?php

use App\Actions\Admissions\ApproveClaim;
use App\Actions\Admissions\ReviewApplication;
use App\Actions\Admissions\SaveApplicantDraftPatch;
use App\Actions\Admissions\SaveApplicantForm;
use App\Actions\Admissions\TransferApplicationOwnership;
use App\Actions\Assignments\AssignOperators;
use App\Actions\RecordAudit;
use App\Http\Controllers\CredentialController;
use App\Models\Application;
use App\Models\SkClaim;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('app.env') !== 'testing' || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'pmb_isolated_race') {
    throw new LogicException('Worker hanya boleh memakai database pmb_isolated_race pada lingkungan testing.');
}

$payload = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
touch($payload['barrier'].'/ready-'.$payload['worker']);
$deadline = microtime(true) + 15;
while (! file_exists($payload['barrier'].'/start')) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Barrier proses tidak dibuka.');
    }
    usleep(10000);
}

try {
    $actor = User::findOrFail($payload['actor']);
    match ($payload['operation']) {
        'approve' => app(ApproveClaim::class)->handle($actor, SkClaim::findOrFail($payload['subject']), $payload['version']),
        'assign' => app(AssignOperators::class)->handle($actor, $payload['subject'], $payload['version'], $payload['operators']),
        'submit' => app(SaveApplicantForm::class)->handle($actor, $payload['subject'], $payload['version'], $payload['input'], true),
        'draft' => app(SaveApplicantDraftPatch::class)->handle($actor, $payload['subject'], $payload['version'], $payload['batch_id'], $payload['changes']),
        'review' => app(ReviewApplication::class)->handle($actor, Application::findOrFail($payload['subject']), $payload['version'], 'verify', ['identity_checked' => 1, 'sk_checked' => 1, 'data_checked' => 1]),
        'ownership' => app(TransferApplicationOwnership::class)->handle($actor, Application::findOrFail($payload['subject']), $payload['input']),
        'issue' => (function () use ($actor, $payload): void {
            Auth::setUser($actor);
            View::share('errors', new ViewErrorBag);
            $request = Request::create('/test-credential-issue', 'POST', ['reason' => 'Penerbitan bersamaan pada pengujian terisolasi.']);
            $request->setUserResolver(fn () => $actor);
            app(CredentialController::class)->issue($request, SkClaim::findOrFail($payload['subject']), app(RecordAudit::class));
        })(),
        default => throw new LogicException('Operasi pengujian tidak dikenal.'),
    };
    echo json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['status' => 'conflict'], JSON_THROW_ON_ERROR);
} catch (HttpException $exception) {
    if (! in_array($exception->getStatusCode(), [403, 409], true)) {
        throw $exception;
    }
    echo json_encode(['status' => 'conflict'], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage());
    exit(1);
}
