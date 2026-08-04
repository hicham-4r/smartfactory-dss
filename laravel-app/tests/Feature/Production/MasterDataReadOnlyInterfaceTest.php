<?php

namespace Tests\Feature\Production;

use App\Enums\RoleName;
use App\Models\ProductionLine;
use App\Models\User;
use Database\Seeders\ProductionMasterDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataReadOnlyInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            RolesAndPermissionsSeeder::class
        );

        $this->seed(
            ProductionMasterDataSeeder::class
        );
    }

    public function test_secured_administrator_can_view_master_data_overview(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->get('/admin/master-data');

        $response
            ->assertOk()
            ->assertSeeText(
                'Production master data'
            )
            ->assertSeeText('16')
            ->assertSeeText('21')
            ->assertHeader(
                'Pragma',
                'no-cache'
            );
    }

    public function test_operator_cannot_view_administrator_master_data(): void
    {
        $operator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $operator->assignRole(
            RoleName::Operator->value
        );

        $response = $this
            ->actingAs($operator)
            ->get('/admin/master-data');

        $response->assertForbidden();
    }

    public function test_product_search_is_validated_and_filtered(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->get(
                '/admin/master-data/products?q=Orange'
            );

        $response
            ->assertOk()
            ->assertSeeText('VP-ORANGE-1L')
            ->assertSeeText('VE-ORANGE-NECTAR-1L')
            ->assertDontSeeText('VL-BANANA-200');
    }

    public function test_machine_list_can_be_filtered_by_production_line(): void
    {
        $administrator =
            $this->administrator();

        $line = ProductionLine::query()
            ->where('code', 'LINE-01')
            ->firstOrFail();

        $response = $this
            ->actingAs($administrator)
            ->get(
                '/admin/master-data/machines'
                .'?production_line_id='
                .$line->getKey()
            );

        $response
            ->assertOk()
            ->assertSeeText('LINE-01-PAST')
            ->assertSeeText('LINE-01-PACK')
            ->assertDontSeeText('LINE-02-PAST');
    }

    public function test_operator_page_does_not_display_private_contact_values(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->get(
                '/admin/master-data/operators'
            );

        $response
            ->assertOk()
            ->assertSeeText('SIM-OP-01')
            ->assertSeeText(
                'Simulated Operator 01'
            )
            ->assertDontSee(
                'sim.operator01@smartfactory.test'
            );
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->get(
                '/admin/master-data/products'
                .'?status=deleted'
            );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors(
                'status'
            );
    }

    public function test_master_data_interface_has_no_write_endpoint(): void
    {
        $administrator =
            $this->administrator();

        $response = $this
            ->actingAs($administrator)
            ->post(
                '/admin/master-data/products',
                [
                    'name' => 'Unauthorized product',
                ]
            );

        $response->assertStatus(405);
    }

    private function administrator(): User
    {
        $administrator = User::factory()->create([
            'is_active' => true,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $administrator->assignRole(
            RoleName::Administrator->value
        );

        return $this
            ->enableConfirmedTwoFactorAuthentication(
                $administrator
            );
    }
}