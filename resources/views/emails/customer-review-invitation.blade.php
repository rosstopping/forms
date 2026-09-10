<x-email-layout :linked="false">
@foreach (explode("\n\n", $invitation->body) as $paragraph)
<p>{!! nl2br(e($paragraph)) !!}</p>
@endforeach
<p><a href="{{ $invitation->review_url }}">Leave an honest review</a></p>
</x-email-layout>
