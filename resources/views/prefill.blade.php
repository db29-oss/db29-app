@include('header')

<div>
  <form method="POST">
    @csrf

    <div>
      <label for="email">email</label><br>
      <input type="text" id="email" name="email" value="{{ auth()->user()->email }}"><br>
      @error('email')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
      <br>
    </div>

    <div>
      <label for="username">username</label><br>
      <input type="text" id="username" name="username" value="{{ auth()->user()->username }}"><br>
      @error('username')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
      <br>
    </div>

    <div>
      <label for="name">name</label><br>
      <input type="text" id="name" name="name" value="{{ auth()->user()->name }}"><br>
      @error('name')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
      <br>
    </div>

    <div>
      <button type="submit">{{ __('trans.update') }}</button>
    </div>

  </form>
</div>
