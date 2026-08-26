<!DOCTYPE html>
<html lang="en">
<body marginwidth="0" leftmargin="0">
@foreach ($lines as $line)
{{ $line }}@unless ($loop->last)<br>@endunless
@endforeach
</body>
</html>
