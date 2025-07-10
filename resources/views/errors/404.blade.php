@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-16 text-center">
    <h1 class="text-6xl font-bold text-gray-300 mb-4">404</h1>
    <h2 class="text-3xl font-bold mb-4">{{ __('Page Not Found') }}</h2>
    <p class="text-gray-600 mb-8">{{ __('The page you are looking for does not exist.') }}</p>
    <a href="/" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700">
        {{ __('Go to Home') }}
    </a>
</div>
@endsection