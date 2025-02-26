@include('header', ['title' => 'DB29 - SOURCE'])
<div>
  @foreach ($sources as $source)
  <div class="mb-2">
    <a>{{ $source->name }}</a>
    -
    <a class="inline-block" href="{{ route('register-instance', ['source' => $source->name]) }}">
      {{ __('trans.create') }}
    </a>
    @if (
      (! array_key_exists('disable_pricing', $user_setting) || ! $user_setting['disable_pricing']) &&
      count($source->plans)
    )
    <a class="text-gray-400 pb-4 pointer-events-none select-none">
      ({{ formatNumberShort($source->plans[0]->price) }}/{{ __('trans.day') }})
    </a>
    @endif
  </div>
  @endforeach
</div>
