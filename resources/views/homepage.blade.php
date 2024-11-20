<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DB29 - hosting mã nguồn mở</title>

  <style>
    body {
      font-size: 1.25rem;
      font-family: monospace;
    }
  </style>
</head>
<body>
  <div>
    <a href="{{ route('login') }}">{{ mb_ucfirst(__('trans.login')) }}</a>
    <br>
    <a href="https://forum.db29.ovh">{{ mb_ucfirst(__('trans.blog')) }}</a>
    <br>
    <a href="{{ route('faq') }}">{{ mb_ucfirst(__('trans.question')) }}</a>
  </div>
</body>
</html>
