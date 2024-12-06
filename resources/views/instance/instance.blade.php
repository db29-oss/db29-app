@include('header')
<div>
  @foreach ($sn_ii_map as $source_name => $instance_ids)
    <h3>{{ str($source_name)->upper() }}</h3>

    @foreach ($instances as $instance)
      @if (array_key_exists($instance->id, $instance_ids))
      <div class="pb-3">
        <svg width="10" height="10" xmlns="http://www.w3.org/2000/svg">
          <circle cx="5" cy="5" r="5"
          @if ($instance->status === 'queue')
            fill="darkgrey"
          @elseif ($instance->queue_active)
            fill="coral"
          @elseif ($instance->status === 'rt_up')
            fill="lime"
          @else
            fill="coral"
          @endif
          />
        </svg>
        <a class="inline-block"
          @if ($instance->status !== 'rt_up')
          href="https://{{ $instance->subdomain.'.'.config('app.domain') }}"
          target="_blank"
          @endif
        >
          {{ $instance->subdomain.'.'.config('app.domain') }}
        </a>
        @if (! $instance->queue_active)
        <form class="inline" method="POST">
          @csrf

          @method('DELETE')

          <input hidden name="instance_id" value="{{ $instance->id }}"/>

          <button onclick="if (! window.confirm('{{ __('trans.confirm').' '.__('trans.delete').' '.$instance->subdomain.'.'.config('app.domain').'?' }}')) { event.preventDefault(); }">
            {{ __('trans.delete') }}
          </button>
        </form>
        @endif
      </div>
      @endif
    @endforeach
  @endforeach
