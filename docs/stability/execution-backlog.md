# CF7 VK Stability Hardening Execution Backlog

Date: 2026-08-31

This backlog follows the `decompose-work` task shape. Status values are intentionally conservative:

- `needs_design` means an owner or architecture decision is still required.
- `waiting_dependency` means the task is execution-ready after its listed dependency is complete.
- Code implementation started after task `S0` was approved by the owner on 2026-08-31.

## Dependency Order

Recommended order:

1. `S0`
2. `T1`, `T2`
3. `T3`, `T4`, `T5`
4. `T6`, `T7`, `T8`, `T9`
5. `T10`, `T11`, `T12`, `T13`
6. `T14`, `T15`, `T16`
7. `T17`, `T18`, `T19`
8. `QA1` through `QA6`

Execution status as of 2026-08-31:

- `S0`: completed by owner approval in chat.
- `T1`: completed for source/test harness; locally verified through compat runner because the available CLI runtime is PHP 8.0.30 while the plugin requires PHP 8.1.
- `T2`: completed; release ZIP build and validation pass, and repeated builds are byte-identical.
- `T3`: completed for the current lifecycle smoke contract; the full Docker matrix passes, with `v-0.1.2` covered by a local git-tag package builder because no public ZIP was found.
- `T4`: completed; maintenance lifecycle, repair, retention, and slug-rename activation hardening are implemented and covered.
- `T5`: completed; migration runner state, locks, retry, self-heal, and admin recovery guards are implemented and covered.
- `T6`: completed; migration/lifecycle characterization, relation fixtures, damaged fixture repair evidence, and all published/tagged baselines are covered.
- `T7`: completed; VK API and Long Poll HTTP calls are isolated behind a gateway contract, normalized delivery result, VK-aware redactor, and recording fake test harness.
- `T8`: completed; `Logger::write()` centrally redacts sensitive keys, VK tokens, Long Poll keys, emails, phones, and custom filter patterns before hook dispatch and database storage.
- `T9`: completed; credential updates now validate candidate group/token/API version through the VK gateway before persistence and reset bot-owned relations only after validated community identity changes.
- `T10`: completed; Long Poll fetch now coordinates with maintenance/fetch locks, returns structured transient errors, preserves `failed=1/2/3` semantics, and does not advance `longPollTs` after non-ignorable per-update processing failures.
- `T11`: completed; CF7 delivery now returns per-channel/per-recipient structured results, continues later active chats after one VK failure, keeps CF7 success independent from VK transport failure, and emits sanitized `cf7vk_deliveries_completed` summaries.
- `T12`: completed; React REST requests now use typed sanitized `ApiError` diagnostics, paginated bot/chat/channel and CF7 form collection loading, duplicate-ID protection, fail-closed later-page errors, and permalink-safe force-delete URLs.
- `T13`: completed; admin bootstrap now keeps independent resource state, preserves loaded sections across unrelated REST failures, exposes targeted load errors, retries only failed resources, disables dependency-gated controls, and wraps the settings app in an error boundary.
- `T14`: todo; unblocked by completed `T12`, `T13`, and `T9`.

Completed first execution batch after approval:

- `T1. Add PHP Test Runner And Baseline Unit Harness`
- `T2. Add Deterministic Release ZIP Builder And Validator`

Current next execution target:

- `T14. Harden Admin Mutation Sequencing, State Safety, And Selectors`

## S0. Approve VK Stability Contract And First Batch

Status: completed

Goal: approve the VK stability hardening contract, evidence style, fake transport policy, and first implementation batch before code work starts.

Scope:

- Confirm that VK uses the same chronological Stability evidence style as Telegram for this release.
- Confirm fake VK transport only for automated tests.
- Confirm no live VK credentials, no real VK API calls, and no human-in-the-loop VK confirmation in CI.
- Confirm the first execution batch is `T1` plus `T2`.
- Confirm the relation-reset policy for validated VK community identity changes.

Out of Scope:

- Implementation.
- Release tagging.
- WordPress.org publication.

DoR:

- `docs/stability/roadmap.md` exists.
- Owner has reviewed the recommended contract.

DoD:

- Owner approved proceeding on 2026-08-31.
- First execution batch is `T1` plus `T2`.
- Tasks blocked only by this decision can be moved from `waiting_dependency` to `todo`.

AC:

- Given the approved decision, when implementation starts, then no worker has to guess whether live VK may be used.
- Given the approved decision, when a release candidate is verified, then required evidence gates are known.
- Given the approved decision, when credentials identify a different VK community, then relation handling is deterministic.

Dependencies:

- None.

Notes/Risks:

- Recommended identity policy: reset bot-owned bot-chat and bot-channel relations when the validated VK community identity changes; preserve channel posts and form-channel relations.
- Approved default: fake VK only in automated tests; no live VK credentials, no real VK API calls, and no human-in-the-loop VK confirmation in CI.

## T1. Add PHP Test Runner And Baseline Unit Harness

Status: completed

Goal: create the backend test wrapper needed for fast verification of VK hardening.

Scope:

- Update `plugin-dir/composer.json` with `require-dev.phpunit/phpunit`, `scripts.test`, `scripts.test:phpunit`, and `scripts.test:compat`.
- Add `plugin-dir/phpunit.xml.dist`.
- Add `plugin-dir/tests/bootstrap.php` with minimal WordPress, REST, cron, HTTP, posts, options, translations, and wpConnections/wpPostAble stubs required by unit tests.
- Add `plugin-dir/tests/run.php` compatibility runner equivalent to Telegram, with VK class names.
- Add `plugin-dir/tests/TestCase.php`.
- Add first tests for:
  - `Client::getChannels()` querying `posts_per_page => -1`;
  - `Chat::detectTypeByPeerId()`;
  - `Bot::isMaskedSecretValue()`;
  - `Util::versionToInt()` and prerelease ordering;
  - `MessageFormatter::formatForVk()` basic plaintext/html normalization.

Out of Scope:

- Real WordPress integration tests.
- Docker lifecycle smoke.
- VK transport refactor.
- Migration implementation changes.

DoR:

- `S0` approved.

DoD:

- `cd plugin-dir && composer install` installs dev test dependencies.
- `cd plugin-dir && composer test` passes on the local PHP runtime.
- `cd plugin-dir && composer test:compat` passes without relying on PHPUnit platform extensions.
- PHP lint passes for all new PHP test files.

AC:

- Given PHPUnit is installed and required extensions exist, when `composer test` runs, then it executes PHPUnit.
- Given PHPUnit or required extensions are absent, when `composer test:compat` runs, then it executes the compatibility runner.
- Given a regression changes `Client::getChannels()` back to default pagination, then a PHP test fails.
- Given a masked access token value is submitted, then tests prove it is not treated as a real token.

Dependencies:

- `S0`.

Notes/Risks:

- Keep the bootstrap small. Add stubs only when tests require them.
- Do not copy Telegram names blindly; use `Cf7vk_TestCase`, `CF7VK_*`, `cf7vk_*`, and `message-bridge-for-contact-form-7-and-vk`.
- Implemented files:
  - `plugin-dir/phpunit.xml.dist`;
  - `plugin-dir/tests/bootstrap.php`;
  - `plugin-dir/tests/run.php`;
  - `plugin-dir/tests/TestCase.php`;
  - `plugin-dir/tests/BotTest.php`;
  - `plugin-dir/tests/ChatTest.php`;
  - `plugin-dir/tests/ClientTest.php`;
  - `plugin-dir/tests/MessageFormatterTest.php`;
  - `plugin-dir/tests/UtilTest.php`;
  - `plugin-dir/tests/VkApiTest.php`.
- Composer now has `require-dev.phpunit/phpunit`, `scripts.test`, `scripts.test:phpunit`, `scripts.test:compat`, deterministic autoloader suffix `Cf7Vk`, and `config.platform.php=8.1.0` so dev dependencies resolve against the plugin minimum runtime.
- Verification evidence:
  - `cd plugin-dir && composer validate --no-check-publish --no-interaction` passed.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 15 tests, 0 failures, 1 PHP 8.1 dependency-heavy skip.
  - `cd plugin-dir && composer test:compat` passed: 15 tests, 0 failures, 1 PHP 8.1 dependency-heavy skip.
  - `cd plugin-dir && find tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l` passed.
- Environment caveat:
  - The local machine does not provide PHP 8.1 or the PHPUnit extensions `dom`, `mbstring`, `xml`, and `xmlwriter`; full `vendor/bin/phpunit` should be run in CI or another target runtime.

## T2. Add Deterministic Release ZIP Builder And Validator

Status: completed

Goal: replace ad hoc release packaging with a reproducible ZIP build and fail-closed artifact validation.

Scope:

- Add `scripts/build-release-zip.sh` with VK defaults:
  - `PLUGIN_SLUG=message-bridge-for-contact-form-7-and-vk`;
  - `ENTRYPOINT=cf7-vk.php`;
  - `ZIP_NAME=message-bridge-for-contact-form-7-and-vk-wp-plugin.zip` unless the owner chooses another stable artifact name.
- Add `scripts/validate-release-zip.sh` with VK constants:
  - `CF7VK_VERSION`;
  - `CF7VK_FILE`;
  - deterministic Composer autoloader suffix such as `Cf7Vk`.
- Exclude development files, tests, package manifests, React source, source maps, hidden files, logs, dumps, archives, keys, and possible secret filenames.
- Normalize ZIP timestamps with UTC `SOURCE_DATE_EPOCH`.
- Keep `install/build-plugin-package.sh` until the new scripts and workflow fully replace it.

Out of Scope:

- CI workflow replacement.
- WordPress.org promotion.
- Plugin Check gate.
- Test matrix execution.

DoR:

- `S0` approved.

DoD:

