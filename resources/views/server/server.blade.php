@include('header')

<div>
  <h3>{{ str(__('trans.server'))->upper() }}</h3>
  @foreach ($machines as $machine)
  <div class="inline-block mb-2">
  {{ $machine->hostname }}
  @if ($machine->instances_count > 0)
  <span class="text-gray-400 pointer-events-none select-none">
    ({{ $machine->instances_count.' '.__('trans.instance') }})
  </span>
  @endif
  </div>
  <button>
    <a class="no-underline text-inherit" href="{{ route('edit-server', ['machine_id' => $machine->id]) }}">
      {{ __('trans.edit') }}
    </a>
  </button>

  <form class="inline" method="POST" action="{{ route('delete-server', ['machine_id' => $machine->id]) }}">
    @csrf
    <input hidden name="machine_id" value="{{ $machine->id }}"/>
    <button type="submit" onclick="if (! window.confirm('{{ __('trans.confirm').' '.__('trans.delete').'.'.config('app.domain').'?' }}')) { event.preventDefault(); }">{{ __('trans.delete') }}</button>
  </form><br>
  @endforeach
</div>
