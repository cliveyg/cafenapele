<?php

namespace App\Models;

use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuOptionValue;
use Igniter\Cart\Models\Stock;
use Illuminate\Validation\ValidationException;

class PosStock
{
    public static function lock(Menu|MenuOptionValue $item, int $locationId): Stock
    {
        $stocks = $item->stocks()->where('location_id', $locationId)->orderBy('id')->lockForUpdate()->get();
        if ($stocks->count() > 1) {
            throw ValidationException::withMessages(['version' => 'An item has duplicate stock records. The order was not marked paid.']);
        }

        return $stocks->first() ?? $item->stocks()->create(['location_id' => $locationId]);
    }
}
