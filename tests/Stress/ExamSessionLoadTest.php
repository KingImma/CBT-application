<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\TestCase;

use function Pest\Stressless\stress;

uses(TestCase::class);

$provisionData = null;
$serverPort = 8899;
$outputPath = '/tmp/stress-provision.json';
$serverProc = null;

beforeAll(function () use (&$provisionData, $serverPort, $outputPath, &$serverProc) {
    exec("fuser -k {$serverPort}/tcp 2>/dev/null; true");

    $serverProc = new Process(
        ['php', 'artisan', 'serve', "--port={$serverPort}"],
        timeout: null,
    );
    $serverProc->start();
    usleep(2_000_000);

    $provision = new Process(
        ['php', 'artisan', 'stress:provision', "--port={$serverPort}", "--output={$outputPath}",
            '--no-interaction'],
        timeout: 180,
    );
    $provision->mustRun();

    $provisionData = json_decode(file_get_contents($outputPath), true);
});

afterAll(function () use ($outputPath, &$serverProc) {
    $serverProc?->stop();

    if (file_exists($outputPath)) {
        $teardown = new Process(
            ['php', 'artisan', 'stress:teardown', "--input={$outputPath}"],
            timeout: 30,
        );
        $teardown->run();
    }
});

function stressHeaders(array $data): array
{
    return [
        'X-Tenant' => $data['tenant_handle'],
        'Authorization' => 'Bearer '.$data['student_token'],
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ];
}

test('questions endpoint handles student navigation load', function () use (&$provisionData, $serverPort) {
    $url = "http://localhost:{$serverPort}/api/student/exams/{$provisionData['exam_id']}/questions";

    $result = stress($url)
        ->headers(stressHeaders($provisionData))
        ->concurrency(20)
        ->for(25)
        ->seconds();

    expect($result->requests()->failed()->rate)->toBeLessThan(0.05);
})->group('stress', 'read');

test('time remaining endpoint handles high-frequency polling', function () use (&$provisionData, $serverPort) {
    $url = "http://localhost:{$serverPort}/api/student/exams/attempts/{$provisionData['attempt_id']}/time-remaining";

    $result = stress($url)
        ->headers(stressHeaders($provisionData))
        ->concurrency(20)
        ->for(20)
        ->seconds();

    expect($result->requests()->failed()->rate)->toBeLessThan(0.05);
})->group('stress', 'heartbeat');

test('answer save handles concurrent writes safely', function () use (&$provisionData, $serverPort) {
    $url = "http://localhost:{$serverPort}/api/student/exams/attempts/{$provisionData['attempt_id']}/answers/{$provisionData['question_id']}";

    $result = stress($url)
        ->headers(stressHeaders($provisionData))
        ->put(['selected_option_ids' => [$provisionData['option_id']], 'time_spent_seconds' => 5])
        ->concurrency(20)
        ->for(20)
        ->seconds();

    expect($result->requests()->failed()->rate)->toBeLessThan(0.05);
})->group('stress', 'write');

test('submit endpoint is idempotent under race conditions', function () use (&$provisionData, $serverPort) {
    $url = "http://localhost:{$serverPort}/api/student/exams/attempts/{$provisionData['attempt_id']}/submit";

    $result = stress($url)
        ->headers(stressHeaders($provisionData))
        ->post([])
        ->concurrency(20)
        ->for(15)
        ->seconds();

    $total = $result->requests()->count();
    $failed = $result->requests()->failed()->count();
    $successCount = $total - $failed;

    expect($successCount)->toBeLessThanOrEqual(1);

    $checkUrl = "http://localhost:{$serverPort}/api/student/exams/attempts/{$provisionData['attempt_id']}/time-remaining";
    $verify = stress($checkUrl)
        ->headers(stressHeaders($provisionData))
        ->concurrency(1)
        ->for(3)
        ->seconds();

    expect($verify->requests()->failed()->rate)->toBeLessThan(0.5);
})->group('stress', 'submit');

test('concurrent start requests do not create duplicate attempts', function () use (&$provisionData, $serverPort) {
    $url = "http://localhost:{$serverPort}/api/student/exams/{$provisionData['exam_id']}/start";

    $result = stress($url)
        ->headers(stressHeaders($provisionData))
        ->post([])
        ->concurrency(20)
        ->for(15)
        ->seconds();

    $rate = $result->requests()->failed()->rate;

    expect($rate)->toBeGreaterThan(0.9);
})->group('stress', 'concurrent-start');
