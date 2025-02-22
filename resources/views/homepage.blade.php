@include('header', ['title' => 'DB29 - HOST THE WORLD'])
<body>
  <div>
    <pre><a href="{{ route('login') }}">{{ mb_ucfirst(__('trans.login')) }}</a></pre>
  </div>
  <div class="mt-8">
    {!! __('trans.explain_db29_app') !!}
  </div>
  <div class="mt-4">
    {!! __('trans.checkout_social_link') !!}
  </div>
  <br>
  <br>
  <iframe width="560" height="315" src="https://www.youtube.com/embed/nR7GX93MAzA" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
</body>
