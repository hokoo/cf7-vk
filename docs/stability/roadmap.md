# CF7 VK Stability Hardening Roadmap

Date: 2026-08-31

## Source Baseline

Reference repository:

- Path: `../cf7-telegram`
- Reference commit: `02e3552aaa326db404f0551f45663c43edf99965`
- Reference branch at inspection time: `bridge-ui-terminology`
- Reference worktree note: that branch has uncommitted UI terminology changes. This roadmap treats the committed Stability work through `02e3552` as the source of truth and excludes the uncommitted terminology work.

Current repository:

- Path: `../cf7-vk`
- Current branch: `master`
- Current commit: `ad95e2a`
- Current published/stable tag: `v-0.1.4`
- Current plugin version: `0.1.4`
- Current worktree note: `.codex` is untracked and is not part of this analysis.

## Objective

Bring `cf7-vk` to the same stability standard recently delivered in `cf7-telegram`:

- identify stability bugs that are likely still present in the VK plugin;
- port the hardening patterns that are transport-agnostic;
- adapt Telegram-specific transport work to VK Long Poll and VK API semantics;
- add the missing PHP, WordPress, React, release, browser, and fake-transport test wrapper;
- make future release decisions depend on executable evidence rather than manual confidence.

This document started as the delivery plan. Code work began after owner approval on 2026-08-31; current execution status and evidence live in `docs/stability/execution-backlog.md`.

Implementation progress as of 2026-09-01:

- `T1. Add PHP Test Runner And Baseline Unit Harness`: completed.
- `T2. Add Deterministic Release ZIP Builder And Validator`: completed.
- `T3. Add Stability Source Manifest And Lifecycle Smoke Harness`: completed for the current lifecycle smoke contract.
- `T4. Implement Maintenance Lifecycle, Repair, And Log Retention`: completed.
- `T5. Harden Migration Runner State, Locks, And Recovery`: completed.
- `T6. Add Migration And Lifecycle Characterization Evidence`: completed.
- `T7. Introduce VK Gateway Contract And Recording Fake`: completed.
- `T8. Add Central Redaction For Logs, Transport Errors, And Evidence`: completed.
- `T9. Add Transactional VK Credential Update Contract`: completed.
- `T10. Harden VK Long Poll Cursor, Locks, And Update Processing`: completed.
- `T11. Normalize CF7 Delivery Results And Per-Recipient Failure Handling`: completed.
- `T12. Harden REST API And React API Client`: completed.
- `T13. Add React Resource State, Error Boundary, And Retry-Failed UI`: completed.
- `T14. Harden Admin Mutation Sequencing, State Safety, And Selectors`: completed.
- `T15. Scope Admin Styles And Notice Policy`: completed.
- `T16. Migrate React Build To WordPress Scripts`: completed.
- `T17. Add Release Workflow, Audits, Support Matrix, And Plugin Check Gate`: completed.
- `T18. Add Real WordPress REST/Admin And Browser Lifecycle Smoke`: completed.
- `T19. Add Fake VK Form Delivery And Admin Setup E2E`: completed.
- `QA1`, `QA2`, `QA3`, `QA4`, and `QA6`: completed.
- Remaining decision-gated task: `T20. Add Manual WordPress.org Promotion Gate`.

## Reference Stability Scope

The Telegram Stability release closed six epics and deferred a seventh:

| Reference Epic | Status | Transferability To VK |
| --- | --- | --- |
| E1 Stability Gate | completed | Highly portable. VK needs lifecycle, upgrade, rollback, and artifact evidence. |
| E2 Migrations And Data Integrity | completed | Highly portable as runner hardening. VK has no comparable legacy importer today, but the migration runner still needs state, locks, retries, and false-success prevention. |
| E3 Telegram Gateway And Reliable Delivery | completed | Partly portable. VK does not use the removed Telegram SDK, but it still needs a narrow gateway, normalized delivery results, fake transport, redaction, transactional credential updates, and delivery continuity. |
| E4 REST API, Admin UI And Diagnostics | completed | Highly portable. VK already has some POST and pagination improvements, but still lacks CF7 form pagination, partial failure UI, safe delete URL construction, typed API errors, and broad React tests. |
| E5 Release Delivery And Maintainability | completed | Highly portable. VK still uses the older `release.yml` path and lacks the fail-closed build, audit, Plugin Check, support matrix, browser canary, and promotion evidence gates. |
| E6 Fake Telegram Form Delivery And Admin Setup E2E | completed | Highly portable with fake VK. VK needs a deterministic fake VK API/Long Poll harness that proves a real public CF7 submit produces expected VK send attempts. |
| E7 Test Suite Taxonomy Refactor | deferred | Should also be deferred for VK until the Stability hardening is complete and has rollout feedback. |

