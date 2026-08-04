<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class AdministratorAiHealthDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        config()->set(
            'ai.default',
            'fastapi'
        );

        config()->set(
            'ai.allow_internal_http',
            true
        );

        config()->set(
            'ai.service',
            [
                'base_url' =>
                    'http://127.0.0.1:8001',
                'token' =>
                    str_repeat('t', 64),
                'verify_tls' => false,
                'health_endpoint' =>
                    '/health/ready',
                'version_endpoint' =>
                    '/version',
                'analytics_contract_endpoint' =>
                    '/internal/v1/contracts/analytics/validate',
                'connect_timeout_seconds' => 2,
                'timeout_seconds' => 5,
                'retry_attempts' => 1,
                'retry_delay_milliseconds' => 0,
                'maximum_request_bytes' =>
                    1048576,
                'maximum_response_bytes' =>
                    262144,
                'user_agent' =>
                    'SmartFactory-DSS/1.0',
                'log_channel' => 'stack',
            ]
        );
    }

    public function test_administrator_sees_available_fastapi_foundation(): void
    {
        Http::fake(
            function (
                Request $request
            ) {
                $requestId = $request
                    ->header(
                        'X-Request-ID'
                    )[0];

                return Http::response([
                    'status' => 'ready',
                    'service' =>
                        'SmartFactory DSS AI Service',
                    'version' => '0.1.0',
                    'api_version' => 'v1',
                    'checked_at' =>
                        now()->utc()->toIso8601String(),
                    'dependencies' => [
                        [
                            'name' => 'ollama',
                            'status' => 'available',
                            'required' => false,
                            'model' => 'llama3:8b',
                            'latency_ms' => 12,
                            'message' =>
                                'The configured local Ollama model is available.',
                        ],
                    ],
                    'request_id' => $requestId,
                ]);
            }
        );

        $this
            ->actingAs(
                $this->administrator()
            )
            ->get(
                route('admin.dashboard')
            )
            ->assertOk()
            ->assertSee(
                'AI service status'
            )
            ->assertSee(
                'Available'
            )
            ->assertSee(
                '0.1.0'
            )
            ->assertSee(
                'FastAPI health and verified model inference are available.'
            )
            ->assertHeaderContains(
                'Cache-Control',
                'no-store'
            );
    }

    public function test_administrator_sees_degraded_ollama_without_losing_fastapi_health(): void
    {
        Http::fake(
            function (
                Request $request
            ) {
                $requestId = $request
                    ->header(
                        'X-Request-ID'
                    )[0];

                return Http::response([
                    'status' => 'ready',
                    'service' =>
                        'SmartFactory DSS AI Service',
                    'version' => '0.1.0',
                    'api_version' => 'v1',
                    'checked_at' =>
                        now()->utc()->toIso8601String(),
                    'dependencies' => [
                        [
                            'name' => 'ollama',
                            'status' => 'degraded',
                            'required' => false,
                            'model' => 'llama3:8b',
                            'latency_ms' => null,
                            'message' =>
                                'The local Ollama service is unavailable.',
                        ],
                    ],
                    'request_id' => $requestId,
                ]);
            }
        );

        $this
            ->actingAs(
                $this->administrator()
            )
            ->get(
                route('admin.dashboard')
            )
            ->assertOk()
            ->assertSee(
                'Degraded'
            )
            ->assertSee(
                'The FastAPI inference service is ready, but the configured Ollama dependency is degraded.'
            );
    }

    public function test_fastapi_failure_does_not_break_administrator_dashboard(): void
    {
        Http::fake([
            '*' => Http::response(
                [
                    'error' => [
                        'code' => 'unavailable',
                    ],
                ],
                503
            ),
        ]);

        $this
            ->actingAs(
                $this->administrator()
            )
            ->get(
                route('admin.dashboard')
            )
            ->assertOk()
            ->assertSee(
                'Degraded'
            )
            ->assertSee(
                'The FastAPI health endpoint returned an unavailable state.'
            );
    }

    private function administrator(): User
    {
        return $this
            ->enableConfirmedTwoFactorAuthentication(
                $this->userWithRole(
                    RoleName::Administrator
                )
            );
    }

    private function userWithRole(
        RoleName $role
    ): User {
        $user = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $user->assignRole(
            $role->value
        );

        return $user;
    }
}