- `scripts/build-release-zip.sh` creates a candidate ZIP under `dist/`.
- `scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip` passes.
- Running the build twice from the same source creates byte-identical ZIP files.
- `git diff --check` passes.

AC:

- Given a release ZIP contains `react/src`, then validation fails.
- Given a release ZIP contains `plugin-dir/tests` or `phpunit.xml.dist`, then validation fails.
- Given plugin header version differs from `CF7VK_VERSION` or `readme.txt` Stable tag, then validation fails.
- Given `EXPECTED_VERSION` is set, then mismatched release tags fail validation.

Dependencies:

- `S0`.

Notes/Risks:

- The current stable slug is the WordPress.org text domain, `message-bridge-for-contact-form-7-and-vk`; verify the final ZIP root matches WordPress.org expectations before promotion.
- Implemented files:
  - `scripts/build-release-zip.sh`;
  - `scripts/validate-release-zip.sh`.
- Verification evidence:
  - `bash -n scripts/build-release-zip.sh` passed.
  - `bash -n scripts/validate-release-zip.sh` passed.
  - `./scripts/build-release-zip.sh` passed and created `dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip`.
  - `scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed.
  - Initial repeated build SHA-256 remained `e376f52bbbf053ff60140d4c59b7c768c676efb0fbfa3ccd2b63c54be3de3f85`; ZIP size remained `239298` bytes.
  - After T4, current repeated build SHA-256 remained `ae4fc79bf9548824fe7c5ee86fc5385923d27a2a2ef5fed006f44dc281f19d94`; ZIP size remained `245092` bytes.
  - After T5, current build SHA-256 is `5dd83b81f51a5f3216176fbd58ca00d8c20d69485d7bd4da97873f00838232bf`; ZIP size is `248972` bytes.
  - `git diff --check` passed.
  - `CI=true npm --prefix plugin-dir/react test -- --watchAll=false` passed: 2 suites, 4 tests.
- Follow-up risks:
  - `npm ci` reports 61 vulnerabilities in the current Create React App dependency chain; that is tracked by frontend/release hardening tasks instead of being fixed with a breaking `npm audit fix --force` in T2.
  - CRA emits the known undeclared `@babel/plugin-proposal-private-property-in-object` warning; T16 should remove or contain this build-chain risk when migrating toward WordPress scripts.

## T3. Add Stability Source Manifest And Lifecycle Smoke Harness

Status: completed

Goal: create the isolated WordPress lifecycle smoke wrapper used by later migration, REST, browser, and fake VK gates.

Scope:

- Add `tests/stability/e1-version-sources.json`.
- Record published VK baselines:
  - `v-0.1.0`;
  - `v-0.1.1`;
  - `v-0.1.2`;
  - `v-0.1.3`;
  - `v-0.1.4`.
- Add `tests/stability/e1-smoke-matrix.sh` adapted from Telegram.
- Add fixture scripts:
  - `tests/stability/wp-seed-fixture.php`;
  - `tests/stability/wp-state-snapshot.php`;
  - `tests/stability/wp-upgrade-candidate.php`.
- Add `tests/stability/fixtures/production-fixture.schema.json` with VK-safe fields.
- Emit `summary.json`, `evidence.jsonl`, logs, rollback SQL, and state snapshots.

Out of Scope:

- Complex migration assertions.
- Browser tests.
- Fake VK delivery.
- Release workflow wiring.

DoR:

- `T2` completed.

DoD:

- Fresh install, activation, deactivation, reactivation, uninstall, and rollback smoke can run against a candidate ZIP.
- At least `fresh` and `upgrade-v-0.1.4` cases pass locally before implementation proceeds to migration hardening.
- The harness never writes into the developer WordPress volume.

AC:

- Given no candidate ZIP exists, then the harness fails with an actionable message or stages a complete local candidate according to documented rules.
- Given a published baseline URL or checksum is wrong, then the harness fails before upgrade assertions.
- Given uninstall leaves plugin-owned posts, relations, logs, options, cron, or locks, then a smoke assertion fails once `T4` is implemented.

Dependencies:

- `T2`.

Notes/Risks:

- Current VK tags use the `v-0.1.x` shape. The manifest should normalize expected versions to `0.1.x`.
- If any published WordPress.org ZIP is unavailable, record that as `waiting_dependency` for the affected upgrade case and keep local tag ZIP fallback separate from official artifact evidence.
- Implemented files:
  - `tests/stability/e1-version-sources.json`;
  - `tests/stability/e1-smoke-matrix.sh`;
  - `tests/stability/wp-seed-fixture.php`;
  - `tests/stability/wp-state-snapshot.php`;
  - `tests/stability/wp-upgrade-candidate.php`;
  - `tests/stability/fixtures/production-fixture.schema.json`.
- Artifact source evidence:
  - GitHub release ZIP verified for `v-0.1.0`, SHA-256 `dd5ed6861eb0c96987212d3fb419cab00a6d3fe26d37b382e9d95e7b28d8aea7`; root is legacy `cf7-vk/`.
  - GitHub release ZIP verified for `v-0.1.1`, SHA-256 `ac0af87193a639e6e545718f347b1eb8d75dc891bb4e1cb14af95df17b9259db`.
  - WordPress.org ZIP verified for `v-0.1.3`, SHA-256 `d6a80065d56f23ebb3e6d1a7bf928f5489934593fc586855aa52441f219367bf`.
  - WordPress.org ZIP verified for `v-0.1.4`, SHA-256 `ead46ec71761bae5f7796b5c3022166f98200e1200394696b4e820d2141d8dbb`.
  - `v-0.1.2` local tag verified at commit `e51ef2106898512c33d834bb50a9e8baf3d1bda0`; no public installable ZIP was found, so its upgrade case remains disabled until a recovered ZIP or local tag package builder is added.
- Verification evidence:
  - `jq empty tests/stability/e1-version-sources.json tests/stability/fixtures/production-fixture.schema.json` passed.
  - `bash -n tests/stability/e1-smoke-matrix.sh` passed.
  - `php -l tests/stability/wp-seed-fixture.php` passed.
  - `php -l tests/stability/wp-state-snapshot.php` passed.
  - `php -l tests/stability/wp-upgrade-candidate.php` passed.
  - `tests/stability/e1-smoke-matrix.sh --artifact-only` passed: 10 steps, 9 passed, 1 skipped for the missing public `v-0.1.2` ZIP, 0 failures.
  - `tests/stability/e1-smoke-matrix.sh --case fresh` passed in Docker: 25 steps, 24 passed, 1 skipped for `v-0.1.2`, 0 failures.
  - `tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.4` passed in Docker: 36 steps, 35 passed, 1 skipped for `v-0.1.2`, 0 failures; rollback SQL evidence was emitted.
  - After T4, `tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.0` passed, proving the legacy `cf7-vk/` root can upgrade to the current `message-bridge-for-contact-form-7-and-vk/` root without a duplicate global function fatal.
  - After T4, full `tests/stability/e1-smoke-matrix.sh` passed: summary `/tmp/cf7vk-e1-20260831T144624Z-97750.f4c6I4/results/summary.json`, 129 steps, 128 passed, 1 skipped for the missing public `v-0.1.2` ZIP, 0 failures; current candidate SHA-256 `ae4fc79bf9548824fe7c5ee86fc5385923d27a2a2ef5fed006f44dc281f19d94`.
  - After T5, full `tests/stability/e1-smoke-matrix.sh` passed: summary `/tmp/cf7vk-e1-20260831T152054Z-8070.KTunYL/results/summary.json`, 129 steps, 128 passed, 1 skipped for the missing public `v-0.1.2` ZIP, 0 failures; current candidate SHA-256 `5dd83b81f51a5f3216176fbd58ca00d8c20d69485d7bd4da97873f00838232bf`.
  - After T6, full `tests/stability/e1-smoke-matrix.sh` passed with `v-0.1.2` included through local tag packaging: summary `/tmp/cf7vk-e1-20260831T160122Z-37689.bEHEDH/results/summary.json`, 165 steps, 165 passed, 0 skipped, 0 failures; current candidate SHA-256 `5dd83b81f51a5f3216176fbd58ca00d8c20d69485d7bd4da97873f00838232bf`.
- Follow-up risks:
  - The harness now captures lifecycle state and exercises maintenance hooks; deeper migration-state characterization remains owned by `T6`.
  - `upgrade-v-0.1.0` uses an old plugin root; candidate activation is now guarded against duplicate global function declarations during same-process slug migration.
  - `upgrade-v-0.1.2` is covered by a local git-tag package builder because no public installable ZIP was found.

## T4. Implement Maintenance Lifecycle, Repair, And Log Retention

Status: completed

Goal: add the missing lifecycle owner for cleanup, uninstall, relation repair, cron scheduling, and bounded logs.

Scope:

- Add `plugin-dir/lib/Maintenance.php`.
- Initialize it from `plugin-dir/cf7-vk.php`.
- Register activation, deactivation, and uninstall hooks.
- Add cleanup schedule constants with VK prefixes:
  - `cf7vk_cleanup`;
  - `cf7vk_cleanup_interval`;
  - `cf7vk_cleanup_lock`;
  - `cf7vk_cleanup_cron_last_error`.
- Add cleanup lock and fetch-lock coordination.
- Add dry-run/apply repair of broken plugin-owned relations.
- Preserve ambiguous orphan chats by default.
- Cascade plugin-owned relation deletion when a bot, chat, or channel post is deleted.
- Delete plugin-owned posts, relations, log table, and options on uninstall.
- Add log retention by days and max rows.
- Add filters/constants for cleanup interval and log retention.

Out of Scope:

- Migration runner state changes.
- VK gateway changes.
- Release workflow changes.

DoR:

- `T1` completed.
- `T3` completed.

DoD:

- New PHP tests cover schedule registration, duplicate cleanup prevention, deactivation cleanup, uninstall cleanup, repair dry-run/apply, cascade deletion, and log retention.
- `tests/stability/e1-smoke-matrix.sh --case fresh` passes.
- `tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.4` passes or has documented migration-only failures owned by `T5`.

AC:

- Given activation runs multiple times, then at most one recurring cleanup event exists.
- Given deactivation runs, then cleanup cron and active fetch locks are cleared.
- Given uninstall runs, then plugin-owned data is removed and unrelated options/posts remain.
- Given a broken relation points to the wrong post type, then repair apply deletes the relation and its meta.
- Given an orphan chat cannot be proven safe to delete, then scheduled cleanup preserves it.
- Given log rows exceed retention or cap, then cleanup deletes only `cf7vk_log` rows.

Dependencies:

- `T1`.
- `T3`.

Notes/Risks:

- Use table-name helpers from wpConnections as in Telegram if available.
- Do not make scheduled cleanup destructive toward chats unless a later owner decision explicitly permits that.
- Implemented files:
  - `plugin-dir/lib/Maintenance.php`;
  - `plugin-dir/cf7-vk.php`;
  - `plugin-dir/lib/Bot.php`;
  - `plugin-dir/tests/MaintenanceTest.php`;
  - `plugin-dir/tests/fixtures/wordpress/wp-admin/includes/upgrade.php`;
  - `plugin-dir/tests/bootstrap.php`;
  - `plugin-dir/tests/TestCase.php`.
- Behavior implemented:
  - cleanup cron registration through `cf7vk_cleanup` and `cf7vk_cleanup_interval`;
  - activation scheduling, deactivation cleanup, cleanup locks, and fetch-lock coordination;
  - dry-run/apply relation repair for plugin-owned relation definitions;
  - preservation of ambiguous orphan chats;
  - plugin-owned relation cascade on bot/chat/channel deletion;
  - uninstall cleanup for plugin-owned posts, relations, connection tables, log table, options, cron, and locks;
  - log retention through `cf7vk/logRetentionDays` and `cf7vk/logMaxRows`;
  - guarded entrypoint constants/global update-message function to survive `v-0.1.0` legacy root upgrades in the same WP-CLI process.
- Verification evidence:
  - `php -l plugin-dir/lib/Maintenance.php` passed.
  - `php -l plugin-dir/cf7-vk.php` passed.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 37 tests, 0 failures, 1 PHP 8.1 dependency-heavy skip.
  - `./scripts/build-release-zip.sh` passed after T4 and validated the ZIP.
  - `scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed.
  - Current repeated build SHA-256 remained `ae4fc79bf9548824fe7c5ee86fc5385923d27a2a2ef5fed006f44dc281f19d94`; ZIP size remained `245092` bytes.
  - Full `tests/stability/e1-smoke-matrix.sh` passed after T4: summary `/tmp/cf7vk-e1-20260831T144624Z-97750.f4c6I4/results/summary.json`, 129 steps, 128 passed, 1 skipped for missing public `v-0.1.2`, 0 failures.

