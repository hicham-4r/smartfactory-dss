<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class TrustedProxyHttpsTest extends TestCase
{
    public function test_forwarded_https_is_used_for_login_urls(): void
    {
        $this->withoutVite();

        $server = [
            'REMOTE_ADDR' => '10.244.0.10',
            'HTTP_HOST' => 'localhost:8443',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ];

        $login = $this
            ->withServerVariables($server)
            ->get('/login');

        $login->assertOk();
        $login->assertSee(
            'action="https://localhost:8443/login"',
            escape: false
        );
        $login->assertSee(
            'href="https://localhost:8443/forgot-password"',
            escape: false
        );

        $root = $this
            ->withServerVariables($server)
            ->get('/');

        $root->assertRedirect(
            'https://localhost:8443/login'
        );
    }
}
