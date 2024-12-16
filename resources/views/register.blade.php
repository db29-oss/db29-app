@include('header', ['title' => 'DB29 - LOGIN'])
<div>
  <pre class="text-red-500">{{ __('trans.save_login_id') }}.</pre>
  <div class="text-base font-mono">{{ $user->login_id }}</div>

  <form class="mt-4" method="POST" action="{{ route('login') }}">
    <input type="hidden" id="login_id" name="login_id" value ="{{ $user->login_id }}" required>

    @csrf

    <div>
      <button type="submit">{{ __('trans.login') }}</button>
    </div>
  </form>
</div>
