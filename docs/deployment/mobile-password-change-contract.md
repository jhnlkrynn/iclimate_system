# Mobile app: password change and recovery (required before the lockdown is turned on)

**For:** the iClimate mobile app developer. **Server side:** done and tested (`tests/Feature/DefaultPasswordLockdownTest.php`).
**Switch:** `ICLIMATE_PASSWORD_LOCKDOWN=true` on the server turns it on. Keep it off until a release with the
changes below is installed by Farmers, or they will see a sign-in error they cannot act on.

The app has no tokens; every call below is a plain JSON POST.

## 1. Handle the two `409` answers on sign-in

`POST /api/mobile/login` with `{email, password}` answers **409** instead of 200 + profile in two cases.

**a) Shared password (published default or copied to other accounts): `recovery_required`.** The password proves
nothing, so the app may NOT offer "choose a new password" here:

```json
{ "message": "Your password is not safe. Ask the Municipal Agriculture Office for a code to choose a new one.",
  "recovery_required": true, "recovery_method": "mao", "recover_url": "https://<host>/account/recover" }
```

`recovery_method` is `email` when the account has a working address (a reset link was just sent) or `mao` (the Farmer
gets a one-time code from MAO Staff after they check who it is). The app shows the message and opens `recover_url`
in the phone's browser, where the Farmer enters email, code and new password (a CSRF-protected web form, limited to
5 tries per 10 minutes; the app cannot post it directly).

**b) A unique one-time password from MAO: `password_change_required`.**

```json
{ "message": "Choose a new password before using iClimate.", "password_change_required": true,
  "change_password_url": "https://<host>/api/mobile/password", "web_change_password_url": "https://<host>/account/change-password",
  "forgot_password_url": "https://<host>/api/mobile/forgot-password" }
```

Show a "Choose a new password" screen that calls section 2. Never keep a profile from an earlier sign-in.

## 2. Choose a new password

`POST /api/mobile/password` (limit: 5 per minute per IP)

| Field | Rule |
|---|---|
| `email` | the account's email |
| `current_password` | the password just typed on the sign-in screen |
| `password`, `password_confirmation` | the site's password policy (min 8, mixed case, number by default), not a shared default, different from `current_password` |

- **200** `{message, user}` — saved; `user` is the same profile as a normal sign-in. All of the account's web sessions are signed out.
- **409** `recovery_required` — the current password is shared; go to section 1a.
- **422** — validation errors in Laravel's usual `errors` object (`email`: wrong credentials or the account cannot sign in; `password`: policy).
- **429** — too many attempts; show "Try again in a minute".

## 3. Forgot password

`POST /api/mobile/forgot-password` with `{email}` (limit: 3 per minute per IP) always answers **200** with the same
message, whether or not the email has an account. If the account has a real email address, it gets the standard
reset link (valid 60 minutes), which opens the website's reset page.

## 4. Recovery when the app cannot be updated yet

An old build only shows the 409 `message`. That message already tells the Farmer what to do (check email, or ask the
Municipal Agriculture Office for a code); the code is entered on the website at `/account/recover`. After that the
app signs in normally with the new password.

## Acceptance (to switch the lockdown on)

1. Sign in with a shared-password test account → app shows the recovery message; no profile; no "new password" form.
2. MAO issues a code → the Farmer enters it with a new password → the app signs in; the old password no longer works.
3. "Forgot password" → reset email arrives for an account with a real address.
4. A Farmer on the previous app version can recover through the website as in section 4.
