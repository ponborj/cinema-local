<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101113">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sua biblioteca') · Cinema Local</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-cinema text-ivory antialiased">
    <header class="sticky top-0 z-50 border-b border-white/10 bg-cinema shadow-lg shadow-black/15">
        <div class="mx-auto flex min-h-24 max-w-[1680px] items-center justify-between gap-5 px-6 lg:px-14">
            <a href="{{ route('library', absolute: false) }}" class="brand flex items-center gap-3 rounded-lg"
                aria-label="Cinema Local, início">
                <span class="flex size-10 items-center justify-center rounded-xl bg-gold text-cinema"><x-icon
                        name="play" class="size-5 fill-current" /></span>
                <span class="text-lg font-bold tracking-[.07em]">CINEMA<span
                        class="ml-1 font-normal text-stone">LOCAL</span></span>
            </a>
            <div class="hidden items-center gap-2 text-sm text-stone md:flex"><span
                    class="size-1.5 rounded-full bg-gold"></span>Sua biblioteca, na sua rede</div>
            @yield('header-action')
        </div>
    </header>
    @yield('content')
    <footer class="mx-auto mt-16 max-w-[1680px] px-6 pb-8 lg:px-14">
        <div class="flex flex-wrap justify-between gap-3 border-t border-white/10 pt-6 text-sm text-stone">
            <p>Do seu computador para a sua tela.</p>
            <p>Use as setas e OK do controle para navegar.</p>
        </div>
    </footer>
</body>

</html>
