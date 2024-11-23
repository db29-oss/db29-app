<div>
  <pre style="color: red">{{ __('trans.login_id_display_once_warning') }}.</pre>
  <pre>login_id: {{ $user->login_id }}</pre>
  <pre><a href="{{ route('login') }}">{{ mb_ucfirst(__('trans.login')) }}</a></pre>
</div>
