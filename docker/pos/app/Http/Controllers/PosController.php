<?php

namespace App\Http\Controllers;

use App\Models\PosOrder;
use App\Models\PosStock;
use Igniter\Cart\Classes\CartManager;
use Igniter\Cart\Classes\OrderManager;
use Igniter\Cart\Models\Menu;
use Igniter\Cart\Models\MenuItemOptionValue;
use Igniter\Cart\Models\MenuOptionValue;
use Igniter\Cart\Models\Order;
use Igniter\Cart\Models\Stock;
use Igniter\Local\Facades\Location;
use Igniter\Reservation\Models\DiningTable;
use Igniter\User\Facades\AdminAuth;
use Igniter\User\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PosController extends Controller
{
    private const array MANAGER_GROUPS = ['managers', 'owners'];

    public function index(): View
    {
        $orders = DB::table('cafenapele_pos_orders as pos')
            ->join('orders', 'orders.order_id', '=', 'pos.order_id')
            ->leftJoin('dining_tables', 'dining_tables.id', '=', 'pos.dining_table_id')
            ->leftJoin('admin_users as staff', 'staff.user_id', '=', 'pos.staff_id')
            ->where('orders.location_id', Location::getId())
            ->select('orders.order_id', 'orders.first_name', 'orders.order_total', 'orders.processed',
                'pos.version', 'pos.staff_id', 'pos.dining_table_id', 'dining_tables.name as table_name',
                'staff.name as staff_name')
            ->orderByDesc('orders.order_id')->get()
            ->map(function ($order): object {
                $order->can_manage = $this->canManageOrder($order->staff_id);

                return $order;
            });

        return view('pos.orders', compact('orders'));
    }

    public function show(int $order): View
    {
        $pos = DB::table('cafenapele_pos_orders')->where('order_id', $order)->first();
        $orderRow = DB::table('orders')->where('order_id', $order)
            ->where('location_id', Location::getId())->first();
        abort_unless($pos && $orderRow, 404);

        return view('pos.order', [
            'order' => $orderRow,
            'pos' => $pos,
            'tableName' => $pos->dining_table_id
                ? DB::table('dining_tables')->where('id', $pos->dining_table_id)->value('name') ?? 'Removed table'
                : null,
            'items' => self::orderItems($order),
        ]);
    }

    private function canManageOrder(?int $staffId): bool
    {
        $staff = AdminAuth::user();
        if (!$staff) {
            return false;
        }
        if ($staff->isSuperUser() || ($staffId !== null && (int) $staffId === (int) $staff->getKey())) {
            return true;
        }

        return $staff->groups
            ->pluck('user_group_name')
            ->contains(fn (string $group): bool => in_array(strtolower($group), self::MANAGER_GROUPS, true));
    }

    public static function tables(int $locationId): array
    {
        return DiningTable::query()
            ->where('is_enabled', true)
            ->whereHas('dining_area', fn ($query) => $query->where('location_id', $locationId))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public static function draftToken(Request $request): string
    {
        if (!$request->session()->has('pos.token')) {
            $request->session()->put('pos.token', (string) Str::uuid());
        }

        return $request->session()->get('pos.token');
    }

    public function newOrder(Request $request, CartManager $cartManager): RedirectResponse
    {
        $cartManager->getCart()->destroy();
        $request->session()->forget('pos');

        return redirect(restaurant_url('local/menus'));
    }

    public function edit(Request $request, int $order, CartManager $cartManager): RedirectResponse
    {
        $pos = DB::table('cafenapele_pos_orders')->where('order_id', $order)->first();
        $existing = Order::findOrFail($order);
        abort_unless($pos && (int) $existing->location_id === (int) Location::getId(), 404);
        if ($existing->processed) {
            return redirect()->route('pos.orders')->with('error', 'Paid orders cannot be edited.');
        }
        if (!$this->canManageOrder($pos->staff_id)) {
            return redirect()->route('pos.orders')->with('error', 'Only the server who opened this order, or a manager or owner, can edit it.');
        }
        if ($cartManager->getCart()->count()) {
            return redirect()->route('pos.orders')->with('error', 'Add or discard the items on the menu before opening another order.');
        }

        $request->session()->put('pos', [
            'order_id' => $order,
            'version' => $pos->version,
            'token' => (string) Str::uuid(),
            'name' => $existing->first_name,
            'table_id' => $pos->dining_table_id,
        ]);

        return redirect(restaurant_url('local/menus'));
    }

    public function save(Request $request, CartManager $cartManager, OrderManager $orderManager): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:32'],
            'table_id' => ['nullable', 'integer', 'min:1'],
            'token' => ['required', 'uuid'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'version' => ['nullable', 'required_with:order_id', 'integer', 'min:1'],
        ]);
        if (Auth::check()) {
            throw ValidationException::withMessages(['name' => 'Sign out of the customer account before placing a staff order.']);
        }
        $staff = AdminAuth::user();
        if (!$staff) {
            throw ValidationException::withMessages(['name' => 'Log in to the staff admin before placing orders.']);
        }
        $staffId = $staff->getKey();
        if ($data['token'] !== $request->session()->get('pos.token')) {
            throw ValidationException::withMessages(['name' => 'This cart is no longer current. Reload the page.']);
        }
        if ((int) ($data['order_id'] ?? 0) !== (int) $request->session()->get('pos.order_id', 0)
            || (int) ($data['version'] ?? 0) !== (int) $request->session()->get('pos.version', 0)) {
            throw ValidationException::withMessages(['name' => 'This cart is no longer current. Reload the order before saving.']);
        }
        if (!empty($data['table_id']) && !array_key_exists($data['table_id'], self::tables(Location::getId()))) {
            throw ValidationException::withMessages(['table_id' => 'Select an available table.']);
        }
        $cart = $cartManager->getCart();
        $hasItems = $cart->count() > 0;
        if (!$hasItems && empty($data['order_id'])) {
            throw ValidationException::withMessages(['name' => 'Add at least one item before placing the order.']);
        }
        if ($hasItems) {
            $cartManager->validateContents();
            $cartManager->validateLocation();
            $cartManager->validateOrderTime();
        }

        DB::transaction(function () use ($data, $orderManager, $staffId, $cart, $hasItems): void {
            $id = $data['order_id'] ?? null;
            if ($id) {
                $pos = DB::table('cafenapele_pos_orders')->where('order_id', $id)->lockForUpdate()->first();
                $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
                if (!$pos || $order->processed
                    || (int) $pos->version !== (int) $data['version']) {
                    throw ValidationException::withMessages(['name' => 'This order changed or was paid. Reload it before saving.']);
                }
                if (!$this->canManageOrder($pos->staff_id)) {
                    throw ValidationException::withMessages(['name' => 'Only the server who opened this order, or a manager or owner, can change it.']);
                }
                abort_unless((int) $order->location_id === (int) Location::getId(), 404);
            } else {
                if (DB::table('cafenapele_pos_orders')->where('draft_token', $data['token'])->exists()) {
                    return;
                }
                $order = new Order(['first_name' => $data['name'], 'last_name' => '', 'email' => '', 'telephone' => '']);
                $orderManager->applyRequiredAttributes($order);
                $order->save();
                $statusId = setting('default_order_status');
                if (!$statusId || !$order->updateOrderStatus($statusId, ['notify' => false])) {
                    throw ValidationException::withMessages(['name' => 'A default order status must be configured.']);
                }
            }

            if (!$id) {
                $orderManager->saveOrder($order, ['first_name' => $data['name'], 'last_name' => '']);
            } else {
                $order->update(['first_name' => $data['name'], 'last_name' => '']);
                if ($hasItems) {
                    $this->appendItems($order, $cart->content()->all());
                }
            }
            if ($id) {
                DB::table('cafenapele_pos_orders')->where('order_id', $id)->update([
                    'dining_table_id' => $data['table_id'] ?? null,
                    'version' => $pos->version + 1,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('cafenapele_pos_orders')->insert([
                    'order_id' => $order->getKey(),
                    'dining_table_id' => $data['table_id'] ?? null,
                    'staff_id' => $staffId,
                    'draft_token' => $data['token'],
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }, 3);

        $cartManager->getCart()->destroy();
        $request->session()->forget('pos');

        return redirect()->route('pos.orders')->with('success', 'Order saved.');
    }

    /**
     * Adds cart items to an existing order without replacing its current items.
     */
    private function appendItems(Order $order, iterable $cartItems): void
    {
        foreach ($cartItems as $cartItem) {
            $orderMenu = $order->menus()->create([
                'menu_id' => $cartItem->id,
                'name' => $cartItem->name,
                'quantity' => $cartItem->qty,
                'price' => $cartItem->price,
                'subtotal' => $cartItem->subtotal,
                'comment' => $cartItem->comment,
                'option_values' => $cartItem->options,
            ]);

            foreach ($cartItem->options ?? [] as $menuOption) {
                foreach ($menuOption->values as $value) {
                    $freeQty = $value->free_qty ?? 0;
                    $order->menu_options()->create([
                        'order_menu_id' => $orderMenu->getKey(),
                        'menu_option_id' => $menuOption->id,
                        'menu_option_value_id' => $value->id,
                        'order_option_name' => $value->name,
                        'order_option_price' => max(0, $value->qty - $freeQty) > 0 ? $value->price : 0,
                        'quantity' => $value->qty,
                        'free_qty' => $freeQty,
                    ]);
                }
            }
        }

        $order->calculateTotals();
    }

    /**
     * Removes one item, with its options, from an open order.
     */
    public function removeItem(Request $request, int $order, int $orderMenu): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $newVersion = null;
        DB::transaction(function () use ($order, $orderMenu, $data, &$newVersion): void {
            $pos = DB::table('cafenapele_pos_orders')->where('order_id', $order)->lockForUpdate()->first();
            $orderModel = Order::whereKey($order)->lockForUpdate()->firstOrFail();
            abort_unless($pos && (int) $orderModel->location_id === (int) Location::getId(), 404);
            if (!$this->canManageOrder($pos->staff_id)) {
                throw ValidationException::withMessages(['version' => 'Only the server who opened this order, or a manager or owner, can change it.']);
            }
            if ($orderModel->processed) {
                throw ValidationException::withMessages(['version' => 'Paid orders cannot be changed.']);
            }
            if ((int) $pos->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'This order changed. Reload it before removing items.']);
            }
            $item = $orderModel->menus()->whereKey($orderMenu)->firstOrFail();
            $orderModel->menu_options()->where('order_menu_id', $item->getKey())->delete();
            $item->delete();
            $orderModel->calculateTotals();

            $newVersion = $pos->version + 1;
            DB::table('cafenapele_pos_orders')->where('order_id', $order)->update([
                'version' => $newVersion,
                'updated_at' => now(),
            ]);
        }, 3);

        if ((int) $request->session()->get('pos.order_id', 0) === $order) {
            $request->session()->put('pos.version', $newVersion);
        }

        return redirect(restaurant_url('local/menus'))->with('success', 'Item removed.');
    }

    /**
     * @return Collection<int, object>
     */
    public static function orderItems(int $orderId): Collection
    {
        return DB::table('order_menus')->where('order_id', $orderId)
            ->orderBy('order_menu_id')->get();
    }

    public function paid(Request $request, int $order): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($order, $data): void {
            $pos = DB::table('cafenapele_pos_orders')->where('order_id', $order)->lockForUpdate()->first();
            $orderModel = PosOrder::whereKey($order)->lockForUpdate()->firstOrFail();
            abort_unless($pos && (int) $orderModel->location_id === (int) Location::getId(), 404);
            if (!$this->canManageOrder($pos->staff_id)) {
                throw ValidationException::withMessages(['version' => 'Only the server who opened this order, or a manager or owner, can mark it paid.']);
            }
            if ($orderModel->processed) {
                return;
            }
            if ((int) $pos->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'This order changed. Reload the order list before marking it paid.']);
            }

            $this->lockAndValidateStock($orderModel);
            $orderModel->markAsPaymentProcessed();
            DB::table('cafenapele_pos_orders')->where('order_id', $order)->update([
                'version' => $pos->version + 1,
                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->route('pos.orders')->with('success', 'Order marked paid.');
    }

    private function lockAndValidateStock(Order $order): void
    {
        $items = $order->getOrderMenus();
        $options = $order->menu_options;
        $menus = Menu::whereIn('menu_id', $items->pluck('menu_id')->unique())
            ->orderBy('menu_id')->lockForUpdate()->get()->keyBy('menu_id');
        $optionLinks = MenuItemOptionValue::whereIn('menu_option_value_id', $options->pluck('menu_option_value_id')->unique())
            ->orderBy('menu_option_value_id')->lockForUpdate()->get()->keyBy('menu_option_value_id');
        $optionValues = MenuOptionValue::whereIn('option_value_id', $optionLinks->pluck('option_value_id')->unique())
            ->orderBy('option_value_id')->lockForUpdate()->get()->keyBy('option_value_id');

        $quantities = [];
        foreach ($items as $item) {
            $menu = $menus->get($item->menu_id);
            if (!$menu) {
                throw ValidationException::withMessages(['version' => 'An ordered item no longer exists. The order was not marked paid.']);
            }
            $stock = PosStock::lock($menu, $order->location_id);
            $quantities[$stock->id] = ($quantities[$stock->id] ?? 0) + $item->quantity;
        }
        $itemsById = $items->keyBy('order_menu_id');
        foreach ($options as $option) {
            $item = $itemsById->get($option->order_menu_id);
            if (!$item) {
                throw ValidationException::withMessages(['version' => 'An ordered option has no item. The order was not marked paid.']);
            }
            $link = $optionLinks->get($option->menu_option_value_id);
            $value = $link ? $optionValues->get($link->option_value_id) : null;
            if (!$value) {
                throw ValidationException::withMessages(['version' => 'An ordered option no longer exists. The order was not marked paid.']);
            }
            $stock = PosStock::lock($value, $order->location_id);
            $quantities[$stock->id] = ($quantities[$stock->id] ?? 0) + $option->quantity * $item->quantity;
        }
        $stocks = Stock::whereIn('id', array_keys($quantities))->orderBy('id')->lockForUpdate()->get();
        foreach ($stocks as $stock) {
            if (!$stock->checkStock($quantities[$stock->id])) {
                throw ValidationException::withMessages(['version' => 'There is not enough stock to mark this order paid.']);
            }
        }
    }
}
