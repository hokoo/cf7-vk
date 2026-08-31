const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const repoRoot = path.resolve(__dirname, '..', '..');
const {expect, test} = require(path.join(repoRoot, 'plugin-dir/react/node_modules/@playwright/test'));

const resultPath = process.env.CF7VK_E6_RESULT_JSON || path.join(
	process.env.CF7VK_E6_RESULTS_DIR || process.cwd(),
	'browser-result.json'
);
const expectedCandidateVersion = process.env.CF7VK_EXPECTED_CANDIDATE_VERSION || '0.1.4';
const expectedCandidateSha256 = process.env.CF7VK_CANDIDATE_SHA256 || '';
const controlAction = 'cf7vk_e6_fake_vk_control';
const publicTokenCanary = 'vk1.e6_fake_token_canary_public';
const adminTokenCanary = 'vk1.e6_fake_token_canary_admin';
const adminGroupId = '660002';
const adminUser = process.env.CF7VK_E6_ADMIN_USER || 'admin';
const adminPassword = process.env.CF7VK_E6_ADMIN_PASSWORD || 'admin-password';
const publicExpectedPeerIds = ['990001', '990002'];
const publicUnexpectedPeerId = '990099';
const adminUpdatePeerId = '990123';
const adminSafetyPeerId = '990199';

const peerBucket = (value) => crypto
	.createHash('sha256')
	.update(String(value).trim())
	.digest('hex')
	.slice(0, 16);

const requiredCheckIds = [
	'fake-transport-active',
	'public-form-renders',
	'cf7-submit-success',
	'send-message-attempts',
	'no-unexpected-recipient',
	'no-token-leakage',
	'no-page-errors',
	'no-console-errors',
	'admin-empty-state',
	'partial-failure-cf7-success',
	'partial-failure-recipient-continuity',
	'partial-failure-evidence-redacted',
	'partial-failure-no-page-errors',
	'partial-failure-no-console-errors',
	'admin-bot-created',
	'admin-bot-token-validated',
	'admin-channel-created',
	'admin-form-selectable',
	'admin-chat-discovered',
	'admin-relations-assigned',
	'admin-delivery-targets-assigned-chat',
	'admin-evidence-redacted',
	'admin-deletion-safety',
	'admin-no-page-errors',
	'admin-no-console-errors',
];

const checks = new Map();
const evidence = {
	console_errors: [],
	page_errors: [],
	request_failures: [],
	contact_form_7_responses: [],
	vk: {},
	fixture: {},
};

const sanitizeUrl = (url) => {
	const parsed = new URL(url);
	for (const key of Array.from(parsed.searchParams.keys())) {
		if (/nonce|token|secret|password|key/i.test(key)) {
			parsed.searchParams.set(key, '[redacted]');
		}
	}
	return `${parsed.origin}${parsed.pathname}${parsed.search}${parsed.hash}`;
};

const writeResult = (status = 'unknown') => {
	const directory = path.dirname(resultPath);
	fs.mkdirSync(directory, {recursive: true});

	const normalizedChecks = requiredCheckIds.map((id) => checks.get(id) || {
		id,
		status: 'fail',
		message: 'Required check did not run.',
		extra: {},
	});

	fs.writeFileSync(
		resultPath,
		JSON.stringify({
			schema: 1,
			status,
			candidate: {
				version: expectedCandidateVersion,
				sha256: expectedCandidateSha256,
			},
			output_dir: path.join(path.dirname(resultPath), 'playwright-artifacts'),
			checks: normalizedChecks,
			evidence,
		}, null, 2)
	);
};

const recordCheck = (id, passed, message, extra = {}) => {
	checks.set(id, {
		id,
		status: passed ? 'pass' : 'fail',
		message,
		extra,
	});
};

const expectCheck = async (id, message, callback) => {
	try {
		const extra = await callback();
		recordCheck(id, true, message, extra || {});
	} catch (error) {
		recordCheck(id, false, message, {
			error: error.message,
		});
		throw error;
	} finally {
		writeResult('running');
	}
};

