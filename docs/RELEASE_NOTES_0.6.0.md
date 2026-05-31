# Social Media Scheduler 0.6.0 Release Candidate

This release candidate starts from the stable 0.5.10 feature set and refactors persistence to Doctrine ORM entities.

## Important

This build is intended for clean installation testing. The user has confirmed that 0.5.10 was saved separately and uninstalled before testing 0.6.0.

## What changed

- Added Doctrine entities for package tables.
- Added package entity provider registration in `controller.php`.
- Added `src/Package/Installer.php` to keep the package controller small.
- Kept dashboard pages clean: Posts, Create, Logs, Configuration.
- Kept sender/channel logic from 0.5.10.
- Added `icon.png` as 184 × 184 package icon.

## Test checklist

1. Fresh install package.
2. Confirm tables are created.
3. Create channels.
4. Create a posting.
5. Run manual Send Now.
6. Run automated task.
7. Check logs.
8. Uninstall package and verify tables are removed.
