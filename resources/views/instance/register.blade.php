@include('header', ['title' => 'DB29 - SOURCE'])

<script src="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/awesomplete/1.1.5/awesomplete.min.css">

@include('instance.'.$source_name.'.register')

@if ($i_s_count > 0)
<div class="pt-5 text-gray-400 pointer-events-none select-none">
  ({{ __('trans.current_instance_have', ['count' => $i_s_count]) }})
</div>
@endif
