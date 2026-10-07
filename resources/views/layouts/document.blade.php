<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @if($description)<meta name="description" content="{{ $description }}">@endif
    <style>
        .atlas-body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #111827; }
        .atlas-body img { max-width: 100%; }
        @media (max-width: 720px) { .atlas-columns--stack { grid-template-columns: 1fr !important; } }
    </style>
    {{ $head }}
</head>
<body class="atlas-body">
{{ $content }}
{{ $scripts }}
</body>
</html>
