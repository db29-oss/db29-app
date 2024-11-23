<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DB29 - mã nguồn hỗ trợ</title>

  <style>
    form {
      display: inline;
    }

    body {
      font-family: monospace;
    }
  </style>
</head>
<body>
  <div>
    @foreach ($sources as $source)
    <div>
      <a>{{ $source->name }}</a>
      -
      <a href="{{ route('register-instance', ['source' => $source->name]) }}">
        {{ __('trans.create') }}
      </a>
    </div>
    @endforeach
  </div>
</body>
</html>
