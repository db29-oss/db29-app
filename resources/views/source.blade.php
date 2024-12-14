@include('header', ['title' => 'DB29 - SOURCE'])
<div>
  @if (auth()->user()->instance_count === 0 && \App\Models\User::FREE_CREDIT === auth()->user()->credit)
  <div class="text-gray-400 pb-4 pointer-events-none select-none">
    {{ __('trans.explain_plan', ['amount' => formatNumberShort(auth()->user()->credit)]) }}
  </div>
  @endif

  @foreach ($sources as $source)
  <div>
    <a>{{ $source->name }}</a>
    -
    <a class="inline-block" href="{{ route('register-instance', ['source' => $source->name]) }}">
      {{ __('trans.create') }}
    </a>
    @if (count($source->plans))
    <a class="text-gray-400 pb-4 pointer-events-none select-none">({{ formatNumberShort($source->plans[0]->price) }}/{{ __('trans.day') }})</a>
    @endif
  </div>
  @endforeach
</div>
