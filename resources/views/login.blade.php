<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DB29 Login</title>
</head>
<body>
  <div>
    <h2>{{ mb_ucfirst(__('trans.login')) }}</h2>
    <form method="POST" action="{{ route('login') }}">
      <div>
        <label for="login_id">login_id</label>
        <input type="text" id="login_id" name="login_id" required>
      </div>

      @csrf

      <br>

      <div>
        <button type="submit">{{ __('trans.login') }}</button>
      </div>
    </form>

    <br>

    <h2>{{ mb_ucfirst(__('trans.register')) }}</h2>
    <form method="POST" action="{{ route('post-register') }}">

      @csrf

      <div>
        <button type="submit">{{ __('trans.register') }}</button>
      </div>
    </form>
  </div>
</body>
</html>
