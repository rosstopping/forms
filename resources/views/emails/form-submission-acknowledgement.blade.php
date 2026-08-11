<!DOCTYPE html>
<html lang="en">
<body>
@foreach ($lines as $line)
{{ $line }}@unless ($loop->last)<br>@endunless
@endforeach
</body>
</html>