const control = async (page, baseURL, action, fields = {}) => {
	const response = await page.request.post(`${baseURL}/wp-admin/admin-ajax.php`, {
		form: {
			action: controlAction,
			e6_action: action,
			...fields,
		},
	});

	const body = await response.json();
	expect(response.status()).toBe(200);
	expect(body.success).toBe(true);
	return body.data;
};

const vkEvidence = async (page, baseURL) => {
	const data = await control(page, baseURL, 'evidence');
	evidence.vk = data.vk || {};
	evidence.fixture = data.fixture || {};
	return data;
};

const vkCalls = (data) => data.vk?.calls || [];
const sendMessageCalls = (data) => vkCalls(data).filter((call) => call.method === 'messages.send');

const privateEvidenceCanaries = [
	'990001',
	'990002',
	'990099',
	'990123',
	'990199',
	'E6 Delivery Chat 1',
	'E6 Delivery Chat 2',
	'E6 Unrelated Chat',
	'E6 Safety Chat',
	'E6 Admin Chat',
	'e6_delivery_chat_1',
	'e6_delivery_chat_2',
	'e6_unrelated_chat',
	'e6_safety_chat',
	'e6_admin_chat',
	'E6 Browser User',
	'e6-browser@example.test',
	'E6 fake VK delivery',
];

const expectEvidenceRedacted = (data) => {
	const serialized = JSON.stringify(data);
	expect(serialized.includes('e6_fake_token_canary')).toBe(false);
	expect(serialized.includes(publicTokenCanary)).toBe(false);
	expect(serialized.includes(adminTokenCanary)).toBe(false);
	expect(privateEvidenceCanaries.some((value) => serialized.includes(value))).toBe(false);
	return serialized;
};

const isCf7FeedbackResponse = (response) => (
	response.request().method() === 'POST'
	&& /\/contact-form-7\/v1\/contact-forms\/\d+\/feedback(?:\?|$)/.test(response.url())
);

const itemTitle = (item) => {
	if (!item) {
		return '';
	}

	if (typeof item.title === 'string') {
		return item.title;
	}

	return item.title?.rendered || item.title?.raw || '';
};

const urlWithQuery = (url, params = {}) => {
	const parsed = new URL(url);
	for (const [key, value] of Object.entries(params)) {
		if (typeof value !== 'undefined' && value !== null) {
			parsed.searchParams.set(key, String(value));
		}
	}

	return parsed.toString();
};

const adminUrl = (baseURL) => `${baseURL}/wp-admin/admin.php?page=wpcf7_vk`;

const login = async (page, baseURL) => {
	await page.goto(`${baseURL}/wp-login.php`, {waitUntil: 'domcontentloaded'});
	const username = page.locator('#user_login');
	const password = page.locator('#user_pass');
	await expect(username).toBeVisible();
	await expect(password).toBeVisible();
	await username.fill('');
	await password.fill('');
	await username.fill(adminUser);
	await password.fill(adminPassword);
	await expect(username).toHaveValue(adminUser);
	await expect(password).toHaveValue(adminPassword);
	await Promise.all([
		page.waitForURL(/\/wp-admin(?:\/|$)/, {timeout: 30000, waitUntil: 'domcontentloaded'}),
		page.locator('#wp-submit').click(),
	]);
};

const openAdmin = async (page, baseURL) => {
	await page.goto(adminUrl(baseURL), {waitUntil: 'domcontentloaded'});
	await expect(page.locator('#wpadminbar')).toBeVisible();
	await expect(page.locator('#settings-content')).toBeVisible();
	await expect(page.locator('#cf7-vk-container')).toBeVisible();
	await expect(page.getByRole('heading', {name: /VK Message Bridge Settings/i})).toBeVisible();
};

const adminData = async (page) => {
	const data = await page.evaluate(() => window.cf7vkData);
	expect(data?.nonce).toBeTruthy();
	expect(data?.routes?.bots).toBeTruthy();
	expect(data?.routes?.channels).toBeTruthy();
	expect(data?.routes?.chats).toBeTruthy();
	expect(data?.routes?.relations).toBeTruthy();
	return data;
};

