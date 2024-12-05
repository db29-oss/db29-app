<div>
  <pre style="color: red">{{ __('trans.save_login_id') }}.</pre>
  <pre>login_id: {{ $user->login_id }}</pre>

  <form method="POST" action="{{ route('login') }}">
    <input type="hidden" id="login_id" name="login_id" value ="{{ $user->login_id }}" required>

    @csrf

    <div>
      <button type="submit">{{ __('trans.login') }}</button>
    </div>
  </form>
</div>
