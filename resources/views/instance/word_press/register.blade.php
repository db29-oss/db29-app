<div>
  <h2>{{ str(request('source'))->upper() }}</h2>
  <form method="POST">
    @csrf

    @include('instance.user_own_server')

    <button type="submit">{{ __('trans.register') }}</button>
  </form>
</div>
