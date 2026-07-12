<?php

declare(strict_types=1);

use App\Jobs\ImportTeachersJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Adjust these to match your test setup (tenant creation, admin user
| factory, route name/URI, and the central connection name).
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->central = config('tenancy.database.central_connection');

    // TODO: replace with however you initialize a tenant + authenticated
    // admin user in your test suite, e.g.:
    // $this->tenant = Tenant::factory()->create();
    // tenancy()->initialize($this->tenant);
    // $this->admin = User::factory()->create();
    // $this->admin->assignRole(RoleType::Admin->value);
});

it('queues an ImportTeachersJob when a non-dry-run import is submitted', function () {
    Bus::fake();

    $file = UploadedFile::fake()->createWithContent(
        'teachers.csv',
        "first_name,last_name,email\nJohn,Doe,john@example.com\n"
    );

    $response = $this->actingAs($this->admin)
        ->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

    $response->assertStatus(202);

    Bus::assertDispatched(ImportTeachersJob::class);

    $this->assertDatabaseHas('import_jobs', [
        'tenant_id' => tenant('id'),
        'type' => 'teacher',
        'status' => 'pending',
    ], $this->central);
});

it('captures the correct import job id on the dispatched job', function () {
    Bus::fake();

    $file = UploadedFile::fake()->createWithContent(
        'teachers.csv',
        "first_name,last_name,email\nJohn,Doe,john@example.com\n"
    );

    $this->actingAs($this->admin)
        ->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

    $capturedId = null;

    Bus::assertDispatched(function (ImportTeachersJob $job) use (&$capturedId) {
        $ref = new ReflectionProperty($job, 'importJobId');
        $ref->setAccessible(true);
        $capturedId = $ref->getValue($job);

        return true;
    });

    expect($capturedId)->not->toBeEmpty();

    $this->assertDatabaseHas('import_jobs', [
        'id' => $capturedId,
        'status' => 'pending',
    ], $this->central);
});

it('does not queue a job for dry-run imports', function () {
    Bus::fake();

    $file = UploadedFile::fake()->createWithContent(
        'teachers.csv',
        "first_name,last_name,email\n"
    );

    $this->actingAs($this->admin)
        ->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

    Bus::assertNotDispatched(ImportTeachersJob::class);
});

it('processes the import and marks the row completed when handle() is run directly', function () {
    $importJobId = Str::uuid()->toString();

    DB::connection($this->central)->table('import_jobs')->insert([
        'id' => $importJobId,
        'tenant_id' => tenant('id'),
        'type' => 'teacher',
        'status' => 'pending',
        'file_contents' => "first_name,last_name,email\nJohn,Doe,john@x.com\n",
        'meta' => json_encode(['overwrite_existing' => 'skip']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new ImportTeachersJob($importJobId))->handle();

    // Row is deleted on successful completion (see handle()'s final delete()).
    $this->assertDatabaseMissing('import_jobs', [
        'id' => $importJobId,
    ], $this->central);
});

it('skips processing if the import job row is already completed', function () {
    $importJobId = Str::uuid()->toString();

    DB::connection($this->central)->table('import_jobs')->insert([
        'id' => $importJobId,
        'tenant_id' => tenant('id'),
        'type' => 'teacher',
        'status' => 'completed',
        'file_contents' => '',
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new ImportTeachersJob($importJobId))->handle();

    // Row should still exist untouched (not re-processed, not re-deleted).
    $this->assertDatabaseHas('import_jobs', [
        'id' => $importJobId,
        'status' => 'completed',
    ], $this->central);
});

it('marks the import job as failed and logs when the job fails permanently', function () {
    $importJobId = Str::uuid()->toString();

    DB::connection($this->central)->table('import_jobs')->insert([
        'id' => $importJobId,
        'tenant_id' => tenant('id'),
        'type' => 'teacher',
        'status' => 'processing',
        'file_contents' => '',
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    (new ImportTeachersJob($importJobId))->failed(new RuntimeException('boom'));

    $this->assertDatabaseHas('import_jobs', [
        'id' => $importJobId,
        'status' => 'failed',
    ], $this->central);
});
