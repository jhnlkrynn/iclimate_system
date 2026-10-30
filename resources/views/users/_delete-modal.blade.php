{{-- IT, Users list: the Delete account dialog (App\Http\Controllers\UserManagementController::destroy). A Delete button
     ([data-user-delete], users/_row-actions) fills it with that user's details. The reason is required ("Other" needs at
     least config user_deletion.other_min_length characters), the internal note is IT-only, and the preview shows what
     the person will see (in their language) and whether an email goes out. Delete stays disabled until a reason is
     given and the name is typed exactly. --}}
@php
    use App\Services\Accounts\AccountDeletion;
    $minOther = (int) config('user_deletion.other_min_length', 10);
    $reasonData = collect(AccountDeletion::reasons())->map(fn (array $flags, string $key): array => [
        'label' => AccountDeletion::reasonLabel($key, 'en'),
        'requiresText' => (bool) $flags['requires_text'],
        'showPanel' => (bool) $flags['show_panel'],
        'sendEmail' => (bool) $flags['send_email'],
        'message' => ['en' => AccountDeletion::reasonMessage($key, 'en'), 'fil' => AccountDeletion::reasonMessage($key, 'fil')],
    ])->all();
    $words = [
        'panelTitle' => ['en' => trans('user_deletion.panel.title', [], 'en'), 'fil' => trans('user_deletion.panel.title', [], 'fil')],
        'noLongerActive' => ['en' => trans('user_deletion.no_longer_active', [], 'en'), 'fil' => trans('user_deletion.no_longer_active', [], 'fil')],
        'previewNone' => __('user_deletion.modal.preview_none', ['message' => ':message']),
        'emailTo' => __('user_deletion.modal.email_to', ['email' => ':email']),
        'noEmail' => __('user_deletion.modal.no_email'),
        'noEmailReason' => 'No email will be sent for this reason.',
        'confirm' => __('user_deletion.modal.confirm', ['name' => ':name']),
    ];
