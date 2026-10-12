    /* -- SPLIT PAGE shared by sign in and create account: plain green panel on the left, form on the right -- */
    /* Moving between the two pages crossfades the form while the green side stays put. */
    @@view-transition { navigation: auto; }
    .login-visual { view-transition-name: auth-visual; }
    .auth-toggle { view-transition-name: auth-toggle; }

    .login-split { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); min-height: 100vh; }
    .login-visual {
      position: sticky; top: 0; height: 100vh;
      /* Back button top left; the heading and the card sit together in the centre. */
      display: flex; flex-direction: column; align-items: center; gap: 28px;
      padding: 28px 32px 32px;
      /* Plain page green, the same as the landing page; no photo. */
      background: #1d5443;
      border-right: 1px solid #E3EAE6;
    }
    .login-visual-copy {
      max-width: 440px; margin-top: auto; text-align: center;
      font-size: clamp(26px, 3vw, 38px); font-weight: 600; letter-spacing: -0.035em; line-height: 1.1; color: #fff;
    }
    .login-panel { display: flex; flex-direction: column; align-items: center; padding: 32px 32px 24px; background: #fff; }
    .login-panel-foot { margin-top: auto; padding-top: 32px; font-size: 12px; color: #65736C; text-align: center; }

    /* Back button: a plain dark tile on the green panel. */
    .back-link-top {
      align-self: flex-start; display: inline-flex; align-items: center; gap: 8px;
      height: 40px; padding: 0 18px; border-radius: 980px;
      font-size: 14px; font-weight: 500; color: #fff; text-decoration: none;
      background: #133a2e; border: 1px solid rgba(255,255,255,.12);
      transition: background-color .2s ease;
    }
    .back-link-top:hover { background: #0f2e24; }
    .back-link-top:focus-visible { outline: 2px solid #fff; outline-offset: 3px; }

    /* Sign in | Create account switch at the top of the form side. */
    .auth-toggle {
      display: grid; grid-template-columns: 1fr 1fr; gap: 4px;
      width: 100%; max-width: 400px; padding: 4px; margin-bottom: 40px;
      border-radius: 980px; background: #F1F4F2;
    }
    .auth-toggle a {
      display: flex; align-items: center; justify-content: center; height: 40px; border-radius: 980px;
      font-size: 14px; font-weight: 500; color: #65736C; text-decoration: none;
      transition: color .2s ease, background-color .2s ease;
    }
    .auth-toggle a:hover { color: #1E2B25; }
    .auth-toggle a[aria-current="page"] { background: #fff; color: #1E2B25; box-shadow: 0 1px 3px rgba(6,30,22,.12), 0 1px 2px rgba(6,30,22,.06); }
    .auth-toggle a:focus-visible { outline: 2px solid #1F684F; outline-offset: 2px; }

    /* Card on the green side (weather on sign in, account notes on create account):
       a plain dark tile, like the cards on the landing page. */
    .glass-card {
      width: 100%; max-width: 380px; margin-bottom: auto;
      background: #133a2e; border: 1px solid rgba(255,255,255,.12); border-radius: 28px;
      padding: 28px; color: #fff;
    }

    @media (max-width: 900px) {
      .login-split { grid-template-columns: minmax(0, 1fr); }
      .login-visual { position: relative; height: auto; border-right: 0; border-bottom: 1px solid #E3EAE6; padding: 20px 20px 24px; }
      .login-visual-copy { margin-top: 12px; }
      .glass-card { margin-bottom: 0; }
      .login-panel { padding: 28px 20px 24px; }
    }
    @media (max-width: 560px) {
      .glass-card { padding: 22px; border-radius: 22px; }
    }
