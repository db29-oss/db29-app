<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DB29 - mã nguồn hỗ trợ</title>
</head>
<div>
  @foreach ($sources as $source)
  <pre><a>{{ $source->name }}</a> - <a href="{{ $source->source_link }}">{{ __('trans.source_link') }}</a> - <a href="{{ $source->demo_link }}">demo</a></pre>
  @endforeach
</div>