## T5. Harden Migration Runner State, Locks, And Recovery

Status: completed

Goal: prevent false-success migrations and give future migration work a safe, observable runner.

Scope:

- Extend `plugin-dir/lib/Controllers/Migration.php` with:
  - `STATE_OPTION`;
  - `LOCK_OPTION`;
  - `STATE_SCHEMA`;
  - `LOCK_TTL`;
  - scheduled/running/failed/completed statuses;
  - source/target version context;
  - schedule failure handling;
  - stale lock cleanup;
  - self-healing scheduling;
  - admin retry method and guarded `admin-post` handler.
- Move version writes after successful migration step execution.
- Separate scheduled event hook from internal migration callback action if needed to avoid recursive ambiguity.
- Add safe admin recovery data in `Settings`.
- Add admin-post action for retry.

Out of Scope:

- A large legacy importer unless a concrete VK legacy source is identified.
- Live database dump import.
- UI redesign beyond a minimal recovery notice/action.

DoR:

- `T1` completed.
- `T4` completed or the task explicitly stubs cleanup-lock coordination until `T4` lands.

DoD:

- PHP tests cover scheduled, running, failed, completed, locked, retry, already-done, and schedule-failed states.
- Existing `Migration::stripPrerelease()` and migration priority behavior remain covered.
- `cf7vk_version` is not advanced after a failed migration step.
- Admin recovery state contains no raw exception details.

AC:

- Given a migration callback throws, then migration state becomes `failed`, version is not advanced, and the error is categorized.
- Given migration is already completed, then a second run is idempotent and does not re-run completed steps.
- Given a stale lock exists, then self-healing clears it and schedules a retry.
- Given a schedule call fails, then state records schedule failure and a test can assert it.
- Given admin retry is requested without capability or nonce, then it is rejected.

Dependencies:

- `T1`.
- `T4`.

Notes/Risks:

- Because VK currently has only a placeholder migration file, this work is mostly preventative. It is still high priority because future migrations are otherwise unsafe.
- Implemented files:
  - `plugin-dir/lib/Controllers/Migration.php`;
  - `plugin-dir/lib/Settings.php`;
  - `plugin-dir/tests/MigrationTest.php`;
  - `plugin-dir/tests/bootstrap.php`.
- Behavior implemented:
  - separate public cron hook `cf7vk_migrations` and internal step hook `cf7vk_migration_steps`;
  - durable migration state option `cf7vk_migration_state`;
  - migration lock option `cf7vk_migration_lock` with stale-lock recovery;
  - source/target version context stored as scalar cron args, including legacy `cf7-vk/cf7-vk.php` basename support;
  - version advancement only after migration steps finish without a failed state;
  - idempotent completed-step skip and already-done marker handling;
  - schedule failure recording without advancing `cf7vk_version`;
  - self-heal scheduling when incomplete state or legacy/modern migration evidence exists;
  - admin recovery state with sanitized public error details;
  - guarded `admin_post_cf7vk_retry_migration` handler that rejects missing capability or invalid nonce.
