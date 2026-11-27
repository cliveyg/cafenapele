<?php

namespace Tests\Feature;

use App\Models\PosOrder;
use App\Models\PosStock;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOption;
use Igniter\Cart\Models\MenuItemOptionValue;
use Igniter\Cart\Models\MenuOptionValue;
use Igniter\Cart\Models\Order;
use Igniter\System\Models\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosOrderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pos_order_suppresses_customer_mail_but_keeps_staff_recipients(): void
    {
        Settings::set(['order_email' => ['customer', 'admin'], 'site_email' => 'staff@example.invalid', 'site_name' => 'Staff']);
        $pos = new PosOrder(['first_name' => 'Alex', 'email' => '']);
        $normal = new Order(['first_name' => 'Alex', 'email' => '']);

        $this->assertSame([], $pos->mailGetRecipients('customer'));
        $this->assertNotEmpty($normal->mailGetRecipients('customer'));
        $this->assertSame($normal->mailGetRecipients('admin'), $pos->mailGetRecipients('admin'));
        $this->assertSame([], $pos->mailGetReplyTo('admin'));
    }

    public function test_pos_order_uses_native_order_relations(): void
    {
        $pos = new PosOrder;

        $this->assertSame('order_id', $pos->menus()->getForeignKeyName());
        $this->assertSame('order_id', $pos->menu_options()->getForeignKeyName());
        $this->assertSame('order_id', $pos->totals()->getForeignKeyName());
    }

    public function test_stock_deduction_counts_options_for_every_ordered_item(): void
    {
        $order = Order::factory()->create();
        $menu = Menu::factory()->create();
        $itemOption = MenuItemOption::factory()->create(['menu_id' => $menu->getKey()]);
        $option = MenuOptionValue::factory()->create(['option_id' => $itemOption->option_id]);
        $itemOptionValue = MenuItemOptionValue::factory()->create([
            'menu_option_id' => $itemOption->getKey(),
            'option_value_id' => $option->getKey(),
        ]);
        foreach ([$menu, $option] as $stockable) {
            $stock = $stockable->getStockByLocation($order->location_id);
            $stock->is_tracked = true;
            $stock->quantity = 10;
            $stock->save();
        }
        $item = $order->menus()->create([
            'menu_id' => $menu->getKey(), 'name' => 'Meal', 'quantity' => 3,
        ]);
        $order->menu_options()->create([
            'order_menu_id' => $item->getKey(),
            'menu_option_id' => $itemOption->getKey(),
            'menu_option_value_id' => $itemOptionValue->getKey(),
            'order_option_name' => 'Extra',
            'quantity' => 2,
        ]);

        PosOrder::findOrFail($order->getKey())->subtractStock();

        $this->assertSame(7, $menu->getStockByLocation($order->location_id)->quantity);
        $this->assertSame(4, $option->getStockByLocation($order->location_id)->quantity);
    }

    public function test_duplicate_stock_records_are_rejected(): void
    {
        $order = Order::factory()->create();
        $menu = Menu::factory()->create();
        $menu->stocks()->create(['location_id' => $order->location_id]);
        $menu->stocks()->create(['location_id' => $order->location_id]);

        $this->expectException(ValidationException::class);
        PosStock::lock($menu, $order->location_id);
    }
}
