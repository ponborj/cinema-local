@extends('layouts.app')
@section('title', $catalog['title'])
@section('header-action')<a class="button-secondary" href="#connection" data-connection-toggle aria-controls="connection" aria-expanded="false"><x-icon
name="tv" />Acessar na TV</a>@endsection
@section('content')
    <main class="mx-auto max-w-[1680px] px-6 pt-12 lg:px-14 lg:pt-16" x-data="libraryPage"
        data-parent="{{ $catalog['parent'] === null ? '' : route('library', ['folder' => $catalog['parent']], false) }}">
        <section class="mb-12 flex flex-wrap items-end justify-between gap-8">
            <div>
                <p class="eyebrow">SESSÃO EM CASA</p>
                <h1 class="mt-4 text-4xl font-semibold tracking-[-.045em] md:text-5xl lg:text-6xl">O que vamos assistir?
                </h1>
                <p class="mt-5 text-base text-stone lg:text-lg">Seus filmes e séries. O conforto de estar em casa.</p>
            </div>
            <label class="relative w-full md:max-w-80"><span
                    class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-stone"><x-icon
                        name="search" /></span><input class="field w-full pl-12" type="search" x-model="query"
                    @input="filterItems" placeholder="Buscar nesta pasta" aria-label="Buscar nesta pasta"
                    autocomplete="off"></label>
        </section>
        <section id="connection" class="notice mb-10" x-show="connectionOpen" x-cloak aria-label="Acesso pela TV">
            <div class="flex items-start gap-4"><x-icon name="tv" class="mt-1 size-7 shrink-0 text-gold" />
                <div>
                    <h2 class="text-lg font-semibold">Seu cinema também está na TV</h2>
                    <p class="mt-2 text-stone">Abra o navegador da TV e digite um destes endereços:</p>
                    @forelse($addresses as $address)<a href="{{ $address }}"
                    class="mt-3 block break-all text-xl font-semibold text-gold">{{ $address }}</a>@empty<p
                        class="mt-3 text-gold">Conecte o computador à rede local para exibir o IP.</p>@endforelse
                    <p class="mt-4 text-sm text-stone">TV e computador precisam estar na mesma rede. O servidor deve
                        continuar ligado; o firewall do Windows precisa permitir o acesso.</p>
                </div>
            </div>
        </section>
        <nav class="mb-8 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-stone" aria-label="Pastas"><a
                href="{{ route('library', absolute: false) }}" class="breadcrumb"><x-icon
                    name="folder" />{{ $catalog['libraryName'] }}</a>@foreach($breadcrumbs as $crumb)<span
                        class="text-white/20" aria-hidden="true">/</span><a class="breadcrumb"
                    href="{{ route('library', ['folder' => $crumb['folder']], false) }}">{{ $crumb['title'] }}</a>@endforeach
        </nav>
        <div class="mb-7 flex flex-wrap items-center justify-between gap-5">
            <div>
                <h2 class="max-w-4xl text-2xl font-semibold tracking-tight">{{ $catalog['title'] }}</h2>
                <p class="mt-2 text-sm text-stone">{{ $catalog['folders'] }}
                    {{ $catalog['folders'] === 1 ? 'pasta' : 'pastas' }}<span
                        class="px-2 text-white/20">·</span>{{ $catalog['videos'] }}
                    {{ $catalog['videos'] === 1 ? 'vídeo' : 'vídeos' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex rounded-xl border border-white/10 bg-surface p-1" role="group"
                    aria-label="Filtrar biblioteca">
                    <button class="filter-button" :class="allFilterClass" :aria-pressed="allFilterSelected" data-filter="all" @click="changeFilter"><x-icon
                            name="grid" />Todos</button>
                    <button class="filter-button" :class="folderFilterClass" :aria-pressed="folderFilterSelected" data-filter="folder"
                        @click="changeFilter"><x-icon name="folder" /><span class="hidden sm:inline">Pastas</span></button>
                    <button class="filter-button" :class="videoFilterClass" :aria-pressed="videoFilterSelected" data-filter="video"
                        @click="changeFilter"><x-icon name="film" /><span class="hidden sm:inline">Vídeos</span></button>
                </div><a href="{{ request()->getRequestUri() }}" class="button-icon"
                    aria-label="Atualizar biblioteca"><x-icon name="refresh" /></a>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4 2xl:gap-6" aria-label="Filmes, séries e pastas">
            @foreach($catalog['items'] as $item)
                <a href="{{ $item['kind'] === 'folder' ? route('library', ['folder' => $item['path']], false) : route('media.watch', ['path' => $item['path']], false) }}"
                    class="media-card group" data-kind="{{ $item['kind'] }}" data-path="{{ $item['path'] }}"
                    data-search="{{ mb_strtolower($item['title']) }}">
                    <div class="card-art card-tone-{{ $loop->index % 4 }}">
                        <div class="card-glow"></div><x-icon :name="$item['kind'] === 'folder' ? 'folder' : 'play'"
                            class="relative size-14 opacity-75 transition-transform duration-200 group-hover:scale-110" /><span
                            class="absolute bottom-4 left-4 text-xs font-semibold tracking-[.12em]">{{ $item['kind'] === 'folder' ? 'COLEÇÃO' : $item['extension'] }}</span><span
                            class="absolute right-4 top-4 rounded-md border border-white/15 bg-black/15 px-2 py-1 text-xs">{{ $item['kind'] === 'folder' ? 'PASTA' : 'VÍDEO' }}</span>
                    </div>
                    <div class="flex flex-1 flex-col gap-4 p-4 lg:p-5">
                        <h3 class="text-base leading-relaxed font-semibold break-words">{{ $item['title'] }}</h3>
                        <div class="mt-auto">
                            <p class="text-sm text-stone">
                                {{ $item['kind'] === 'folder' ? 'Explorar coleção' : number_format($item['size'] / 1024 ** 3, 2, ',', '.') . ' GB · Reproduzir' }}
                            </p>
                            <p class="resume-label mt-2 hidden text-sm text-gold"></p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="rounded-2xl border border-dashed border-white/15 px-6 py-14 text-center" x-show="empty"
            @if(count($catalog['items'])) x-cloak @endif role="status"><x-icon name="film"
                class="mx-auto mb-4 size-10 text-stone" />
            <h3 class="text-lg font-semibold">Nenhum vídeo por aqui</h3>
            <p class="mt-2 text-stone">Tente outra busca ou abra outra pasta da biblioteca.</p>
        </div>
    </main>
@endsection