- Verification evidence:
  - `php -l plugin-dir/lib/Controllers/Migration.php` passed.
  - `php -l plugin-dir/tests/bootstrap.php` passed.
  - `php -l plugin-dir/tests/MigrationTest.php` passed.
  - `cd plugin-dir && composer validate --no-check-publish --no-interaction` passed.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 58 tests, 0 failures, 1 PHP 8.1 dependency-heavy skip.
  - `git diff --check` passed.
  - `./scripts/build-release-zip.sh` passed after T5 and validated the ZIP.
  - `scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed.
  - Current candidate ZIP SHA-256 is `5dd83b81f51a5f3216176fbd58ca00d8c20d69485d7bd4da97873f00838232bf`; ZIP size is `248972` bytes.
  - The ZIP contains no matched dev/test artifacts for `tests`, `phpunit`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `node_modules`, `react/src`, or `.map`.
  - Full `tests/stability/e1-smoke-matrix.sh` passed after T5: summary `/tmp/cf7vk-e1-20260831T152054Z-8070.KTunYL/results/summary.json`, 129 steps, 128 passed, 1 skipped for missing public `v-0.1.2`, 0 failures.

## T6. Add Migration And Lifecycle Characterization Evidence

Status: completed

Goal: turn lifecycle and migration behavior into repeatable evidence across published baselines and damaged fixtures.

Scope:

- Add a VK equivalent of Telegram E2 characterization mode, for example `CF7VK_E2_CHARACTERIZATION=1`.
- Add fixtures:
  - `legacy-basic`;
  - `legacy-heavy`;
  - `damaged-modern`;
  - `partial-modern`;
  - `none`.
- Validate entity counts, relation counts, duplicate relations, option state, migration state, and second-run fingerprints.
- Validate rollback SQL can restore a published baseline after candidate uninstall.

Out of Scope:

- Browser behavior.
- Fake VK transport.
- Live production data.

DoR:

- `T3` completed.
- `T4` completed.
- `T5` completed.

DoD:

- All required baseline cases emit machine-readable E2 JSON evidence.
- Second migration run fingerprints are stable.
- Known unsupported legacy cases are recorded as explicit expected failures only if the owner accepts them.

AC:

- Given `v-0.1.4` upgrades to the candidate, then migration state reaches completed and lifecycle checks pass.
- Given damaged relation fixtures exist, then repair evidence shows planned and applied behavior without data loss.
- Given a second migration run happens, then no duplicate bot-chat, bot-channel, chat-channel, or form-channel relation is created.

Dependencies:

- `T3`.
- `T4`.
- `T5`.

Notes/Risks:

- Do not import raw production data. Use redacted structural fixtures that match `production-fixture.schema.json`.
- Implemented files:
  - `tests/stability/wp-characterize-migration.php`;
  - `tests/stability/wp-seed-fixture.php`;
  - `tests/stability/wp-state-snapshot.php`;
  - `tests/stability/e1-smoke-matrix.sh`;
  - `tests/stability/e1-version-sources.json`.
- Behavior implemented:
  - `CF7VK_E2_CHARACTERIZATION=1` default gate after candidate upgrade;
  - direct migration runner characterization in a fresh WP-CLI process after upgrade;
  - first-run completion assertion;
  - second-run idempotency assertion through unchanged attempts and stable fingerprint;
  - migration state, relation summary, and lifecycle fingerprint in every state snapshot;
  - structured JSON result embedded into `summary.json` for `migration_characterization` steps;
  - relation-seeding fixtures for bot-chat, bot-channel, chat-channel, and form-channel relations;
  - `modern-heavy`, `modern-basic`, `legacy-heavy`, `legacy-basic`, `partial-modern`, `damaged-modern`, and `none` fixture modes;
  - damaged fixture dry-run/apply repair evidence through `Maintenance::buildRepairPlan()` and `Maintenance::runRepair( apply )`;
  - local git-tag package builder for `v-0.1.2` when no public installable ZIP exists.
- Verification evidence:
  - `php -l tests/stability/wp-state-snapshot.php` passed.
  - `php -l tests/stability/wp-characterize-migration.php` passed.
  - `php -l tests/stability/wp-seed-fixture.php` passed.
  - `bash -n tests/stability/e1-smoke-matrix.sh` passed.
  - `jq . tests/stability/e1-version-sources.json >/dev/null` passed.
  - `git diff --check` passed before documentation updates.
  - `tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.4` passed after parser fix: summary `/tmp/cf7vk-e1-20260831T153248Z-16553.5Vathd/results/summary.json`, 38 steps, 37 passed, 1 skipped, 0 failures; characterization `ok=true`, first/second status `completed`, attempts remained `1`, fingerprint stable.
  - `tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.0` passed: summary `/tmp/cf7vk-e1-20260831T153358Z-18203.tbkWD7/results/summary.json`, 38 steps, 37 passed, 1 skipped, 0 failures; characterization `ok=true`, source `0.1.0`, target `0.1.4`, fingerprint stable.
  - Full `tests/stability/e1-smoke-matrix.sh` passed after T6 partial: summary `/tmp/cf7vk-e1-20260831T153506Z-19850.CNrMu9/results/summary.json`, 137 steps, 136 passed, 1 skipped for missing public `v-0.1.2`, 0 failures.
  - In the full matrix, `upgrade-v-0.1.0`, `upgrade-v-0.1.1`, `upgrade-v-0.1.3`, and `upgrade-v-0.1.4` all emitted `migration_characterization` with `ok=true`, first/second status `completed`, attempts `1`, `error_count=0`, and stable after-first/after-second fingerprints.
  - `tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.4` passed with relation fixtures: summary `/tmp/cf7vk-e1-20260831T155326Z-30222.1pH9qv/results/summary.json`, 38 steps, 37 passed, 1 skipped from the then-unbuilt `v-0.1.2` artifact-only preflight, 0 failures; relation counts were `bot2chat=4`, `bot2channel=1`, `chat2channel=4`, `form2channel=2`, total `11`, duplicates `0`, repair plan `0`.
  - `CF7VK_E1_FIXTURE=damaged-modern tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.4` passed: summary `/tmp/cf7vk-e1-20260831T155436Z-31865.b2BDF0/results/summary.json`, 38 steps, 37 passed, 1 skipped from the then-unbuilt `v-0.1.2` artifact-only preflight, 0 failures; expected repair `2`, dry-run planned `2`, apply deleted `2`, post-repair relations returned to total `11`.
  - `tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.2` passed after local tag packaging: summary `/tmp/cf7vk-e1-20260831T155955Z-35727.ulPFio/results/summary.json`, 39 steps, 39 passed, 0 skipped, 0 failures; local `v-0.1.2` artifact SHA-256 `377a1f392058448496f0309c2c2acfc148138d279345cd1320610b0260856069`, size `237390` bytes.
  - Full `tests/stability/e1-smoke-matrix.sh` passed after T6 completion: summary `/tmp/cf7vk-e1-20260831T160122Z-37689.bEHEDH/results/summary.json`, 165 steps, 165 passed, 0 skipped, 0 failures.
  - In the final full matrix, `upgrade-v-0.1.0`, `upgrade-v-0.1.1`, `upgrade-v-0.1.2`, `upgrade-v-0.1.3`, and `upgrade-v-0.1.4` all emitted `migration_characterization` with `ok=true`, relation total `11`, duplicates `0`, source/target versions correct, and stable after-first/after-second fingerprints.
  - `CF7VK_E1_FIXTURE=partial-modern tests/stability/e1-smoke-matrix.sh --case upgrade-v-0.1.4` passed: summary `/tmp/cf7vk-e1-20260831T160831Z-46358.FxkE0J/results/summary.json`, 38 steps, 38 passed, 0 skipped, 0 failures; relation total `1`, duplicates `0`, fingerprint stable.

## T7. Introduce VK Gateway Contract And Recording Fake

Status: completed

Goal: isolate VK network behavior behind a narrow, testable gateway.

Scope:

- Add `plugin-dir/lib/Vk/VkGateway.php`.
- Add `plugin-dir/lib/Vk/VkDeliveryResult.php`.
- Add `plugin-dir/lib/Vk/WordPressVkGateway.php`.
- Add `plugin-dir/lib/Vk/VkRedactor.php` or reuse `LogRedactor` if the design keeps all redaction centralized.
- Move direct `wp_remote_*` calls from `VkApi` into the production gateway or replace `VkApi` with an adapter over the gateway.
- Add a filter such as `cf7vk_vk_gateway` for tests.
- Normalize result fields:
  - `ok`;
  - `status`;
  - `errorCode`;
  - `description`;
  - `retryAfter`;
  - `errorType`;
  - `result`;
  - `method`;
  - `requestId` when useful.

Out of Scope:

- Admin UI changes.
- Full delivery behavior changes.
- Live VK integration tests.

DoR:

- `T1` completed.
- `S0` approves fake transport only.

DoD:

- PHP tests cover successful API response, VK API error, HTTP error, invalid JSON, missing response payload, WP_Error, thrown transport exception, Long Poll success, and Long Poll failure.
- Production behavior still uses WordPress HTTP API.
- Tests can inject a fake gateway without intercepting global HTTP.

AC:

- Given VK returns `{response: ...}`, then the gateway returns `ok=true` and exposes result payload.
- Given VK returns `{error: ...}`, then the gateway returns `ok=false`, `errorType=vk_api`, and a redacted description.
- Given `wp_remote_post` returns `WP_Error`, then the result is `errorType=transport` and contains no token or Long Poll key.
- Given JSON is malformed, then the result is `errorType=malformed_response`.

Dependencies:

- `T1`.
- `S0`.

Notes/Risks:

- VK API methods and Long Poll requests have different URL/body shapes. Keep the gateway explicit instead of creating one generic method that hides cursor/key risk.
- Implemented files:
  - `plugin-dir/lib/Vk/VkGateway.php`;
  - `plugin-dir/lib/Vk/VkDeliveryResult.php`;
  - `plugin-dir/lib/Vk/VkRedactor.php`;
  - `plugin-dir/lib/Vk/WordPressVkGateway.php`;
  - `plugin-dir/tests/Fakes/RecordingVkGateway.php`;
  - `plugin-dir/tests/VkGatewayTest.php`;
  - updates to `plugin-dir/lib/VkApi.php`, `plugin-dir/tests/VkApiTest.php`, and `plugin-dir/tests/bootstrap.php`.
- Production behavior still uses the WordPress HTTP API through `WordPressVkGateway`; `VkApi` remains the backwards-compatible adapter that maps gateway failures back to existing `VkApiException` types.
- Tests can replace the gateway through `cf7vk_vk_gateway` or constructor injection without intercepting global HTTP.
- Long Poll protocol failures such as `failed=1` remain successful transport results so existing cursor handling in `Bot::fetchUpdates()` remains unchanged.
- Verification evidence:
  - `php -l` passed for all production and test PHP files under `plugin-dir/lib` and `plugin-dir/tests`.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 71 tests, 0 failures, 1 PHP 8.1 dependency-heavy skip.
  - `git diff --check` passed.
  - `./scripts/build-release-zip.sh` passed after T7.
  - `./scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed after T7.
  - Current candidate ZIP SHA-256 is `f2f154786cdb42cc8d52b3a246faa1dab1b5b7bc723c7f9541d99f13706ece90`; size is `253521` bytes.
  - `tests/stability/e1-smoke-matrix.sh --case fresh` passed: summary `/tmp/cf7vk-e1-20260831T162159Z-51324.7eOyeT/results/summary.json`, 25 steps, 25 passed, 0 skipped, 0 failures.

## T8. Add Central Redaction For Logs, Transport Errors, And Evidence

Status: completed

Goal: prevent VK secrets and user-submitted private data from leaking into logs or test artifacts.

Scope:

- Add `plugin-dir/lib/LogRedactor.php`.
- Route every `Logger::write()` payload and title through redaction.
- Add default sensitive keys:
  - `token`;
  - `accessToken`;
  - `authorization`;
  - `password`;
  - `secret`;
  - `key`;
  - `longPollKey`;
  - `peerId`;
  - `chatPeerId`;
  - `userId`;
  - `email`;
  - `phone`.
- Redact raw and URL-encoded VK access tokens and Long Poll keys.
- Redact key-value strings and nested arrays/objects.
- Add filters:
  - `cf7vk/logSensitiveKeys`;
  - `cf7vk/logRedactionPatterns`.

Out of Scope:

- Product-level privacy settings.
- Changing delivery message content sent to VK.

DoR:

- `T1` completed.

DoD:

- `LoggerTest` covers arrays, objects, strings, JSON-like strings, encoded tokens, Long Poll URLs, emails, phones, and custom filter patterns.
- All transport exceptions passed into logs are redacted.
- Release/e2e evidence helpers reuse the same redaction rules where practical.

AC:

- Given a log payload contains an access token, then `cf7vk_log.data` stores `[redacted]`.
- Given a `WP_Error` message contains a Long Poll URL with `key=...`, then the stored log does not contain the key.
- Given a payload contains a CF7 email or phone field, then the stored log redacts it.
- Given a custom sensitive key filter is registered, then matching nested values are redacted.

Dependencies:

- `T1`.

Notes/Risks:

- Be careful not to redact every numeric ID in internal diagnostics before tests can distinguish expected recipients. Evidence can store hashed/bucketed IDs instead.
- Implemented files:
  - `plugin-dir/lib/LogRedactor.php`;
  - `plugin-dir/tests/LoggerTest.php`;
  - updates to `plugin-dir/lib/Logger.php` and `tests/stability/wp-state-snapshot.php`.