## Current VK Baseline

Already present:

- standalone plugin entrypoint in `plugin-dir/cf7-vk.php`;
- VK entities for bots, chats, channels, and CF7 forms;
- REST CPT controllers for bots, chats, and channels;
- React admin app under `plugin-dir/react`;
- a small Jest baseline: `App.test.js` and `utils/botTitle.test.js`;
- direct VK API wrapper in `plugin-dir/lib/VkApi.php`;
- manual Long Poll sync and auth-command chat linking;
- CF7 delivery formatting and delivery hooks;
- old high-level test plan in `docs/TESTING-PLAN.md`;
- old GitHub release workflow in `.github/workflows/release.yml`.

Initially missing or incomplete relative to the Telegram Stability standard:

- no `plugin-dir/tests` PHP test suite;
- no `plugin-dir/phpunit.xml.dist`;
- no `tests/stability` shell/WP-CLI harness;
- no `tests/e2e` Playwright harness;
- no `scripts/build-release-zip.sh`;
- no `scripts/validate-release-zip.sh`;
- no release audit script;
- no Plugin Check gate;
- no support matrix evidence;
- no fake VK transport evidence;
- no `Maintenance` lifecycle class;
- no activation, deactivation, or uninstall hooks;
- no central log redactor;
- no log retention or cap;
- no migration state, lock, retry, or admin recovery model;
- no narrow VK gateway interface or normalized result object;
- no transactional credential validation endpoint;
- no CI gate equivalent to Telegram `build-zip.yml`;
- no WordPress.org promotion workflow equivalent to Telegram `promote-wordpress-org.yml`.

## High-Confidence Findings

### F1. Lifecycle and uninstall are not hardened

Evidence:

- `plugin-dir/cf7-vk.php` initializes `Client`, `CPT`, `Settings`, and `Migration`, but does not call `register_activation_hook`, `register_deactivation_hook`, or `register_uninstall_hook`.
- There is no `plugin-dir/lib/Maintenance.php`.

Risk:

- plugin-owned custom posts, relations, logs, options, cron events, and locks can survive uninstall or drift after deactivation;
- future cleanup work has no centralized owner;
- release validation cannot prove install, deactivate, reactivate, uninstall, and rollback lifecycle behavior.

Recommended action:

- add a VK `Maintenance` class adapted from Telegram E5;
- register activation, deactivation, uninstall, cleanup schedule, relation cascade, repair dry-run/apply, fetch-lock cleanup, log retention, and option cleanup;
- prove behavior through PHP tests and isolated WordPress lifecycle smoke.

Status as of 2026-08-31:

- mitigated by T4;
- full lifecycle smoke passed after implementation: `/tmp/cf7vk-e1-20260831T144624Z-97750.f4c6I4/results/summary.json`.

### F2. Migration runner can report success before migration callbacks are proven

Evidence:

- `plugin-dir/lib/Controllers/Migration.php` calls `update_option( self::VERSION_OPTION, CF7VK_VERSION )` before triggering migration callbacks.
- The same hook name, `cf7vk_migrations`, is used both as the scheduled event and as the migration callback action.
- There is no migration state option, lock, retry path, self-healing path, or admin recovery action.
- `plugin-dir/inc/migrations/index.php` is only a placeholder.

Risk:

