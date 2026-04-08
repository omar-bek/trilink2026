@extends('layouts.app')

@section('title', 'TriLink Trading - The Complete Digital Trade & Procurement Ecosystem')

@section('content')

    {{-- Scoped design tokens (#0B0E14 canvas, #94A3B8 secondary, white icons) --}}
    <div class="landing-page">
        <x-landing.navbar />
        <x-landing.hero />
        <x-landing.features />
        <x-landing.ecosystem />
        <x-landing.how-it-works />
        <x-landing.trust />
        <x-landing.cta />
        <x-landing.footer />
    </div>

@endsection