- `Logger::write()` now redacts data and title before the `logger` integration hook and before insertion into `cf7vk_log`.
- Stability state snapshots reuse `LogRedactor` when the production class is available in the WP-CLI process.
- Verification evidence:
  - `php -l plugin-dir/lib/LogRedactor.php plugin-dir/lib/Logger.php plugin-dir/tests/LoggerTest.php tests/stability/wp-state-snapshot.php` passed.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 77 tests, 0 failures, 1 PHP 8.1 dependency-heavy skip.
  - `git diff --check` passed.
  - `./scripts/build-release-zip.sh` passed after T8.
  - `./scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed after T8.
  - Current candidate ZIP SHA-256 is `d3141e69aa1ab2979136cf0b5b89e831c907ce5d596821ca8f6321c5f311f8a6`; size is `254959` bytes.
  - `tests/stability/e1-smoke-matrix.sh --case fresh` passed: summary `/tmp/cf7vk-e1-20260831T162744Z-53169.70zzTR/results/summary.json`, 25 steps, 25 passed, 0 skipped, 0 failures.

## T9. Add Transactional VK Credential Update Contract

Status: completed

Goal: avoid replacing a working VK bot configuration with unvalidated group/token/API version values.

Scope:

- Add a dedicated REST route such as `/wp/v2/cf7vk_bot/{id}/credentials`.
- Validate candidate `groupId`, `accessToken`, and `apiVersion` through the VK gateway before persistence.
- Persist new credentials only after validation succeeds.
- Persist validated VK community identity, name/screen name, Long Poll bootstrap data, and last status.
- Reset bot-owned relations only when the validated community identity changes according to the approved `S0` policy.
- Keep generic REST field updates backward compatible but steer the React UI to the transactional route.
- Update `Bot.js`, `BotView.js`, and `NewBot.js` to use the route for credential changes.

Out of Scope:

- Live VK validation in tests.
- Changing the admin visual design.
- Migrating existing stored credentials unless required for identity metadata.

DoR:

- `T7` completed.
- `T8` completed.
- `S0` approved relation policy.

DoD:

- PHP tests prove failed validation preserves old token, group ID, API version, identity, Long Poll state, and relations.
- React tests prove UI shows validation failure without losing the previous saved state.
- Successful validation updates bot title/status and starts polling only from persisted state.

AC:

- Given a bot has a working token and group, when an invalid token is submitted, then the stored token and group are unchanged.
- Given a candidate token validates against the same community identity, then existing bot-chat and bot-channel relations are preserved.
- Given a candidate token/group validates against a different community identity, then old bot-owned relations are reset and the UI reflects that reset.
- Given access token is defined by PHP constant, then UI cannot overwrite it through REST.

Dependencies:

- `T7`.
- `T8`.
- `S0`.

Notes/Risks:

- VK may validate `groups.getById` differently from Long Poll bootstrap. Prefer a validation sequence that proves both community access and Long Poll readiness when Long Poll is required for this plugin.
- Implemented files:
  - updates to `plugin-dir/lib/Bot.php`;
  - updates to `plugin-dir/lib/Controllers/RestApi/BotController.php`;
  - updates to `plugin-dir/lib/Controllers/RestApi.php`;
  - updates to `plugin-dir/react/src/components/Bot.js`;
  - updates to `plugin-dir/react/src/components/NewBot.js`;
  - updates to `plugin-dir/react/src/App.js`;
  - updates to `plugin-dir/react/src/utils/api.js`;
  - `plugin-dir/tests/RestBotControllerTest.php`;
  - `plugin-dir/react/src/components/Bot.test.js`;
  - REST stubs added to `plugin-dir/tests/bootstrap.php`.
- New endpoint: `POST /wp/v2/cf7vk_bot/{id}/credentials`.
- Failed candidate validation does not mutate stored token, group ID, API version, community identity, Long Poll state, title, or relations.
- Same community identity preserves bot-owned relations; different identity resets bot-owned `bot2chat` and `bot2channel` relations after successful validation.
- React saves group/token changes through the credentials endpoint, shows validation failures, does not overwrite UI state with failed responses, and does not ping again immediately after transactional validation.
- Verification evidence:
  - `php -l` passed for changed backend PHP files and the new REST test.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 83 tests, 0 failures, 1 PHP 8.1 dependency-heavy skip.
  - `CI=true npm --prefix plugin-dir/react test -- --watchAll=false --runInBand` passed: 3 suites, 6 tests.
  - `git diff --check` passed.
  - `./scripts/build-release-zip.sh` passed after T9.
  - `./scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed after T9.
  - Current candidate ZIP SHA-256 is `589c9a9e8d81b8bafcf4ec8aaf21e4c1cd4e3b4a99478c8046b8d8bdd032a8f0`; size is `257072` bytes.
  - `tests/stability/e1-smoke-matrix.sh --case fresh` passed: summary `/tmp/cf7vk-e1-20260831T164002Z-55539.lTdgDp/results/summary.json`, 25 steps, 25 passed, 0 skipped, 0 failures.

## T10. Harden VK Long Poll Cursor, Locks, And Update Processing

Status: completed

Goal: make VK dialog discovery deterministic and safe under transport, malformed payload, per-update, and concurrency failures.

Scope:

- Define cursor advancement policy for `longPollTs`.
- Recommended default: do not advance `longPollTs` when any update in the batch fails processing unless the failure is explicitly classified as safely ignorable.
- Coordinate fetch locks with `Maintenance` cleanup lock.
- Return structured errors from `fetchUpdates()` without throwing for transient VK failures that the admin UI can retry.
- Preserve `failed=1/2/3` Long Poll semantics, but expose them as structured result fields.
- Redact Long Poll server/key/ts in diagnostics where needed.
- Add tests for:
  - lock held;
  - stale lock;
  - failed=1 cursor update;
  - failed=2/3 bootstrap refresh;
  - malformed update ignored;
  - processing exception does not silently lose cursor;
  - duplicate peer ID in one batch;
  - profile/conversation lookup failures.

Out of Scope:

- Background polling service.
- Webhook support.
- Live VK calls.

DoR:

- `T4` completed.
- `T7` completed.
- `T8` completed.

DoD:

- PHP tests cover cursor and lock semantics.
- Real WordPress REST smoke can trigger fetch updates through fake VK responses.
- Admin UI receives structured transient failure data instead of only generic thrown errors.

AC:

- Given cleanup lock is active, when fetch updates runs, then it returns `locked=true` and does not call VK.
- Given a Long Poll transport error occurs, then bot status and UI diagnostics update without raw key/token leakage.
- Given one update creates a chat and a later update fails processing, then cursor behavior matches the documented policy.
- Given VK returns `failed=2` or `failed=3`, then bootstrap is refreshed and no stale key is logged.

Dependencies:

- `T4`.
- `T7`.
- `T8`.

Notes/Risks:

- Implemented cursor policy is fail-closed for non-ignorable update-processing failures: the bot keeps the previous `longPollTs` and returns `nextTs` separately.
- Optional profile/conversation lookups are classified as safely ignorable; they are logged through the central redactor and do not block cursor advancement.
- Verification evidence:
  - `php -l` passed for `plugin-dir/lib/Bot.php`, `plugin-dir/tests/BotLongPollTest.php`, and `tests/stability/wp-fake-vk-fetch-updates.php`.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 92 tests, 0 failures, 3 PHP 8.1 dependency-heavy skips.
  - `CI=true npm --prefix plugin-dir/react test -- --watchAll=false --runInBand` passed: 3 suites, 7 tests.
  - `git diff --check` passed.
  - `./scripts/build-release-zip.sh` and `./scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed.
  - Current candidate ZIP SHA-256 is `8dcfdd91cdb5f545aea6c7535f0dea38eed00bbe356c474d677fa3d41ddc90f4`; size is `257824` bytes.
  - `tests/stability/e1-smoke-matrix.sh --case fresh` passed: summary `/tmp/cf7vk-e1-20260831T165303Z-58286.V0rQBE/results/summary.json`, 26 steps, 26 passed, 0 skipped, 0 failures; the real WordPress REST fake-VK step returned 200, processed 1 update, advanced the cursor, created 1 chat, and created 1 bot-chat connection.
  - `tests/stability/e1-smoke-matrix.sh` passed: summary `/tmp/cf7vk-e1-20260831T165458Z-59950.Hw1VIC/results/summary.json`, 166 steps, 166 passed, 0 skipped, 0 failures.

## T11. Normalize CF7 Delivery Results And Per-Recipient Failure Handling

Status: completed

Goal: make CF7-to-VK delivery observable, resilient across recipients, and independent from CF7 form success.

Scope:

- Return per-recipient delivery result data from channel/bot send paths.
- Continue later recipients after a VK failure.
- Keep CF7 submission success independent from VK transport failure.
- Emit a completion action with structured results.
- Keep existing public hooks:
  - `cf7vk_skip_delivery`;
  - `cf7vk_unfiltered_message`;
  - `cf7vk_prepared_message`;
  - `cf7vk_channel_sendout`;
  - `cf7vk_delivery_exception`.
- Add a new structured completion action if needed, for example `cf7vk_deliveries_completed`.
- Add formatter tests for multiline, HTML, arrays, private posted fields, and empty values.

Out of Scope:

- VK attachment/media delivery.
- Changing the default message format beyond bug fixes.
- Live VK verification.

DoR:

- `T7` completed.
- `T8` completed.

DoD:

- PHP tests cover no channel, no bot, no chats, inactive chats, relation lookup failure, status lookup failure, one recipient failure plus later success, and message formatting.
- Fake VK E2E in `T18/T19` can assert delivery attempts from structured evidence.

AC:

- Given one active chat fails delivery, when another active chat remains, then the second chat is still attempted.
- Given VK delivery fails for all recipients, then CF7 does not abort solely because of VK.
- Given delivery logs are written, then raw token, Long Poll key, peer ID, email, and phone values are absent or hashed/bucketed.
- Given delivery completes, then hooks receive enough context for downstream diagnostics without exposing secrets.

Dependencies:

- `T7`.
- `T8`.

Notes/Risks:

- VK message size and formatting limits should be verified before adding Telegram-style chunking. Do not assume Telegram's 4096-character rule applies.
- `cf7vk_deliveries_completed` intentionally receives sanitized delivery summary data only; existing hooks still expose their historical richer objects/context for backward compatibility.
- `Channel::getBot()` now handles Ramsey's empty-collection exception as the real no-bot state instead of letting a channel without a bot abort delivery.
- Verification evidence:
  - `php -l` passed for changed backend/test PHP files.
  - `docker run --rm -v "$PWD/plugin-dir":/app -w /app php:8.3-cli php tests/run.php` passed: 100 tests, 448 assertions. PHP 8.3 deprecation notices are emitted by the test doubles/vendor dynamic properties but do not fail the suite.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 100 tests, 0 failures, 10 PHP 8.1 dependency-heavy skips.
  - `CI=true npm --prefix plugin-dir/react test -- --watchAll=false --runInBand` passed: 3 suites, 7 tests.
  - `./scripts/build-release-zip.sh` and `./scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed.
  - Current candidate ZIP SHA-256 is `801886989bc32a7652ce00393708edae67c55a23ff634ee8b5422c6e4139fc0e`; size is `259130` bytes.
  - `tests/stability/e1-smoke-matrix.sh --case fresh` passed: summary `/tmp/cf7vk-e1-20260831T171215Z-74650.9dOEGZ/results/summary.json`, 26 steps, 26 passed, 0 skipped, 0 failures.
  - `tests/stability/e1-smoke-matrix.sh` passed: summary `/tmp/cf7vk-e1-20260831T171312Z-77346.axvHIe/results/summary.json`, 166 steps, 166 passed, 0 skipped, 0 failures.