- a future migration can fail after the version is already written as successful;
- cron scheduling failures are not visible;
- duplicate or concurrent runs are not coordinated;
- corrupted partial states cannot be detected and retried deterministically.

Recommended action:

- port the Telegram migration runner state machine with VK prefixes;
- mark scheduled/running/failed/completed states explicitly;
- write version only after every required migration step succeeds;
- add self-healing and admin retry only when state proves it is needed;
- add characterization tests now, even before VK has a complex legacy importer.

Status as of 2026-08-31:

- mitigated by T5;
- PHP migration tests passed after implementation: 58 tests, 0 failures, 1 PHP 8.1 dependency-heavy skip on local PHP 8.0.30;
- full lifecycle smoke passed after implementation: `/tmp/cf7vk-e1-20260831T152054Z-8070.KTunYL/results/summary.json`, 129 steps, 128 passed, 1 skipped for missing public `v-0.1.2`, 0 failures;
- full lifecycle and migration characterization smoke passed after T6 completion: `/tmp/cf7vk-e1-20260831T160122Z-37689.bEHEDH/results/summary.json`, 165 steps, 165 passed, 0 skipped, 0 failures;
- current candidate ZIP SHA-256: `5dd83b81f51a5f3216176fbd58ca00d8c20d69485d7bd4da97873f00838232bf`.

### F3. PHP test wrapper is absent

Evidence:

- `plugin-dir/composer.json` has no `require-dev` and no `scripts.test`.
- `plugin-dir/phpunit.xml.dist` does not exist.
- `plugin-dir/tests` does not exist.

Risk:

- backend hardening cannot be verified cheaply;
- release gates must rely on browser or manual checks;
- transport, migration, logging, and REST regressions can land without a fast signal.

Recommended action:

- add the same two-mode PHP runner used by Telegram: PHPUnit when available and a compatibility harness fallback;
- seed WordPress stubs only for unit-level tests;
- keep real WordPress behavior in `tests/stability` instead of over-mocking integration paths.

Status as of 2026-08-31:

- mitigated by T1;
- backend suite currently has 58 tests through PHPUnit-or-compat runner.

### F4. Release pipeline was not fail-closed

Initial evidence:

- VK had only `.github/workflows/release.yml`.
- Release packaging went through `install/build-plugin-package.sh`.
- There were no `scripts/build-release-zip.sh`, `scripts/validate-release-zip.sh`, or `scripts/run-release-audits.sh`.

Risk:

- release ZIP reproducibility is not proven;
- dev files, maps, test files, package manifests, logs, dumps, or hidden files can slip into an artifact;
- Plugin Check and dependency audits are not blocking release evidence;
- WordPress.org deployment is coupled to release publication rather than an evidence-backed promotion gate.

Recommended action:

- port Telegram E5 release scripts with `cf7-vk` defaults;
- replace the old release workflow with a verify/publish split;
- add a separate manual promotion workflow with canary, rollback, exact SHA, and environment approval.

Status as of 2026-08-31:

- mitigated for PR/release ZIP verification by T2 and T17;
- deterministic local ZIP builder and fail-closed ZIP validator are implemented and passing;
- `.github/workflows/build-zip.yml` replaces the old release workflow and runs source checks, PHP tests, React tests, release audits, deterministic ZIP validation, reproducibility, Plugin Check, current lifecycle smoke, and release support matrix evidence;
- Plugin Check passes with 0 errors and 5 warnings on the current candidate ZIP;
- WordPress.org production promotion remains intentionally separate and is tracked by T20.

### F5. Logs are neither redacted nor bounded

Evidence:

- `plugin-dir/lib/Logger.php` writes raw arrays after `json_encode`.
- `plugin-dir/lib/Bot.php`, `Channel.php`, and `VkApiException` can pass peer IDs, group IDs, Long Poll failures, request errors, submitted message context, and exception messages into logs.
- There is no `LogRedactor`.
- There is no retention policy for `cf7vk_log`.

Risk:

- access tokens, Long Poll keys, user identifiers, email addresses, phone values, or CF7 submitted values can persist in plugin logs;
- log rows can grow without bound;
- evidence artifacts can expose secrets unless every test independently sanitizes them.

