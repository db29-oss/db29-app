@include('header', ['title' => 'DB29 - LOGIN'])
<body>
  <div>
    <h2>{{ mb_ucfirst(__('trans.login')) }}</h2>
    <form method="POST" action="{{ route('login') }}">
      @csrf
      <div>
        <input class="w-40" id="login_id" type="text" name="login_id" required placeholder="login_id"></input>

        <button type="submit">{{ __('trans.login') }}</button>
      </div>
    </form>

    <h2>{{ mb_ucfirst(__('trans.register')) }}</h2>
    <form method="POST" action="{{ route('post-register') }}">

      @csrf

      <div>
        <button type="submit">{{ __('trans.register') }}</button>
      </div>
    </form>
  </div>
</body>
