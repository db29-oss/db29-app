@include('header', ['title' => 'DB29 - RECHARGE'])
<div>
  @foreach ($banking_details as $banking_detail)
  <div class="my-4">
  @foreach ($banking_detail as $bd_k => $bd_v)
    <div class="text-gray-400 pointer-events-none select-none">
      @if ($bd_k === 'bank_name')
      {{ __('trans.bank') }}: 
      @elseif ($bd_k === 'account_number')
      {{ __('trans.account') }}:
      @endif
      <div class="text-black inline pointer-events-auto select-text">
        {{ $bd_v }}
      </div>
    </div>
  @endforeach
    <div class="text-gray-400 pointer-events-none select-none">
      {{ __('trans.amount_money') }}:
      <div class="text-black inline">
        {{ __('trans.minimum_amount_money', ['amount' => formatNumberShort(\App\Models\User::FREE_CREDIT)]) }}
      </div>
    </div>
    <div class="text-gray-400 pointer-events-none select-none">
      {{ __('trans.message') }}:
      <div class="text-black inline pointer-events-auto select-text">
        D9{{ str_pad(auth()->user()->recharge_number, 6, 0, STR_PAD_LEFT) }}
      </div>
    </div>
  </div>
  @endforeach

  <div class="text-gray-400 select-none">
    {{ __('trans.copy_to_prevent_mistake') }}
  </div>
</div>
