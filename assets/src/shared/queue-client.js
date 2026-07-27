/**
 * Queue client.
 *
 * Replaces the browser-side translation engine. The old AIService chunked
 * content, fired concurrent provider requests and created posts. All of that
 * now happens on the server, so this module only queues work and polls for
 * progress.
 */

const cfg = () => window.automlp_wpml_bulk_translate_object || {};

const REST_ROOT = () => (cfg().queueRouteUrl || '').replace(/\/$/, '');

/**
 * Terminal job states. Polling stops once every job reaches one.
 */
const CLOSED_STATES = ['done', 'failed', 'stopped'];

/**
 * Call a queue endpoint.
 *
 * @param {string} path   Endpoint path, e.g. '/queue/posts'.
 * @param {Object} body   Request body. Omit for GET.
 * @param {string} method HTTP method.
 * @return {Promise<Object>} Parsed response.
 */
const request = async (path, body = null, method = 'POST') => {
	const url = REST_ROOT() + path;

	const options = {
		method,
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
			'X-WP-Nonce': cfg().nonce,
		},
	};

	if (body && method !== 'GET') {
		options.body = JSON.stringify(body);
	}

	const response = await fetch(url, options);
	const data = await response.json();

	if (!response.ok) {
		const message =
			data && data.message ? data.message : 'Request failed.';
		throw new Error(message);
	}

	return data;
};

/**
 * Queue posts for translation.
 *
 * Returns immediately: translation happens on the server afterwards.
 *
 * @param {number[]} postIds   Post ids.
 * @param {string[]} languages Target language codes.
 * @param {string}   provider  Provider slug, e.g. 'openai'. Optional.
 * @return {Promise<Object>} { queued, skipped, errors, jobs }
 */
export const queuePosts = async (postIds, languages, provider = '') =>
	request('/queue/posts', {
		post_ids: postIds,
		languages,
		provider,
	});

/**
 * Queue String Translation strings.
 *
 * @param {number[]} stringIds String ids.
 * @param {string}   language  Target language code.
 * @param {string}   provider  Provider slug. Optional.
 * @return {Promise<Object>} { queued, jobs }
 */
export const queueStrings = async (stringIds, language, provider = '') =>
	request('/queue/strings', {
		string_ids: stringIds,
		language,
		provider,
	});

/**
 * Fetch status for specific jobs.
 *
 * @param {number[]} jobIds Job ids.
 * @return {Promise<Object>} { jobs, counts, finished, health }
 */
export const fetchStatus = async (jobIds = []) => {
	const query = jobIds.length
		? '?' + jobIds.map((id) => `job_ids[]=${encodeURIComponent(id)}`).join('&')
		: '';

	return request('/queue/status' + query, null, 'GET');
};

/**
 * Ask the server to drain the queue now.
 *
 * Needed on sites where WP-Cron is disabled or throttled.
 *
 * @param {number} limit Optional max jobs for this run. Use 1 while polling so
 *                       status can update between jobs.
 * @return {Promise<Object>} { ran, counts }
 */
export const runQueueNow = async (limit = 0) =>
	request('/queue/run', limit > 0 ? { limit } : {});

/**
 * Send a failed job back to the queue.
 *
 * Only works while the job's source content is still stored: it is cleared on
 * completion and by pruning, so very old failures cannot be retried.
 *
 * @param {number} jobId Job id.
 * @return {Promise<Object>} { retried, job }
 */
export const retryJob = async (jobId) =>
	request('/queue/retry', { job_id: jobId });

/**
 * Poll until every job closes.
 *
 * Kicks the dispatcher in the background (one job at a time) without awaiting
 * it, so /queue/status keeps returning while translation runs. Awaiting
 * /queue/run would block for the full AI call and make the first status look
 * like an instant 100% jump.
 *
 * @param {Object}   options            Options.
 * @param {number[]} options.jobIds     Jobs to watch.
 * @param {Function} options.onUpdate   Called with each status payload.
 * @param {Function} options.shouldStop Return true to abort polling.
 * @param {number}   options.timeoutMs  Give up after this long.
 * @return {Promise<Object>} Final status payload.
 */
export const pollUntilDone = async ({
	jobIds,
	onUpdate = () => {},
	shouldStop = () => false,
	timeoutMs = 30 * 60 * 1000,
}) => {
	const startedAt = Date.now();

	let interval = 1000;
	let last = null;
	let runInFlight = false;

	const kickOne = () => {
		if (runInFlight) {
			return;
		}

		runInFlight = true;
		runQueueNow(1)
			.catch(() => {})
			.finally(() => {
				runInFlight = false;
			});
	};

	// Start the first waiting job immediately so we do not wait on cron.
	kickOne();

	// eslint-disable-next-line no-constant-condition
	while (true) {
		if (shouldStop()) {
			return last;
		}

		if (Date.now() - startedAt > timeoutMs) {
			throw new Error(
				'Translation is taking longer than expected. It will keep running in the background.'
			);
		}

		let status;

		try {
			status = await fetchStatus(jobIds);
		} catch (error) {
			interval = Math.min(interval * 2, 5000);
			await wait(interval);
			continue;
		}

		last = status;
		onUpdate(status);

		if (status.finished) {
			return status;
		}

		const jobs = Array.isArray(status.jobs) ? status.jobs : [];
		const hasWaiting = jobs.some((job) => job.state === 'waiting');
		const hasActive = jobs.some((job) =>
			['claimed', 'sent', 'writing'].includes(job.state)
		);

		// Next waiting job when the previous one finished, or when cron is unhealthy.
		if ((hasWaiting && !hasActive) || (status.health && status.health.state !== 'ok')) {
			kickOne();
		}

		await wait(interval);

		interval = hasActive ? 1000 : Math.min(interval + 500, 3000);
	}
};

/**
 * How far along a single job is, for a smooth progress bar.
 *
 * Closed jobs alone would jump 0 → 100 when two short jobs finish between
 * polls. Weighting in-flight states keeps the bar moving.
 */
export const STATE_WEIGHT = {
	waiting: 0,
	claimed: 0.15,
	sent: 0.55,
	writing: 0.85,
	done: 1,
	failed: 1,
	stopped: 1,
};

/**
 * Percent complete for one job state (0–100).
 *
 * @param {string} state Queue job state.
 * @return {number}
 */
export const jobProgressPercent = (state) => {
	const weight = STATE_WEIGHT[state];
	return Math.round((weight !== undefined ? weight : 0) * 100);
};

/**
 * Summarise a status payload for a progress bar.
 *
 * @param {Object} status Status payload.
 * @return {Object} { total, closed, percent, failed }
 */
export const summarise = (status) => {
	const jobs = (status && status.jobs) || [];
	const total = jobs.length;

	if (!total) {
		return { total: 0, closed: 0, percent: 0, failed: 0 };
	}

	const closed = jobs.filter((job) => CLOSED_STATES.includes(job.state)).length;
	const failed = jobs.filter((job) => job.state === 'failed').length;

	const weight = jobs.reduce((sum, job) => {
		const state = job && job.state ? job.state : 'waiting';
		return sum + (STATE_WEIGHT[state] !== undefined ? STATE_WEIGHT[state] : 0);
	}, 0);

	return {
		total,
		closed,
		failed,
		percent: Math.round((weight / total) * 100),
	};
};

/**
 * Sleep.
 *
 * @param {number} ms Milliseconds.
 * @return {Promise<void>} Resolves after the delay.
 */
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export default {
	queuePosts,
	retryJob,
	queueStrings,
	fetchStatus,
	runQueueNow,
	pollUntilDone,
	summarise,
};
