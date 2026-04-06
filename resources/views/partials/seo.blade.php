{{-- Reusable SEO + social meta block. Each page can override the defaults
     by yielding into these sections:

       @section('meta_title',       'My Page')
       @section('meta_description', 'A short page-specific summary')
       @section('meta_keywords',    'one, two, three')
       @section('meta_image',       asset('og/my-page.png'))

     Falls back to sensible app-wide defaults pulled from translations + config. --}}

@php
    $brand        = config('app.name', 'TriLink Trading');
    $pageTitle    = trim($__env->yieldContent('meta_title', $__env->yieldContent('title', '')));
    $fullTitle    = $pageTitle !== '' ? "{$pageTitle} — {$brand}" : $brand;
    $description  = trim($__env->yieldContent('meta_description', __('seo.default_description')));
    $keywords     = trim($__env->yieldContent('meta_keywords', __('seo.default_keywords')));
    $image        = trim($__env->yieldContent('meta_image', asset('logo/logo.png')));
    $canonical    = url()->current();
    $locale       = app()->getLocale();
    $ogLocale     = $locale === 'ar' ? 'ar_AE' : 'en_US';
    $alternate    = $locale === 'ar' ? 'en_US' : 'ar_AE';
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<meta name="keywords" content="{{ $keywords }}">
<meta name="author" content="{{ $brand }}">
<meta name="application-name" content="{{ $brand }}">
<meta name="theme-color" content="#2563EB">
{{-- Each layout supplies its own robots directive (public layout = index,
     dashboard layout = noindex). --}}
<link rel="canonical" href="{{ $canonical }}">

{{-- Favicons (use the existing brand logo as the source) --}}
<link rel="icon" type="image/png" href="{{ asset('logo/logo.png') }}">
<link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('logo/logo.png') }}">
<link rel="mask-icon" href="{{ asset('logo/logo.png') }}" color="#2563EB">

{{-- Hreflang for multilingual SEO --}}
<link rel="alternate" hreflang="en" href="{{ url()->current() }}">
<link rel="alternate" hreflang="ar" href="{{ url()->current() }}">
<link rel="alternate" hreflang="x-default" href="{{ url('/') }}">

{{-- Open Graph --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $brand }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:alt" content="{{ $brand }}">
<meta property="og:locale" content="{{ $ogLocale }}">
<meta property="og:locale:alternate" content="{{ $alternate }}">

{{-- Twitter Card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
<meta name="twitter:image:alt" content="{{ $brand }}">

{{-- Organization JSON-LD: helps Google show a knowledge panel for the brand --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Organization',
    'name'     => $brand,
    'url'      => url('/'),
    'logo'     => asset('logo/logo.png'),
    'sameAs'   => array_filter([
        config('app.linkedin_url'),
        config('app.twitter_url'),
        config('app.facebook_url'),
    ]),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
