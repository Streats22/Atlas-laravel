<!DOCTYPE html>
<html {{ $htmlAttributes }}>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if($description)<meta name="description" content="{{ $description }}">@endif
    {{ $head }}
</head>
<body class="atlas-body">
{{ $content }}
{{ $scripts }}
</body>
</html>
