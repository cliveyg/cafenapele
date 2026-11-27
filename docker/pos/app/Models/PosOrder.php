<?php

namespace App\Models;

use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOptionValue;
use Igniter\Cart\Models\MenuOptionValue;
use Igniter\Cart\Models\Order;
use Illuminate\Validation\ValidationException;

class PosOrder extends Order
{
    public function getForeignKey()
    {
        return 'order_id';
    }

    public function subtractStock(): void
    {
        foreach ($this->getOrderMenus() as $item) {
            $menu = Menu::whereKey($item->menu_id)->lockForUpdate()->first();
            if (!$menu) {
                throw ValidationException::withMessages(['version' => 'An ordered item no longer exists. The order was not marked paid.']);
            }
            PosStock::lock($menu, $this->location_id)->updateStockSold($this->getKey(), $item->quantity);

            foreach ($this->menu_options->where('order_menu_id', $item->order_menu_id) as $option) {
                $link = MenuItemOptionValue::whereKey($option->menu_option_value_id)->lockForUpdate()->first();
                $value = $link ? MenuOptionValue::whereKey($link->option_value_id)->lockForUpdate()->first() : null;
                if (!$value) {
                    throw ValidationException::withMessages(['version' => 'An ordered option no longer exists. The order was not marked paid.']);
                }
                PosStock::lock($value, $this->location_id)->updateStockSold(
                    $this->getKey(), $option->quantity * $item->quantity,
                );
            }
        }
    }

    public function mailGetRecipients($type): array
    {
        return $type === 'customer' ? [] : parent::mailGetRecipients($type);
    }

    public function mailGetReplyTo($type): array
    {
        return [];
    }
}
