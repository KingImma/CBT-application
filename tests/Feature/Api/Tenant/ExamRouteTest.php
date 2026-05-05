<?php

namespace Tests\Feature\Api\Tenant;

use Tests\TestCase;

class ExamRouteTest extends TestCase
{
    public function test_exam_routes_are_registered(): void
    {
        // Test that exam routes are registered
        $response = $this->get('/api/exams');
        
        // Should get 401 (unauthenticated) not 404 (route not found)
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_student_exam_routes_are_registered(): void
    {
        $response = $this->get('/api/student/exams/available');
        $this->assertNotEquals(404, $response->getStatusCode());
    }
}