const restRequest = async (page, url, method = 'GET', data = null) => {
	const settings = await adminData(page);
	const response = await page.request.fetch(url, {
		method,
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': settings.nonce,
		},
		...(data ? {data} : {}),
	});
	const body = await response.json();
	expect(response.status()).toBeLessThan(400);
	return body;
};

const restCollection = async (page, collection) => {
	const settings = await adminData(page);
	const routes = {
		bots: settings.routes.bots,
		channels: settings.routes.channels,
		chats: settings.routes.chats,
		forms: settings.routes.forms,
	};
	const route = routes[collection] || settings.routes.relations?.[collection];
	expect(route, `REST route for ${collection}`).toBeTruthy();

	if (collection === 'forms') {
		return restRequest(page, urlWithQuery(route, {per_page: 100, offset: 0, order: 'asc', orderby: 'id'}));
	}

	return restRequest(page, urlWithQuery(route, {per_page: 100, page: 1, order: 'asc', orderby: 'id'}));
};

const waitForValue = async (producer, assertion, message) => {
	let value = null;
	await expect(async () => {
		value = await producer();
		await assertion(value);
	}).toPass({message, timeout: 30000, intervals: [250, 500, 1000]});

	return value;
};

const selectReactOption = async (page, picker, label) => {
	await picker.click();
	const option = page.locator('.select-picker__option', {hasText: label}).last();
	await expect(option).toBeVisible();
	await option.click();
};

const setReactInputValue = async (locator, value) => {
	await locator.evaluate((element, nextValue) => {
		const descriptor = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');
		descriptor.set.call(element, nextValue);
		element.dispatchEvent(new Event('input', {bubbles: true}));
	}, value);
};

const submitPublicForm = async (page, fixture, marker) => {
	await page.goto(fixture.page_url, {waitUntil: 'domcontentloaded'});
	await expect(page.locator('.wpcf7 form')).toBeVisible();
	await page.locator('input[name="your-name"]').fill('E6 Browser User');
	await page.locator('input[name="your-email"]').fill('e6-browser@example.test');
	await page.locator('input[name="your-subject"]').fill('E6 fake VK delivery');
	await page.locator('input[name="e6-marker"]').fill(marker);
	await page.locator('textarea[name="your-message"]').fill(`E6 public submit marker ${marker}`);

	const feedbackResponse = page.waitForResponse(isCf7FeedbackResponse, {timeout: 30000});
	await page.locator('.wpcf7 form input[type="submit"], .wpcf7 form button[type="submit"]').click();
	const response = await feedbackResponse;
	expect(response.status()).toBe(200);
	const body = await response.json();
	expect(body.status).toBe('mail_sent');
	await expect(page.locator('.wpcf7 form')).toHaveClass(/sent/);
	await expect(page.locator('.wpcf7-response-output')).toBeVisible();

	return body;
};

const callHasMarker = (call, marker) => (call.params?.message?.markers || []).includes(marker);

test.describe.configure({mode: 'serial'});

test.afterAll(() => {
	const failed = requiredCheckIds
		.map((id) => checks.get(id))
		.some((check) => !check || check.status !== 'pass');
	writeResult(failed ? 'failed' : 'passed');
});

