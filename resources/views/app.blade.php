<!DOCTYPE html>
<html lang="{{ str_replace('-', '_', app()->getLocale()) }}" prefix="og: http://ogp.me/ns#">
<head>
    <!-- Metadata -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="author" content="coalaura">
    <meta name="description" content="OP-Framework - Admin Panel">

    <!-- Open Graph Protocol -->
    <meta property="og:title" content="OP-FW - Admin Panel">
    <meta property="og:type" content="admin.fivem">
    <meta property="og:image" content="{{ asset('favicon.jpg') }}">

    <!-- Page title -->
    <title>OP-FW - Admin @yield('title')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.jpg') }}?v=1624011750066">

    <!-- Styling -->
    <link rel="stylesheet" type="text/css" href="{{ mix('css/app.css') }}">

    <!-- Remote Debugging -->
    <script>var REMOTE_DEBUG = false;</script>
    <script src="/debugging.js"></script>

    <script>const classifierJSON = "{{ fileVersion('helpers/classifier.json') }}";</script>

    <!-- Scripts -->
    <script defer type="application/javascript" src="{{ mix('js/app.js') }}"></script>
    <script defer type="application/javascript" src="https://kit.fontawesome.com/0074643143.js" crossorigin="anonymous"></script>

    <!-- Extra header -->
    {!! extraHeader() !!}
</head>

<body class="h-full font-sans text-black antialiased" style="background: rgb(17, 24, 39)">
    @inertia
</body>
