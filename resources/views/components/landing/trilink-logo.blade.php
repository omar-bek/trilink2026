@props(['class' => 'h-10', 'withText' => false])

<img src="{{ asset('logo/logo.png') }}" alt="TriLink Trading" {{ $attributes->merge(['class' => $class . ' object-contain']) }} />