test('public CF7 submit records fake VK messages.send attempts', async ({baseURL, page}) => {
	const unexpectedConsoleErrors = [];
	const pageErrors = [];
	const marker = `cf7vk-e6-${Date.now()}`;

	page.on('console', (message) => {
		if (message.type() !== 'error') {
			return;
		}

		const entry = {
			text: message.text(),
			location: message.location(),
		};
		unexpectedConsoleErrors.push(entry);
		evidence.console_errors.push(entry);
	});

	page.on('pageerror', (error) => {
		const entry = {
			message: error.message,
			stack: error.stack || '',
		};
		pageErrors.push(entry);
		evidence.page_errors.push(entry);
	});

	page.on('requestfailed', (request) => {
		evidence.request_failures.push({
			method: request.method(),
			url: sanitizeUrl(request.url()),
			failure: request.failure()?.errorText || '',
		});
	});

	page.on('response', async (response) => {
		const url = response.url();
		if (!isCf7FeedbackResponse(response)) {
			return;
		}

		try {
			evidence.contact_form_7_responses.push({
				status: response.status(),
				url: sanitizeUrl(url),
				body: await response.json(),
			});
		} catch (error) {
			evidence.contact_form_7_responses.push({
				status: response.status(),
				url: sanitizeUrl(url),
				error: error.message,
			});
		}
	});

	await expectCheck('fake-transport-active', 'Fake VK transport is active and resettable.', async () => {
		await control(page, baseURL, 'reset');
		const data = await vkEvidence(page, baseURL);
		expect(data.active).toBe(true);
		expect(data.fixture.form_id).toBeTruthy();
		expect(data.fixture.page_url).toMatch(/^http/);
		expect(data.fixture.expected_peer_buckets).toHaveLength(2);
		expect(data.fixture.expected_peer_buckets).toEqual(publicExpectedPeerIds.map(peerBucket));
		expect(data.fixture.unexpected_peer_bucket).toBe(peerBucket(publicUnexpectedPeerId));
		return {
			form_id: data.fixture.form_id,
			page_url: data.fixture.page_url,
			expected_peer_buckets: data.fixture.expected_peer_buckets,
			unexpected_peer_bucket: data.fixture.unexpected_peer_bucket,
		};
	});

	const fixture = evidence.fixture;

	await expectCheck('public-form-renders', 'The real public Contact Form 7 page renders.', async () => {
		await page.goto(fixture.page_url, {waitUntil: 'domcontentloaded'});
		await expect(page.locator('.wpcf7 form')).toBeVisible();
		await expect(page.locator('input[name="your-name"]')).toBeVisible();
		await expect(page.locator('input[name="your-email"]')).toBeVisible();
		await expect(page.locator('input[name="e6-marker"]')).toBeVisible();
		await expect(page.locator('textarea[name="your-message"]')).toBeVisible();
		return {
			url: page.url(),
			form_id: fixture.form_id,
		};
	});

	await expectCheck('cf7-submit-success', 'Submitting the public CF7 form succeeds in the browser.', async () => {
		const body = await submitPublicForm(page, fixture, marker);
		return {
			status: body.status,
			message: body.message,
			marker,
		};
	});

	await expectCheck('send-message-attempts', 'Fake VK captured expected messages.send attempts.', async () => {
		let data = null;
		await expect(async () => {
			data = await vkEvidence(page, baseURL);
			const calls = sendMessageCalls(data);
			expect(calls).toHaveLength(fixture.expected_peer_buckets.length);
			for (const expectedBucket of fixture.expected_peer_buckets) {
				const call = calls.find((entry) => entry.params.peer_bucket === expectedBucket);
				expect(call, `Expected messages.send for peer bucket ${expectedBucket}`).toBeTruthy();
				expect(callHasMarker(call, marker)).toBe(true);
				expect(call.response.ok).toBe(true);
			}
		}).toPass({message: 'Expected messages.send evidence should be recorded.', timeout: 30000, intervals: [250, 500, 1000]});

		return {
			expected_peer_buckets: fixture.expected_peer_buckets,
			send_message_calls: sendMessageCalls(data || {}).map((call) => ({
				index: call.index,
				peer_bucket: call.params.peer_bucket,
				token_hash: call.token_hash,
				response: call.response,
			})),
		};
	});

	await expectCheck('no-unexpected-recipient', 'No unrelated peer receives a messages.send attempt.', async () => {
		const data = await vkEvidence(page, baseURL);
		const calls = sendMessageCalls(data);
		const recipients = calls.map((call) => call.params.peer_bucket);
		expect(recipients).not.toContain(fixture.unexpected_peer_bucket);
		expect(new Set(recipients)).toEqual(new Set(fixture.expected_peer_buckets));
		return {recipient_buckets: recipients};
	});

	await expectCheck('no-token-leakage', 'Fake VK evidence does not expose full tokens or private chat labels.', async () => {
		const data = await vkEvidence(page, baseURL);
		const serialized = expectEvidenceRedacted(data);
		expect(serialized).toContain(fixture.bot_token_hash);
		return {
			token_hash: fixture.bot_token_hash,
			call_count: data.vk?.calls?.length || 0,
			private_chat_fields_redacted: true,
		};
	});

	await expectCheck('no-page-errors', 'No page errors occurred during the E6 public submit flow.', async () => {
		expect(pageErrors).toEqual([]);
		return {page_errors: pageErrors};
	});

	await expectCheck('no-console-errors', 'No unexpected console errors occurred during the E6 public submit flow.', async () => {
		expect(unexpectedConsoleErrors).toEqual([]);
		return {console_errors: unexpectedConsoleErrors};
	});
});

