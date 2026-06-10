<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Api\Tenant\TeacherController;
use App\Services\TenantUserService;
use ReflectionMethod;
use Tests\TestCase;

class TeacherControllerDependencyTest extends TestCase
{
    public function test_revoke_uses_the_tenant_user_service_from_the_services_namespace(): void
    {
        $method = new ReflectionMethod(TeacherController::class, 'revoke');
        $type = $method->getParameters()[0]->getType();

        $this->assertSame(TenantUserService::class, $type?->getName());
    }
}
