# Testing Plan

## Status

Consolidated implementation plan for automated tests in `cf7-vk`.

This document merges the strongest parts of the earlier drafts and reflects the agreed scope as of April 3, 2026.

## Scope And Working Assumptions

- the first implementation stage is backend-first
- backend coverage is integration-first because the plugin is tightly coupled to WordPress, Contact Form 7, `wpConnections`, and `wpPostAble`
- browser coverage uses Playwright, but only as a small high-value smoke/E2E layer
- external VK calls are always mocked in automated tests
- the current frontend stack stays on Jest + React Testing Library
- WordPress integration tests must use real `Contact Form 7`, `wpConnections`, and `wpPostAble`
- small safe refactors for testability are allowed
- local and CI execution must use the same Docker-first interface
- the test environment must stay isolated from the current development stack and its persistent volumes

## Current Repository Baseline

- frontend test baseline already exists through `react-scripts test`, `setupTests.js`, and a few Jest tests
- backend PHPUnit infrastructure does not exist yet
- the repository already has a development Docker stack, but tests need a separate isolated stack rather than an extension of the existing dev environment

## Compatibility Baseline

The initial compatibility target should be:

- PHP: `8.0`, `8.1`, `8.2`, `8.3`, `8.4`
- WordPress: `6.8`, `6.9`

Notes:

- as of April 3, 2026, WordPress `6.9` is the current major line and `6.8` is the previous one
- WordPress `7.0` should not be included until it is actually released
- full browser coverage should not run on the full matrix in the first iteration

## Goals

- add reliable automated coverage for backend and frontend behavior
- separate fast feedback from expensive environment-dependent checks
- run the whole test stack inside Docker locally first
- reuse the same commands in GitHub Actions later without redesigning the workflow
- focus first on the most business-critical paths instead of broad but shallow coverage

## Non-Goals For The First Iteration

- full UI regression coverage of every admin interaction
- real VK API integration tests
- a database engine matrix
- visual regression tooling
- support for PHP `7.4` and older runtimes

## Rollout Principles

### Docker-First

The first deliverable is an isolated test stack in Docker. All suites should run through that stack locally, and CI should later call the same commands.

### Backend-First

The most important early value is on the server side because the plugin behavior depends heavily on WordPress hooks, REST wiring, settings storage, and the Contact Form 7 delivery flow.

### Integration-First For Backend

Backend unit tests are still useful, but most initial backend confidence will come from integration tests running against a real WordPress environment with real dependent plugins activated.

### Narrow Browser Coverage

Playwright should validate only critical admin scenarios in the real WordPress UI. It is a confidence layer, not the main source of coverage.

## Test Pyramid

### 1. Frontend Unit And Component Tests

Tooling:

- `react-scripts test`
- Jest
- React Testing Library

Scope:

- utility functions in `plugin-dir/react/src/utils`
- component behavior with mocked API calls
- loading, optimistic updates, error handling, and critical state transitions

Primary targets:

- `plugin-dir/react/src/utils/api.js`
- `plugin-dir/react/src/App.js`
- `plugin-dir/react/src/components/Settings.js`
- critical branches in `plugin-dir/react/src/components/Bot.js`
- critical branches in `plugin-dir/react/src/components/Channel.js`

Why this layer exists:

- fastest feedback
- no WordPress bootstrap cost
- good fit for UI branching that is awkward to validate through the browser only

### 2. PHP Unit Tests

Tooling:

- PHPUnit
- a WordPress-independent bootstrap where possible

Scope:

- pure functions
- value normalization
- formatting and parsing logic
- logic extracted from WordPress-bound services as needed

Primary targets:

- `iTRON\cf7Vk\Util`
- `iTRON\cf7Vk\Chat::detectTypeByPeerId()`
- `iTRON\cf7Vk\Bot::isMaskedSecretValue()`
- additional helpers extracted during safe refactors

Why this layer exists:

- fast backend feedback without full WordPress bootstrap
- safer refactoring of business rules
- a place to move logic that should not remain buried inside WordPress-heavy classes

### 3. WordPress Integration Tests

Tooling:

- PHPUnit
- real WordPress test bootstrap
- real plugin activation
- real `Contact Form 7`, `wpConnections`, and `wpPostAble`

Scope:

- plugin bootstrap and hooks
- CPT, settings, REST fields, and route registration
- relation wiring between bot, chat, channel, and form
- `Settings` screen wiring where it affects backend behavior
- bot ping, fetch updates, and activate chat flows
- `CF7` submit flow and message preparation
- behavior that depends on `wpdb`, REST, CPT registration, or WordPress hooks

Primary targets:

- `plugin-dir/cf7-vk.php`
- `plugin-dir/lib/Client.php`
- `plugin-dir/lib/Controllers/RestApi.php`
- `plugin-dir/lib/Controllers/CF7.php`
- `plugin-dir/lib/Settings.php`
- `plugin-dir/lib/Channel.php`

Why this layer exists:

- much of the plugin is tightly coupled to WordPress internals
- replacing that with mocks would be higher effort and lower confidence than running a real WordPress test environment
- this layer provides the highest value earliest in this codebase

### 4. Browser Smoke And E2E Tests

Tooling:

- Playwright
- real WordPress admin
- real plugin UI
- mocked outbound VK traffic

Scope:

- login to `wp-admin`
- open the plugin admin screen
- verify critical CRUD and linking scenarios
- exercise REST and nonce flow through the real browser