Recommended action:

- add `LogRedactor` with VK-aware patterns and filter extension points;
- route every `Logger::write` title and payload through the redactor;
- add cleanup by age and row cap in `Maintenance`;
- add regression tests for raw, URL-encoded, repeatedly encoded, and JSON/key-value secrets.

Status as of 2026-08-31:

- partially mitigated by T4 for log retention and row cap;
- partially mitigated by T7 for VK transport result descriptions and failure payloads;
- mitigated by T8 for central `Logger::write()` payload/title redaction and stability state snapshot redaction.

### F6. VK transport is direct and exception-driven

Evidence:

- `plugin-dir/lib/VkApi.php` calls `wp_remote_post` and `wp_remote_get` directly.
- `Bot` owns persistence, transport construction, error logging, status mutation, and delivery decisions together.
- There is no gateway interface, fake gateway, normalized result object, retry metadata, or transport error taxonomy.

Risk:

- tests must intercept global WordPress HTTP instead of replacing a narrow contract;
- transport failures and malformed responses are only observable as exceptions;
- redaction must be repeated at many call sites;
- delivery evidence cannot easily distinguish transport, HTTP, VK API, malformed JSON, and validation failures.

Recommended action:

- introduce `iTRON\cf7Vk\Vk\VkGateway`, `VkDeliveryResult`, `VkRedactor`, and `WordPressVkGateway`;
- keep the production gateway on WordPress HTTP API;
- allow tests to inject a recording fake through a filter such as `cf7vk_vk_gateway`;
- normalize `api`, `long_poll`, `http`, `transport`, and `malformed_response` failures.

Status as of 2026-08-31:

- mitigated by T7;
- `VkApi` now delegates VK API and Long Poll HTTP to `WordPressVkGateway`;
- failures are normalized through `VkDeliveryResult` and mapped back to the existing public `VkApiException` behavior;
- transport tests pass through a recording fake and no longer require global HTTP interception;
- delivery continuity is additionally mitigated by T11: channel sendout returns per-recipient results, continues later active chats after one recipient fails, and emits sanitized completion summaries through `cf7vk_deliveries_completed`.

### F7. Credential changes are not transactional

Evidence:

- `RestApi::registerBotFields()` writes `accessToken`, `groupId`, and other bot meta through generic update callbacks.
- `Bot::setAccessToken()` and `Bot::setGroupId()` persist values before any successful VK validation is required.

Status as of 2026-08-31:

- mitigated by T9 for the React credential flow and new dedicated REST endpoint;
- generic field callbacks remain backward compatible, but the admin UI now sends group/token changes through transactional validation.
- `Bot.js` saves connection settings and then pings.

Risk:

- one invalid edit can replace a previously working credential;
- a group/token mismatch can leave old chats and channels attached to a now-different community;
- admins may see transient offline status but lose the known-good configuration.

Recommended action:

- add an explicit transactional credential update route;
- validate the candidate group/token/API version through the gateway before persistence;
- decide and document the relation policy when VK community identity changes. Recommended default: reset only bot-owned bot-chat and bot-channel relations when the validated community identity changes; preserve channels and forms themselves.

### F8. VK Long Poll progress semantics need characterization

Evidence:

- `Bot::fetchUpdates()` persists `longPollTs` after processing the returned update batch.
- `processLongPollUpdates()` catches per-update exceptions, logs, continues, and still allows the batch timestamp to be persisted.
- `checkLongPoll()` sends Long Poll `key` and `ts` in the request URL.

Risk:

- an update that failed processing may be lost when `ts` advances;
- Long Poll request errors can include sensitive query values;
- concurrency between cleanup and fetch locks is not coordinated because there is no maintenance cleanup lock.

Recommended action:

- define and test the VK Long Poll cursor contract;
- either fail closed without advancing `ts` on process errors or record explicit skipped-update evidence with accepted loss semantics;
- coordinate fetch locks with cleanup locks;
- redact Long Poll URLs and keys in every error path.

