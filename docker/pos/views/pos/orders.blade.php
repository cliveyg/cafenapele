<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders</title>
    <style>
        body { font: 16px system-ui, sans-serif; max-width: 56rem; margin: auto; padding: 1rem; color: #202124; }
        a, button { font: inherit; }
        button, .button { display: inline-block; padding: .7rem 1rem; border: 1px solid #444; border-radius: .4rem; background: white; color: #111; text-decoration: none; cursor: pointer; }
        .primary { background: #d83a00; color: white; border-color: #d83a00; }
        .order { border: 1px solid #ddd; border-radius: .5rem; padding: 1rem; margin: 1rem 0; }
        .actions { display: flex; flex-wrap: wrap; gap: .5rem; }
        .error { color: #a00000; }
        .success { color: #166534; }
    </style>
</head>
<body>
    <h1>Orders</h1>
    @if(session('success')) <p class="success">{{ session('success') }}</p> @endif
    @if(session('error')) <p class="error">{{ session('error') }}</p> @endif
    @if($errors->any()) <p class="error">{{ $errors->first() }}</p> @endif
    <a class="button" href="{{ restaurant_url('local/menus') }}">Menu</a>
    <form method="post" action="{{ route('pos.new') }}" style="display:inline" onsubmit="return confirm('Discard the items currently on the menu and start a new order?')">
        @csrf
        <button class="primary" type="submit">New order</button>
    </form>
    @forelse($orders as $order)
        <article class="order">
            <h2>#{{ $order->order_id }} &middot; {{ $order->first_name }}</h2>
            <p>Table: {{ $order->dining_table_id ? ($order->table_name ?? 'Removed table') : '<none>' }}</p>
            <p>{{ currency_format($order->order_total) }} &middot; {{ $order->processed ? 'Paid' : 'Unpaid' }}</p>
            <p>Opened by {{ $order->staff_name ?? 'unknown' }}</p>
            @if(!$order->processed && $order->can_manage)
                <div class="actions">
                    <form method="post" action="{{ route('pos.edit', $order->order_id) }}">
                        @csrf
                        <button type="submit">Edit order</button>
                    </form>
                    <form method="post" action="{{ route('pos.paid', $order->order_id) }}" onsubmit="return confirm('Mark this order as paid externally?')">
                        @csrf
                        <input type="hidden" name="version" value="{{ $order->version }}">
                        <button class="primary" type="submit">Mark paid</button>
                    </form>
                </div>
            @endif
        </article>
    @empty
        <p>No orders yet.</p>
    @endforelse
</body>
</html>
