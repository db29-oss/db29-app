<!DOCTYPE html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DB29 Dashboard</title>
</head>
<div>
  <pre>username: {{ auth()->user()->username }}</pre>
  <form method="POST" action="{{ route('post-logout') }}">
    @csrf
    <button class="button">{{ __('logout') }}</button>
  </form>

  <div style="margin-top: 1.5rem;"></div>

  <pre><a href="{{ route('registered-service') }}">{{ mb_ucfirst(__('trans.registered_service')) }}</a></pre>
  <pre><a href="{{ route('supported-source') }}">{{ mb_ucfirst(__('trans.supported_source')) }}</a></pre>

  <div style="margin-top: 1.5rem;"></div>

  <pre><a href="{{ route('advanced-feature') }}">{{ mb_ucfirst(__('trans.advanced_feature')) }}</a></pre>

  <div style="margin-top: 1.5rem;"></div>

  <pre><a href="{{ route('account-update') }}">{{ mb_ucfirst(__('trans.account_update')) }}</a></pre>
  <pre><a href="{{ route('faq') }}">{{ mb_ucfirst(__('trans.faq')) }}</a></pre>

</div>