Initial critical scenarios:

1. admin can open the CF7 VK screen and initial data loads successfully
2. admin can create a bot, edit key fields, and remove it
3. admin can create a channel and link a bot to it
4. admin can link a Contact Form 7 form to a channel
5. admin can trigger bot ping or manual update fetch against mocked VK responses and see the expected state change

Why this layer exists:

- the frontend lives inside WordPress admin and depends on real WordPress browser behavior
- Playwright is well suited to validate localized data, nonce handling, admin routing, and end-to-end UI wiring
- keeping the scenario count small limits maintenance cost

## Docker-First Test Environment

### Requirements

- test execution must not reuse the current dev containers or dev database volume
- tests must be runnable locally with one command per suite
- CI must be able to call the same commands later
- the repository should be mounted into containers instead of copied into ad hoc images for each run

### Proposed Files

- `docker-compose.test.yml`
- `install/testing/`
- `install/testing/bin/`
- `install/testing/wp/`
- `install/testing/playwright/`

Suggested contents:

- a dedicated Compose file for test services
- shell entrypoints for provisioning WordPress and running suites
- seed scripts for WordPress content and plugin fixtures
- Playwright config and helpers

### Proposed Services

- `db`
- `php`
- `nginx`
- `node`
- `playwright`

Guidelines:

- use a dedicated test database container and disposable volumes
- parameterize `PHP_VERSION` and `WP_VERSION`
- keep the browser runner isolated from the app container
- keep the service topology close enough to future CI usage that local and CI commands remain identical

### Provisioning Flow

Each test environment should be able to:

1. start containers
2. install root Composer dependencies if needed
3. install plugin Composer dependencies if needed
4. install frontend dependencies if needed
5. install the target WordPress version
6. create a clean test database
7. activate required plugins
8. seed admin credentials, a sample CF7 form, and minimal plugin fixtures
9. run the requested suite
10. tear down cleanly

## VK Mocking Strategy

No automated test should call the real VK API.

Recommended first implementation:

- intercept outbound WordPress HTTP requests through `pre_http_request`
- return deterministic fixture responses for VK endpoints
- allow scenario-specific fixtures through environment variables or seed flags

Why this should be server-side:

- the same mocking mechanism can be reused by WordPress integration tests and Playwright
- the browser layer does not need to know VK protocol details
- tests remain deterministic even when the UI triggers server-side actions

## Implementation Roadmap

### Phase 1. Docker Test Stack

Deliverables:

- isolated `docker-compose.test.yml`
- test-specific bootstrap and runner scripts
- clean WordPress provisioning flow that does not touch the development stack

### Phase 2. Backend Test Harness

Deliverables:

- `require-dev` dependencies for PHPUnit and related tooling
- `phpunit.xml`
- bootstrap files
- `tests/unit`
- `tests/integration`
- WordPress test environment wiring

### Phase 3. VK Mock Layer And Fixtures

Deliverables:

- reusable server-side VK mock layer
- stable fixture format for success and failure responses
- hooks or flags to switch scenarios per test suite

### Phase 4. Backend Integration Tests

Deliverables:

- tests for plugin bootstrap and hooks
- tests for CPT, settings, REST fields, and routes
- tests for bot, chat, channel, and form relationships
- tests for CF7 delivery behavior
- tests for bot ping, fetch updates, and related flows with VK mocked

This is the first major confidence milestone and should come before broad frontend or Playwright expansion.

### Phase 5. Backend Unit Tests

Deliverables:

- unit coverage for pure helpers and isolated domain logic
- small refactors that extract testable logic from WordPress-heavy classes where this materially improves clarity and safety

### Phase 6. Frontend Test Harness And Coverage

Deliverables:

- shared mocks for WordPress globals and REST behavior
- reusable frontend test utilities
- stronger tests for `utils/api`, `App`, `Settings`, `Bot`, and `Channel`
- coverage for loading, success, and failure branches in critical CRUD flows

### Phase 7. Playwright Smoke Coverage

Deliverables:

- admin login helper
- basic app-load smoke coverage
- a very small set of critical management flows in real WordPress admin

This phase should stay intentionally narrow. Playwright should cover the most valuable end-to-end paths, not every interface branch.

### Phase 8. Documentation And CI-Ready Commands

Deliverables:

- documented local commands for each suite
- a stable command interface that GitHub Actions can adopt later without redesign
- notes on supported matrix lanes and expected run cost

## Compatibility Matrix

### Backend Matrix

Run:

- PHP unit tests
- WordPress integration tests

Against:

| PHP | WP 6.8 | WP 6.9 |
| --- | --- | --- |
| 8.0 | yes | yes |
| 8.1 | yes | yes |
| 8.2 | yes | yes |
| 8.3 | yes | yes |
| 8.4 | yes | yes |

### Browser Matrix

Run initially only on:

- PHP `8.3`
- WordPress `6.9`

Optional later extension:

- add one smoke lane on PHP `8.0` + WordPress `6.8`

## Exit Criteria For The First Iteration

- isolated Docker-based test stack exists and does not depend on the current dev environment
- backend unit and integration suites can run locally through the Docker test interface
- frontend Jest suite runs consistently both locally and in containers
- Playwright smoke tests pass on the primary browser lane
- no automated test makes real VK network calls
- commands are documented in a form that can be reused in GitHub Actions later
