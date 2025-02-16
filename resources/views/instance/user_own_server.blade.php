@if (array_key_exists($source_name, Source::UUOS))
<div class="pb-4 rounded-md text-gray-400">
  {{ __('trans.unsupported_user_own_server') }}
</div>
@endif

@if (count($machines) > 0 && ! array_key_exists($source_name, Source::UUOS))
<div class="border-dashed p-2 py-4 rounded-md">
  <label class="select-none" for="hostnames">{{ __('trans.use_your_own_server') }}</label><br>
  <input list="hostnames" name="hostname" id="hostname"
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
  <br>

  @if (array_key_exists($source_name, Source::M_R))
  <div id="mail_uos" class="">
    <label class="select-none" for="aws_key">
      {{ __('trans.aws_key') }}
    </label><br>
    <div class="inline-block text-gray-400 text-wrap break-all">
      <div class="select-all">
        ({{ __('trans.create_email_identity_and_send_raw_mail') }})
      </div>
    </div><br>
    <input type="text" name="aws_key" placeholder="AKIA6OBWXBFWMYTZT6AT"
           value="{{ old('aws_key') }}">
    <br>
    <label class="select-none" for="aws_secret">{{ __('trans.aws_secret') }}</label><br>
    <input type="text" name="aws_secret" placeholder="BPALC6Jkrcc9looro3Uj9PVmgJvwlFF02CczmJ3+6++Z"
           value="{{ old('aws_secret') }}">
    <br>
    <label class="select-none" for="aws_ses_region">{{ __('trans.aws_ses_region') }}</label><br>
    <input type="text" name="aws_ses_region" placeholder="us-east-1" id="aws_ses_region"
           value="{{ old('aws_ses_region') }}">
    <br>

    @error('aws_key')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
  </div>
  @endif

</div><br>
@endif
