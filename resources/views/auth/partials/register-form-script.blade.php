{{-- Behaviour of the farmer sign-up form (auth/partials/register-form.blade.php): name joining, inline checks, password
     strength (the eye is <x-password-toggle>), farm location and the other-organizations picker. Shared by /register and the landing page. --}}
<script @nonce>
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
      [password, () => {
        if (! password.value) return 'Please create a password.';
        return password.value.length >= 8 ? '' : 'Your password must be at least 8 characters.';
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

    firstName.addEventListener('input', syncName);
    lastName.addEventListener('input', syncName);
    email.addEventListener('blur', () => { email.value = email.value.trim().toLowerCase(); });
    password.addEventListener('input', () => {
      checkStrength(password.value);
      syncConfirmFeedback();
    });
    confirmPassword.addEventListener('input', syncConfirmFeedback);

    // After a failed submission only the combined name comes back if the split fields were not posted.
    if (! firstName.value && ! lastName.value && name.value) {
      const parts = name.value.trim().split(/\s+/);
      lastName.value = parts.length > 1 ? parts.pop() : '';
      firstName.value = parts.join(' ');
    }
    syncName();
    checkStrength(password.value || '');

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
