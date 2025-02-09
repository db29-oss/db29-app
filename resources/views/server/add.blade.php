@include('header')

<div>
  <div class="text-gray-400 pointer-events-none select-none">
    <div>{{ __('trans.support_debian_only') }}</div>
  </div><br>

  <form method="POST">
    @csrf

    <label class="select-none" for="ssh_address">ssh address*</label><br>
    <div class="text-gray-400 pointer-events-none select-none">
      {{ __('trans.ipv4_only') }}
    </div>
    <input type="text" name="ssh_address" value="{{ old('ssh_address') }}"><br>
    @error('ssh_address')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="ssh_port">ssh port*</label><br>
    <input type="text" name="ssh_port" value="{{ old('ssh_port') ?? 22 }}"><br>
    @error('ssh_port')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <div class="select-none">ssh publickey*</div>
    <div class="p-1 inline-block bg-gray-100 text-gray-500 text-wrap break-all">
      <div class="select-all">
        {{ $ssh_publickey }}
      </div>
    </div><br>
    @error('ssh_publickey')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="ssh_username">ssh username*</label><br>
    <div class="text-gray-400 pointer-events-none select-none">
      {{ __('trans.user_must_have_sudo_privileged') }}
    </div>
    <input type="text" name="ssh_username" value="{{ old('ssh_username') ?? 'root' }}"><br>
    @error('ssh_username')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="storage_path">storage path*</label><br>
    <div class="text-gray-400 pointer-events-none select-none">
      {{ __('trans.path_to_install_software_on') }}
    </div>
    <input type="text" name="storage_path" value="{{ old('storage_path') ?? '/opt/' }}"
      placeholder="{{ __('trans.default') }} (/opt/)"
    ><br>
    @error('storage_path')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <button type="submit">{{ __('trans.register') }}</button>
  </form>
</div>
