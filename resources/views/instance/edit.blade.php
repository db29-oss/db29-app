@include('header')

<div>
  <form method="POST">
    @csrf

    @include('instance.user_own_domain')

    <button type="submit">{{ __('trans.update') }}</button>
  </form>
</div>
