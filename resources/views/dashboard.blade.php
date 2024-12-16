@include('header', ['title' => 'DB29 Dashboard'])
<div>
  <div class="text-base mb-2">{{ auth()->user()->login_id }}</div>
  @if (auth()->user()->instance_count !== 0)
  <div class="mb-2 @if (auth()->user()->credit < 0) text-red-600 @endif">
    <div class="inline-block">
    {{ __('trans.balance') }}: {{ formatNumberShort(auth()->user()->credit) }}
    </div>
    <div class="ml-4 inline-block">
      <a href="{{ route('recharge') }}">{{ __('trans.recharge') }}</a>
    </div>
  </div>
  @endif

  <form method="POST" action="{{ route('post-logout') }}">
    @csrf
    <button>{{ __('logout') }}</button>
  </form>

  <div style="margin-top: 2rem;"></div>

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
    <a href="{{ route('prefill') }}">{{ mb_ucfirst(__('trans.prefill')) }}</a>
  </div>
  @endif

</div>

