@include('header', ['title' => 'DB29 Dashboard'])
<div>
  <pre>{{ auth()->user()->login_id }}</pre>
  <form method="POST" action="{{ route('post-logout') }}">
    @csrf
    <button>{{ __('logout') }}</button>
  </form>

  <div style="margin-top: 1.5rem;"></div>

  <div class="pb-4">
    <a class="" href="{{ route('source') }}">{{ mb_ucfirst(__('trans.source')) }}</a>
    @if (auth()->user()->instance_count === 0)
    <span class="text-gray-400 pointer-events-none select-none">
      <-- {{ __('trans.click_here') }}
    </span>
    @endif
  </div>
  @if (auth()->user()->instance_count !== 0)
  <div class="pb-4">
    <a href="{{ route('instance') }}">{{ mb_ucfirst(__('trans.instance')) }}</a>
    <span class="text-gray-400 pointer-events-none select-none">
      ({{ auth()->user()->instance_count }})
    </span>
  </div>
  <div class="pb-4">
    <a href="{{ route('account') }}">{{ mb_ucfirst(__('trans.account')) }}</a>
  </div>
  @endif

</div>