## T12. Harden REST API And React API Client

Status: completed

Goal: make admin REST traffic paginated, explicit, sanitized, and robust across WordPress permalink modes.

Scope:

- Port `ApiError`, safe URL, safe data, `appendQueryParams`, `forceDeleteUrl`, `fetchAllPages`, and `mergePageItems` patterns from Telegram.
- Add CF7 forms pagination using `per_page` and `offset`.
- Keep bots/chats/channels pagination through `page` and `X-WP-TotalPages`.
- Deduplicate by stable `id`.
- Reject whole collection load when a later page fails.
- Fix force-delete URLs for bot, channel, and chat deletion.
- Add tests for pretty and non-pretty REST route shapes.

Out of Scope:

- UI resource state rendering.
- Server route schema changes except where needed for tests.

DoR:

- `S0` approved.
- Existing React dependencies are installable.

DoD:

- `plugin-dir/react/src/utils/api.test.js` covers pagination, dedupe, later-page failure, invalid JSON, permission errors, sanitized diagnostics, and force delete URLs.
- Existing `npm test` suite passes.

AC:

- Given forms count is greater than one CF7 REST page, then `fetchForms()` returns every form.
- Given a second REST page fails, then the collection request rejects instead of returning partial success.
- Given route URL already contains `?rest_route=`, then `force=true` is appended as a separate query parameter.
- Given a failing URL includes nonce/token/key params, then thrown diagnostics redact them.

Dependencies:

- `S0`.

Notes/Risks:

- This task can run before the `@wordpress/scripts` migration, but tests may be easier to stabilize after `T16`.
- Implemented files:
  - updates to `plugin-dir/react/src/utils/api.js`;
  - `plugin-dir/react/src/utils/api.test.js`.
- `ApiError` now carries `status`, `code`, `category`, `method`, sanitized `url`, and sanitized `data`.
- Bots, chats, and channels use WordPress collection pagination with `per_page=100`, `page`, `orderby=id`, and `order=asc`.
- Contact Form 7 forms use bounded offset pagination with `per_page=100`, `offset`, `orderby=id`, and `order=asc`, because the CF7 endpoint does not provide the same total-page contract.
- Collection merging deduplicates by stable `id` and rejects the full load when a later page fails instead of returning partial success.
- Delete helpers append `force=true` outside existing query strings, including non-pretty REST URLs such as `index.php?rest_route=/wp/v2/cf7vk_chat/`.
- Diagnostics redact nonce/token/secret/password/key/peer/email/phone values in thrown errors and console diagnostics.
- Verification evidence:
  - `CI=true npm --prefix plugin-dir/react test -- --watchAll=false --runInBand` passed: 4 suites, 21 tests.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 100 tests, 0 failures, 10 PHP 8.1 dependency-heavy skips.
  - `git diff --check` passed.
  - `./scripts/build-release-zip.sh` and `./scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed.
  - Current candidate ZIP SHA-256 is `4014e2f997da1d16985f24142325c32f52ceed69aba24592c407d41c663035a7`; size is `260326` bytes.
  - `tests/stability/e1-smoke-matrix.sh --case fresh` passed: summary `/tmp/cf7vk-e1-20260831T173025Z-92678.HUSHz4/results/summary.json`, 26 steps, 26 passed, 0 skipped, 0 failures.
  - `tests/stability/e1-smoke-matrix.sh` passed: summary `/tmp/cf7vk-e1-20260831T173123Z-95429.IrXhDm/results/summary.json`, 166 steps, 166 passed, 0 skipped, 0 failures.

## T13. Add React Resource State, Error Boundary, And Retry-Failed UI

Status: completed

Goal: prevent a single failed admin resource from blanking the settings screen.

Scope:

- Replace all-or-nothing `Promise.all` bootstrap with per-resource state and `Promise.allSettled`.
- Add an error boundary around the settings app.
- Render partial resource errors for bots, channels, forms, chats, and relations.
- Add a retry-failed button that reloads only failed resources.
- Disable create/select controls when their required resource has not loaded.
- Keep loaded resources visible when unrelated resources fail.

Out of Scope:

- Styling redesign.
- Browser E2E.
- Transport/gateway changes.

DoR:

- `T12` completed.

DoD:

- `App.test.js` covers loading, partial success, partial failure, retry-failed, error boundary retry, and disabled controls.
- No unexpected `console.error` output in passing tests except explicit error-boundary expectations.

AC:

- Given channels fail but bots load, then bot cards still render and a channel-specific error appears.
- Given forms fail, then channel form controls are disabled or show a targeted error without hiding existing channels.
- Given retry-failed succeeds, then the error notice disappears and the newly loaded data is rendered.
- Given a render exception happens, then the error boundary shows a retry action.

Dependencies:

- `T12`.

Notes/Risks:

- Keep visible text concise and consistent with existing plugin terminology.
- Implemented files:
  - updates to `plugin-dir/react/src/App.js`;
  - updates to `plugin-dir/react/src/App.scss`;
  - updates to `plugin-dir/react/src/App.test.js`;
  - updates to `plugin-dir/react/src/components/Bot.js`;
  - updates to `plugin-dir/react/src/components/BotView.js`;
  - updates to `plugin-dir/react/src/components/Channel.js`;
  - updates to `plugin-dir/react/src/components/ChannelView.js`;
  - updates to `plugin-dir/react/src/components/NewBot.js`;
  - updates to `plugin-dir/react/src/components/NewChannel.js`.
- `App` now loads bots, channels, chats, forms, and each relation collection through independent resource states and `Promise.allSettled`.
- Loaded bot/channel sections remain visible when another resource fails; failed resources show a top-level retry action and targeted section/card messages.
- `Retry failed requests` reloads only resources currently in `error` state.
- Bot/channel creation buttons and channel form/bot/chat controls are disabled or replaced with targeted loading/error states until their required resource data is ready.
- `SettingsErrorBoundary` contains render failures and exposes a retry action without logging raw exception details.
- Verification evidence:
  - `CI=true npm --prefix plugin-dir/react test -- --watchAll=false --runInBand` passed: 4 suites, 26 tests.
  - `npm --prefix plugin-dir/react run build` passed.
  - `cd plugin-dir && composer test` passed through the compatibility runner on local PHP 8.0.30: 100 tests, 0 failures, 10 PHP 8.1 dependency-heavy skips.
  - `git diff --check` passed.
  - `./scripts/build-release-zip.sh` and `./scripts/validate-release-zip.sh dist/message-bridge-for-contact-form-7-and-vk-wp-plugin.zip 0.1.4` passed.
  - Current candidate ZIP SHA-256 is `fe1193428ca016d84f3da20b0548e7af24754c5e31afbafcda444ed3a77ce8ef`; size is `261384` bytes.
  - `tests/stability/e1-smoke-matrix.sh --case fresh` passed: summary `/tmp/cf7vk-e1-20260831T174649Z-7548.WoDplk/results/summary.json`, 26 steps, 26 passed, 0 skipped, 0 failures.
  - `tests/stability/e1-smoke-matrix.sh` passed: summary `/tmp/cf7vk-e1-20260831T174747Z-10270.Barn9V/results/summary.json`, 166 steps, 166 passed, 0 skipped, 0 failures.
- Known build-chain noise remains from Create React App and React test tooling: dependency deprecation warnings, the undeclared `@babel/plugin-proposal-private-property-in-object` warning, and ReactDOMTestUtils `act` warnings. These are tracked by T16 and did not fail T13 verification.

## T14. Harden Admin Mutation Sequencing, State Safety, And Selectors

Status: todo

Goal: make admin CRUD and relation mutations safe under failures and testable in browser E2E.

Scope:

- Add focused component tests for:
  - `Bot`;
  - `BotView`;
  - `Channel`;
  - `ChannelView`;
  - `NewBot`;
  - `NewChannel`.
- Preserve saved token/group state after failed save or validation.
- Stop polling before destructive bot deletion.
- Avoid stale DOM-only success after failed REST mutation.
- Add small stable selectors or accessible labels required by Playwright.
- Ensure bot/channel/chat deletion updates REST state and preserves unrelated chats.
- Ensure transient fetch-update timeouts do not create persistent card errors when the next retry succeeds.

Out of Scope:

- Visual redesign.
- Full accessibility overhaul.
- Fake VK E2E implementation.

DoR:

- `T12` completed.
- `T13` completed.
- `T9` completed for credential mutation behavior.

DoD:

- React component tests cover success, failure, saving state, cancel, retry, and destructive actions.
- Playwright smoke can select controls without brittle CSS-only assumptions.

AC:

- Given a bot save fails, then the card exits saving state and does not overwrite the last saved form snapshot.
- Given bot deletion fails, then the card remains visible.
- Given channel deletion succeeds, then bot-channel, chat-channel, and form-channel local state is cleaned.
- Given delete uses non-pretty REST, then real REST state confirms the entity is gone.

Dependencies:

- `T12`.
- `T13`.
- `T9`.

Notes/Risks:

- Selector additions should be minimal and should not change production behavior.

## T15. Scope Admin Styles And Notice Policy

Status: waiting_dependency

Goal: keep the plugin admin page usable without leaking styles into unrelated WordPress admin UI.

Scope:

- Scope `ul#adminmenu` arrow styles under `body[class*="page_wpcf7_vk"]`.
- Add full-page background coverage equivalent to Telegram for `#wpwrap`, `#wpcontent`, `#wpbody`, and `#wpbody-content` on the plugin page.
- Hide only unrelated system notices when the plugin needs the full admin surface.
- Preserve plugin-owned notices with a VK-specific class such as `cf7vk-notice`.
- Avoid duplicate `id="cf7-vk-container"` roots.
- Update `Settings::renderPage()` or React root markup if needed.

