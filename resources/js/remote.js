export function installRemoteNavigation() {
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' || event.keyCode === 10009) {
            if (document.fullscreenElement) return;
            const player = document.querySelector('[data-player]');
            const parent = player ? JSON.parse(player.dataset.player).parent : document.querySelector('[data-parent]')?.dataset.parent;
            if (parent) { event.preventDefault(); location.assign(parent); }
            return;
        }
        const active = document.activeElement;
        if (['INPUT', 'SELECT', 'VIDEO'].includes(active.tagName) || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
        const controls = Array.from(document.querySelectorAll('a,button,input,select,video')).filter(el => !el.disabled && el.getClientRects().length && !el.closest('[hidden]'));
        if (!controls.length) return;
        event.preventDefault();
        if (!controls.includes(active)) return (document.querySelector('.media-card:not([hidden])') || controls[0]).focus();
        const rect = active.getBoundingClientRect(), x = rect.left + rect.width / 2, y = rect.top + rect.height / 2;
        const horizontal = event.key === 'ArrowLeft' || event.key === 'ArrowRight', direction = ['ArrowLeft', 'ArrowUp'].includes(event.key) ? -1 : 1;
        let best = null, score = Infinity;
        controls.forEach(el => {
            if (el === active) return;
            const r = el.getBoundingClientRect(), dx = r.left + r.width / 2 - x, dy = r.top + r.height / 2 - y;
            const along = horizontal ? dx : dy, across = horizontal ? dy : dx;
            if (along * direction <= 2) return;
            const distance = Math.abs(along) + Math.abs(across) * 3;
            if (distance < score) { best = el; score = distance; }
        });
        if (best) { best.focus(); best.scrollIntoView({ block: 'nearest' }); }
    });
}
