<?php

namespace Tests\Feature\AI;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class Phase7FinalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_operator_is_excluded_from_ai_explanations_and_workspace(): void
    {
        $operator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        $operator->assignRole(RoleName::Operator->value);

        self::assertFalse(
            $operator->can(
                PermissionName::ViewProductionAiExplanations->value,
            ),
        );
        self::assertFalse(
            $operator->can(
                PermissionName::ViewMaintenanceAiRecommendations->value,
            ),
        );

        $this
            ->actingAs($operator)
            ->get(route('ai-insights.index'))
            ->assertForbidden();
    }

    public function test_explanation_route_is_post_only_and_browser_views_do_not_target_ollama(): void
    {
        $route = app('router')
            ->getRoutes()
            ->getByName('ai-insights.explanations.generate');

        self::assertInstanceOf(Route::class, $route);
        self::assertSame(['POST'], array_values($route->methods()));

        $viewPaths = [
            resource_path('views/ai/insights/index.blade.php'),
            resource_path('views/ai/insights/partials/result.blade.php'),
            resource_path('views/ai/insights/partials/explanation.blade.php'),
        ];

        foreach ($viewPaths as $viewPath) {
            $source = File::get($viewPath);

            self::assertStringNotContainsString('127.0.0.1:11434', $source);
            self::assertStringNotContainsString('/api/chat', $source);
            self::assertStringNotContainsString('/api/generate', $source);
        }
    }
}
