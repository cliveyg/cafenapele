{{-- Always returns to the home page; the page builder's target page is ignored. --}}
<div class="mb-3" wire:ignore>
    <a
        class="text-decoration-none"
        href="{{ page_url('home') }}"
    >
        <i class="fa fa-arrow-left-long"></i>&nbsp;&nbsp;
        {{ lang($settings['text']) }}
    </a>
</div>
