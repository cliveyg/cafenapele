@if(controller()->getPage()?->getId() === 'home')
    <div>
        <a class="btn w-100 btn-primary btn-lg" href="{{ restaurant_url('local/menus') }}">
            @lang('igniter.orange::default.menu_menu')
        </a>
        <a class="btn w-100 btn-outline-secondary btn-lg mt-2" href="{{ route('pos.orders') }}">
            View orders
        </a>
    </div>
@else
    @include('cafenapele.original-local-search')
@endif
