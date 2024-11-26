<div>
  <pre style="color: red">{{ __('trans.save_login_id') }}.</pre>
  <pre>login_id: {{ $user->login_id }}</pre>
  <pre><a href="{{ route('login') }}">{{ mb_ucfirst(__('trans.login')) }}</a></pre>
</div>
