<?php

namespace App\Models;

use Igniter\Cart\Models\Order;

class PosOrder extends Order
{
    public function getForeignKey()
    {
        return 'order_id';
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
