<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Surat Menyurat - Ditajenad TNI AD' }}</title>
    <link rel="icon" href="{{ asset('images/logo_ajendam.png') }}">
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="bg-app-background font-sans text-text-dark antialiased">
    {{ $slot }}

    @livewireScripts
</body>
</html>
