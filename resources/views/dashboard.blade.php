@include('header', ['title' => 'DB29 Dashboard'])
<div>
  <pre>{{ auth()->user()->login_id }}</pre>
  <form method="POST" action="{{ route('post-logout') }}">
    @csrf
    <button>{{ __('logout') }}</button>
  </form>

  <div style="margin-top: 1.5rem;"></div>

  <pre><a href="{{ route('source') }}">{{ mb_ucfirst(__('trans.source')) }}</a></pre>
  <pre><a href="{{ route('instance') }}">{{ mb_ucfirst(__('trans.instance')) }}</a></pre>
  <pre><a href="{{ route('advanced-feature') }}">{{ mb_ucfirst(__('trans.advanced_feature')) }}</a></pre>
  <pre><a href="{{ route('account-update') }}">{{ mb_ucfirst(__('trans.account_update')) }}</a></pre>

</div>

