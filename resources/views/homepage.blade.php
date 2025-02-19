@include('header', ['title' => 'DB29 - HOST THE WORLD'])
<body>
  <div>
    <pre><a href="{{ route('login') }}">{{ mb_ucfirst(__('trans.login')) }}</a></pre>
  </div>
  <div class="mt-8">
    {!! __('trans.explain_db29_app') !!}
  </div>
  <div class="mt-4">
    {!! __('trans.checkout_our_social_link') !!}
  </div>
</body>
