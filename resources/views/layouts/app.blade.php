<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'PeopleFlow' }}</title>
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/img/favicon.svg') }}">
    <script>if (localStorage.getItem('pf-theme') === 'dark' || (!localStorage.getItem('pf-theme') && matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark')</script>
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/peopleflow.css') }}">
    <script src="{{ asset('assets/vendor/tailwind.js') }}"></script>
    <style type="text/tailwindcss">
        @custom-variant dark (&:where(.dark, .dark *));
        @theme { --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    </style>
    @livewireStyles
</head>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    {{ $slot }}
    @livewireScripts
</body>
</html>
