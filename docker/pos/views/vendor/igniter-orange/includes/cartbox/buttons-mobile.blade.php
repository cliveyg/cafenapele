@php($openOrderId = session('pos.order_id'))
<div class="fixed-bottom d-block d-lg-none">
    @if($openOrderId)
        <a
            class="btn btn-primary w-100 btn-lg radius-none cart-toggle text-nowrap"
            href="{{ route('pos.orders.show', $openOrderId) }}"
        >
            View order #{{ $openOrderId }}:
            <span class="fw-bold">{{ currency_format(\Illuminate\Support\Facades\DB::table('orders')->where('order_id', $openOrderId)->value('order_total') ?? 0) }}</span>
        </a>
    @else
        <a
            class="btn btn-primary w-100 btn-lg radius-none cart-toggle text-nowrap"
            href="{{ page_url('cart') }}"
        >
            @lang('igniter.orange::default.button_view_cart'):
            <span class="fw-bold">{{ $this->cartTotal }}</span>
        </a>
    @endif
</div>
