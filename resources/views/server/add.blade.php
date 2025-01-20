@include('header')

<div>
  <div class="text-gray-400 pointer-events-none select-none">
    <div>{{ __('trans.support_debian_only') }}</div>
    <div>{{ __('trans.support_ipv4_only') }}</div>
    <div>{{ __('trans.support_rsa_or_ed25519') }}</div>
  </div><br>

  <form method="POST">
    @csrf

    <label class="select-none" for="ssh_address">ssh address*</label><br>
    <input type="text" name="ssh_address" value=""><br>
    @error('ssh_address')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="ssh_port">ssh port*</label><br>
    <input type="text" name="ssh_port" value=""><br>
    @error('ssh_port')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="ssh_privatekey">ssh privatekey*</label><br>
    <input type="text" name="ssh_privatekey" value=""><br>
    @error('ssh_privatekey')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="storage_path">storage path</label><br>
    <input type="text" name="storage_path" value=""
      placeholder="{{ __('trans.default') }} (/opt/)"
    ><br>
    @error('storage_path')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <button type="submit">{{ __('trans.register') }}</button>
  </form>
</div>
