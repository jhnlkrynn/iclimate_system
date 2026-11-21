// Alpine.js components for the CSP build (@alpinejs/csp, 2026-11-20). The CSP build cannot evaluate inline
// JavaScript, so templates only name a component (x-data="modal") and its properties/methods (x-show="show",
// x-on:click="close"); every piece of logic lives here. That lets the Content-Security-Policy drop 'unsafe-eval'.

const FOCUSABLE = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';

export function registerAlpineComponents(Alpine) {
    // <x-modal name="…" :show="…" focusable>: opened and closed by "open-modal" / "close-modal" window events
    // carrying its name, closed by Escape, the backdrop or a "close" event from inside; Tab keeps focus inside.
    Alpine.data('modal', () => ({
        show: false,
        name: '',

        init() {
            this.name = this.$el.dataset.modalName || '';
            this.show = this.$el.dataset.modalShow === 'true';
            this.$watch('show', (value) => {
                document.body.classList.toggle('overflow-y-hidden', value);
                if (value && this.$el.hasAttribute('data-modal-focusable')) {
                    setTimeout(() => this.firstFocusable()?.focus(), 100);
                }
            });
        },
        openIfNamed(event) {
            if (event.detail === this.name) this.show = true;
        },
        closeIfNamed(event) {
            if (event.detail === this.name) this.show = false;
        },
        close() {
            this.show = false;
        },
        focusables() {
            return [...this.$el.querySelectorAll(FOCUSABLE)].filter((el) => !el.hasAttribute('disabled'));
        },
        firstFocusable() {
            return this.focusables()[0];
        },
        lastFocusable() {
            return this.focusables().slice(-1)[0];
        },
        focusNext(event) {
            if (event.shiftKey) return;
            const list = this.focusables();
            const next = list[(list.indexOf(document.activeElement) + 1) % (list.length + 1)] || this.firstFocusable();
            next?.focus();
        },
        focusPrevious() {
            const list = this.focusables();
            const prev = list[Math.max(0, list.indexOf(document.activeElement)) - 1] || this.lastFocusable();
            prev?.focus();
        },
    }));

    // A button that opens a modal by name: x-data="modalTrigger" data-modal-target="confirm-user-deletion".
    Alpine.data('modalTrigger', () => ({
        open() {
            this.$dispatch('open-modal', this.$el.dataset.modalTarget);
        },
    }));

    // "Saved." notes: shown, then hidden after two seconds (x-transition fades them).
    Alpine.data('flash', () => ({
        show: true,
        init() {
            setTimeout(() => {
                this.show = false;
            }, 2000);
        },
    }));
}
