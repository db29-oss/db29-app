@include('header')

@if (count($instances) === 1 && $instances[0]->status !== 'rt_up')
<!--explain bubble color-->
<div class="text-gray-400 pointer-events-none select-none">
  <svg width="10" height="10" xmlns="http://www.w3.org/2000/svg">
    <circle cx="5" cy="5" r="5" fill="darkgrey"/>
  </svg>
  {{ __('trans.explain_darkgrey') }}
</div>

<div class="text-gray-400 pointer-events-none select-none">
  <svg width="10" height="10" xmlns="http://www.w3.org/2000/svg">
    <circle cx="5" cy="5" r="5" fill="coral"/>
  </svg>
  {{ __('trans.explain_coral') }}
</div>

<div class="text-gray-400 pointer-events-none select-none">
  <svg width="10" height="10" xmlns="http://www.w3.org/2000/svg">
    <circle cx="5" cy="5" r="5" fill="lime"/>
  </svg>
  {{ __('trans.explain_lime') }}
</div>
@endif

@foreach ($instances as $instance)
@if ($instance->status === 'queue')
<!--reload page every 5s if there was instance queue/queue_active-->
  <script>
    setTimeout(() => {
      window.location.reload();
    }, 5000);
  </script>
  @break
@elseif ($instance->queue_active)
<!--reload page every 10s if there was instance queue/queue_active-->
  <script>
    setTimeout(() => {
      window.location.reload();
    }, 10_000);
  </script>
  @break
@endif
@endforeach

<div>
  @foreach ($sn_ii_map as $source_name => $instance_ids)
    <h3>{{ str($source_name)->upper() }}</h3>

    @foreach ($instances as $instance)
      @if (array_key_exists($instance->id, $instance_ids))
      <div class="pb-3">
        <svg width="10" height="10" xmlns="http://www.w3.org/2000/svg">
          <circle cx="5" cy="5" r="5"
          @if ($instance->queue_active)
            fill="coral"
          @elseif ($instance->status === 'queue' || $instance->status === 'ct_dw')
            fill="darkgrey"
          @elseif ($instance->status === 'rt_up')
            fill="lime"
          @else
            fill="coral"
          @endif
          />
        </svg>
        <a class="inline-block"
          @if ($instance->status === 'rt_up')
          @if (array_key_exists('domain', json_decode($instance->extra, true)['reg_info']))
          href="https://{{ json_decode($instance->extra, true)['reg_info']['domain'] }}"
          @else
          href="https://{{ $instance->subdomain.'.'.config('app.domain') }}"
          target="_blank"
          @endif
          @endif
        >
          @if (array_key_exists('domain', json_decode($instance->extra, true)['reg_info']))
          {{ json_decode($instance->extra, true)['reg_info']['domain'] }}
          @else
          {{ $instance->subdomain.'.'.config('app.domain') }}
          @endif
        </a>
        @if ($instance->queue_active)
        <span class="text-gray-400 pointer-events-none select-none">({{ __('trans.wait_a_sec') }})</span>
        @endif

        @if (! $instance->queue_active)
        <a href="{{ route('edit-instance', ['instance_id' => $instance->id]) }}" class="inline-block">
          <button class="inline-block">{{ __('trans.edit') }}</button>
        </a>

        @if ($instance->status === 'ct_dw')
        <form class="inline" method="POST" action="{{ route('turn-on-instance') }}">
          @csrf
          <input hidden name="instance_id" value="{{ $instance->id }}"/>
          <button type="submit">{{ __('trans.turn_on') }}</button>
        </form>

        <form class="inline" method="POST" action="{{ route('delete-instance') }}">
          @csrf
          <input hidden name="instance_id" value="{{ $instance->id }}"/>
          <button type="submit" onclick="if (! window.confirm('{{ __('trans.confirm').' '.__('trans.delete').' '.$instance->subdomain.'.'.config('app.domain').'?' }}')) { event.preventDefault(); }">
            {{ __('trans.delete') }}
          </button>
        </form>
        @elseif ($instance->status === 'rt_up')
        <form class="inline" method="POST" action="{{ route('turn-off-instance') }}">
          @csrf
          <input hidden name="instance_id" value="{{ $instance->id }}"/>
          <button type="submit" onclick="if (! window.confirm('{{ __('trans.confirm').' '.__('trans.turn_off').' '.$instance->subdomain.'.'.config('app.domain').'?' }}')) { event.preventDefault(); }">
            {{ __('trans.turn_off') }}
          </button>
        </form>
        @endif

        @endif

        @if (
          $instance->status === 'rt_up' &&
          auth()->user()->is_new &&
          ! $instance->queue_active
        )
        <!--tutorial for new user-->
        <div class="text-gray-400 pointer-events-none select-none pl-28">↑</div>
        <div class="text-gray-400 pointer-events-none select-none pl-14">
          {{ __('trans.click_here') }}
        </div>
        <!--end tutorial-->
        @endif
      </div>
      @endif
    @endforeach
  @endforeach
</div>
