<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Terms;

use App\Exceptions\Domain\Session\DuplicateTermNameException;
use App\Models\Tenant\Term;
use Illuminate\Database\QueryException;

class UpdateTerm
{
    public function execute(Term $term, array $data): Term
    {
        $query = Term::where('tenant_id', $term->tenant_id)
            ->where('name', $data['name'] ?? $term->name)
            ->where('id', '!=', $term->id);

        if ($query->exists()) {
            throw new DuplicateTermNameException($data['name'] ?? $term->name);
        }

        try {
            $term->update($data);

            return $term->fresh();
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000' && ! str_contains($e->getMessage(), 'Duplicate entry')) {
                throw $e;
            }

            throw new DuplicateTermNameException($data['name'] ?? $term->name);
        }
    }
}