@endphp
<style>
    .ud-dialog { width: min(600px, calc(100vw - 24px)); max-height: calc(100dvh - 24px); padding: 0; border: 1px solid #d6e4da; border-radius: 14px; box-shadow: 0 24px 60px rgba(13, 31, 24, .28); }
    .ud-dialog::backdrop { background: rgba(13, 31, 24, .45); }
    .ud-head { padding: 16px 20px; background: #17432A; color: #fff; }
    .ud-head h2 { margin: 0; font-size: 1.2rem; font-weight: 700; }
    .ud-body { padding: 18px 20px; display: grid; gap: 14px; }
    .ud-who { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 4px 12px; margin: 0; padding: 12px 14px; border-radius: 10px; background: #f4f8f5; }
    .ud-who dt { color: #5f7569; font-weight: 600; }
    .ud-who dd { margin: 0; overflow-wrap: anywhere; }
    .ud-preview { padding: 12px 14px; border-radius: 10px; background: #FBF5E3; border: 1px solid #eadfb9; }
    .ud-preview-title { margin: 0 0 4px; font-weight: 700; color: #17432A; }
    .ud-preview p { margin: 0; overflow-wrap: anywhere; }
    .ud-mail { margin: 0; font-weight: 600; overflow-wrap: anywhere; }
    .ud-warning { margin: 0; padding: 10px 12px; border-radius: 10px; background: #fdecea; color: #8a1f17; font-weight: 600; }
    .ud-foot { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid #e3ece6; }
    @media (max-width: 480px) { .ud-foot .btn { flex: 1 1 100%; } .ud-who { grid-template-columns: minmax(0, 1fr); } .ud-who dt { margin-top: 4px; } }
</style>
<dialog class="ud-dialog" id="userDeleteDialog" aria-labelledby="userDeleteTitle" data-user-delete-dialog>
    <form method="POST" action="" data-user-delete-form>
        @csrf
        @method('DELETE')
        <header class="ud-head"><h2 id="userDeleteTitle">{{ __('user_deletion.modal.title') }}</h2></header>
        <div class="ud-body">
            <p class="mb-0 small text-muted">{{ __('user_deletion.modal.intro', ['days' => (int) config('user_deletion.restore_days', 30)]) }}</p>
            <dl class="ud-who">
                <dt>{{ __('user_deletion.modal.name') }}</dt><dd data-ud="name"></dd>
                <dt>{{ __('user_deletion.modal.role') }}</dt><dd data-ud="role"></dd>
                <dt>{{ __('user_deletion.modal.barangay') }}</dt><dd data-ud="barangay"></dd>
                <dt>{{ __('user_deletion.modal.email') }}</dt><dd data-ud="email"></dd>
            </dl>
            <p class="ud-warning" data-ud-mao-head hidden>{{ __('user_deletion.modal.mao_head') }}</p>

            <div>
                <label class="form-label fw-semibold" for="udReason">{{ __('user_deletion.modal.reason') }}</label>
                <select class="form-select" id="udReason" name="reason" required>
                    <option value="">{{ __('user_deletion.modal.reason_choose') }}</option>
                    @foreach ($reasonData as $key => $reason)
                        <option value="{{ $key }}">{{ $reason['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div data-ud-other hidden>
                <label class="form-label fw-semibold" for="udOther">{{ __('user_deletion.modal.other') }}</label>
                <textarea class="form-control" id="udOther" name="reason_other" rows="2" maxlength="1000" aria-describedby="udOtherHint"></textarea>
                <span class="form-text" id="udOtherHint">{{ __('user_deletion.modal.other_hint', ['min' => $minOther]) }}</span>
            </div>
            <div>
                <label class="form-label fw-semibold" for="udNote">{{ __('user_deletion.modal.note') }}</label>
                <textarea class="form-control" id="udNote" name="note_internal" rows="2" maxlength="2000" aria-describedby="udNoteHint"></textarea>
                <span class="form-text" id="udNoteHint">{{ __('user_deletion.modal.note_hint') }}</span>
            </div>

            <section aria-labelledby="udPreviewTitle">
                <h3 class="form-label fw-semibold mb-2" id="udPreviewTitle">{{ __('user_deletion.modal.preview') }}</h3>
                <div class="ud-preview" data-ud-preview>
                    <p class="ud-preview-title" data-ud-preview-title></p>
                    <p data-ud-preview-text>—</p>
                    <p class="mt-1" data-ud-preview-other hidden></p>
                </div>
                <p class="ud-mail mt-2" data-ud-mail></p>
            </section>

            <div>
                <label class="form-label fw-semibold" for="udConfirm" data-ud-confirm-label></label>
                <input class="form-control" id="udConfirm" name="confirm_name" type="text" autocomplete="off" spellcheck="false" required>
            </div>
        </div>
        <footer class="ud-foot">
            <button type="button" class="btn btn-outline-secondary" data-ud-cancel>{{ __('user_deletion.modal.cancel') }}</button>
            <button type="submit" class="btn btn-danger" data-ud-submit disabled data-loading-text="Deleting...">{{ __('user_deletion.modal.submit') }}</button>
        </footer>
    </form>
</dialog>
<script>
    (() => {
        const dialog = document.getElementById('userDeleteDialog');
        if (!dialog || dialog.dataset.bound) return;
        dialog.dataset.bound = '1';
        const reasons = @json($reasonData);
        const words = @json($words);
        const minOther = {{ $minOther }};
        const form = dialog.querySelector('[data-user-delete-form]');
        const reason = form.querySelector('#udReason');
        const other = form.querySelector('#udOther');
        const confirmBox = form.querySelector('#udConfirm');
        const submit = form.querySelector('[data-ud-submit]');
        let user = null;

        const update = () => {
            if (!user) return;
            const chosen = reasons[reason.value];
            const lang = user.language === 'fil' ? 'fil' : 'en';
            const needsText = !!chosen?.requiresText;
            form.querySelector('[data-ud-other]').hidden = !needsText;
            other.required = needsText;
            other.disabled = !needsText;   // a hidden "Other" text is not sent

            const title = form.querySelector('[data-ud-preview-title]');
            const text = form.querySelector('[data-ud-preview-text]');
            const extra = form.querySelector('[data-ud-preview-other]');
            const mail = form.querySelector('[data-ud-mail]');
            if (!chosen) {
                title.textContent = '';
                text.textContent = '—';
                extra.hidden = true;
                mail.textContent = '';
            } else if (!chosen.showPanel) {
                title.textContent = '';
                text.textContent = words.previewNone.replace(':message', words.noLongerActive[lang]);
                extra.hidden = true;
                mail.textContent = words.noEmailReason;
            } else {
                title.textContent = words.panelTitle[lang];
                text.textContent = chosen.message[lang];
                extra.hidden = !(needsText && other.value.trim());
                extra.textContent = other.value.trim();
                mail.textContent = !chosen.sendEmail ? words.noEmailReason
                    : (user.recipient ? words.emailTo.replace(':email', user.recipient) : words.noEmail);
            }

            const reasonOk = !!chosen && (!needsText || other.value.trim().length >= minOther);
            submit.disabled = !(reasonOk && confirmBox.value.trim() === user.name.trim());
        };

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-user-delete]');
            if (!button) return;
            event.preventDefault();
            user = JSON.parse(button.dataset.userDelete);
            form.reset();
            form.action = user.action;
            ['name', 'role', 'barangay', 'email'].forEach((key) => { form.querySelector(`[data-ud="${key}"]`).textContent = user[key]; });
            form.querySelector('[data-ud-mao-head]').hidden = !user.maoHead;
            form.querySelector('[data-ud-confirm-label]').textContent = words.confirm.replace(':name', user.name);
            update();
            dialog.showModal();
            reason.focus();
        });
        [reason, other, confirmBox].forEach((field) => field.addEventListener('input', update));
        reason.addEventListener('change', update);
        dialog.querySelector('[data-ud-cancel]').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
        form.addEventListener('submit', (event) => { if (submit.disabled) event.preventDefault(); });
    })();
</script>
