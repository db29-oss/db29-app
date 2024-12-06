@include('header', ['title' => 'DB29 - SOURCE'])
<div>
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
