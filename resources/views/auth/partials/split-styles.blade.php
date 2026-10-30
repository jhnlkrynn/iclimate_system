    /* -- SPLIT PAGE shared by sign in and create account: plain light green panel on the left, form on the right -- */
    /* Moving between the two pages crossfades the form while the green side stays put. */
    @@view-transition { navigation: auto; }
    .login-visual { view-transition-name: auth-visual; }
    .auth-toggle { view-transition-name: auth-toggle; }

    /* The header sits on top; the split fills the rest of the screen. */
    html body:is(.login-page, .register-page) { display: flex; flex-direction: column; flex-wrap: nowrap; }
    body:is(.login-page, .register-page) > .auth-masthead { flex: 0 0 auto; }
    body:is(.login-page, .register-page) .auth-masthead ~ .login-split { flex: 1 1 auto; min-height: 0; }

    .login-split { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); }
    .login-visual {
      /* The heading and the card sit together in the centre. */
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 24px;
      padding: 32px;
      /* Plain light green, the same as the landing page; no photo. */
      background: var(--sage-100);
      border-right: 1px solid var(--sage-100);
    }
    .login-visual-copy {
      max-width: 440px; text-align: center;
      font-size: clamp(26px, 3vw, 34px); font-weight: 700; line-height: 1.2; color: var(--sage-700);
    }
    .login-panel { display: flex; flex-direction: column; align-items: center; padding: 32px 32px 24px; background: #fff; }
    .login-panel-foot { margin-top: auto; padding-top: 32px; font-size: 12px; color: #4D5C50; text-align: center; }

    /* Sign in | Create account switch at the top of the form side. */
    .auth-toggle {
      display: grid; grid-template-columns: 1fr 1fr; gap: 4px;
      width: 100%; max-width: 400px; padding: 4px; margin-bottom: 32px;
      border-radius: 12px; background: var(--sage-50); border: 1px solid var(--sage-100);
    }
    .auth-toggle a {
      display: flex; align-items: center; justify-content: center; height: 42px; border-radius: 8px;
      font-size: 15px; font-weight: 600; color: #4D5C50; text-decoration: none;
    }
    .auth-toggle a:hover { color: var(--sage-800); background: var(--sage-100); }
    .auth-toggle a[aria-current="page"] { background: var(--sage-200); color: var(--sage-800); }
    .auth-toggle a:focus-visible { outline: 2px solid var(--sage-700); outline-offset: 2px; }

    /* Card on the green side (weather on sign in, account notes on create account): a plain white box. */
    .glass-card {
      width: 100%; max-width: 400px;
      background: #fff; border: 1px solid var(--sage-100); border-radius: 10px;
      padding: 26px; color: var(--palette-ink);
    }

    @media (max-width: 900px) {
      .login-split { grid-template-columns: minmax(0, 1fr); }
      .login-visual { border-right: 0; border-bottom: 1px solid var(--sage-100); padding: 24px 20px; }
      .login-panel { padding: 28px 20px 24px; }
    }
    @media (max-width: 560px) {
      .glass-card { padding: 20px; }
    }
