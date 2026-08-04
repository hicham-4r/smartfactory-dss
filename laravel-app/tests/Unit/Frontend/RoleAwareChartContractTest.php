<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

final class RoleAwareChartContractTest extends TestCase
{
    public function test_chart_assets_are_local_and_registered_with_vite(): void
    {
        $applicationJavaScript = file_get_contents(
            resource_path('js/app.js')
        );

        $chartJavaScript = file_get_contents(
            resource_path(
                'js/smartfactory-charts.js'
            )
        );

        $this->assertIsString(
            $applicationJavaScript
        );

        $this->assertIsString(
            $chartJavaScript
        );

        $this->assertStringContainsString(
            "import './smartfactory-charts';",
            $applicationJavaScript
        );

        $this->assertStringContainsString(
            '[data-sf-chart]',
            $chartJavaScript
        );

        /*
         * The W3C SVG namespace is required by
         * document.createElementNS(). It is an identifier,
         * not an external network dependency.
         */
        $this->assertStringContainsString(
            "const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';",
            $chartJavaScript
        );

        /*
         * Remove the legitimate SVG namespace before checking
         * for external HTTP or HTTPS dependencies.
         */
        $networkDependencyCheck = str_replace(
            'http://www.w3.org/2000/svg',
            '',
            $chartJavaScript
        );

        $this->assertStringNotContainsString(
            'https://',
            $networkDependencyCheck
        );

        $this->assertStringNotContainsString(
            'http://',
            $networkDependencyCheck
        );
    }

    public function test_role_aware_chart_partials_are_wired_to_the_dashboards(): void
    {
        $dashboard = file_get_contents(
            resource_path(
                'views/dashboard.blade.php'
            )
        );

        $administratorDashboard = file_get_contents(
            resource_path(
                'views/admin/dashboard.blade.php'
            )
        );

        $roleCharts = file_get_contents(
            resource_path(
                'views/dashboard/partials/role-charts.blade.php'
            )
        );

        $this->assertIsString(
            $dashboard
        );

        $this->assertIsString(
            $administratorDashboard
        );

        $this->assertIsString(
            $roleCharts
        );

        $this->assertStringContainsString(
            'dashboard.partials.role-charts',
            $dashboard
        );

        $this->assertStringContainsString(
            'admin.partials.operations-charts',
            $administratorDashboard
        );

        $this->assertStringContainsString(
            '$overview->operatorDashboard !== null',
            $roleCharts
        );

        $this->assertStringContainsString(
            '$overview->productionSupervisor !== null',
            $roleCharts
        );

        $this->assertStringContainsString(
            '$overview->productionManager !== null',
            $roleCharts
        );

        $this->assertStringContainsString(
            '$overview->maintenanceManager !== null',
            $roleCharts
        );
    }

    public function test_chart_card_keeps_an_accessible_data_table_fallback(): void
    {
        $chartCard = file_get_contents(
            resource_path(
                'views/dashboard/partials/chart-card.blade.php'
            )
        );

        $this->assertIsString(
            $chartCard
        );

        $this->assertStringContainsString(
            'View accessible chart data',
            $chartCard
        );

        $this->assertStringContainsString(
            'type="application/json"',
            $chartCard
        );

        $this->assertStringContainsString(
            'data-sf-chart',
            $chartCard
        );
    }
}