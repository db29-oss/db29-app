<style>
  body {
    font-family: monospace;
  }
</style>

<div>
  <div>
    {{ __('trans.change_default_config_at') }}
    <a href={{ route('account-update') }}>{{ __('trans.account_update') }}</a>
  </div><br>

  <h2>{{ str(request('source'))->upper() }}</h2>
  <form method="POST">
    @csrf

    <label for="email">email</label><br>
    <input type="text" id="email" value="{{ auth()->user()->email }}"><br><br>

    <label for="password">password</label><br>
    <input type="text" id="password" value=""><br><br>

    <label for="name">name</label><br>
    <input type="text" id="name" value="{{ auth()->user()->username }}"><br><br>

    <label for="username">username</label><br>
    <input type="text" id="username" value="{{ auth()->user()->username }}"><br><br>

    <button type="submit">{{ __('trans.register') }}</button>
  </form>
</div>