Out of Scope:

- Visual redesign.
- New design system.

DoR:

- `T13` completed.

DoD:

- React/admin smoke proves:
  - app root renders;
  - plugin-owned notices remain visible;
  - unrelated notices are hidden only on the plugin page;
  - full background is applied;
  - no duplicate root IDs exist.

AC:

- Given a plugin-owned migration or error notice is rendered, then it is visible on the settings page.
- Given a core update notice is present, then it does not overlap the plugin UI on the settings page.
- Given another wp-admin page loads, then VK CSS is not enqueued and does not affect it.

Dependencies:

- `T13`.

Notes/Risks:

- The current CSS is only enqueued on the plugin page, but selector scoping still matters for smoke assertions and future reuse.

## T16. Migrate React Build To WordPress Scripts

Status: waiting_dependency

Goal: align the admin asset build with the hardened Telegram release path and reduce CRA-specific release fragility.

Scope:

- Replace `react-scripts` with `@wordpress/scripts`.
- Add `plugin-dir/react/webpack.config.js`.
- Add `plugin-dir/react/jest-unit.config.js`.
- Update `plugin-dir/react/src/index.js` to use WordPress element render if desired.
- Emit stable `static/js/main.js`, `static/css/main.css`, `static/js/main.asset.php`, and `settings-content.html`.
- Remove CRA-only `config-overrides.js` and `scripts/postbuild-stable.js` once replacement build is green.
- Update `Settings::admin_enqueue_scripts()` to read `main.asset.php` dependencies and version.

Out of Scope:

- Component behavior changes.
- Browser E2E.

DoR:

- `T12` and `T13` completed or their tests are known to pass under the old runner before migration.

DoD:

- `cd plugin-dir/react && npm ci` succeeds.
- `cd plugin-dir/react && CI=true npm test -- --watchAll=false --runInBand` passes.
- `cd plugin-dir/react && npm run build` emits required assets.
- `scripts/build-release-zip.sh` validates assets from the new build.

AC:

- Given the production build is generated, then `react/build/settings-content.html` exists.
- Given the production build is generated, then `main.asset.php` exists and contains dependencies/version data.
- Given `Settings::admin_enqueue_scripts()` runs, then it enqueues script dependencies from `main.asset.php` plus `wp-i18n`.

Dependencies:

- `T12`.
- `T13`.

Notes/Risks:

- This can be moved earlier if CRA blocks React tests. If moved earlier, re-run all React tests after `T12` and `T13`.

## T17. Add Release Workflow, Audits, Support Matrix, And Plugin Check Gate

Status: waiting_dependency

Goal: make PR and release verification fail closed on source, dependency, artifact, lifecycle, REST, and browser evidence.

Scope:

- Add `.github/workflows/build-zip.yml` adapted from Telegram.
- Install Composer and React dependencies in CI.
- Run PHP lint.
- Run `composer test`.
- Run React tests.
- Add `scripts/run-release-audits.sh`.
- Add `tests/stability/e5-plugin-check-gate.sh`.
- Add `tests/stability/e5-plugin-check-parser-test.sh`.
- Add `tests/stability/e5-plugin-check-results.jq`.
- Add `tests/stability/e5-release-zip-hygiene-negative.sh`.
- Add `tests/stability/fixtures/e5-release-zip-forbidden-entries.txt`.
- Add `tests/stability/e5-support-matrix.sh`.
- Run lifecycle smoke on push and support matrix on release.
- Upload audit, lifecycle, support, REST, browser, and fake VK evidence artifacts when those gates exist.

Out of Scope:

- WordPress.org production promotion.
- Fake VK E2E implementation.

DoR:

- `T1` completed.
- `T2` completed.
- `T3` completed.
- `T4` completed.
- `T5` completed.
- `T16` completed.

DoD:

- Push CI runs source checks, PHP tests, React tests, release audits, build/validate ZIP, Plugin Check, and current lifecycle smoke.
- Pull request CI also runs REST/admin and browser gates once `T18/T19` exist.
- Release CI runs support matrix and uploads evidence.

AC:

- Given Plugin Check exits with blocking errors, then CI fails.
- Given a release ZIP is not reproducible, then CI fails.
- Given a blocking dependency audit fails, then CI fails.
- Given support matrix evidence is missing on a release run, then CI fails.

Dependencies:

- `T1`.
- `T2`.
- `T3`.
- `T4`.
- `T5`.
- `T16`.

Notes/Risks:

- Keep `release.yml` until `build-zip.yml` is proven. Remove or disable the old workflow in the same PR that introduces the replacement to avoid double publication.

## T18. Add Real WordPress REST/Admin And Browser Lifecycle Smoke

Status: waiting_dependency

Goal: verify admin behavior against a real isolated WordPress install before the fake VK delivery E2E is added.

Scope:

- Add `tests/stability/e4-rest-ui-smoke.sh`.
- Add `tests/stability/wp-e4-rest-ui-smoke.php`.
- Add `tests/stability/e5-browser-smoke.sh`.
- Add `tests/stability/wp-e5-browser-fixture.php`.
- Add `tests/e2e/playwright.config.js`.
- Add `tests/e2e/e5-browser-smoke.spec.js`.
- Seed more than ten bots, chats, channels, and forms.
- Verify REST routes, POST mutation routes, collection pagination, admin render, asset loading, full background, notice policy, and mutation observation.

Out of Scope:

- Public CF7 form submission to VK.
- Fake VK delivery.
- Live VK.

DoR:

- `T12` completed.
- `T13` completed.
- `T14` completed.
- `T15` completed.
- `T17` completed enough to build candidate ZIP locally.

DoD:

- `tests/stability/e4-rest-ui-smoke.sh` passes locally.
- `tests/stability/e5-browser-smoke.sh --skip-browser-install` passes locally when Chromium is already installed.
- Evidence JSON includes all required check IDs and fails when a required check does not run.

AC:

- Given 12 seeded forms exist, then REST/admin evidence proves the app can see forms beyond the first page.
- Given admin UI performs a destructive mutation, then REST state confirms the mutation, not only DOM state.
- Given an unrelated WordPress notice exists, then it does not hide or overlap the app; plugin-owned notices remain visible.
- Given page or console errors occur, then browser summary fails.

Dependencies:

- `T12`.
- `T13`.
- `T14`.
- `T15`.
- `T17`.

Notes/Risks:

- Use isolated Docker workdirs and random web ports. Do not reuse the development WordPress containers or volumes.

## T19. Add Fake VK Form Delivery And Admin Setup E2E

Status: waiting_dependency

Goal: prove a real public CF7 form submission produces expected VK delivery attempts through deterministic fake VK transport, and prove the admin setup graph behind it.

Scope:

- Add `tests/stability/e6-form-delivery-smoke.sh`.
- Add `tests/stability/wp-e6-form-delivery-fixture.php`.
- Add `tests/e2e/e6-form-delivery.spec.js`.
- Add `tests/e2e/e6-playwright.config.js` if the delivery run needs distinct reporting.
- Install candidate ZIP in isolated WordPress with Contact Form 7.
- Add mu-plugin fake VK transport using `pre_http_request`.
- Intercept:
  - VK API methods used by ping, community lookup, users lookup, conversation lookup, and `messages.send`;
  - VK Bots Long Poll server requests used by `checkLongPoll`.
- Record sanitized method, params, token bucket, Long Poll key bucket, peer bucket, response summary, and call ordering.
- Add control endpoints for reset, scripted updates, scripted failures, and evidence readback.
- Block live VK egress in Docker networking.
- Cover:
  - public CF7 submit happy path;
  - two expected recipients;
  - no unexpected recipient;
  - partial `messages.send` failure plus later recipient success;
  - admin bot create/remove;
  - admin credential validation;
  - channel create/remove;
  - CF7 form visibility/selection;
  - fake auth-command Long Poll chat discovery;
  - relation assignment;
  - deletion safety.

Out of Scope:

- Live VK API calls.
- Human VK confirmation.
- Attachments/media delivery.
- VK webhook/callback API support.
- Release tagging or deployment.

