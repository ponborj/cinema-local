import { readStorage } from "./storage";

export const libraryPage = () => ({
    query: "",
    filter: "all",
    connectionOpen: false,
    empty: false,
    get allFilterSelected() {
        return this.filter === "all";
    },
    get folderFilterSelected() {
        return this.filter === "folder";
    },
    get videoFilterSelected() {
        return this.filter === "video";
    },
    get allFilterClass() {
        return this.filter === "all" ? "filter-active" : "";
    },
    get folderFilterClass() {
        return this.filter === "folder" ? "filter-active" : "";
    },
    get videoFilterClass() {
        return this.filter === "video" ? "filter-active" : "";
    },
    init() {
        const bookmarks = readStorage("cinema-progress");
        this.$root
            .querySelectorAll('.media-card[data-kind="video"]')
            .forEach((card) => {
                const seconds = Number(bookmarks[card.dataset.path]);
                if (seconds > 10) {
                    const label = card.querySelector(".resume-label");
                    label.textContent =
                        "Continuar de " + Math.floor(seconds / 60) + " min";
                    label.classList.remove("hidden");
                }
            });
        this.connectionHandler = (event) => {
            event.preventDefault();
            this.connectionOpen = !this.connectionOpen;
            event.currentTarget.setAttribute("aria-expanded", String(this.connectionOpen));
            event.currentTarget.classList.toggle("is-selected", this.connectionOpen);
            if (this.connectionOpen)
                this.$nextTick(() =>
                    this.$root
                        .querySelector("#connection")
                        .scrollIntoView({ block: "nearest" }),
                );
        };
        document
            .querySelector("[data-connection-toggle]")
            ?.addEventListener("click", this.connectionHandler);
        this.filterItems();
    },
    destroy() {
        document
            .querySelector("[data-connection-toggle]")
            ?.removeEventListener("click", this.connectionHandler);
    },
    changeFilter(event) {
        this.filter = event.currentTarget.dataset.filter;
        this.filterItems();
    },
    filterItems() {
        const query = this.query.toLocaleLowerCase("pt-BR").trim();
        let count = 0;
        this.$root.querySelectorAll(".media-card").forEach((card) => {
            const visible =
                (this.filter === "all" || this.filter === card.dataset.kind) &&
                card.dataset.search.includes(query);
            card.hidden = !visible;
            if (visible) count++;
        });
        this.empty = count === 0;
    },
});
