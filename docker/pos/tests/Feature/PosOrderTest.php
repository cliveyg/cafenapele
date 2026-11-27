<?php

namespace Tests\Feature;

use App\Models\PosOrder;
use Igniter\Cart\Models\Order;
use Igniter\System\Models\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
}
