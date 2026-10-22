<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    public function test_local_frontend_can_preflight_bearer_api_requests(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/auth/login');

        $response->assertNoContent();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $this->assertStringContainsString(
            'POST',
            (string) $response->headers->get('Access-Control-Allow-Methods'),
        );
    }

    public function test_unconfigured_origin_does_not_receive_cors_permission(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://untrusted.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/auth/login');

        $response->assertNoContent();
        $this->assertNotSame(
            'https://untrusted.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
