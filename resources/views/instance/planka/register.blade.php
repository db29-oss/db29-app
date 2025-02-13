<div>
  <h2>{{ str(request('source'))->upper() }}</h2>
  <form method="POST">
    @csrf

    @include('instance.user_own_server')

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
</div>
