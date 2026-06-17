<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Terms;

use App\Models\Tenant\Term;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class UpdateTerm
{
    public function execute(Term $term, array $data): Term
    {
        $query = Term::where('tenant_id', $term->tenant_id)
            ->where('name', $data['name'] ?? $term->name)
            ->where('id', '!=', $term->id);

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => __('The term name must be unique per tenant.'),
            ]);
        }

        try {
            $term->update($data);

            return $term->fresh();
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
