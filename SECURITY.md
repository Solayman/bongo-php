# Security policy

## Reporting a vulnerability

Please **do not** report security problems in public issues, discussions or pull requests.

Report them privately through GitHub: open the repository's **Security** tab and choose **"Report a vulnerability"**.

Please include:
- what an attacker can do, and which version is affected;
- the steps or a small example to reproduce it;
- a suggested fix, if you have one.

We aim to answer within 7 days. We'll fix confirmed problems as fast as we can, release the fix, and credit you in the changelog unless you prefer to stay anonymous.

## Supported versions

Bongo is in the 0.x stage. Only the **latest release** gets security fixes, so upgrade with `php bongo upgrade`.

## Keeping your app safe

Run `php bongo security:check` before you deploy. It checks for debug mode, HTTPS, `APP_KEY`, demo passwords and pending migrations.
