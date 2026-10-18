{{-- Behaviour of the farmer sign-up form (auth/partials/register-form.blade.php): name joining, inline checks, password
     strength (the eye is <x-password-toggle>), farm location and the other-organizations picker. Shared by /register and the landing page. --}}
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.register-form');
    const formMessage = document.getElementById('formMessage');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const name = document.getElementById('name');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const confirmSuccess = document.getElementById('confirmPassword-success');
    const captchaAnswer = document.getElementById('captcha_answer');
    const agreeTerms = document.getElementById('agreeTerms');
    const submit = document.getElementById('createAccountBtn');
    const organization = document.getElementById('farmer_organization_id');
    const assignedBarangayGroup = document.getElementById('assignedBarangayGroup');
    const assignedBarangay = document.getElementById('assignedBarangay');
    const manualBarangayGroup = document.getElementById('manualBarangayGroup');
    const manualBarangay = document.getElementById('barangay');
    const strengthLabel = document.getElementById('passwordStrengthLabel');
    const strengthFill = document.getElementById('passwordStrengthFill');

    // Inline messages replace the browser's validation bubbles; the server still validates everything.
    form.noValidate = true;
    // On a laptop a message is cut to one line; its full text shows on hover.
    document.querySelectorAll('.field-error').forEach((slot) => { slot.title = slot.textContent.trim(); });

    const syncName = () => {
      name.value = `${firstName.value.trim()} ${lastName.value.trim()}`.trim();
    };

    const passwordRules = (val) => ({
      length: val.length >= 8,
      lowercase: /[a-z]/.test(val),
      uppercase: /[A-Z]/.test(val),
      number: /[0-9]/.test(val),
      symbol: /[^A-Za-z0-9]/.test(val),
    });

    const checkStrength = (val) => {
      const rules = passwordRules(val);
      const score = Object.values(rules).filter(Boolean).length;
      const level = score === 0 ? null : score <= 2 ? 'weak' : score === 3 ? 'fair' : score === 4 ? 'good' : 'strong';
      const names = { weak: 'Weak', fair: 'Fair', good: 'Good', strong: 'Strong' };
      const widths = { weak: '25%', fair: '50%', good: '75%', strong: '100%' };

      Object.entries(rules).forEach(([rule, isMet]) => {
        const item = document.querySelector(`[data-password-rule="${rule}"]`);
        item?.classList.toggle('is-met', isMet);
        const state = item?.querySelector('[data-rule-state]');
        if (state) state.textContent = isMet ? ' (met)' : ' (not yet met)';
      });

      strengthLabel.className = 'pwd-strength-label' + (level ? ` is-${level}` : '');
      strengthLabel.querySelector('strong').textContent = level ? names[level] : '—';
      strengthFill.className = 'pwd-strength-fill' + (level ? ` is-${level}` : '');
      strengthFill.style.width = level ? widths[level] : '0';
    };

    const setError = (field, message) => {
      const slot = document.getElementById(`${field.id}-error`);
      field.classList.toggle('is-invalid', Boolean(message));
      if (message) field.setAttribute('aria-invalid', 'true'); else field.removeAttribute('aria-invalid');
      if (slot) {
        slot.textContent = message || '';
        slot.title = message || '';
        slot.hidden = ! message;
      }
    };

    const validators = new Map([
      [firstName, () => firstName.value.trim() ? '' : 'Please enter your first name.'],
      [lastName, () => lastName.value.trim() ? '' : 'Please enter your last name.'],
      [email, () => {
        const value = email.value.trim();
        if (! value) return 'Please enter your email address.';
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Please enter a valid email address.';
      }],
      [manualBarangay, () => organization.value || manualBarangay.value ? '' : 'Please select your barangay.'],
      [password, () => {
        if (! password.value) return 'Please create a password.';
        return Object.values(passwordRules(password.value)).every(Boolean) ? '' : 'Your password must meet all the requirements below.';
      }],
      [confirmPassword, () => {
        if (! confirmPassword.value) return 'Please re-enter your password.';
        return confirmPassword.value === password.value ? '' : 'Passwords do not match.';
      }],
      [captchaAnswer, () => captchaAnswer.value.trim() ? '' : 'Please enter the code shown in the image.'],
      [agreeTerms, () => agreeTerms.checked ? '' : 'Please agree to the Terms of Use and Privacy Policy to continue.'],
    ]);

    const validateField = (field) => {
      const message = validators.get(field)();
      setError(field, message);
      return ! message;
    };

    // Match feedback appears only once the user has typed a confirmation.
    const syncConfirmFeedback = () => {
      if (! confirmPassword.value) {
        confirmSuccess.hidden = true;
        return;
      }
      const matches = confirmPassword.value === password.value;
      setError(confirmPassword, matches ? '' : 'Passwords do not match.');
      confirmSuccess.hidden = ! matches;
    };

    validators.forEach((validate, field) => {
      const event = field === agreeTerms || field.tagName === 'SELECT' ? 'change' : 'input';
      field.addEventListener(event, () => {
        if (field.classList.contains('is-invalid')) validateField(field);
      });
      field.addEventListener('blur', () => {
        if (field.type !== 'checkbox' && field.value.trim()) validateField(field);
      });
    });

    const syncFarmLocation = () => {
      const selected = organization?.selectedOptions?.[0];
      const barangay = selected?.dataset?.barangay || '';
      const hasOrganization = Boolean(organization?.value);

      assignedBarangay.value = barangay;
      assignedBarangayGroup.style.display = hasOrganization ? '' : 'none';
      manualBarangayGroup.style.display = hasOrganization ? 'none' : '';
      manualBarangay.disabled = hasOrganization;
      manualBarangay.required = ! hasOrganization;
      if (hasOrganization) setError(manualBarangay, '');
    };

    // Other organizations: the hidden native multi-select stays the source of truth (it is what gets submitted); this
    // builds a searchable dropdown with chips on top of it. The main organization is never offered as an "other" one.
    const setupOtherOrganizations = () => {
      const native = document.getElementById('other_organizations');
      if (! native) return () => {};
      const group = native.closest('[data-org-multiselect]');
      const errorSlot = document.getElementById('other_organizations-error');
      const options = [...native.options];
      const placeholder = 'Select organizations (optional)';
      const svg = (path) => `<svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="${path}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

      // The field looks like the other inputs: icon, the chosen organizations as chips on one line ("+N more" for
      // the ones that do not fit), then the search box. The list floats below it, or above when there is no room.
      const wrap = document.createElement('div');
      wrap.className = 'form-input has-icon org-ms';
      const chips = document.createElement('ul');
      chips.className = 'org-ms-chips';
      chips.setAttribute('aria-label', 'Chosen organizations');
      const more = document.createElement('button');
      Object.assign(more, { type: 'button', className: 'org-ms-more', hidden: true });
      const input = document.createElement('input');
      Object.assign(input, { type: 'text', id: 'other_organizations_search', className: 'org-ms-search', placeholder, autocomplete: 'off', spellcheck: false });
      Object.entries({ role: 'combobox', 'aria-expanded': 'false', 'aria-controls': 'other_organizations_list', 'aria-autocomplete': 'list', 'aria-describedby': 'other_organizations-error' })
        .forEach(([key, value]) => input.setAttribute(key, value));
      if (native.classList.contains('is-invalid')) {
        wrap.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
      }
      const list = document.createElement('ul');
      Object.assign(list, { id: 'other_organizations_list', className: 'org-ms-list', hidden: true });
      list.setAttribute('role', 'listbox');
      list.setAttribute('aria-multiselectable', 'true');
      list.setAttribute('aria-label', 'Other organizations');

      wrap.append(organization.parentElement.querySelector('.input-icon').cloneNode(true), chips, more, input, list);
      native.hidden = true;
      group.querySelector('.org-native-hint').hidden = true;
      group.querySelector('label').htmlFor = input.id;
      native.after(wrap);

      let visible = [];
      let active = -1;
      const isOpen = () => ! list.hidden;

      const renderList = () => {
        const query = input.value.trim().toLowerCase();
        visible = options.filter((option) => ! option.disabled
          && (! query || `${option.dataset.name} ${option.dataset.barangay}`.toLowerCase().includes(query)));
        active = Math.min(active, visible.length - 1);

        list.replaceChildren(...visible.map((option, index) => {
          const item = document.createElement('li');
          item.id = `other_organizations_option_${option.value}`;
          item.className = 'org-ms-option' + (index === active ? ' is-active' : '');
          item.dataset.index = index;
          item.setAttribute('role', 'option');
          item.setAttribute('aria-selected', String(option.selected));
          const check = document.createElement('span');
          check.className = 'org-ms-check';
          check.innerHTML = svg('M2.5 6.2l2.2 2.2 4.8-5');
          const text = document.createElement('span');
          text.className = 'org-ms-text';
          const barangay = document.createElement('span');
          barangay.className = 'org-ms-barangay';
          barangay.textContent = `· ${option.dataset.barangay}`;
          text.append(`${option.dataset.name} `, barangay);
          item.append(check, text);
          return item;
        }));
        if (! visible.length) {
          const empty = document.createElement('li');
          empty.className = 'org-ms-empty';
          empty.textContent = 'No organization matches your search.';
          list.append(empty);
        }

        // Scroll only the list (never the page) to keep the highlighted option in view.
        const current = list.querySelector('.is-active');
        if (current) {
          input.setAttribute('aria-activedescendant', current.id);
          if (current.offsetTop < list.scrollTop) list.scrollTop = current.offsetTop;
          else if (current.offsetTop + current.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = current.offsetTop + current.offsetHeight - list.clientHeight;
        } else {
          input.removeAttribute('aria-activedescendant');
        }
      };

      // Chips stay on one line: the ones that do not fit are hidden and counted in "+N more".
      const fitChips = () => {
        const items = [...chips.children];
        let shown = items.length;
        const showMore = () => {
          more.hidden = shown === items.length;
          more.textContent = shown ? `+${items.length - shown} more` : `${items.length} chosen`;
          more.setAttribute('aria-label', `${items.length} organizations chosen. Show the list.`);
        };
        items.forEach((item) => { item.hidden = false; });
        showMore();
        while (shown > 0 && chips.scrollWidth > chips.clientWidth + 1) {
          items[--shown].hidden = true;
          showMore();
        }
        input.placeholder = items.length ? '' : placeholder;
      };

      const renderChips = () => {
        chips.replaceChildren(...options.filter((option) => option.selected).map((option) => {
          const chip = document.createElement('li');
          chip.className = 'org-ms-chip';
          const name = document.createElement('span');
          name.textContent = option.dataset.name;
          name.title = `${option.dataset.name} · ${option.dataset.barangay}`;
          const remove = document.createElement('button');
          remove.type = 'button';
          remove.setAttribute('aria-label', `Remove ${option.dataset.name}`);
          remove.innerHTML = svg('M3 3l6 6M9 3l-6 6');
          remove.addEventListener('click', () => {
            toggle(option, false);
            input.focus();
          });
          chip.append(name, remove);
          return chip;
        }));
        fitChips();
      };

      const toggle = (option, on = ! option.selected) => {
        option.selected = on;
        wrap.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        if (errorSlot) errorSlot.hidden = true;
        renderChips();
        if (isOpen()) renderList();
      };

      const open = () => {
        if (! isOpen()) {
          const box = wrap.getBoundingClientRect();
          const below = window.innerHeight - box.bottom;
          wrap.classList.toggle('is-up', below < 260 && box.top > below);
          list.hidden = false;
          wrap.classList.add('is-open');
          input.setAttribute('aria-expanded', 'true');
        }
        renderList();
      };

      const close = () => {
        list.hidden = true;
        active = -1;
        wrap.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
      };

      const move = (step) => {
        if (! isOpen()) return open();
        if (! visible.length) return;
        active = active < 0 ? (step > 0 ? 0 : visible.length - 1) : (active + step + visible.length) % visible.length;
        renderList();
      };

      // Pressing anywhere in the field keeps the focus in the search box, so the list stays open for picking several.
      wrap.addEventListener('mousedown', (event) => {
        if (event.target !== input) event.preventDefault();
      });
      wrap.addEventListener('click', (event) => {
        const item = event.target.closest('.org-ms-option');
        if (item) {
          active = Number(item.dataset.index);
          toggle(visible[active]);
          return;
        }
        if (list.contains(event.target) || event.target.closest('.org-ms-chip button')) return;
        input.focus();
        open();
      });
      input.addEventListener('input', () => {
        active = 0;
        open();
      });
      input.addEventListener('blur', close);
      input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          move(event.key === 'ArrowDown' ? 1 : -1);
        } else if (event.key === 'Enter') {
          // Enter picks an organization; it never submits the form from this field.
          event.preventDefault();
          if (isOpen() && visible[active]) toggle(visible[active]); else open();
        } else if (event.key === 'Escape' && isOpen()) {
          event.preventDefault();
          close();
        } else if (event.key === 'Backspace' && ! input.value) {
          const last = options.filter((option) => option.selected).pop();
          if (last) toggle(last, false);
        }
      });
      if ('ResizeObserver' in window) new ResizeObserver(fitChips).observe(wrap);

      // Called whenever the main organization changes: it leaves the options and the chips.
      return () => {
        options.forEach((option) => {
          option.disabled = option.value === organization.value;
          if (option.disabled) option.selected = false;
        });
        renderChips();
        if (isOpen()) renderList();
      };
    };
    const syncOtherOrganizations = setupOtherOrganizations();

    firstName.addEventListener('input', syncName);
    lastName.addEventListener('input', syncName);
    email.addEventListener('blur', () => { email.value = email.value.trim().toLowerCase(); });
    password.addEventListener('input', () => {
      checkStrength(password.value);
      syncConfirmFeedback();
    });
    confirmPassword.addEventListener('input', syncConfirmFeedback);
    organization.addEventListener('change', () => {
      syncFarmLocation();
      syncOtherOrganizations();
    });

    // After a failed submission only the combined name comes back if the split fields were not posted.
    if (! firstName.value && ! lastName.value && name.value) {
      const parts = name.value.trim().split(/\s+/);
      lastName.value = parts.length > 1 ? parts.pop() : '';
      firstName.value = parts.join(' ');
    }
    syncName();
    checkStrength(password.value || '');
    syncFarmLocation();
    syncOtherOrganizations();

    form.addEventListener('submit', (event) => {
      syncName();
      email.value = email.value.trim().toLowerCase();

      const invalid = [...validators.keys()].filter((field) => ! validateField(field));
      confirmSuccess.hidden = confirmPassword.classList.contains('is-invalid') || ! confirmPassword.value;

      if (invalid.length) {
        event.preventDefault();
        formMessage.hidden = false;
        invalid[0].focus();
        return;
      }

      formMessage.hidden = true;
      submit.disabled = true;
      submit.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>Creating account...';
    });

    // Returning with the Back button restores the page from cache: make the button usable again.
    window.addEventListener('pageshow', (event) => {
      if (! event.persisted) return;
      submit.disabled = false;
      submit.textContent = 'Create account';
    });
  });
</script>
