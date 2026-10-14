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
 * Posts per /queue/posts request.
 *
 * @type {number}
 */
const QUEUE_POSTS_PER_REQUEST = 4;

/**
 * Queue posts for translation.
 *
 * Returns quickly: translation happens on the server afterwards. Posts are
 * sent a few at a time so no single request holds a PHP worker for long
 * while WPML builds the jobs. The merged result has the same shape as a
 * single call.
 *
 * @param {number[]} postIds              Post ids.
 * @param {string[]} languages            Target language codes.
 * @param {string}   provider             Provider slug, e.g. 'openai'. Optional.
 * @param {Object}   options              Optional.
 * @param {number}   options.perRequest   Posts per request.
 * @param {Function} options.onChunk      Called after each request with
 *                                        (postIds, result, error).
 * @return {Promise<Object>} { queued, skipped, errors, jobs, items, skip_reasons, message }
 */
export const queuePosts = async (postIds, languages, provider = '', options = {}) => {
	const ids = Array.isArray(postIds) ? postIds : [postIds];
	const merged = { queued: 0, skipped: 0, errors: {}, jobs: [], items: {}, skip_reasons: {}, message: '' };
	const perRequest = Math.max(1, Number(options.perRequest) || QUEUE_POSTS_PER_REQUEST);
	const onChunk = typeof options.onChunk === 'function' ? options.onChunk : null;
	let firstError = null;

	for (let i = 0; i < ids.length; i += perRequest) {
		const chunk = ids.slice(i, i + perRequest);
		let res;

		try {
			res = await request('/queue/posts', {
				post_ids: chunk,
				languages,
				provider,
			});
		} catch (err) {
			if (onChunk) {
				onChunk(chunk, null, err);
			}

			// Keep going: other posts may still queue. Rethrown below only
			// when nothing at all was queued.
			firstError = firstError || err;
			merged.errors[`request_${i}`] = err && err.message ? err.message : String(err);
			continue;
		}

		merged.queued += Number(res.queued) || 0;
		merged.skipped += Number(res.skipped) || 0;
		Object.assign(merged.errors, res.errors || {});
		merged.jobs = merged.jobs.concat(Array.isArray(res.jobs) ? res.jobs : []);
		Object.assign(merged.items, res.items || {});
		Object.entries(res.skip_reasons || {}).forEach(([key, count]) => {
			merged.skip_reasons[key] = (merged.skip_reasons[key] || 0) + (Number(count) || 0);
		});

		if (res.message) {
			merged.message = res.message;
		}

		if (onChunk) {
			onChunk(chunk, res, null);
		}
	}

	if (firstError && !merged.queued && !merged.skipped) {
		throw firstError;
	}

	if (merged.queued) {
		merged.message = '';
	}

	return merged;
};

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
 * @param {number[]|Function} options.jobIds Jobs to watch, or a getter while more are added.
 * @param {Function} options.onUpdate   Called with each status payload.
 * @param {Function} options.shouldStop Return true to abort polling.
 * @param {Function} options.isAdding   Return true while more jobs are still being queued.
 * @param {number}   options.timeoutMs  Give up after this long.
 * @return {Promise<Object>} Final status payload.
 */
export const pollUntilDone = async ({
	jobIds,
	onUpdate = () => {},
	shouldStop = () => false,
	isAdding = () => false,
	timeoutMs = 30 * 60 * 1000,
}) => {
	const currentIds = () => (typeof jobIds === 'function' ? jobIds() : jobIds);
	const startedAt = Date.now();

	// Each poll is a full WordPress request. On small hosts (two PHP workers)
	// polling every second keeps a worker busy, so poll gently.
	let interval = 2500;
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
			status = await fetchStatus(currentIds());
		} catch (error) {
			interval = Math.min(interval * 2, 5000);
			await wait(interval);
			continue;
		}

		// Never finished while selected posts are still being queued.
		if (status && status.finished && isAdding()) {
			status = { ...status, finished: false };
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

		// Start the next waiting job only when none is running. The server
		// lock makes extra kicks harmless, but each one still costs a worker.
		if (hasWaiting && !hasActive) {
			kickOne();
		}

		await wait(interval);

		interval = hasActive ? 2500 : Math.min(interval + 500, 4000);
	}
};

/**
 * Percent complete for one job (0–100) from fields_translated / field_count.
 *
 * @param {Object} job Job row from /queue/status.
 * @return {number}
 */
export const jobProgressPercent = (job) => {
	if (!job || typeof job !== 'object') {
		return 0;
	}

	if (typeof job.progress_percent === 'number' && !Number.isNaN(job.progress_percent)) {
		return Math.min(100, Math.max(0, Math.round(job.progress_percent)));
	}

	if (job.state === 'done') {
		return 100;
	}

	const total = Number(job.field_count) || 0;
	const done = Number(job.fields_translated) || 0;

	if (total < 1 || done < 1) {
		return 0;
	}

	return Math.min(100, Math.max(0, Math.round((done / total) * 100)));
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

	const percent = Math.round(
		jobs.reduce((sum, job) => sum + jobProgressPercent(job), 0) / total
	);

	return {
		total,
		closed,
		failed,
		percent: Math.min(100, Math.max(0, percent)),
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
