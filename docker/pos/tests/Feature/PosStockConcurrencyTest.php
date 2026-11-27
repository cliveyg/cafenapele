<?php

namespace Tests\Feature;

use App\Models\PosStock;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\Order;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosStockConcurrencyTest extends TestCase
{
    public function test_locked_stock_uses_latest_committed_quantity_after_a_snapshot_read(): void
    {
        $order = Order::factory()->create();
        $menu = Menu::factory()->create();
        $stock = $menu->getStockByLocation($order->location_id);
        $stock->is_tracked = true;
        $stock->quantity = 10;
        $stock->save();
        config(['database.connections.pos_other' => config('database.connections.'.config('database.default'))]);

        try {
            DB::transaction(function () use ($menu, $stock, $order): void {
                $this->assertSame(10, $stock->fresh()->quantity);
                DB::connection('pos_other')->table('stocks')->where('id', $stock->id)->update(['quantity' => 7]);

                $locked = PosStock::lock($menu, $order->location_id);
                $this->assertSame(7, $locked->quantity);
                $locked->updateStockSold($order->getKey(), 3);
            });

            $this->assertSame(4, $stock->fresh()->quantity);
        } finally {
            DB::disconnect('pos_other');
            DB::table('stock_history')->where('stock_id', $stock->id)->delete();
            $stock->delete();
            $menu->delete();
            $location = $order->location;
            $order->delete();
            $location?->delete();
        }
    }
}
