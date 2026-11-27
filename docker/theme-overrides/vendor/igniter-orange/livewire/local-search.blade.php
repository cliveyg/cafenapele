@if(controller()->getPage()?->getId() === 'home')
    <div>
        <a class="btn w-100 btn-primary btn-lg" href="{{ restaurant_url('local/menus') }}">
            @lang('igniter.orange::default.menu_menu')
        </a>
    </div>
@else
    @include('cafenapele.original-local-search')
@endif
