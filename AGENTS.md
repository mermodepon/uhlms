# Project Instructions

## Environment Compatibility

- Treat `localhost` and the Cloudflare-hosted app as equally supported runtime targets.
- When making changes, preserve compatibility for both local development and the Cloudflare-exposed version of the app.
- Avoid solutions that only work when `APP_URL` matches one environment unless the workflow explicitly switches and verifies that value.
- Prefer request-aware or relative URL generation for assets, media, and internal links when possible.
- For anything involving media, previews, redirects, callbacks, or generated links, sanity-check behavior in both local and Cloudflare modes.
