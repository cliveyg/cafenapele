<?php

namespace Tests\Feature;

use App\Http\Controllers\PosController;
use Igniter\Cart\CartCondition;
use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Order;
use Igniter\Local\Facades\Location;
use Igniter\User\Facades\AdminAuth;
use Igniter\User\Models\User;
use Igniter\User\Models\UserGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PosCartRestoreTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $staff = User::factory()->create(['password' => Hash::make('password')]);
        $staff->groups()->attach(UserGroup::factory()->create(['user_group_name' => 'Managers']));
        AdminAuth::login($staff);
    }

    public function test_starting_a_new_order_clears_previous_cart_condition_data(): void
    {
        $cart = app('cart')->instance('restore-test');
        $condition = new class(['name' => 'example', 'cartInstance' => 'restore-test']) extends CartCondition {};
        $cart->loadCondition($condition);
        $condition->setMetaData('amount', 5);
        $cart->add(['id' => 999, 'name' => 'Item', 'price' => 10, 'qty' => 1]);
        $manager = $this->createMock(CartManager::class);
        $manager->expects($this->once())->method('getCart')->willReturn($cart);
        $request = Request::create('/pos/new', 'POST');
        $request->setLaravelSession($store = $this->app['session']->driver());
        $store->put('pos', ['name' => 'Old order']);

        (new PosController)->newOrder($request, $manager);

        $this->assertSame(0, $cart->count());
        $this->assertSame([], $condition->getMetaData());
        $this->assertFalse($store->has('pos'));
    }

    public function test_edit_rejects_an_order_whose_original_total_cannot_be_restored(): void
    {
        $menu = Menu::factory()->create();
        $order = Order::factory()->create();
        $order->cart = [[
            'id' => $menu->getKey(), 'name' => 'Meal', 'qty' => 1,
            'price' => 10, 'options' => [], 'conditions' => [], 'subtotal' => 10,
        ]];
        $order->order_total = 5;
        $order->processed = false;
        $order->saveQuietly();
        DB::table('cafenapele_pos_orders')->insert([
            'order_id' => $order->getKey(), 'version' => 1,
            'draft_token' => '3c69d6e5-05bb-4860-b62d-3d3de2688f30',
        ]);
        Location::shouldReceive('getId')->andReturn($order->location_id);
        Location::shouldReceive('current')->andReturnNull();
        $cart = app('cart')->instance('restore-test');
        $cart->destroy();
        $manager = $this->createMock(CartManager::class);
        $manager->expects($this->atLeastOnce())->method('getCart')->willReturn($cart);
        $request = Request::create('/pos/orders/'.$order->getKey().'/edit', 'POST');
        $request->setLaravelSession($this->app['session']->driver());

        $response = (new PosController)->edit($request, $order->getKey(), $manager);

        $this->assertStringContainsString('original order total', $response->getSession()->get('error'));
        $this->assertSame(0, $cart->count());
        $this->assertSame(5.0, $order->fresh()->order_total);
    }
}
