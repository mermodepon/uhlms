# Phase 9 security operations

## Safe rollout order

1. Keep `ADMIN_MFA_MODE=optional` during enrollment.
2. Store current copies of `APP_KEY` and a separately generated 32-byte `BACKUP_ENCRYPTION_KEY` in an external operational escrow. The backup key is a base64 value and must not be stored in the database, a backup, source control, email, or logs.
3. Run `php artisan migrate --force`, clear cached configuration, and restart the web and queue processes.
4. Sign in as a super administrator, open `/admin/mfa-setup`, enroll an authenticator, verify a TOTP code, and store the displayed recovery codes offline.
5. Verify recent-MFA access to Backup & Restore. Run `php artisan backup:audit` before treating the backup directory as production-ready.
6. Convert any trusted legacy dump with `php artisan backup:import-legacy "C:\absolute\backup.sql"`. Add `--delete-source` only when secure deletion of the verified source is intended, then confirm the interactive prompt.
7. Change `ADMIN_MFA_MODE=enforce`, run `php artisan config:clear`, and restart the web and queue processes.

Backup operations deliberately fail until a valid backup key is configured and the current super administrator has confirmed MFA within ten minutes. Emergency MFA rollback changes only `ADMIN_MFA_MODE` to `optional`; backup operations still require recent MFA.

## Recovery and audit commands

- `php artisan admin:mfa-recover` is the interactive local-console recovery path. It accepts only an exact super-administrator email and exact confirmation phrase, and writes a security event.
- `php artisan backup:audit` reports missing/incorrect keys, plaintext SQL, damaged encrypted files, retention violations, unsafe permissions, and web-accessible storage.
- Dedicated redacted security events are written to `storage/logs/security-YYYY-MM-DD.log` and retained for 30 days.

The default backup directory is `storage/app/backups`, outside `public`. Routine `.uhlmsbak` files are retained at most 30 days and ten files; pre-restore snapshots are retained at most seven days and three files.

## Dependency checks

- PHP: `composer audit --locked --abandoned=report`
- JavaScript: `npm ci --include=dev --ignore-scripts`, then `npm run audit:security`

The GitHub Actions dependency-security workflow runs these checks on pull requests, `main` pushes, weekly, and on manual dispatch. An unreachable advisory service fails the workflow rather than silently passing.
