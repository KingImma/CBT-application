<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tenancy;

use App\Modules\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    #[Test]
    public function it_reports_central_context_when_no_tenant_is_initialised(): void
    {
        $context = new TenantContext;

        $this->assertNull($context->id());
        $this->assertNull($context->name());
        $this->assertTrue($context->isCentral());
    }

    #[Test]
    public function it_namespaces_cache_keys_into_a_central_bucket(): void
    {
        $context = new TenantContext;

        $this->assertSame('central:session_term_context:session', $context->cacheKey('session_term_context:session'));
    }

    #[Test]
    public function require_id_fails_loudly_outside_a_tenant(): void
    {
        $this->expectException(RuntimeException::class);

        (new TenantContext)->requireId();
    }
}
