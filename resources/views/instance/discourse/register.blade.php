@include('header', ['title' => 'DB29 - SOURCE'])

<div>
  @if (str()->after(auth()->user()->email, '@') === config('app.domain'))
  <div class="text-gray-400 pointer-events-none select-none">
    {{ __('trans.change_default_config_at') }}
    <a class="pointer-events-auto" href={{ route('prefill') }}>{{ __('trans.prefill') }}</a>
  </div><br>
  @endif

  <h2>{{ str(request('source'))->upper() }}</h2>
  <form method="POST">
    @csrf

    <label class="select-none" for="email">email ({{ __('trans.authenticable') }})</label><br>
    @if (str()->after(auth()->user()->email, '@') === config('app.domain'))
    <input type="text" name="email" value=""><br>
    @else
    <input type="text" name="email" value="{{ auth()->user()->email }}"><br>
    @endif
    @error('email')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <div>
      <button type="submit">{{ __('trans.register') }}</button>
    </div>
  </form>

  <div class="pt-5 text-gray-400">
    <div class="my-2">
    {{ __('trans.discourse_email_explain') }}
    </div>
    <div class="my-2">
    {{ __('trans.discourse_send_email_on_your_behalf') }}
    </div>
    <div class="my-2">
    {{ __('trans.discourse_send_verification_link') }}
    </div>
    <div class="my-2">
    {{ __('trans.discourse_change_email_later') }}
    </div>
  </div>

  @if ($i_s_count > 0)
  <div class="pt-5 text-gray-400 pointer-events-none select-none">
    ({{ __('trans.current_instance_have', ['count' => $i_s_count]) }})
  </div>
  @endif
</div>
