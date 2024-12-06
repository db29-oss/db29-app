@include('header', ['title' => 'DB29 - SOURCE'])

<div>
  <div>
    {{ __('trans.change_default_config_at') }}
    <a href={{ route('account-update') }}>{{ __('trans.account_update') }}</a>
  </div><br>

  <h2>{{ str(request('source'))->upper() }}</h2>
  <form method="POST">
    @csrf

    <label for="email">email</label><br>
    <input type="text" name="email" value="{{ auth()->user()->email }}">
    @error('email')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br><br>

    <label for="password">password</label><br>
    <input type="text" name="password" value="">
    @error('password')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br><br>

    <label for="name">name</label><br>
    <input type="text" name="name" value="{{ auth()->user()->username }}">
    @error('name')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br><br>

    <label for="username">username</label><br>
    <input type="text" name="username" value="{{ auth()->user()->username }}">
    @error('username')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br><br>

    <button type="submit">{{ __('trans.register') }}</button>
  </form>
</div>
