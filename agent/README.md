# /agent

This directory is reserved for automated agent scripts, background workers,
or CLI utilities that support the Grocery List application.

## Intended uses

| Script / file         | Purpose                                              |
|-----------------------|------------------------------------------------------|
| `cleanup_old_lists.php` | (planned) Delete lists that haven't been updated in N days |
| `health_check.php`    | (planned) Verify DB connectivity and schema version  |

Add agent scripts here; they should **not** be accessible via the web root.
Configure your web server to deny direct HTTP access to this directory.

## Running agent scripts

```bash
php agent/cleanup_old_lists.php
```
