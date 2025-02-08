@include('header', ['title' => 'DB29 - SOURCE'])

<div>
  @if (auth()->user()->email && str()->after(auth()->user()->email, '@') === config('app.domain'))
  <div class="text-gray-400 pointer-events-none select-none">
    {{ __('trans.change_default_config_at') }}
    <a class="pointer-events-auto" href={{ route('prefill') }}>{{ __('trans.prefill') }}</a>
  </div><br>
  @endif

  <h2>{{ str(request('source'))->upper() }}</h2>

  <form method="POST">
    @csrf

    <label class="select-none" for="email">{{ __('trans.admin_email') }}</label><br>
    <input type="text" name="email" placeholder="hello@gmail.com" value="{{ old('email') }}"><br>
    @error('email')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <label class="select-none" for="system_email">
      {{ __('trans.system_email') }} ({{ __('trans.dns_authenticate') }})
    </label><br>
    <input id="system_email_input" oninput="updateTXTDomain(this)"
           type="text" name="system_email" placeholder="hello@example.com"
           value="{{ old('system_email') }}"><br>
    @error('system_email')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <div class="select-none">DKIM (TXT record)</div>
    <div class="p-1 inline-block text-wrap break-all select-none">
      <div class="select-text" id="dkim_txt">
        {{ $input_seeder['dkim_selector']."._domainkey.example.com" }}
      </div>
    </div><br>
    <div class="p-1 inline-block bg-gray-100 text-gray-500 text-wrap break-all">
      <div class="select-text">
        {{ "p=".preg_replace('/-----.*?-----|\r?\n/', '', $input_seeder['dkim_publickey']) }}
      </div>
    </div><br>
    @error('dkim_txt')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <div class="select-none">SPF (TXT record)</div>
    <div class="p-1 inline-block text-wrap break-all select-none">
      <div class="select-text" id="spf_txt">
        {{ $input_seeder['dkim_selector'] }}.example.com
      </div>
    </div><br>
    <div class="p-1 inline-block bg-gray-100 text-gray-500 select-none">
      <div class="select-text" id="spf_txt_val">
        v=spf1 include:example.com ~all
      </div>
    </div><br>
    @error('spf_txt')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <div class="select-none">DMARC (TXT record)</div>
    <div class="p-1 inline-block text-wrap break-all select-none">
      <div class="select-text" id="dmarc_txt">
        _dmarc.example.com
      </div>
    </div><br>
    <div class="p-1 inline-block bg-gray-100 text-gray-500 select-none">
      <div class="select-text">
        v=DMARC1; p=reject; adkim=r; aspf=r;
      </div>
    </div><br>
    @error('dmarc_txt')
    <div style="display: inline; color: red">{{ $message }}</div>
    @enderror
    <br>

    <div>
      <button type="submit">{{ __('trans.register') }}</button>
    </div>
  </form>

  @if ($i_s_count > 0)
  <div class="pt-5 text-gray-400 pointer-events-none select-none">
    ({{ __('trans.current_instance_have', ['count' => $i_s_count]) }})
  </div>
  @endif

 <script>
   function updateTXTDomain(self) {
     if (! self.value) {
       document.querySelector('#dkim_txt').textContent =
         "{{ $input_seeder['dkim_selector'] }}._domainkey.example.com"

       document.querySelector('#spf_txt').textContent = "{{ $input_seeder['dkim_selector'] }}.example.com"

       document.querySelector('#spf_txt_val').textContent = "v=spf1 include:example.com ~all"

       document.querySelector('#dmarc_txt').textContent = "_dmarc.example.com"

       return;
     }

     let domain = self.value.split('@')[1];

     if (! domain) {
       document.querySelector('#dkim_txt').textContent =
         "{{ $input_seeder['dkim_selector'] }}._domainkey.example.com"

       document.querySelector('#spf_txt').textContent = "{{ $input_seeder['dkim_selector'] }}.example.com"

       document.querySelector('#spf_txt_val').textContent = "v=spf1 include:example.com ~all"

       document.querySelector('#dmarc_txt').textContent = "_dmarc.example.com"

       return;
     }

     document.querySelector('#dkim_txt').textContent = "{{ $input_seeder['dkim_selector'] }}._domainkey." + domain

     document.querySelector('#spf_txt').textContent = "{{ $input_seeder['dkim_selector'] }}." + domain

     document.querySelector('#spf_txt_val').textContent = "v=spf1 include:" + domain + " ~all"

     document.querySelector('#dmarc_txt').textContent = "_dmarc." + domain
   }

   document.querySelector('#system_email_input').dispatchEvent(new Event('input'));
 </script>
</div>
