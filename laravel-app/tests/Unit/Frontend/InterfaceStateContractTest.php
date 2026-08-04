<?php

namespace Tests\Unit\Frontend;

use Tests\TestCase;

final class InterfaceStateContractTest extends TestCase
{
    public function test_interface_state_assets_are_registered(): void
    {
        $applicationJavaScript = file_get_contents(
            resource_path('js/app.js')
        );

        $interfaceJavaScript = file_get_contents(
            resource_path(
                'js/smartfactory-interface-states.js'
            )
        );

        $this->assertIsString(
            $applicationJavaScript
        );

        $this->assertIsString(
            $interfaceJavaScript
        );

        $this->assertStringContainsString(
            "import './smartfactory-interface-states';",
            $applicationJavaScript
        );

        $this->assertStringContainsString(
            '[data-sf-loading]',
            $interfaceJavaScript
        );

        $this->assertStringContainsString(
            '[data-sf-drilldown-scope]',
            $interfaceJavaScript
        );
    }

    public function test_standard_error_pages_do_not_render_exception_details(): void
    {
        foreach (
            [
                '403',
                '404',
                '419',
                '429',
                '500',
            ] as $status
        ) {
            $path = resource_path(
                "views/errors/{$status}.blade.php"
            );

            $content = file_get_contents(
                $path
            );

            $this->assertIsString(
                $content
            );

            $this->assertStringNotContainsString(
                '$exception',
                $content
            );

            $this->assertStringNotContainsString(
                'getTrace',
                $content
            );
        }
    }

    public function test_chart_card_exposes_a_detail_action(): void
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
            'Open details',
            $chartCard
        );

        $this->assertStringContainsString(
            '$chartActionUrl',
            $chartCard
        );
    }

    public function test_analytics_filters_use_loading_state(): void
    {
        foreach (
            [
                'analytics/production/index.blade.php',
                'analytics/maintenance/index.blade.php',
                'analytics/quality/index.blade.php',
            ] as $view
        ) {
            $content = file_get_contents(
                resource_path(
                    'views/'.$view
                )
            );

            $this->assertIsString(
                $content
            );

            $this->assertStringContainsString(
                'data-sf-loading',
                $content
            );

            $this->assertStringContainsString(
                'analytics.partials.active-drilldowns',
                $content
            );
        }
    }
}
