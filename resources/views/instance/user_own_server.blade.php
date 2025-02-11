@if (count($hostnames) > 0)
<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css">

<div class="border-dashed p-2 py-4 rounded-md">
  <label class="select-none" for="hostnames">{{ __('trans.use_your_own_server') }}</label><br>
  <input list="hostnames" name="hostname" class="awesomplete" data-minchars="0" data-autofirst="true">
  <datalist id="hostnames">
    @foreach ($hostnames as $hostname)
    <option value="{{ $hostname }}">
    @endforeach
  </datalist>
</div><br>

@endif
