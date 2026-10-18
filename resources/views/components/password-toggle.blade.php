{{-- Show/hide eye for a password field: place it after the input inside a positioned wrapper and pass the input's id.
     Starts hidden; one click handler per page flips the type, the label and the slash, and keeps the caret in the input
     when the person was typing. --}}
@props(['for'])

<button type="button" {{ $attributes }} data-password-toggle aria-controls="{{ $for }}" aria-label="Show password" aria-pressed="false">
    <svg width="18" height="18" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/><path class="pwd-toggle-slash" d="M2.5 2.5l11 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" hidden/></svg>
</button>

@once
    <script>
        (() => {
            const fieldOf = (button) => document.getElementById(button.getAttribute('aria-controls'));

            // A mouse press would move focus to the button; keep it in the field if the person is typing there.
            document.addEventListener('mousedown', (event) => {
                const button = event.target.closest('[data-password-toggle]');
                if (button && fieldOf(button) === document.activeElement) event.preventDefault();
            });

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-password-toggle]');
                const input = button && fieldOf(button);
                if (! input) return;

                const typing = input === document.activeElement;
                const { selectionStart, selectionEnd } = input;
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                button.setAttribute('aria-pressed', String(show));
                button.querySelector('.pwd-toggle-slash').toggleAttribute('hidden', ! show);
                if (typing) {
                    input.focus();
                    input.setSelectionRange(selectionStart, selectionEnd);
                }
            });
        })();
    </script>
@endonce
