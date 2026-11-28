<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order #{{ $order->order_id }}</title>
    <style>
        body { font: 16px system-ui, sans-serif; max-width: 56rem; margin: auto; padding: 1rem; color: #202124; }
        a, button { font: inherit; }
        .button { display: inline-block; padding: .7rem 1rem; border: 1px solid #444; border-radius: .4rem; background: white; color: #111; text-decoration: none; }
        .primary { background: #d83a00; color: white; border-color: #d83a00; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #ddd; }
        td.num, th.num { text-align: right; }
        .actions { display: flex; flex-wrap: wrap; gap: .5rem; }
    </style>
</head>
<body>
    <h1>Order #{{ $order->order_id }} &middot; {{ $order->first_name }}</h1>
    <p>Table: {{ $tableName ?? '<none>' }}</p>
    <p>Status: {{ $order->processed ? 'Paid' : 'Unpaid' }}</p>

    @if($items->isEmpty())
        <p>No items on this order yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ currency_format($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2">Total</th>
                    <th class="num">{{ currency_format($order->order_total) }}</th>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="actions">
        <a class="button" href="{{ restaurant_url('local/menus') }}">Menu</a>
        <a class="button" href="{{ route('pos.orders') }}">All orders</a>
    </div>
</body>
</html>
