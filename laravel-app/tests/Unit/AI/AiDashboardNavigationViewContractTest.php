<?php

namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;

final class AiDashboardNavigationViewContractTest extends TestCase
{
    public function test_role_aware_ai_navigation_partial_is_safe_and_complete(): void
    {
        $root = dirname(__DIR__, 3);
        $partial = $this->contents(
            $root.'/resources/views/ai/insights/partials/navigation-card.blade.php'
        );

        $this->assertStringContainsString(
            "route('ai-insights.index')",
            $partial
        );
        $this->assertStringContainsString(
            "route('reports.index')",
            $partial
        );
        $this->assertStringContainsString(
            'ViewProductionManagerDashboard',
            $partial
        );
        $this->assertStringContainsString(
            'ViewProductionSupervisorDashboard',
            $partial
        );
        $this->assertStringContainsString(
            'ViewMaintenanceManagerDashboard',
            $partial
        );
        $this->assertStringContainsString(
            'ViewAdministratorDashboard',
            $partial
        );
        $this->assertStringContainsString(
            'simulated_prototype',
            $partial
        );
    }

    /**
     * @dataProvider hostViewProvider
     */
    public function test_ai_navigation_is_mounted_in_existing_workspaces(
        string $relativePath,
        string $context,
    ): void {
        $root = dirname(__DIR__, 3);
        $contents = $this->contents($root.'/'.$relativePath);

        $this->assertStringContainsString(
            'STEP21O_AI_NAVIGATION_START',
            $contents
        );
        $this->assertStringContainsString(
            "['context' => '{$context}']",
            $contents
        );
        $this->assertSame(
            1,
            substr_count($contents, 'STEP21O_AI_NAVIGATION_START')
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function hostViewProvider(): iterable
    {
        yield 'role-aware dashboard' => [
            'resources/views/dashboard.blade.php',
            'dashboard',
        ];

        yield 'administrator dashboard' => [
            'resources/views/admin/dashboard.blade.php',
            'administrator',
        ];

        yield 'existing reports workspace' => [
            'resources/views/reports/production/index.blade.php',
            'reports',
        ];
    }

    private function contents(string $path): string
    {
        $contents = file_get_contents($path);

        $this->assertNotFalse(
            $contents,
            "Unable to read expected view: {$path}"
        );

        return $contents;
    }
}
