@props(['validationErrors'])

@if(session('status'))
    <p class="ui-well p-4 text-sm text-teal-800" role="status">{{ session('status') }}</p>
@endif
@if(session('error'))
    <p class="ui-well p-4 text-sm text-amber-800" role="alert">{{ session('error') }}</p>
@endif
@if($validationErrors->any())
    <div class="ui-well p-4 text-sm text-rose-800" role="alert"><p class="font-medium">Please check the following:</p><ul class="mt-2 list-inside list-disc">@foreach($validationErrors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
