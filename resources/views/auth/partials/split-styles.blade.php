    /* -- SPLIT PAGE shared by sign in and create account: plain light green panel on the left, form on the right -- */
    /* Moving between the two pages crossfades the form while the green side stays put. */
    @@view-transition { navigation: auto; }
    .login-visual { view-transition-name: auth-visual; }
    .auth-toggle { view-transition-name: auth-toggle; }

    /* The blue strip and the header sit on top; the split fills the rest of the screen. */
    html body:is(.login-page, .register-page) { display: flex; flex-direction: column; flex-wrap: nowrap; }
    body:is(.login-page, .register-page) > :is(.gov-strip, .auth-masthead) { flex: 0 0 auto; }
    body:is(.login-page, .register-page) .auth-masthead ~ .login-split { flex: 1 1 auto; min-height: 0; }

    .login-split { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); }
    .login-visual {
      /* The heading and the card sit together in the centre. */
      display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 24px;
      padding: 32px;
      /* Plain light green, the same as the landing page; no photo. */
      background: #E4F3DE;
      border-right: 1px solid #DCE8D6;
    }
    .login-visual-copy {
      max-width: 440px; text-align: center;
      font-size: clamp(26px, 3vw, 34px); font-weight: 700; line-height: 1.2; color: #1F3D22;
    }
    .login-panel { display: flex; flex-direction: column; align-items: center; padding: 32px 32px 24px; background: #fff; }
    .login-panel-foot { margin-top: auto; padding-top: 32px; font-size: 12px; color: #4D5C50; text-align: center; }

    /* Sign in | Create account switch at the top of the form side. */
    .auth-toggle {
      display: grid; grid-template-columns: 1fr 1fr; gap: 4px;
      width: 100%; max-width: 400px; padding: 4px; margin-bottom: 32px;
      border-radius: 12px; background: #F3FAF0; border: 1px solid #DCE8D6;
    }
    .auth-toggle a {
      display: flex; align-items: center; justify-content: center; height: 42px; border-radius: 8px;
      font-size: 15px; font-weight: 600; color: #4D5C50; text-decoration: none;
    }
    .auth-toggle a:hover { color: #1F3D22; background: #E4F3DE; }
    .auth-toggle a[aria-current="page"] { background: #A6D79B; color: #173B1F; }
    .auth-toggle a:focus-visible { outline: 2px solid #2F6B34; outline-offset: 2px; }

    /* Card on the green side (weather on sign in, account notes on create account): a plain white box. */
    .glass-card {
      width: 100%; max-width: 400px;
      background: #fff; border: 1px solid #DCE8D6; border-radius: 10px;
      padding: 26px; color: #1E2B21;
    }

    @media (max-width: 900px) {
      .login-split { grid-template-columns: minmax(0, 1fr); }
      .login-visual { border-right: 0; border-bottom: 1px solid #DCE8D6; padding: 24px 20px; }
      .login-panel { padding: 28px 20px 24px; }
    }
    @media (max-width: 560px) {
      .glass-card { padding: 20px; }
    }
