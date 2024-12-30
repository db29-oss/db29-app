@include('header', ['title' => 'DB29 - SOURCE'])

<div>
  <h2>{{ str(request('source'))->upper() }}</h2>
  <form method="POST">
    @csrf

    <button type="submit">{{ __('trans.register') }}</button>
  </form>

  @if ($i_s_count > 0)
  <div class="pt-5 text-gray-400 pointer-events-none select-none">
    ({{ __('trans.current_instance_have', ['count' => $i_s_count]) }})
  </div>
  @endif
</div>
