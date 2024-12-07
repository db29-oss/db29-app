<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title ?? 'DB29 - HOST THE WORLD' }}</title>
  <script src="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.16/lib/index.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.16/base.min.css" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@if (request()->route()->getName() !== 'dashboard' && ! auth()->guest())
<div>
  <pre><a class="select-none" href="{{ route('dashboard') }}"><< {{ __('trans.back') }}</a></pre>
</div>
@endif