test('partial VK failure still attempts later recipients', async ({baseURL, page}) => {
	const unexpectedConsoleErrors = [];
	const pageErrors = [];
	const marker = `cf7vk-e6-failure-${Date.now()}`;

	page.on('console', (message) => {
		if (message.type() !== 'error') {
			return;
		}

		const entry = {
			text: message.text(),
			location: message.location(),
		};
		unexpectedConsoleErrors.push(entry);
		evidence.console_errors.push(entry);
	});

	page.on('pageerror', (error) => {
		const entry = {
			message: error.message,
			stack: error.stack || '',
		};
		pageErrors.push(entry);
		evidence.page_errors.push(entry);
	});

	page.on('requestfailed', (request) => {
		evidence.request_failures.push({
			method: request.method(),
			url: sanitizeUrl(request.url()),
			failure: request.failure()?.errorText || '',
		});
	});

	const reset = await control(page, baseURL, 'reset');
	const fixture = reset.fixture;
	const failingPeerBucket = fixture.expected_peer_buckets[0];
	const succeedingPeerBucket = fixture.expected_peer_buckets[1];
	await control(page, baseURL, 'script-failure', {method: 'messages.send', peer_bucket: failingPeerBucket, count: '1'});

	await expectCheck('partial-failure-cf7-success', 'CF7 submit still succeeds when one VK recipient fails.', async () => {
		const body = await submitPublicForm(page, fixture, marker);
		expect(body.status).toBe('mail_sent');
		return {
			status: body.status,
			marker,
			failing_peer_bucket: failingPeerBucket,
		};
	});

	await expectCheck('partial-failure-recipient-continuity', 'Later VK recipients are still attempted after a failure.', async () => {
		let data = null;
		await expect(async () => {
			data = await vkEvidence(page, baseURL);
			const calls = sendMessageCalls(data);
			expect(calls).toHaveLength(2);
			const failed = calls.find((call) => call.params.peer_bucket === failingPeerBucket);
			const succeeded = calls.find((call) => call.params.peer_bucket === succeedingPeerBucket);
			expect(failed, `Expected failed messages.send for peer bucket ${failingPeerBucket}`).toBeTruthy();
			expect(succeeded, `Expected later messages.send for peer bucket ${succeedingPeerBucket}`).toBeTruthy();
			expect(callHasMarker(failed, marker)).toBe(true);
			expect(callHasMarker(succeeded, marker)).toBe(true);
			expect(failed.response.ok).toBe(false);
			expect(failed.response.failure_category).toBe('scripted_failure');
			expect(succeeded.response.ok).toBe(true);
			expect(calls.map((call) => call.params.peer_bucket)).toEqual([
				failingPeerBucket,
				succeedingPeerBucket,
			]);
		}).toPass({message: 'Expected failed first recipient and successful later recipient.', timeout: 30000, intervals: [250, 500, 1000]});

		return {
			recipients: sendMessageCalls(data || {}).map((call) => ({
				peer_bucket: call.params.peer_bucket,
				ok: call.response.ok,
				category: call.response.failure_category,
			})),
		};
	});

	await expectCheck('partial-failure-evidence-redacted', 'Partial failure evidence remains token and chat identity redacted.', async () => {
		const data = await vkEvidence(page, baseURL);
		const serialized = expectEvidenceRedacted(data);
		expect(serialized).toContain(fixture.bot_token_hash);
		return {
			token_hash: fixture.bot_token_hash,
			call_count: data.vk?.calls?.length || 0,
			private_chat_fields_redacted: true,
		};
	});

	await expectCheck('partial-failure-no-page-errors', 'No page errors occurred during the E6 partial-failure flow.', async () => {
		expect(pageErrors).toEqual([]);
		return {page_errors: pageErrors};
	});

	await expectCheck('partial-failure-no-console-errors', 'No unexpected console errors occurred during the E6 partial-failure flow.', async () => {
		expect(unexpectedConsoleErrors).toEqual([]);
		return {console_errors: unexpectedConsoleErrors};
	});
});