DoR:

- `T7` completed.
- `T8` completed.
- `T9` completed.
- `T10` completed.
- `T11` completed.
- `T18` completed.

DoD:

- Local E6 smoke passes and writes `summary.json`, `browser-result.json`, Playwright report JSON, logs, screenshots/traces on failure, and fake VK evidence.
- Evidence contains no raw access tokens, Long Poll keys, peer IDs, emails, phone numbers, private chat labels, or raw CF7 submitted private values except approved marker fields.
- The smoke fails closed if fake transport is missing or if live VK egress is attempted.

AC:

- Given a real CF7 form is connected to a channel, when the public page form is submitted, then Contact Form 7 reports success.
- Given two active VK chats are connected, then fake VK records two `messages.send` attempts.
- Given one recipient fails, then CF7 still succeeds and the later recipient is attempted.
- Given a unique submit marker is posted, then every expected delivery attempt includes that marker.
- Given admin setup starts empty, then browser evidence proves bot, channel, form, chat, and relation setup through the real admin UI.
- Given bot/channel deletion is confirmed, then REST state changes and unrelated chats remain.

Dependencies:

- `T7`.
- `T8`.
- `T9`.
- `T10`.
- `T11`.
- `T18`.

Notes/Risks:

- VK Long Poll has a different response shape from Telegram `getUpdates`; fixture design must model `failed`, `ts`, and `updates` explicitly.
- Use hashed/bucketed peer IDs in evidence so tests can compare recipients without leaking raw identifiers.

## T20. Add Manual WordPress.org Promotion Gate

Status: waiting_dependency

Goal: separate verified release artifact creation from production WordPress.org promotion.

Scope:

- Add `.github/workflows/promote-wordpress-org.yml` adapted from Telegram.
- Add `scripts/verify-promotion-evidence.sh`.
- Add `scripts/deploy-wordpress-svn.sh`.
- Add `scripts/svn-status.py`.
- Require manual inputs:
  - final release tag;
  - successful canary run ID;
  - candidate SHA-256;
  - rollback version;
  - rollback SHA-256;
  - rollback DB snapshot SHA-256 when support matrix creates one;
  - explicit confirmation string.
- Verify canary, final release, exact ZIP hash, rollback baseline, support/browser/fake VK evidence, and environment approval before SVN deployment.
- Update stable mirror only after WordPress.org deployment succeeds.

Out of Scope:

- Creating the GitHub release itself.
- Live VK testing.

DoR:

- `T17` completed.
- `T18` completed.
- `T19` completed.
- Owner approves WordPress.org production promotion policy.

DoD:

- Promotion workflow fails closed if any required input or evidence is missing or mismatched.
- Deployment script is race-free and idempotent.
- Promotion artifact bundle is uploaded before deployment.

AC:

- Given canary run SHA differs from the final release tag SHA, then promotion fails.
- Given candidate ZIP SHA differs from the approved SHA, then promotion fails.
- Given WordPress.org current version changes while approval is pending, then deployment fails.
- Given SVN status has unexpected changes after deploy staging, then deployment fails before commit.

Dependencies:

- `T17`.
- `T18`.
- `T19`.

Notes/Risks:

- This task is security and release sensitive. It should be reviewed separately from feature hardening.

## T21. Defer Test Suite Taxonomy Refactor

Status: deferred

Goal: preserve delivery evidence traceability during Stability and postpone broad test path churn.

Scope:

- Keep chronological `e1`, `e2`, `e4`, `e5`, `e6` naming during the hardening release.
- Record a future taxonomy candidate after rollout feedback.

Out of Scope:

- Moving current test files by suite type during this hardening.
- Renaming CI artifact names while Stability evidence is still active.

DoR:

- Stability hardening completed and released.
- Rollout feedback window completed without higher-priority regressions.

DoD:

- A future owner-approved taxonomy plan exists before any file moves.

AC:

- Given the Stability release is still active, then test path churn is avoided unless required for a blocker.
- Given a future taxonomy refactor starts, then old-to-new mapping preserves release evidence traceability.

Dependencies:

- `T1` through `T20` completed or explicitly descoped by the owner.

Notes/Risks:

- This mirrors Telegram E7 and should remain deferred unless test organization becomes a real blocker.

## QA1. Independent QA For Test And Release Foundation

Status: waiting_dependency

Goal: independently verify `T1`, `T2`, and `T3` against their acceptance criteria.

Scope:

- Inspect changed test runner, build scripts, validation rules, source manifest, and lifecycle harness.
- Run PHP tests, release ZIP build/validation, reproducibility check, and at least `fresh` lifecycle smoke.
- Verify no unrelated files, secrets, dumps, logs, or generated artifacts are committed.

Out of Scope:

- Implementing fixes unless QA owner is explicitly asked to patch small findings.

DoR:

- `T1`, `T2`, and `T3` are in review.

DoD:

- QA verdict is `pass` or `fail` with evidence.
- Any fail creates follow-up tasks before dependent implementation proceeds.

AC:

- Given QA re-runs the documented commands, then results match the task claims.
- Given a required check is skipped, then QA fails the foundation.

Dependencies:

- `T1`.
- `T2`.
- `T3`.

Notes/Risks:

- Use an independent worker/agent where tooling is available.

## QA2. Independent QA For Lifecycle And Migration Integrity

Status: waiting_dependency

Goal: verify `T4`, `T5`, and `T6` before closing the lifecycle/migration epic.

Scope:

- Review maintenance lifecycle, repair plan, log cleanup, migration state machine, admin retry, and characterization evidence.
- Re-run PHP tests and selected lifecycle/migration matrix cases.

Out of Scope:

- Transport or UI behavior unless it affects lifecycle.

DoR:

- `T4`, `T5`, and `T6` are in review.

DoD:

- QA verdict is `pass` or follow-up tasks are created.

AC:

- Given uninstall is run in a real isolated WordPress install, then plugin-owned data is removed and unrelated data remains.
- Given a migration step fails, then false success is not recorded.

Dependencies:

- `T4`.
- `T5`.
- `T6`.

Notes/Risks:

- Pay special attention to destructive cleanup scope.

## QA3. Independent QA For VK Gateway And Delivery

Status: waiting_dependency

Goal: verify `T7`, `T8`, `T9`, `T10`, and `T11` before fake E2E depends on them.

Scope:

- Review gateway contract, redaction, credential transaction, Long Poll cursor policy, and per-recipient delivery results.
- Run PHP tests and inspect fake gateway evidence.

Out of Scope:

- Browser E2E.
- Live VK.

DoR:

- `T7` through `T11` are in review.

DoD:

- QA verdict is `pass` or follow-up tasks are created.

AC:

- Given transport errors include URLs or encoded secrets, then logs/evidence redact them.
- Given credential validation fails, then old credentials and relations remain unchanged.
- Given one recipient fails, then later recipients are attempted.

Dependencies:

- `T7`.
- `T8`.
- `T9`.
- `T10`.
- `T11`.

Notes/Risks:

- Do not accept live VK calls as QA evidence for automated gates.

## QA4. Independent QA For REST And Admin UI

Status: waiting_dependency

Goal: verify `T12`, `T13`, `T14`, `T15`, and `T16`.

Scope:

- Review React API client, resource states, mutation sequencing, selector additions, CSS/notice policy, and WordPress scripts migration.
- Run React tests, production build, REST smoke, and browser lifecycle smoke.

Out of Scope:

- Fake VK public submit delivery.

DoR:

- `T12` through `T16` are in review.

DoD:

- QA verdict is `pass` or follow-up tasks are created.

AC:

- Given more than ten forms exist, then admin setup can see them.
- Given non-pretty REST URLs are used, then delete mutations still work.
- Given one resource fails, then loaded resources remain usable.

Dependencies:

- `T12`.
- `T13`.
- `T14`.
- `T15`.
- `T16`.

Notes/Risks:

- Check for brittle selectors and unexpected console errors.

## QA5. Independent QA For Release And Promotion Gates

Status: waiting_dependency

Goal: verify `T17` and `T20` before release/promotion is trusted.

Scope:

- Review GitHub workflows, audits, Plugin Check, support matrix, promotion evidence, SVN deployment, and rollback verification.
- Run local script tests where possible and inspect CI dry-run evidence.

Out of Scope:

- Actually publishing to WordPress.org.

DoR:

- `T17` and `T20` are in review.
- Required CI artifacts are available or local substitutes are documented.

DoD:

- QA verdict is `pass`, or owner accepts explicitly documented residual release risk.

AC:

- Given evidence is missing, then gates fail closed.
- Given rollback baseline mismatches WordPress.org state, then promotion fails.

Dependencies:

- `T17`.
- `T20`.

Notes/Risks:

- Promotion is a human decision gate even after QA passes.

## QA6. Independent QA For Fake VK E2E

Status: waiting_dependency

Goal: verify `T18` and `T19` against the public-submit and admin-setup acceptance criteria.

Scope:

- Review fake VK mu-plugin, egress guard, public CF7 submit spec, admin setup spec, evidence redaction, and CI artifact wiring.
- Re-run the E6 smoke locally or inspect CI artifacts from the same commit.

Out of Scope:

- Live VK confirmation.
- Attachments/media.

DoR:

- `T18` and `T19` are in review.
- Candidate ZIP exists.

DoD:

- QA verdict is `pass` or follow-up tasks are created.

AC:

- Given fake transport is disabled, then the smoke fails closed.
- Given public form submit succeeds, then expected fake VK `messages.send` attempts are present.
- Given one recipient fails, then later recipient continuity is proven.
- Given evidence is uploaded, then raw tokens, Long Poll keys, peer IDs, emails, phones, and private labels are absent.

Dependencies:

- `T18`.
- `T19`.

Notes/Risks:

- QA must verify behavior against acceptance criteria, not against implementation claims.
