<div class="border-dashed p-2 py-4 rounded-md">
  <label class="select-none" for="hostnames">{{ __('trans.use_your_own_domain') }}</label><br>
  <div class="inline-block text-gray-500 select-none">
    {{ __('trans.cname_or_alias_record_to', ['domain' => $instance->subdomain.'.'.config('app.domain')]) }}
  </div><br>
  <input name="domain" value="{{ json_decode($instance->extra, true)['reg_info']['domain'] ?? '' }}">
  <br>
  @error('domain')
  <div style="display: inline; color: red">{{ $message }}</div>
  @enderror
</div><br>