test('admin setup builds and removes the delivery graph through the plugin UI', async ({baseURL, page}) => {
	const unexpectedConsoleErrors = [];
	const pageErrors = [];
	const dialogs = [];
	let fixture = {};
	let botId = 0;
	let channelId = 0;
	let discoveredChat = null;

	page.on('console', (message) => {
		if (message.type() !== 'error') {
			return;
		}

		const entry = {
			text: message.text(),
			location: message.location(),
		};
		unexpectedConsoleErrors.push(entry);
		evidence.console_errors.push(entry);
	});

	page.on('pageerror', (error) => {
		const entry = {
			message: error.message,
			stack: error.stack || '',
		};
		pageErrors.push(entry);
		evidence.page_errors.push(entry);
	});

	page.on('requestfailed', (request) => {
		evidence.request_failures.push({
			method: request.method(),
			url: sanitizeUrl(request.url()),
			failure: request.failure()?.errorText || '',
		});
	});

	page.on('dialog', async (dialog) => {
		dialogs.push({type: dialog.type(), message: dialog.message()});
		await dialog.accept();
	});

	await expectCheck('admin-empty-state', 'Admin flow starts from a clean delivery graph.', async () => {
		const reset = await control(page, baseURL, 'admin-reset');
		fixture = reset.fixture;
		await login(page, baseURL);
		await openAdmin(page, baseURL);

		await expect(page.getByTestId('cf7vk-create-bot')).toBeVisible();
		await expect(page.getByTestId('cf7vk-create-channel')).toBeVisible();

		const [bots, channels, chats, forms] = await Promise.all([
			restCollection(page, 'bots'),
			restCollection(page, 'channels'),
			restCollection(page, 'chats'),
			restCollection(page, 'forms'),
		]);
		expect(bots).toHaveLength(0);
		expect(channels).toHaveLength(0);
		expect(chats.map((chat) => String(chat.peerId))).toContain(adminSafetyPeerId);
		expect(forms.some((form) => Number(form.id) === Number(fixture.form_id))).toBe(true);

		return {
			deleted: reset.deleted,
			safety_peer_bucket: fixture.admin_flow.safety_chat.peer_bucket,
			form_id: fixture.form_id,
		};
	});

	await expectCheck('admin-bot-created', 'Create Bot creates a real bot post through the admin UI.', async () => {
		await page.getByTestId('cf7vk-create-bot').click();
		const bots = await waitForValue(
			() => restCollection(page, 'bots'),
			async (items) => {
				expect(items).toHaveLength(1);
				expect(itemTitle(items[0])).toBe('VK Bot');
			},
			'Expected one created bot in REST state.'
		);
		botId = Number(bots[0].id);
		await expect(page.getByTestId(`cf7vk-bot-${botId}`)).toBeVisible();

		return {
			bot_id: botId,
			title: itemTitle(bots[0]),
		};
	});

	await expectCheck('admin-bot-token-validated', 'Editing VK credentials validates against fake groups.getById.', async () => {
		const scripted = await control(page, baseURL, 'script-start-update');
		fixture = scripted.fixture;
		const bot = page.getByTestId(`cf7vk-bot-${botId}`);

		await bot.getByTestId(`cf7vk-bot-token-display-${botId}`).click();
		const tokenInput = bot.getByTestId(`cf7vk-bot-token-input-${botId}`);
		await expect(tokenInput).toBeVisible();
		await tokenInput.fill(adminTokenCanary);
		await setReactInputValue(bot.getByTestId(`cf7vk-bot-group-id-${botId}`), adminGroupId);
		await tokenInput.press('Enter');

		await expect(bot).toHaveClass(/online/, {timeout: 30000});
		await expect(bot.locator('.bot-name')).toContainText('E6 Fake VK Community');

		await waitForValue(
			() => vkEvidence(page, baseURL),
			async (data) => {
				expect(vkCalls(data).some((call) => call.method === 'groups.getById')).toBe(true);
				expect(vkCalls(data).some((call) => call.method === 'groups.getLongPollServer')).toBe(true);
			},
			'Expected fake VK credential validation and Long Poll server discovery evidence.'
		);

		return {
			bot_id: botId,
			group_id: adminGroupId,
			scripted_update_id: fixture.admin_flow.update_id,
		};
	});

	await expectCheck('admin-chat-discovered', 'Fake VK Long Poll creates a pending chat through the real fetch path.', async () => {
		const chats = await waitForValue(
			() => restCollection(page, 'chats'),
			async (items) => {
				const chat = items.find((item) => String(item.peerId) === adminUpdatePeerId);
				expect(chat, `Expected discovered peer ${peerBucket(adminUpdatePeerId)}`).toBeTruthy();
			},
			'Expected fake VK Long Poll start update to create a chat.'
		);
		discoveredChat = chats.find((item) => String(item.peerId) === adminUpdatePeerId);

		const connections = await waitForValue(
			() => restCollection(page, 'bot2chat'),
			async (items) => {
				const relation = items.find((item) => (
					Number(item.data.from) === botId
					&& Number(item.data.to) === Number(discoveredChat.id)
				));
				expect(relation, 'Expected bot2chat relation for discovered chat.').toBeTruthy();
				expect(relation.data.meta.status).toContain('pending');
			},
			'Expected discovered chat to be pending for the bot.'
		);

		await expect(page.getByTestId(`cf7vk-bot-${botId}-chat-${discoveredChat.id}`)).toBeVisible();

		return {
			chat_post_id: discoveredChat.id,
			peer_bucket: peerBucket(discoveredChat.peerId),
			bot2chat_relation: connections.find((item) => Number(item.data.to) === Number(discoveredChat.id))?.data?.id,
		};
	});

	await expectCheck('admin-channel-created', 'Create Channel creates a real channel post through the admin UI.', async () => {
		await page.getByTestId('cf7vk-create-channel').click();
		const channels = await waitForValue(
			() => restCollection(page, 'channels'),
			async (items) => {
				expect(items).toHaveLength(1);
				expect(itemTitle(items[0])).toBe('Channel');
			},
			'Expected one created channel in REST state.'
		);
		channelId = Number(channels[0].id);
		await expect(page.getByTestId(`cf7vk-channel-${channelId}`)).toBeVisible();
		await expect(page.getByTestId(`cf7vk-channel-title-input-${channelId}`)).toHaveValue('Channel');

		return {
			channel_id: channelId,
			title: itemTitle(channels[0]),
		};
	});

	await expectCheck('admin-form-selectable', 'The seeded CF7 form is selectable and assignable in the channel UI.', async () => {
		const channel = page.getByTestId(`cf7vk-channel-${channelId}`);
		await channel.getByTestId(`cf7vk-channel-${channelId}-add-form`).click();
		await selectReactOption(page, channel.locator('.form-picker'), 'CF7VK E6 Delivery Form');

		await waitForValue(
			() => restCollection(page, 'form2channel'),
			async (items) => {
				expect(items.some((item) => (
					Number(item.data.from) === Number(fixture.form_id)
					&& Number(item.data.to) === channelId
				))).toBe(true);
			},
			'Expected form2channel relation after UI form selection.'
		);
		await expect(channel.getByTestId(`cf7vk-channel-${channelId}-form-${fixture.form_id}`)).toBeVisible();

		return {
			form_id: fixture.form_id,
			channel_id: channelId,
		};
	});

	await expectCheck('admin-relations-assigned', 'Bot and discovered chat are assigned to the channel through UI controls.', async () => {
		const channel = page.getByTestId(`cf7vk-channel-${channelId}`);
		await selectReactOption(page, channel.locator('.bot-picker'), 'E6 Fake VK Community');

		await waitForValue(
			() => restCollection(page, 'bot2channel'),
			async (items) => {
				expect(items.some((item) => (
					Number(item.data.from) === botId
					&& Number(item.data.to) === channelId
				))).toBe(true);
			},
			'Expected bot2channel relation after UI bot selection.'
		);
		await expect(channel.locator('.bot-for-channel')).toContainText('E6 Fake VK Community');

		await page.getByTestId(`cf7vk-bot-${botId}-chat-${discoveredChat.id}-toggle`).click();

		await waitForValue(
			() => restCollection(page, 'chat2channel'),
			async (items) => {
				expect(items.some((item) => (
					Number(item.data.from) === Number(discoveredChat.id)
					&& Number(item.data.to) === channelId
				))).toBe(true);
			},
			'Expected chat2channel relation after activating pending chat.'
		);
		await waitForValue(
			() => restCollection(page, 'bot2chat'),
			async (items) => {
				const relation = items.find((item) => (
					Number(item.data.from) === botId
					&& Number(item.data.to) === Number(discoveredChat.id)
				));
				expect(relation?.data?.meta?.status).toContain('active');
			},
			'Expected bot2chat status to become active.'
		);
		await expect(channel.getByTestId(`cf7vk-channel-${channelId}-chat-${discoveredChat.id}`)).toBeVisible();

		return {
			bot_id: botId,
			channel_id: channelId,
			chat_post_id: discoveredChat.id,
			peer_bucket: peerBucket(discoveredChat.peerId),
		};
	});

	await expectCheck('admin-delivery-targets-assigned-chat', 'The admin-built graph delivers only to the assigned chat.', async () => {
		await control(page, baseURL, 'reset');
		const marker = `cf7vk-e6-admin-${Date.now()}`;
		const body = await submitPublicForm(page, fixture, marker);
		expect(body.status).toBe('mail_sent');

		let data = null;
		await expect(async () => {
			data = await vkEvidence(page, baseURL);
			const calls = sendMessageCalls(data);
			expect(calls).toHaveLength(1);
			expect(calls[0].params.peer_bucket).toBe(peerBucket(adminUpdatePeerId));
			expect(callHasMarker(calls[0], marker)).toBe(true);
			expect(calls[0].response.ok).toBe(true);
		}).toPass({message: 'Expected one messages.send for the admin-assigned chat.', timeout: 30000, intervals: [250, 500, 1000]});

		return {
			status: body.status,
			recipient_buckets: sendMessageCalls(data || {}).map((call) => call.params.peer_bucket),
			marker,
		};
	});

	await expectCheck('admin-evidence-redacted', 'Admin flow evidence remains token and chat identity redacted.', async () => {
		const data = await vkEvidence(page, baseURL);
		expectEvidenceRedacted(data);
		return {
			call_count: data.vk?.calls?.length || 0,
			update_count: data.vk?.updates?.length || 0,
			private_chat_fields_redacted: true,
		};
	});

	await expectCheck('admin-deletion-safety', 'Removing the channel and bot does not delete unrelated chats.', async () => {
		await openAdmin(page, baseURL);
		await page.getByTestId(`cf7vk-remove-channel-${channelId}`).click();
		await waitForValue(
			() => restCollection(page, 'channels'),
			async (items) => expect(items).toHaveLength(0),
			'Expected channel to be deleted after UI removal.'
		);

		await page.getByTestId(`cf7vk-remove-bot-${botId}`).click();
		await waitForValue(
			() => restCollection(page, 'bots'),
			async (items) => expect(items).toHaveLength(0),
			'Expected bot to be deleted after UI removal.'
		);

		const chats = await restCollection(page, 'chats');
		const peerIds = chats.map((chat) => String(chat.peerId));
		expect(peerIds).toContain(adminSafetyPeerId);
		expect(peerIds).toContain(adminUpdatePeerId);

		return {
			remaining_peer_buckets: peerIds.map(peerBucket),
			dialogs,
		};
	});

	await expectCheck('admin-no-page-errors', 'No page errors occurred during the E6 admin setup flow.', async () => {
		expect(pageErrors).toEqual([]);
		return {page_errors: pageErrors};
	});

	await expectCheck('admin-no-console-errors', 'No unexpected console errors occurred during the E6 admin setup flow.', async () => {
		expect(unexpectedConsoleErrors).toEqual([]);
		return {console_errors: unexpectedConsoleErrors};
	});
});
