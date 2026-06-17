<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Terms;

use App\Models\Tenant\Term;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreateTerm
{
    public function execute(array $data): Term
    {
        $tenantId = $data['tenant_id'] ?? null;

        if ($tenantId && Term::where('tenant_id', $tenantId)
            ->where('name', $data['name'])
            ->exists()
        ) {
            throw ValidationException::withMessages([
                'name' => __('The term name must be unique per tenant.'),
            ]);
        }

        try {
            return Term::create($data);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000' && ! str_contains($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'name' => __('The term name must be unique per tenant.'),
            ]);
        }
    }
}
