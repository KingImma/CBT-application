<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Terms;

use App\Exceptions\Domain\Session\DuplicateTermNameException;
use App\Models\Tenant\Term;
use Illuminate\Database\QueryException;

class CreateTerm
{
    public function execute(array $data): Term
    {
        $tenantId = $data['tenant_id'] ?? null;

        if ($tenantId && Term::where('tenant_id', $tenantId)
            ->where('name', $data['name'])
            ->exists()
        ) {
            throw new DuplicateTermNameException($data['name']);
        }

        try {
            return Term::create($data);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000' && ! str_contains($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }

            throw new DuplicateTermNameException($data['name']);
        }
    }
}
