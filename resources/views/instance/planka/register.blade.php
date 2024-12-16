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

    <label class="select-none" for="email">email</label><br>
    <input type="text" name="email" value="{{ auth()->user()->email }}"><br>
    @error('email')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="password">password</label><br>
    <input type="text" name="password" value=""><br>
    @error('password')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="username">username</label><br>
    <input type="text" name="username" value="{{ auth()->user()->username }}"><br>
    @error('username')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="name">name</label><br>
    <input type="text" name="name" value="{{ auth()->user()->name }}"><br>
    @error('name')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <button type="submit">{{ __('trans.register') }}</button>
  </form>

  @if ($i_s_count > 0)
  <div class="pt-5 text-gray-400 pointer-events-none select-none">
    ({{ __('trans.current_instance_have', ['count' => $i_s_count]) }})
  </div>
  @endif
</div>
