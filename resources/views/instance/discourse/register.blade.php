<div>
  <h2>{{ str(request('source'))->upper() }}</h2>

  <div class="pb-4 text-gray-400">
    <div class="my-1">
    {{ __('trans.discourse_email_explain') }}
    </div>
    <div class="my-1">
    {{ __('trans.discourse_change_system_email_later') }}
    </div>
  </div>

  <form method="POST">
    @csrf

    @include('instance.user_own_server')

    <div class="border-dashed p-2 rounded-md">
      <label class="select-none" for="email">{{ __('trans.admin_email') }}</label><br>
      <input type="text" name="email" placeholder="hello@gmail.com" value=
      @if (old('email'))
          "{{ old('email') }}"
      @elseif (str()->after(auth()->user()->email, '@') !== config('app.domain'))
          "{{ auth()->user()->email }}"
      @else
          ""
      @endif
      ><br>
      @error('email')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
      <br>
    </div>

    <div class="my-4 text-gray-400">
      <div class="my-1">
      {{ __('trans.skip_system_email_if_email_also_system_email') }}
      </div>
      <div class="my-1">
      {{ __('trans.discourse_send_verification_link') }}
      </div>
    </div>

    <div class="border-dashed p-2 rounded-md">
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
        <div class="select-all" id="dkim_txt">
          {{ $input_seeder['dkim_selector']."._domainkey.example.com" }}
        </div>
      </div><br>
      <div class="p-1 inline-block bg-gray-100 text-gray-500 text-wrap break-all">
        <div class="select-all">
          {{ "p=".preg_replace('/-----.*?-----|\r?\n/', '', $input_seeder['dkim_publickey']) }}
        </div>
      </div><br>
      @error('dkim_txt')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
      <br>

      <div class="select-none">SPF (TXT record)</div>
      <div class="p-1 inline-block text-wrap break-all select-none">
        <div class="select-all" id="spf_txt">
          {{ $input_seeder['dkim_selector'] }}.example.com
        </div>
      </div><br>
      <div class="p-1 inline-block bg-gray-100 text-gray-500 select-none">
        <div class="select-all">
          v=spf1 include:amazonses.com ~all
        </div>
      </div><br>
      @error('spf_txt')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
      <br>

      <div class="select-none">DMARC (TXT record)</div>
      <div class="p-1 inline-block text-wrap break-all select-none">
        <div class="select-all" id="dmarc_txt">
          _dmarc.example.com
        </div>
      </div><br>
      <div class="p-1 inline-block bg-gray-100 text-gray-500 select-none">
        <div class="select-all">
          v=DMARC1; p=reject; adkim=r; aspf=r;
        </div>
      </div><br>
      @error('dmarc_txt')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
      <br>

      <div class="select-none">MX (MX record)</div>
      <div class="p-1 inline-block text-wrap break-all select-none">
        <div class="select-all" id="mx_mx">
          {{ $input_seeder['dkim_selector'] }}.example.com
        </div>
      </div><br>
      <div class="p-1 inline-block bg-gray-100 text-gray-500 select-none">
        <div class="select-all">
          feedback-smtp.ap-northeast-2.amazonses.com
        </div>
      </div><br>
      @error('mx_mx')
      <div style="display: inline; color: red">{{ $message }}</div>
      @enderror
      <br>
    </div>

    <br>

    <div>
      <button type="submit">{{ __('trans.register') }}</button>
    </div>
  </form>

 <script>
   function updateTXTDomain(self) {
     if (! self.value) {
       document.querySelector('#dkim_txt').textContent =
         "{{ $input_seeder['dkim_selector'] }}._domainkey.example.com"

       document.querySelector('#spf_txt').textContent = "{{ $input_seeder['dkim_selector'] }}.example.com"

       document.querySelector('#dmarc_txt').textContent = "_dmarc.example.com"

       document.querySelector('#mx_mx').textContent = "{{ $input_seeder['dkim_selector'] }}.example.com"

       return;
     }

     let domain = self.value.split('@')[1];

     if (! domain) {
       document.querySelector('#dkim_txt').textContent =
         "{{ $input_seeder['dkim_selector'] }}._domainkey.example.com"

       document.querySelector('#spf_txt').textContent = "{{ $input_seeder['dkim_selector'] }}.example.com"

       document.querySelector('#dmarc_txt').textContent = "_dmarc.example.com"

       document.querySelector('#mx_mx').textContent = "{{ $input_seeder['dkim_selector'] }}.example.com"

       return;
     }

     document.querySelector('#dkim_txt').textContent = "{{ $input_seeder['dkim_selector'] }}._domainkey." + domain

     document.querySelector('#spf_txt').textContent = "{{ $input_seeder['dkim_selector'] }}." + domain

     document.querySelector('#dmarc_txt').textContent = "_dmarc." + domain

     document.querySelector('#mx_mx').textContent = "{{ $input_seeder['dkim_selector'] }}." + domain
   }

   document.querySelector('#system_email_input').dispatchEvent(new Event('input'));
 </script>
</div>
