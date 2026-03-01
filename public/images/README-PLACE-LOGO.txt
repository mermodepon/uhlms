The site logo can be placed in `public/` or `public/images/`.

- Current file detected: `public/uh_logo.jpg` — the panel is configured to use this file.
- Recommended sizes: 256x256 (square) for best results.

Optional:
- To use as favicon for browsers, create a `favicon.ico` and place it at `public/favicon.ico` or update `AdminPanelProvider` to point to a different file.

After adding or replacing the image, clear Laravel caches locally:

Windows (PowerShell):
```powershell
php artisan view:clear
php artisan optimize:clear
```

I can try to clear caches from here if you want — say “Yes, clear caches” and I'll run the commands. Otherwise run them locally.