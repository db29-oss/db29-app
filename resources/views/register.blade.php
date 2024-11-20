<div>
  <pre style="color: red">{{ __('trans.username_display_once_warning') }}.</pre>
  <pre>username: {{ $user->username }}</pre>
  <pre><a href="{{ route('login') }}">{{ mb_ucfirst(__('trans.login')) }}</a></pre>
</div>
