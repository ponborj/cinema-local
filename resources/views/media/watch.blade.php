@extends('layouts.app')
@section('title', $title)
@section('header-action')<a href="{{ route('library', ['folder' => $parent], false) }}" class="button-secondary">Voltar
ao catálogo</a>@endsection
@section('content')
    @php
        $defaultAudio = collect($audio)->first(fn($track) => in_array(strtolower($track['tags']['language'] ?? ''), ['por', 'pt', 'pt-br'], true))['index'] ?? ($audio[0]['index'] ?? '');
        $playerData = ['path' => $relative, 'source' => route('media.stream', ['path' => $relative], false), 'parent' => route('library', ['folder' => $parent], false), 'defaultAudio' => (string) $defaultAudio, 'canPrepare' => !$probeError];
    @endphp
    <main class="mx-auto max-w-[1520px] px-6 pt-10 lg:px-14" x-data="cinemaPlayer"
        data-player="{{ json_encode($playerData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
        <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <p class="eyebrow">AGORA NA SUA TELA</p>
                <h1 class="mt-3 max-w-5xl text-2xl leading-snug font-semibold tracking-tight break-words md:text-3xl">
                    {{ $title }}</h1>
            </div><span class="flex items-center gap-2 text-sm text-stone"><x-icon
                    name="clock" />{{ floor($duration / 60) }} min</span>
        </div>
        <div class="overflow-hidden rounded-2xl border border-white/10 bg-black shadow-2xl shadow-black/30"><video
                x-ref="video" controls playsinline preload="metadata" tabindex="0"
                class="aspect-video max-h-[68vh] w-full bg-black" @loadedmetadata="onMetadata" @timeupdate="onTimeUpdate"
                @pause="saveProgress" @ended="saveProgress" @playing="onPlaying" x-on:error="onError">
                @foreach($tracks as $track)
                <track kind="subtitles" src="{{ $track['url'] }}" label="{{ $track['label'] }}" srclang="pt">@endforeach
            </video></div>
        <div class="mt-5 flex flex-wrap gap-2"><button class="button-primary" @click="togglePlay"><x-icon
                    name="play" />Reproduzir / pausar</button><button class="button-secondary" @click="seekBack">Voltar 10
                s</button><button class="button-secondary" @click="seekForward">Avançar 10 s</button><button
                class="button-secondary" @click="restart">Recomeçar</button><button class="button-secondary sm:ml-auto"
                @click="fullscreen"><x-icon name="fullscreen" />Tela cheia</button></div>
        <p class="mt-4 min-h-6 text-sm text-stone" x-text="playbackMessage" role="status"></p>
        <section class="mt-8 rounded-2xl border border-white/10 bg-surface p-5 lg:p-7"
            aria-label="Preferências de reprodução">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Do seu jeito</h2><span class="text-sm text-stone">Áudio e legendas</span>
            </div>
            <div class="grid items-end gap-5 md:grid-cols-2 lg:grid-cols-[1fr_1fr_auto]">
                <label class="grid min-w-0 gap-2 text-sm text-stone"><span class="flex items-center gap-2"><x-icon
                            name="captions" />Legenda</span><select class="field w-full" x-model="subtitle"
                        @change="changeSubtitle">
                        <option value="">Desativada</option>@foreach($tracks as $track)
                        <option value="{{ $loop->index }}">{{ $track['label'] }}</option>@endforeach
                    </select></label>
                <label class="grid min-w-0 gap-2 text-sm text-stone"><span class="flex items-center gap-2"><x-icon
                            name="volume" />Áudio do MP4 para TV</span><select class="field w-full" x-model="audio"
                        @change="audioChanged" :disabled="isBusy">
                        <option value="">Padrão do arquivo</option>@foreach($audio as $track)
                            <option value="{{ $track['index'] }}">
                                {{ $track['tags']['title'] ?? $track['tags']['language'] ?? 'Áudio ' . $track['index'] }} ·
                        {{ strtoupper($track['codec_name']) }}</option>@endforeach
                    </select></label>
                <button class="button-primary justify-center" :disabled="prepareDisabled" @click="prepare"
                    x-text="prepareLabel">Preparar MP4 para TV</button>
            </div>
            <div class="notice mt-6" x-show="hasPreparation" x-cloak>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <p class="text-sm" x-text="preparationMessage" role="status"></p><button
                        class="button-secondary text-sm" x-show="isBusy" @click="cancel">Cancelar preparação</button>
                </div><progress class="mt-4 h-1.5 w-full" :value="percent" max="100" x-show="isBusy"
                    aria-label="Progresso da preparação"></progress>
            </div>
            @if($probeError)
            <p class="notice mt-5 text-sm">{{ $probeError }}</p>@endif
            <p class="mt-5 max-w-4xl text-sm leading-relaxed text-stone">Se o arquivo não abrir ou ficar sem som, prepare um
                MP4 para a TV. A cópia usa áudio AAC estéreo e fica salva no computador. O original é preservado. A escolha
                de áudio acima vale para essa cópia.</p>
        </section>
    </main>
@endsection