Status as of 2026-08-31:

- mitigated by T10;
- `fetchUpdates()` now coordinates with maintenance cleanup and stale fetch locks;
- transient VK Long Poll failures return structured retryable data for the admin UI instead of only generic thrown errors;
- `failed=1/2/3` Long Poll responses preserve VK semantics while exposing cursor state explicitly;
- non-ignorable per-update processing failures keep the previous `longPollTs` and expose `nextTs` without silently dropping the failed update;
- optional profile/conversation lookup failures are logged as safely ignorable and redacted;
- a real WordPress REST smoke gate triggers `/wp/v2/cf7vk_bot/{id}/fetch_updates` through fake VK responses and asserts cursor, chat, relation, and redaction evidence.

### F9. REST and admin bootstrap do not tolerate partial failures

Evidence:

- `plugin-dir/react/src/App.js` uses one `Promise.all` for all initial resources.
- If one request rejects, no loaded resources are applied and the UI simply exits loading.
- There is no error boundary and no retry-failed flow.
- `plugin-dir/react/src/utils/api.js` has generic `Error` objects without sanitized category, route, method, or data.

Risk:

- one failing endpoint can blank or partially break the entire settings screen;
- admins get no actionable retry;
- CI cannot assert diagnostics by category;
- console output may expose unsafe URLs or payloads.

Recommended action:

- port Telegram E4 resource state model, error boundary, `ApiError`, safe URL/data redaction, and retry-failed behavior;
- add React tests for partial success, partial failure, retry, invalid JSON, permission failures, and recoverable transport errors.

Status as of 2026-08-31:

- mitigated by T12 and T13 for the React API-client and admin bootstrap layers;
- `ApiError` now provides sanitized method, route, status, code, category, and data diagnostics;
- invalid JSON, permission failures, transport failures, sensitive URL redaction, and later-page collection failures are covered by React unit tests;
- `App` now uses independent resource state and `Promise.allSettled`, keeps loaded sections visible across unrelated failures, shows targeted resource errors, retries only failed resources, disables dependency-gated controls, and wraps the settings screen in `SettingsErrorBoundary`;
- T14 added focused component tests, mutation failure guards, stable browser selectors, channel removal relation cleanup evidence, failed-save snapshot preservation, and polling transient-error cleanup;
- T15 scoped admin menu/background/notice CSS to the plugin page, preserved `cf7vk-notice`, and removed the duplicate server-side `cf7-vk-container` root;
- build-chain migration and real browser smoke are mitigated by T16 and T18.

### F10. CF7 forms are not paginated in the admin API client

Evidence:

- `fetchBots`, `fetchChannels`, and `fetchChats` use `apiCollectionRequest`.
- `fetchForms` still uses one `apiRequest(cf7vkData.routes.forms)`.

Risk:

- admins with more than the default CF7 REST page size can fail to see forms after the first page;
- channel setup can silently omit routable forms.

Recommended action:

- implement a CF7-specific pagination helper using `per_page` and `offset`, matching Telegram E4;
- prove more-than-10 form visibility in React unit tests and real WordPress smoke.

Status as of 2026-08-31:

- mitigated at the React API-client layer by T12;
- `fetchForms()` now uses `per_page=100` plus `offset`, keeps loading while pages are full, deduplicates repeated IDs, and fails closed if a page request rejects;
- T18 real WordPress smoke proves more-than-default form visibility through REST/admin/browser evidence.

### F11. Delete URL construction is unsafe for non-pretty REST URLs

Evidence:

- `apiDeleteBot`, `apiDeleteChannel`, and `apiDeleteChat` construct URLs as `${route}${id}/?force=true`.

Risk:

- plain permalink REST routes can encode `force=true` inside the `rest_route` value instead of sending it as a query parameter;
- UI deletion can appear successful in DOM but fail in REST state.

Recommended action:

- add `appendQueryParams` and `forceDeleteUrl` helpers;
- use them for all force-delete calls;
- add unit and Playwright coverage that exercises non-pretty REST route shapes.

Status as of 2026-08-31:

