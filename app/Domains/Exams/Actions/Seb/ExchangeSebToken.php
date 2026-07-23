<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Seb;

use App\Domains\Exams\Exceptions\SebLaunchTokenInvalidException;
use App\Domains\Exams\Support\SebLaunchTokenStore;
use App\Models\Tenant;
use App\Models\Tenant\User;

final class ExchangeSebToken
{
    public function __construct(private SebLaunchTokenStore $tokenStore) {}

    /** @return array{bearerToken: string, tenantHandle: string} */
    public function execute(string $rawToken): array
    {
        $data = $this->tokenStore->consume($rawToken);

        throw_if($data === null, SebLaunchTokenInvalidException::class);

        $tenant = Tenant::find($data['tenant_id']);

        throw_if($tenant === null, SebLaunchTokenInvalidException::class);

        // Manual tenancy bootstrap — no middleware did this for us. $tenant->run()
        // switches the DB connection for the closure only, then reverts.
        return $tenant->run(function () use ($data, $tenant) {
            $student = User::findOrFail($data['student_id']);

            // Distinct token name from normal login's 'tenant-token' so a SEB
            // launch never revokes the student's regular browser session.
            $expiresAt = now()->addHours(4);
            $plainTextToken = $student->createToken('seb-token', ['*'], $expiresAt)->plainTextToken;

            return [
                'bearerToken' => $tenant->slug.'::'.$plainTextToken,
                'tenantHandle' => $tenant->handle,
            ];
        });
    }
}
