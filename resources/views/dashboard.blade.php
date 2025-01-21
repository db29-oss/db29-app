@include('header', ['title' => 'DB29 Dashboard'])
<div>
  <div class="mb-2">
    <div class="text-base mb-2">{{ auth()->user()->login_id }}</div>
    @if (auth()->user()->instance_count !== 0)
    <div class="@if (auth()->user()->credit < 0) text-red-600 @endif">
      <div class="inline-block">
      {{ __('trans.credit') }}: {{ formatNumberShort(auth()->user()->credit) }}
      </div>
      <div class="ml-4 inline-block">
        <a href="{{ route('recharge') }}">{{ __('trans.recharge') }}</a>
      </div>
    </div>
    @if (auth()->user()->bonus_credit > 0)
    <div>
    {{ __('trans.bonus_credit') }}: {{ formatNumberShort(auth()->user()->bonus_credit) }}
    </div>
    @endif
    @endif
  </div>

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
  <details id="dashboard-machine-details" class="pb-4 select-none">
    <summary><span class="cursor-pointer">{{ mb_ucfirst(__('trans.server')) }}</span></summary>
    <div class="pl-4 list-none mt-2">
      <li class="pb-2"><a href="{{ route('server') }}">{{ __('trans.list') }}</a></li>
      <li class="pb-2"><a href="{{ route('add-server') }}">{{ __('trans.add') }}</a></li>
    </div>
  </details>

  @endif

  <script>
    const details = document.getElementById("dashboard-machine-details");

    function loadDetailsState() {
      const isOpen = localStorage.getItem("details-open") === "true";
      details.open = isOpen;
    }

    function saveDetailsState() {
      localStorage.setItem("details-open", details.open);
    }

    loadDetailsState();

    details.addEventListener("toggle", saveDetailsState);
  </script>

</div>

