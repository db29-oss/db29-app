@include('header', ['title' => 'DB29 - RECHARGE'])
<div>
  @if (count($banking_details) > 0)
    <h2>{{ str(__('trans.bank_transfer'))->upper() }}</h2>

    <div class="border-dashed p-2 rounded-md">
      @foreach ($banking_details as $banking_detail)
      <div class="my-4">
      @foreach ($banking_detail as $bd_k => $bd_v)
        <div class="text-gray-400 pointer-events-none select-none">
          @if ($bd_k === 'bank_name')
          {{ __('trans.bank') }}:
          @elseif ($bd_k === 'account_number')
          {{ __('trans.account_number') }}:
          @elseif ($bd_k === 'account_name')
          {{ __('trans.account_name') }}:
          @endif
          <div class="text-black @if ($bd_k === 'account_number') pointer-events-auto select-all @endif">
            {{ $bd_v }}
          </div>
        </div>
      @endforeach
        <div class="text-gray-400 pointer-events-none select-none">
          {{ __('trans.amount_money') }}:
          <div class="text-black">
            {{
            __('trans.minimum_amount_money', ['amount' => formatNumberShort(\App\Models\User::SIGN_UP_CREDIT)])
            }}
          </div>
        </div>
        <div class="text-gray-400 pointer-events-none select-none">
          {{ __('trans.message') }}:
          <div class="text-black pointer-events-auto select-all">
            D9{{ str_pad(auth()->user()->recharge_number, 6, 0, STR_PAD_LEFT) }}
          </div>
        </div>
      </div>
      @if (! $loop->last)
      <hr>
      @endif
      @endforeach
    </div>
  @endif

  @if (count($crypto_details) > 0)
    <h2>{{ str(__('trans.crypto'))->upper() }}</h2>

    <div class="border-dashed p-2 rounded-md">
      @foreach ($crypto_details as $crypto_detail)
      <div class="my-4">
      @foreach ($crypto_detail as $cd_k => $cd_v)
        <div class="text-gray-400 pointer-events-none select-none">
          @if ($cd_k === 'coin_name')
          {{ __('trans.coin_name') }}:
          @elseif ($cd_k === 'address')
          {{ __('trans.address') }}:
          @endif
          <div class="text-black @if ($cd_k === 'address') pointer-events-auto select-all @endif">
            {{ $cd_v }}
          </div>
        </div>
      @endforeach
      </div>
      @if (! $loop->last)
      <hr>
      @endif
      @endforeach
    </div>
  @endif
</div>
