@include('header')

<div>
  <h3>{{ str(__('trans.server'))->upper() }}</h3>
  @foreach ($machines as $machine)
  <div class="inline-block">
  {{ $machine->hostname }}
  </div>
  <button class="cursor-pointer">
    <a class="no-underline text-inherit" href="{{ route('edit-server', ['machine_id' => $machine->id]) }}">
      {{ __('trans.edit') }}
    </a>
  </button>

  <form class="inline" method="POST" action="{{ route('delete-server', ['machine_id' => $machine->id]) }}">
    @csrf
    <input hidden name="machine_id" value="{{ $machine->id }}"/>
    <button type="submit" class="cursor-pointer" onclick="if (! window.confirm('{{ __('trans.confirm').' '.__('trans.delete').'.'.config('app.domain').'?' }}')) { event.preventDefault(); }">{{ __('trans.delete') }}</button>
  </form>
  @endforeach
</div>
