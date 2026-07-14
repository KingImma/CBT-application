<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Tenancy\Actions\RemoveTenantUserIndex;
use App\Http\Controllers\Api\Tenant\TeacherController;
use ReflectionMethod;
use Tests\TestCase;

class TeacherControllerDependencyTest extends TestCase
{
    public function test_revoke_uses_the_remove_tenant_user_index_action(): void
    {
        $method = new ReflectionMethod(TeacherController::class, 'revoke');
        $type = $method->getParameters()[0]->getType();

        $this->assertSame(RemoveTenantUserIndex::class, $type?->getName());
    }
}
