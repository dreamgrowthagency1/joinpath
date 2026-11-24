# JoinPath

Plain PHP app for Telegram join tracking (Meta Pixel and Conversions API).

- `launchpad/`: app code (lives outside the web root on the server)
- `dreamgrowthtrack.com/`: web root of the app domain
- `joinpath.in/`: web root of the landing-page domain

Never commit `launchpad/config.php`, `launchpad/install.key` or uploads. Deploy through cPanel Git Version Control (`.cpanel.yml`).
