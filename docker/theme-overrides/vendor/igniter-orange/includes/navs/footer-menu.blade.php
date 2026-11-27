@if(controller()->getPage()?->getId() === 'home')
    @php
        $locationsUrl = controller()->pageUrl('locations');
        $menuItems = array_map(function ($group) use ($locationsUrl) {
            $group = clone $group;
            $group->items = array_values(array_filter(
                $group->items,
                fn ($item) => $item->url !== $locationsUrl,
            ));

            return $group;
        }, $menuItems);
    @endphp
@endif

@include('cafenapele.original-footer-menu', ['menuItems' => $menuItems])
