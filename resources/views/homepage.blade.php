@include('header', ['title' => 'DB29 - HOST THE WORLD'])
<body>
  <div>
    <pre><a href="{{ route('login') }}">{{ mb_ucfirst(__('trans.login')) }}</a></pre>
    <pre><a href="https://forum.db29.ovh">{{ mb_ucfirst(__('trans.blog')) }}</a></pre>
    <pre><a href="{{ route('faq') }}">{{ mb_ucfirst(__('trans.question')) }}</a></pre>
  </div>
</body>
