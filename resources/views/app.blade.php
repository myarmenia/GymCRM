<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    {{-- <script src="../../assets/vendor/libs/jquery/jquery.js"></script> --}}

    {{-- <script src="../../assets/vendor/libs/@algolia/autocomplete-js.js"></script> --}}
    {{-- <script src="../../assets/vendor/js/helpers.js"></script> --}}
    {{-- <script src="../../assets/vendor/js/template-customizer.js"></script> --}}
    {{-- <script src="../../assets/js/config.js"></script> --}}
    {{-- <script src="/assets/vendor/libs/select2/select2.js"></script>
        <link rel="stylesheet" href="/assets/vendor/libs/select2/select2.css"> --}}
    <!-- Scripts -->


    @routes
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead

</head>

<body class="font-sans antialiased">
    @inertia


    {{-- <script src="../../assets/vendor/libs/jquery/jquery.js"></script>

        <script src="../../assets/vendor/libs/popper/popper.js"></script>
        <script src="../../assets/vendor/js/bootstrap.js"></script> --}}
    {{-- <script src="../../assets/vendor/libs/node-waves/node-waves.js"></script> --}}
    {{-- <script src="../../assets/vendor/libs/pickr/pickr.js"></script> --}}
    {{-- <script src="../../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script> --}}
    {{-- <script src="../../assets/vendor/libs/hammer/hammer.js"></script> --}}
    {{-- <script src="../../assets/vendor/js/menu.js"></script>
        <script src="../../assets/js/main.js"></script> --}}

</body>

</html>