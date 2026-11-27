<?php

namespace Tests\Feature;

use App\Http\Controllers\PosController;
use Igniter\Cart\CartCondition;
use Igniter\Cart\Classes\CartManager;
use Igniter\User\Facades\AdminAuth;
use Igniter\User\Models\User;
use Igniter\User\Models\UserGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
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
}
