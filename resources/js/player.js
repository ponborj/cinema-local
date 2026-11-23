import { readStorage, writeStorage } from "./storage";

async function request(url, body) {
    const response = await fetch(url, {
        headers: {
            Accept: "application/json",
            ...(body
                ? {
                      "Content-Type": "application/json",
                      "X-CSRF-TOKEN": document.querySelector(
                          'meta[name="csrf-token"]',
                      ).content,
                  }
                : {}),
        },
        ...(body ? { method: "POST", body: JSON.stringify(body) } : {}),
    });
    const data = await response.json();
    if (!response.ok)
        throw new Error(
            response.status === 419
                ? "A sessão expirou. Atualize esta página para continuar."
                : data.message || "Não foi possível concluir a operação.",
        );
    return data;
}

export const cinemaPlayer = () => ({
    config: {},
    audio: "",
    subtitle: "",
    state: "idle",
    percent: 0,
    key: null,
    requesting: false,
    playbackMessage:
        "Abrindo vídeo. Se não houver som, prepare um MP4 para TV.",
    preparationMessage: "",
    pollTimer: null,
    lastSaved: 0,
    sourceResume: 0,
    get isBusy() {
        return this.state === "queued" || this.state === "working";
    },
    get hasPreparation() {
        return this.preparationMessage.length > 0;
    },
    get prepareDisabled() {
        return !this.config.canPrepare || this.requesting || this.isBusy;
    },
    get prepareLabel() {
        return this.state === "ready"
            ? "Reproduzir MP4 pronto"
            : this.state === "queued"
              ? "Na fila de preparação…"
              : this.state === "working"
                ? "Preparando MP4…"
                : "Preparar MP4 para TV";
    },
    init() {
        this.config = JSON.parse(this.$root.dataset.player);
        this.audio = this.config.defaultAudio;
        this.sourceResume =
            Number(readStorage("cinema-progress")[this.config.path]) || 0;
        this.pageHideHandler = () => this.saveProgress();
        window.addEventListener("pagehide", this.pageHideHandler);
        this.$nextTick(() => {
            this.$refs.video.src = this.config.source;
        });
        const stored = readStorage("cinema-laravel-copies")[this.config.path];
        if (stored?.key) {
            this.key = stored.key;
            this.audio = stored.audio;
            this.checkPreparation();
        }
    },
    destroy() {
        this.saveProgress();
        clearTimeout(this.pollTimer);
        window.removeEventListener("pagehide", this.pageHideHandler);
    },
    onMetadata() {
        const video = this.$refs.video;
        if (this.sourceResume > 5 && this.sourceResume < video.duration - 10)
            video.currentTime = this.sourceResume;
        this.changeSubtitle();
        video.play().catch(() => {
            this.playbackMessage = "Pressione Reproduzir para iniciar.";
        });
    },
    onPlaying() {
        this.playbackMessage =
            "O progresso é salvo neste navegador. Boa sessão!";
    },
    onError() {
        this.playbackMessage =
            "Este navegador não conseguiu reproduzir o formato. Use Preparar MP4 para TV.";
    },
    onTimeUpdate() {
        if (Date.now() - this.lastSaved > 5000) {
            this.lastSaved = Date.now();
            this.saveProgress();
        }
    },
    saveProgress() {
        const video = this.$refs.video;
        if (!video || !Number.isFinite(video.duration) || !video.readyState)
            return;
        const bookmarks = readStorage("cinema-progress");
        if (video.ended || video.currentTime > video.duration - 10)
            delete bookmarks[this.config.path];
        else bookmarks[this.config.path] = video.currentTime;
        writeStorage("cinema-progress", bookmarks);
    },
    togglePlay() {
        const v = this.$refs.video;
        if (v.paused) v.play().catch(() => this.onError());
        else v.pause();
    },
    seekBack() {
        const v = this.$refs.video;
        if (Number.isFinite(v.duration))
            v.currentTime = Math.max(0, v.currentTime - 10);
    },
    seekForward() {
        const v = this.$refs.video;
        if (Number.isFinite(v.duration))
            v.currentTime = Math.min(v.duration, v.currentTime + 10);
    },
    restart() {
        const v = this.$refs.video;
        if (v.readyState) {
            v.currentTime = 0;
            this.saveProgress();
        }
    },
    fullscreen() {
        const video = this.$refs.video,
            method = video.requestFullscreen || video.webkitRequestFullscreen;
        if (method)
            Promise.resolve(method.call(video)).catch(() => {
                this.playbackMessage =
                    "Tela cheia indisponível neste navegador.";
            });
    },
    changeSubtitle() {
        Array.from(this.$refs.video.textTracks).forEach((track, index) => {
            track.mode =
                String(index) === this.subtitle ? "showing" : "disabled";
        });
    },
    audioChanged() {
        if (!this.isBusy) {
            this.state = "idle";
            this.key = null;
            this.preparationMessage =
                "Prepare uma cópia para usar a faixa de áudio selecionada.";
        }
    },
    playPrepared(url) {
        this.saveProgress();
        this.$refs.video.pause();
        this.sourceResume =
            Number(readStorage("cinema-progress")[this.config.path]) || 0;
        this.$refs.video.src = url;
        this.$refs.video.load();
    },
    async prepare() {
        this.requesting = true;
        try {
            const body = {
                path: this.config.path,
                audio: this.audio === "" ? null : Number(this.audio),
            };
            const job = await request("/api/conversions", body);
            this.key = job.key;
            const copies = readStorage("cinema-laravel-copies");
            copies[this.config.path] = { key: job.key, audio: this.audio };
            writeStorage("cinema-laravel-copies", copies);
            this.$refs.video.pause();
            this.applyJob(job);
        } catch (error) {
            this.preparationMessage = error.message;
        } finally {
            this.requesting = false;
        }
    },
    async checkPreparation() {
        clearTimeout(this.pollTimer);
        try {
            this.applyJob(await request("/api/conversions/" + this.key));
        } catch (error) {
            this.preparationMessage = error.message;
            this.state = "error";
        }
    },
    applyJob(job) {
        clearTimeout(this.pollTimer);
        this.state = job.state;
        this.percent = job.percent;
        if (job.state === "ready") {
            this.preparationMessage =
                "MP4 pronto. Reproduzindo a cópia preparada para TV.";
            this.playPrepared(job.url);
        } else if (job.state === "queued") {
            this.preparationMessage =
                "Na fila. A preparação começará assim que o worker estiver disponível.";
            this.pollTimer = setTimeout(() => this.checkPreparation(), 2000);
        } else if (job.state === "working") {
            this.preparationMessage =
                "Preparando MP4: " +
                job.percent +
                "%. Você pode voltar ao catálogo; a preparação continua.";
            this.pollTimer = setTimeout(() => this.checkPreparation(), 2000);
        } else
            this.preparationMessage =
                job.message ||
                (job.state === "cancelled"
                    ? "Preparação cancelada."
                    : "A preparação falhou. Tente novamente.");
    },
    async cancel() {
        if (!this.key) return;
        try {
            this.applyJob(
                await request("/api/conversions/" + this.key + "/cancel", {}),
            );
        } catch (error) {
            this.preparationMessage = error.message;
        }
    },
});
