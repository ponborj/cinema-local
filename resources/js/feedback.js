const controlSelector = [
    ".button-primary",
    ".button-secondary",
    ".button-icon",
    ".filter-button",
    ".media-card",
    ".breadcrumb",
    ".brand",
    "select.field",
].join(",");

export function installPressFeedback() {
    let pressedControl = null;
    let pressedAt = 0;
    let releaseTimer = null;

    function findControl(target) {
        if (!(target instanceof Element)) return null;

        const control = target.closest(controlSelector);
        if (!control || control.matches(":disabled, [aria-disabled='true']")) {
            return null;
        }

        return control;
    }

    function clearPress() {
        clearTimeout(releaseTimer);
        pressedControl?.classList.remove("is-pressed");
        pressedControl = null;
    }

    function press(control) {
        if (!control) return;

        clearPress();
        pressedControl = control;
        pressedAt = performance.now();
        control.classList.add("is-pressed");
    }

    function release() {
        if (!pressedControl) return;

        // Deixa o toque visível mesmo quando o clique é rápido.
        const remaining = Math.max(0, 160 - (performance.now() - pressedAt));
        clearTimeout(releaseTimer);
        releaseTimer = setTimeout(clearPress, remaining);
    }

    document.addEventListener("pointerdown", (event) => {
        if (event.button !== 0 || event.isPrimary === false) return;
        press(findControl(event.target));
    });
    document.addEventListener("pointerup", release);
    document.addEventListener("pointercancel", clearPress);

    document.addEventListener("keydown", (event) => {
        if (event.repeat || !["Enter", " "].includes(event.key)) return;
        press(findControl(event.target));
    });
    document.addEventListener("keyup", (event) => {
        if (["Enter", " "].includes(event.key)) release();
    });

    // Também atende cliques enviados pelo controle da TV.
    document.addEventListener("click", (event) => {
        const control = findControl(event.target);
        if (control) {
            press(control);
            release();
        }
    }, true);
    document.addEventListener("change", (event) => {
        const control = findControl(event.target);
        if (control?.matches("select")) {
            press(control);
            release();
        }
    });
    document.addEventListener("focusout", (event) => {
        if (event.target === pressedControl) clearPress();
    });
    window.addEventListener("blur", clearPress);
    window.addEventListener("pagehide", clearPress);
}
