<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DB29 - Dịch vụ đã đăng ký</title>

  <style>
    form {
      display: inline;
    }

    body {
      font-family: monospace;
    }
  </style>
</head>
<body>
  <div>
    @foreach ($sn_ii_map as $source_name => $instance_ids)
      <h3>{{ str($source_name)->upper() }}</h3>

      @foreach ($instances as $instance)
        @if (array_key_exists($instance->id, $instance_ids))
          <svg width="10" height="10" xmlns="http://www.w3.org/2000/svg">
            <circle cx="5" cy="5" r="5"
            @if ($instance->status === 'queue')
              fill="darkgrey"
              @elseif ($instance->status === 'rt_up')
              fill="lime"
              @else
              fill="coral"
            @endif
            />
          </svg>
          {{ $instance->subdomain.'.'.config('app.domain') }}
          @if (! $instance->queue_active)
          <form method="POST">
            @csrf

            @method('DELETE')

            <input hidden name="instance_id" value="{{ $instance->id }}"/>

            <button onclick="window.confirm('{{ __('trans.confirm').' '.__('trans.delete').'?' }}')">
              {{ __('trans.delete') }}
            </button>
          </form>
          @endif
          <br>
        @endif
      @endforeach
    @endforeach
  </div>
</body>
</html>