- mitigated at the React API-client layer by T12;
- bot, channel, and chat deletion now append `force=true` as a separate query parameter for both pretty and non-pretty REST route shapes;
- React unit tests cover `index.php?rest_route=/wp/v2/...` delete URLs, while real REST state confirmation remains part of T18.

### F12. React build tooling was Create React App based

Initial evidence:

- `plugin-dir/react/package.json` used `react-scripts`.
- `plugin-dir/react/config-overrides.js` and `scripts/postbuild-stable.js` maintained stable filenames manually.
- `.github/workflows/release.yml` used Node 18.

Risk:

- the build path differs from the WordPress-oriented reference;
- release scripts must special-case CRA output;
- browser tooling can drift, as happened in Telegram before E5 pinned Node 20.

Recommended action:

- migrate to `@wordpress/scripts`, `webpack.config.js`, and `jest-unit.config.js`;
- use emitted `main.asset.php` for script dependencies and build versioning;
- pin CI browser jobs to Node 20 or later as in Telegram.

Status as of 2026-08-31:

- mitigated by T16 for the local React build and test path;
- `react-scripts`, `config-overrides.js`, and the CRA postbuild copier were removed;
- `wp-scripts build` now emits stable `main.js`, `main.css`, `main.asset.php`, and `settings-content.html`;
- `Settings::admin_enqueue_scripts()` reads dependencies/version from `main.asset.php` and appends `wp-i18n`;
- CI workflow hardening, release audits, Plugin Check, support matrix, and Node 20 pinning are mitigated by T17.

### F13. There is no fake VK end-to-end delivery gate

Evidence:

- no `tests/e2e`;
- no fake VK transport fixture;
- no public CF7 submit smoke;
- no admin setup smoke.

Risk:

- the most important user path, public CF7 submission to VK dialog delivery, is not release-gated;
- Long Poll chat discovery, channel/form/bot relations, and delivery continuity are not proven together;
- low current user count can hide production blockers until later.

Recommended action:

- add a deterministic fake VK harness equivalent to Telegram E6;
- intercept VK API and Long Poll requests at the WordPress transport boundary;
- block real VK egress in the Docker network;
- record sanitized method, params, identity buckets, ordering, and response summaries;
- require public CF7 submit, admin setup, multi-recipient delivery, partial failure continuity, and deletion safety evidence.

## Recommended Epic Order

1. Test Harness And Release Zip Foundation
2. Lifecycle, Maintenance, And Migration Integrity
3. VK Gateway, Credential Lifecycle, Long Poll, And Delivery
4. REST API, Admin UI, And Diagnostics
5. Release Delivery, Support Matrix, Logging, And Promotion
6. Fake VK Form Delivery And Admin Setup E2E
7. Deferred Test Suite Taxonomy Refactor

The first executable batch should be limited to the test and release foundation. It unlocks fast regression coverage and gives later hardening a stable verification interface.

## Human Decision Gate

Recommended contract:

- keep the current chronological Stability evidence style for VK now: `tests/stability/e1-*`, `e2-*`, etc.;
- use fake VK transport only for automated tests;
- do not use live VK credentials or human-in-the-loop VK confirmation in CI;
- introduce a VK gateway interface before deep transport hardening;
- make PR/release verification fail closed on PHP tests, React tests, release ZIP validation, audits, lifecycle smoke, REST/admin smoke, browser canary, and fake VK delivery smoke;
- defer test-suite taxonomy cleanup until after the VK hardening release has rollout feedback.

Alternative:

- add only the fake VK E2E harness first and leave release/migration/lifecycle hardening for later.
- Consequence: faster visible coverage, but still no reliable unit/release/lifecycle base and no protection against several Telegram Stability regressions.

Alternative:

- port Telegram code mechanically before adding tests.
- Consequence: faster initial code movement, but higher integration risk because VK semantics differ around group IDs, Long Poll cursors, and transport identity.

Owner decision needed:

- resolved on 2026-08-31 by owner direction in chat: proceed when there are no decision gates, bring work to executable state, and start mergeable epics fully or partially.
