<?php

namespace Tests\Feature;

use App\Http\Controllers\PosController;
use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Classes\OrderManager;
use Igniter\User\Facades\AdminAuth;
use Igniter\User\Models\User;
use Igniter\User\Models\UserGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosStaleCartTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $staff = User::factory()->create(['password' => Hash::make('password')]);
        $staff->groups()->attach(UserGroup::factory()->create(['user_group_name' => 'Managers']));
        AdminAuth::login($staff);
    }

    public function test_zero_is_not_accepted_as_a_table(): void
    {
        $request = Request::create('/pos/orders', 'POST', [
            'name' => 'Alex', 'table_id' => 0, 'token' => 'f3f5b77e-3963-4467-85fd-91a6b80f855c',
        ]);
        $request->setLaravelSession($this->app['session']->driver());
        $cart = $this->createMock(CartManager::class);
        $cart->expects($this->never())->method('validateContents');
        $orders = $this->createMock(OrderManager::class);
        $orders->expects($this->never())->method('saveOrder');

        try {
            (new PosController)->save($request, $cart, $orders);
            $this->fail('A table ID must be positive.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('table_id', $exception->errors());
        }
    }

    public function test_a_stale_form_cannot_use_the_current_session_version(): void
    {
        $this->assertStaleFormRejected(
            ['order_id' => 12, 'version' => 1, 'token' => 'f3f5b77e-3963-4467-85fd-91a6b80f855c'],
            ['order_id' => 12, 'version' => 2, 'token' => 'f3f5b77e-3963-4467-85fd-91a6b80f855c'],
        );
    }

    public function test_a_stale_form_cannot_use_a_reopened_edit_session(): void
    {
        $this->assertStaleFormRejected(
            ['order_id' => 12, 'version' => 1, 'token' => 'f3f5b77e-3963-4467-85fd-91a6b80f855c'],
            ['order_id' => 12, 'version' => 1, 'token' => '3c69d6e5-05bb-4860-b62d-3d3de2688f30'],
        );
    }

    private function assertStaleFormRejected(array $form, array $session): void
    {
        $request = Request::create('/pos/orders', 'POST', $form + ['name' => 'Alex']);
        $request->setLaravelSession($store = $this->app['session']->driver());
        $store->start();
        $store->put('pos', $session);
        $cart = $this->createMock(CartManager::class);
        $cart->expects($this->never())->method('validateContents');
        $orders = $this->createMock(OrderManager::class);
        $orders->expects($this->never())->method('saveOrder');

        try {
            (new PosController)->save($request, $cart, $orders);
            $this->fail('A stale form must not be saved.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('no longer current', $exception->errors()['name'][0]);
        }
    }
}
