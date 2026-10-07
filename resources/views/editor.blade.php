<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Editing {{ $page->title }} · Atlas</title>
    <link rel="stylesheet" href="{{ route('atlas.asset', 'atlas.css') }}">
    <script>try{var t=localStorage.getItem('atlas-ui-theme');if(t)document.documentElement.setAttribute('data-theme',t)}catch(e){}</script>
</head>
<body class="atlas-app">
<div id="atlas-root"></div>
<script id="atlas-config" type="application/json">@json($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)</script>
<script src="{{ route('atlas.asset', 'atlas.js') }}"></script>
</body>
</html>
