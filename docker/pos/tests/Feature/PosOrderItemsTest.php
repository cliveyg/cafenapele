<?php

namespace Tests\Feature;

use App\Http\Controllers\PosController;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Order;
use Igniter\Local\Facades\Location;
use Igniter\User\Facades\AdminAuth;
use Igniter\User\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosOrderItemsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_another_server_cannot_remove_an_item(): void
    {
        [$order, $items] = $this->orderWithItems($this->staffUser());
        AdminAuth::login($this->staffUser());

        try {
            $this->removeItem($order, $items[0], 1);
            $this->fail('A server who did not open the order must not remove its items.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('version', $exception->errors());
        }

        $this->assertSame(2, DB::table('order_menus')->where('order_id', $order->getKey())->count());
    }

    public function test_removing_an_item_deletes_it_and_recalculates_the_total(): void
    {
        $staff = $this->staffUser();
        [$order, $items] = $this->orderWithItems($staff);
        AdminAuth::login($staff);

        $this->removeItem($order, $items[0], 1);

        $order = $order->fresh();
        $this->assertSame(0, DB::table('order_menus')->where('order_menu_id', $items[0])->count());
        $this->assertSame(7.0, (float) $order->order_total);
        $this->assertSame(2, (int) DB::table('cafenapele_pos_orders')->where('order_id', $order->getKey())->value('version'));
    }

    public function test_the_order_view_lists_the_items_on_the_order(): void
    {
        $staff = $this->staffUser();
        [$order] = $this->orderWithItems($staff);
        AdminAuth::login($staff);

        $view = (new PosController)->show($order->getKey());

        $this->assertCount(2, $view->getData()['items']);
        $this->assertSame($order->getKey(), $view->getData()['order']->order_id);
    }

    // The factory's bcrypt hash uses cost 10, but the test env uses BCRYPT_ROUNDS=4.
    private function staffUser(): User
    {
        return User::factory()->create(['password' => Hash::make('password')]);
    }

    private function removeItem(Order $order, int $orderMenuId, int $version): void
    {
        $request = Request::create('/pos/orders/'.$order->getKey().'/items/'.$orderMenuId.'/remove', 'POST', ['version' => $version]);
        $request->setLaravelSession($this->app['session']->driver());

        (new PosController)->removeItem($request, $order->getKey(), $orderMenuId);
    }

    /**
     * @return array{0: Order, 1: array<int, int>}
     */
    private function orderWithItems(User $staff): array
    {
        $order = Order::factory()->create(['processed' => false, 'order_total' => 17]);
        Location::shouldReceive('getId')->andReturn($order->location_id)->byDefault();
        Location::shouldReceive('current')->andReturnNull()->byDefault();
        Location::shouldReceive('currentOrDefault')->andReturnNull()->byDefault();
        DB::table('cafenapele_pos_orders')->insert([
            'order_id' => $order->getKey(),
            'staff_id' => $staff->getKey(),
            'draft_token' => (string) Str::uuid(),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $items = [];
        foreach ([10, 7] as $subtotal) {
            $items[] = DB::table('order_menus')->insertGetId([
                'order_id' => $order->getKey(),
                'menu_id' => Menu::factory()->create()->getKey(),
                'name' => 'Item '.$subtotal,
                'quantity' => 1,
                'price' => $subtotal,
                'subtotal' => $subtotal,
            ], 'order_menu_id');
        }

        return [$order, $items];
    }
}
