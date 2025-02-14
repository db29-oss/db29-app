@if (count($machines) > 0)
<div class="border-dashed p-2 py-4 rounded-md">
  <label class="select-none" for="hostnames">{{ __('trans.use_your_own_server') }}</label><br>
  <input list="hostnames" name="hostname"
         class="awesomplete dropdown-input" data-minchars="0"
         data-autofirst="true" value="{{ old('hostname') }}"/>
  <datalist id="hostnames">
    @foreach ($machines as $machine)
    <option value="{{ $machine->hostname }}">
    @endforeach
  </datalist>
  <br>
  @error('hostname')
  <div style="display: inline; color: red">{{ $message }}</div><br>
  @enderror

  @error('ins_cred')
  <div style="display: inline; color: red">{{ $message }}</div><br>
  @enderror

</div><br>
@endif
