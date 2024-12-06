@include('header')
<div>
  <form method="POST">
    @csrf

    <div>
      <label for="email">email</label>
      <input type="text" id="email" name="email" value="{{ auth()->user()->email }}">
      @error('email')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
    </div>

    <br>

    <div>
      <button type="submit">{{ __('trans.update') }}</button>
    </div>

  </form>
</div>
