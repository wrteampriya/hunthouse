@php
    // Positive/continue actions (default "blue" and "green") use the eBroker
    // brand color instead of Tailwind's stock palette, so the installer wizard
    // matches the rest of the admin theme (--bs-primary: #087C7C in app.css).
    // Errors keep the stock red so they still read as a warning state.
    //
    // Inline style + a tiny scoped <style> block for :hover — deliberately NOT
    // Tailwind classes (bg-brand-primary / hover:bg-brand-primary-dark). Those
    // depend on whichever precompiled stylesheet the installer layout happens
    // to load actually containing those custom rules, on whichever server
    // path/cache is in effect — which repeatedly proved unreliable in
    // practice. Inline styles always render regardless of any external CSS
    // file, path, or cache state.
    $color = $attributes->get('color', 'blue');
    $bg = $color === 'red' ? '#ef4444' : '#087C7C';
    $bgHover = $color === 'red' ? '#b91c1c' : '#066363';
    $btnId = 'installer-btn-'.\Illuminate\Support\Str::random(8);
@endphp
<button
    id="{{ $btnId }}"
    type="{{ $attributes->get('type', 'button') }}"
    class="text-white font-bold py-2 px-4 rounded inline-flex items-center"
    style="background-color: {{ $bg }}; border: none;"
    {{ $attributes->except(['type', 'color']) }}
>
    {{ $slot }}
</button>
<style>
    #{{ $btnId }}:hover { background-color: {{ $bgHover }} !important; }
</style>
