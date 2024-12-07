@include('header', ['title' => 'DB29 - SOURCE'])
<div>
  @if (auth()->user()->instance_count === 0)
  <div class="text-gray-400 pb-4 pointer-events-none select-none">
    {{ __('trans.select_source_and_click') }}
    <span class="underline">{{ __('trans.create') }}</span>
  </div>
  @endif

  @foreach ($sources as $source)
  <div>
    <a>{{ $source->name }}</a>
    -
    <a href="{{ route('register-instance', ['source' => $source->name]) }}">
      {{ __('trans.create') }}
    </a>
  </div>
  @endforeach
</div>
