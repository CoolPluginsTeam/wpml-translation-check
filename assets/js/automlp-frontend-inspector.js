/**
 * AutoMLP front-end text origin inspector.
 *
 * Loaded for administrators on every front-end view. The admin-bar item toggles
 * inspect mode in place: the light origin map is read from the page (when it was
 * opened with ?automlp-inspect=1) or fetched from the same URL with that arg.
 * Visible text is matched to the map, a batch AJAX scan lists untranslated
 * strings grouped by source, and any clicked text is resolved on demand.
 */
(function () {
	'use strict';

	var cfg = window.automlpFrontendInspector;
	if (!cfg || !document.body || !window.fetch) {
		return;
	}

	var i18n = cfg.i18n || {};
	// Specific sources win over generic option matches.
	var STRING_KINDS = { gettext: 1, option: 1, theme_mod: 1 };
	var PRIORITY = { widget: 10, nav_menu_item: 9, elementor: 8, post_title: 7, post_content: 6, gettext: 5, theme_mod: 2, option: 1 };
	var HINT_KEYS = ['d', 'o', 'c', 'n', 'p', 'eid', 'wid', 'id', 'did', 'u'];

	var state = {
		open: false,
		busy: false,
		map: null,
		byText: null,
		panel: null,
		detailEl: null,
		listEl: null,
		selectedEl: null,
		scanSeq: 0,
		ct: null
	};

	/* ---------- JS-added text (layer: browser) ---------- */

	var added = window.WeakSet ? new WeakSet() : null;
	if (added && window.MutationObserver) {
		new MutationObserver(function (records) {
			records.forEach(function (r) {
				if (r.type === 'characterData') {
					added.add(r.target);
					return;
				}
				Array.prototype.forEach.call(r.addedNodes, function (n) {
					if (n.nodeType === 3 || (n.nodeType === 1 && !isInspectorUi(n))) {
						added.add(n);
					}
				});
			});
		}).observe(document.body, { childList: true, subtree: true, characterData: true });
	}

	function isAdded(node) {
		if (!added) {
			return false;
		}
		for (var n = node; n && n !== document.body; n = n.parentNode) {
			if (added.has(n)) {
				return true;
			}
		}
		for (var c = node.firstChild; c; c = c.nextSibling) {
			if (c.nodeType === 3 && added.has(c)) {
				return true;
			}
		}
		return false;
	}

	function normalize(text) {
		return String(text || '').replace(/\s+/g, ' ').trim();
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (typeof text === 'string') {
			node.textContent = text;
		}
		return node;
	}

	function format(str) {
		var args = Array.prototype.slice.call(arguments, 1);
		var seq = 0;
		return String(str || '').replace(/%(?:(\d+)\$)?d/g, function (m, pos) {
			var idx = pos ? parseInt(pos, 10) - 1 : seq++;
			return typeof args[idx] !== 'undefined' ? String(args[idx]) : m;
		});
	}

	function isInspectorUi(node) {
		return !!(node && node.closest && (node.closest('#automlp-fi') || node.closest('#wpadminbar')));
	}

	function adminBarLink() {
		var item = cfg.adminBarId ? document.getElementById(cfg.adminBarId) : null;
		return item ? item.querySelector('a') : null;
	}

	function pickHint(list) {
		if (!list || !list.length) {
			return null;
		}
		var best = list[0];
		for (var i = 1; i < list.length; i++) {
			if ((PRIORITY[list[i].k] || 0) > (PRIORITY[best.k] || 0)) {
				best = list[i];
			}
		}
		return best;
	}

	/* ---------- map loading ---------- */

	function readJson(doc, id) {
		var node = doc.getElementById(id);
		if (!node) {
			return null;
		}
		try {
			return JSON.parse(node.textContent);
		} catch (e) {
			return null;
		}
	}

	function readMap(doc) {
		var map = readJson(doc, cfg.mapId || 'automlp-fi-map');
		if (map) {
			state.ct = readJson(doc, cfg.containersId || 'automlp-fi-containers');
		}
		return map;
	}

	/* ---------- container layer ---------- */

	function candidates(node) {
		var list = [];
		var attr = node.getAttribute('data-automlp-fi-text');
		if (attr) {
			list.push(attr);
		}
		var walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT, null);
		var t;
		while ((t = walker.nextNode()) && list.length < 25) {
			var v = normalize(t.nodeValue);
			if (v.length > 1) {
				list.push(v);
			}
		}
		list.push(normalize(node.textContent));
		return list;
	}

	function chainOf(cid) {
		var out = [];
		var all = (state.ct && state.ct.containers) || {};
		while (cid && all[cid] && out.length < 8) {
			out.push(all[cid]);
			cid = all[cid].parent;
		}
		return out;
	}

	function elementorFromDom(node) {
		var w = node.closest ? node.closest('.elementor-element[data-id]') : null;
		var d = node.closest ? node.closest('[data-elementor-id]') : null;
		if (!w || !d) {
			return null;
		}
		var did = parseInt(d.getAttribute('data-elementor-id'), 10) || 0;
		var name = (w.getAttribute('data-widget_type') || w.getAttribute('data-element_type') || '').split('.')[0];
		return {
			type: 'elementor',
			eid: w.getAttribute('data-id'),
			did: did,
			label: 'Elementor ' + name + ' #' + w.getAttribute('data-id') + ' (' + did + ')',
			edit: cfg.adminUrl ? cfg.adminUrl + 'post.php?post=' + did + '&action=elementor' : ''
		};
	}

	function originOf(node, text) {
		var info = { chain: [], region: null, js: false, inServer: false };
		var texts = (state.ct && state.ct.texts) || null;
		var list = candidates(node);
		for (var i = 0; texts && i < list.length; i++) {
			var hit = texts[list[i]];
			if (hit) {
				info.inServer = true;
				info.chain = chainOf(hit[0]);
				info.region = hit[1] ? ((state.ct.containers || {})[hit[1]] || null) : null;
				break;
			}
		}
		var dom = elementorFromDom(node);
		if (dom && !info.chain.some(function (c) { return c.type === 'elementor'; })) {
			info.chain.unshift(dom);
		}
		info.js = isAdded(node) || (!!texts && !info.inServer && !(state.byText && state.byText[text]));
		return info;
	}

	function hintFromChain(chain) {
		for (var i = 0; i < chain.length; i++) {
			var c = chain[i];
			if (c.type === 'elementor' && c.eid && c.did) {
				return { k: 'elementor', eid: String(c.eid), did: c.did };
			}
			if (c.type === 'widget' && c.wid) {
				return { k: 'widget', wid: String(c.wid) };
			}
			if (c.type === 'nav_menu_item' && c.id) {
				return { k: 'nav_menu_item', id: c.id };
			}
			if ((c.type === 'post_content' || c.type === 'block') && c.id) {
				return { k: 'post_content', id: c.id };
			}
		}
		return null;
	}

	/**
	 * The one hint picker shared by click and sidebar scan: string-map hint,
	 * replaced by the container-chain hint when that is more specific.
	 */
	function hintFor(node, text, info) {
		var hint = hintOf(node) || pickHint(state.byText && state.byText[text]);
		var fromChain = hintFromChain((info || originOf(node, text)).chain);
		// A string hit (gettext / Admin Text / theme_mod) always wins; containers stay in "Rendered by".
		if (hint && STRING_KINDS[hint.k]) {
			return hint;
		}
		if (fromChain && (!hint || (PRIORITY[fromChain.k] || 0) > (PRIORITY[hint.k] || 0))) {
			hint = fromChain;
		}
		return hint;
	}

	function inspectUrl() {
		var url = new URL(window.location.href);
		url.searchParams.set(cfg.queryArg, '1');
		url.hash = '';
		return url.toString();
	}

	function loadMap() {
		if (state.map) {
			return Promise.resolve(state.map);
		}
		var inline = readMap(document);
		if (inline) {
			return Promise.resolve(inline);
		}
		return fetch(inspectUrl(), { credentials: 'same-origin' })
			.then(function (res) {
				if (!res.ok) {
					throw new Error('HTTP ' + res.status);
				}
				return res.text();
			})
			.then(function (html) {
				var map = readMap(new DOMParser().parseFromString(html, 'text/html'));
				if (!map) {
					throw new Error('no map');
				}
				return map;
			});
	}

	function indexMap(map) {
		var byText = Object.create(null);
		(Array.isArray(map.entries) ? map.entries : []).forEach(function (entry) {
			if (entry && entry.t) {
				(byText[entry.t] = byText[entry.t] || []).push(entry);
			}
		});
		state.map = map;
		state.byText = byText;
	}

	/* ---------- marking ---------- */

	function markPage() {
		var byText = state.byText;
		var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
			acceptNode: function (node) {
				if (!node.parentElement || isInspectorUi(node.parentElement)) {
					return NodeFilter.FILTER_REJECT;
				}
				var tag = node.parentElement.tagName;
				if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT') {
					return NodeFilter.FILTER_REJECT;
				}
				var t = normalize(node.nodeValue);
				return t.length > 1 && byText[t] ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
			}
		});
		var node;
		var marked = 0;
		while ((node = walker.nextNode()) && marked <= 2000) {
			var parent = node.parentElement;
			if (parent.classList.contains('automlp-fi-mark')) {
				continue;
			}
			var text = normalize(node.nodeValue);
			var hint = pickHint(byText[text]);
			parent.classList.add('automlp-fi-mark');
			parent.setAttribute('data-automlp-fi-text', text);
			if (hint) {
				parent.setAttribute('data-automlp-fi-hint', JSON.stringify(hint));
				if (hint.u) {
					parent.classList.add('automlp-fi-untranslated');
				}
			}
			marked++;
		}
	}

	function unmarkPage() {
		var nodes = document.querySelectorAll('.automlp-fi-mark, .automlp-fi-selected, .automlp-fi-flash');
		Array.prototype.forEach.call(nodes, function (node) {
			node.classList.remove('automlp-fi-mark', 'automlp-fi-untranslated', 'automlp-fi-selected', 'automlp-fi-flash', 'automlp-fi-listed');
			node.removeAttribute('data-automlp-fi-text');
			node.removeAttribute('data-automlp-fi-hint');
		});
		state.selectedEl = null;
	}

	function isVisible(node) {
		if (!node.getClientRects().length) {
			return false;
		}
		var style = window.getComputedStyle(node);
		return style.visibility !== 'hidden' && style.display !== 'none';
	}

	function hintOf(node) {
		var raw = node.getAttribute('data-automlp-fi-hint');
		if (raw) {
			try {
				return JSON.parse(raw);
			} catch (e) {
				return null;
			}
		}
		return null;
	}

	/* ---------- panel ---------- */

	function buildPanel() {
		var panel = el('aside', '');
		panel.id = 'automlp-fi';
		panel.setAttribute('aria-label', i18n.title || 'Text Origin Inspector');

		var header = el('div', 'automlp-fi__header');
		header.appendChild(el('h2', 'automlp-fi__title', i18n.title || 'Text Origin Inspector'));
		var closeBtn = el('button', 'automlp-fi__close', '\u00d7');
		closeBtn.type = 'button';
		closeBtn.setAttribute('aria-label', i18n.close || 'Close');
		closeBtn.addEventListener('click', close);
		header.appendChild(closeBtn);
		panel.appendChild(header);

		var body = el('div', 'automlp-fi__body');
		body.appendChild(el('p', 'automlp-fi__hint', i18n.hint || ''));
		state.detailEl = el('div', 'automlp-fi__detail');
		state.detailEl.setAttribute('aria-live', 'polite');
		body.appendChild(state.detailEl);

		var section = el('section', 'automlp-fi__scan');
		section.appendChild(el('h3', 'automlp-fi__scan-title', i18n.scanTitle || 'Untranslated on this page'));
		state.listEl = el('div', 'automlp-fi__scan-list');
		section.appendChild(state.listEl);
		body.appendChild(section);
		panel.appendChild(body);

		document.body.appendChild(panel);
		document.body.classList.add('automlp-fi-open');
		state.panel = panel;
	}

	function card(text) {
		var c = el('div', 'automlp-fi__card');
		c.appendChild(el('span', 'automlp-fi__label', i18n.selected || 'Selected text'));
		c.appendChild(el('p', 'automlp-fi__value', text));
		return c;
	}

	function renderLoading(text) {
		state.detailEl.innerHTML = '';
		var c = card(text);
		c.appendChild(el('p', 'automlp-fi__hint', i18n.loading || 'Resolving…'));
		state.detailEl.appendChild(c);
	}

	function renderOrigin(c, info) {
		if (!info) {
			return;
		}
		if (info.chain.length) {
			c.appendChild(el('span', 'automlp-fi__label', i18n.container || 'Rendered by'));
			var ul = el('ul', 'automlp-fi__chain');
			info.chain.slice(0, 4).forEach(function (item) {
				var li = el('li', '', item.label || item.type);
				if (item.edit) {
					var a = el('a', 'automlp-fi__edit', i18n.edit || 'Edit');
					a.href = item.edit;
					a.target = '_blank';
					a.rel = 'noopener noreferrer';
					li.appendChild(document.createTextNode(' '));
					li.appendChild(a);
				}
				ul.appendChild(li);
			});
			c.appendChild(ul);
		}
		if (info.region && !info.chain.length) {
			c.appendChild(el('span', 'automlp-fi__label', i18n.region || 'Theme template'));
			c.appendChild(el('p', 'automlp-fi__value automlp-fi__mono', info.region.label || ''));
		}
	}

	function renderResult(text, data, info) {
		state.detailEl.innerHTML = '';
		var c = card(text);
		if (!data) {
			c.appendChild(el('p', 'automlp-fi__value', i18n.error || 'Error'));
			state.detailEl.appendChild(c);
			return;
		}
		if (!data.found && info) {
			if (info.js) {
				data.source_type_label = i18n.byJs || 'Added by JavaScript';
				data.status_label = i18n.byJsStatus || '';
				data.translated = null;
			} else if (info.chain.length) {
				data.source_type_label = info.chain[0].label || data.source_type_label;
				data.status_label = i18n.containerOnly || data.status_label;
				data.translated = null;
			}
		}
		c.appendChild(el('span', 'automlp-fi__label', i18n.source || 'Source'));
		c.appendChild(el('p', 'automlp-fi__value', data.source_type_label || data.source_type || '—'));

		c.appendChild(el('span', 'automlp-fi__label', i18n.status || 'Status'));
		var statusWrap = el('p', 'automlp-fi__value');
		var badge = el('span', 'automlp-fi__badge', data.status_label || '—');
		if (data.translated === false) {
			badge.classList.add('is-bad');
		} else if (data.translated === true) {
			badge.classList.add('is-ok');
		}
		statusWrap.appendChild(badge);
		c.appendChild(statusWrap);

		if (data.identifiers && typeof data.identifiers === 'object') {
			var keys = Object.keys(data.identifiers).filter(function (key) {
				var v = data.identifiers[key];
				return v !== null && typeof v !== 'undefined' && v !== '';
			});
			if (keys.length) {
				c.appendChild(el('span', 'automlp-fi__label', i18n.identifiers || 'Identifiers'));
				var list = el('ul', 'automlp-fi__ids');
				keys.forEach(function (key) {
					list.appendChild(el('li', '', key + ': ' + String(data.identifiers[key])));
				});
				c.appendChild(list);
			}
		}
		if (data.message) {
			c.appendChild(el('p', 'automlp-fi__value', data.message));
		}
		if (data.fix_url) {
			c.appendChild(el('span', 'automlp-fi__label', i18n.fix || 'Fix'));
			var link = el('a', 'automlp-fi__fix', data.fix_label || i18n.openFix || 'Open');
			link.href = data.fix_url;
			link.target = '_blank';
			link.rel = 'noopener noreferrer';
			c.appendChild(link);
		}
		renderOrigin(c, info);
		state.detailEl.appendChild(c);
		return c;
	}

	function select(node) {
		if (state.selectedEl) {
			state.selectedEl.classList.remove('automlp-fi-selected');
		}
		state.selectedEl = node;
		node.classList.add('automlp-fi-selected');
	}

	/* ---------- AJAX ---------- */

	function post(fields) {
		var body = new FormData();
		body.append('nonce', cfg.nonce || '');
		if (state.map && state.map.currentLang) {
			body.append('page_lang', String(state.map.currentLang));
		}
		Object.keys(fields).forEach(function (key) {
			body.append(key, fields[key]);
		});
		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (res) {
			return res.json();
		});
	}

	function resolveNode(node) {
		var text = node.getAttribute('data-automlp-fi-text') || normalize(node.textContent);
		if (!text) {
			return;
		}
		var info = originOf(node, text);
		var hint = hintFor(node, text, info);
		select(node);
		renderLoading(text);

		var fields = { action: cfg.action, text: text, hint: hint ? JSON.stringify(hint) : '' };
		if (hint) {
			fields.hint_k = String(hint.k || '');
			HINT_KEYS.forEach(function (key) {
				if (typeof hint[key] !== 'undefined' && hint[key] !== null && hint[key] !== '') {
					fields['hint_' + key] = String(hint[key]);
				}
			});
		}
		post(fields)
			.then(function (json) {
				if (!json || !json.success) {
					renderResult(text, {
						source_type_label: i18n.error || 'Error',
						status_label: (json && json.data && json.data.message) || i18n.error || 'Error'
					});
					return;
				}
				renderResult(text, json.data, info);
			})
			.catch(function () {
				renderResult(text, null);
			});
	}

	/* ---------- untranslated scan ---------- */

	function collectScanItems() {
		var seen = Object.create(null);
		var items = [];
		var nodes = [];
		function add(node, text, isMark) {
			if (!text || seen[text] || !isVisible(node)) {
				return;
			}
			var info = originOf(node, text);
			var hint = hintFor(node, text, info);
			// Same rule as a click: only server-known text with a resolvable hint.
			if (!isMark && (!hint || info.js)) {
				return;
			}
			seen[text] = true;
			items.push({ t: text, h: hint || {} });
			nodes.push(node);
		}
		Array.prototype.forEach.call(document.querySelectorAll('.automlp-fi-mark'), function (node) {
			add(node, node.getAttribute('data-automlp-fi-text'), true);
		});
		// Text rendered inside known containers (Elementor widgets, blocks, …) that
		// has no string-map mark: exactly what a click on it would resolve.
		var texts = (state.ct && state.ct.texts) || null;
		if (texts && document.body) {
			var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null);
			var tn;
			while ((tn = walker.nextNode())) {
				var parent = tn.parentElement;
				if (!parent || isInspectorUi(parent) || /^(SCRIPT|STYLE|NOSCRIPT|TEMPLATE)$/.test(parent.tagName)) {
					continue;
				}
				var text = normalize(tn.nodeValue);
				if (text.length > 1 && texts[text] && !seen[text]) {
					add(parent, text);
				}
			}
		}
		// Likely-untranslated (u=1) and DB-owned (container) items first so they fit the cap.
		var rank = function (h) {
			return (h.u ? 2 : 0) + (h.k === 'elementor' || h.k === 'post_content' || h.k === 'widget' || h.k === 'nav_menu_item' ? 1 : 0);
		};
		var order = items.map(function (item, i) {
			return i;
		}).sort(function (a, b) {
			return rank(items[b].h) - rank(items[a].h) || a - b;
		});
		var limit = parseInt(cfg.scanLimit, 10) || 200;
		order = order.slice(0, limit);
		return {
			items: order.map(function (i) { return items[i]; }),
			nodes: order.map(function (i) { return nodes[i]; }),
			total: items.length,
			limit: limit
		};
	}

	/* ---------- persistence + skeleton ---------- */

	var STORE_KEY = 'automlpFiOpen:' + (cfg.userId || 0);

	function remember(on) {
		try {
			if (on) {
				window.localStorage.setItem(STORE_KEY, '1');
			} else {
				window.localStorage.removeItem(STORE_KEY);
			}
		} catch (e) {
			// Storage blocked: the inspector simply will not persist.
		}
	}

	function remembered() {
		try {
			return window.localStorage.getItem(STORE_KEY) === '1';
		} catch (e) {
			return false;
		}
	}

	function setBusy(busy) {
		if (state.listEl) {
			state.listEl.setAttribute('aria-busy', busy ? 'true' : 'false');
		}
	}

	function skeleton(rows) {
		var wrap = el('div', 'automlp-fi__skeleton');
		wrap.setAttribute('aria-hidden', 'true');
		for (var i = 0; i < rows; i++) {
			wrap.appendChild(el('div', 'automlp-fi__skel-row' + (i === 0 ? ' automlp-fi__skel-row--title' : '')));
		}
		return wrap;
	}

	function showSkeleton() {
		if (!state.listEl) {
			return;
		}
		state.listEl.innerHTML = '';
		setBusy(true);
		state.listEl.appendChild(el('p', 'screen-reader-text', i18n.scanning || 'Checking'));
		state.listEl.appendChild(skeleton(6));
	}

	function runScan() {
		var seq = ++state.scanSeq;
		var scan = collectScanItems();
		state.listEl.innerHTML = '';
		if (!scan.items.length) {
			setBusy(false);
			state.listEl.appendChild(el('p', 'automlp-fi__hint', i18n.scanEmpty || ''));
			return;
		}
		showSkeleton();

		var results = [];
		var size = parseInt(cfg.scanBatch, 10) || 40;
		function next(offset) {
			if (seq !== state.scanSeq || !state.open || offset >= scan.items.length) {
				return;
			}
			post({ action: cfg.scanAction, items: JSON.stringify(scan.items.slice(offset, offset + size)) })
				.then(function (json) {
					if (seq !== state.scanSeq || !state.open) {
						return;
					}
					if (!json || !json.success || !json.data || !Array.isArray(json.data.results)) {
						throw new Error('scan');
					}
					results = results.concat(json.data.results);
					renderScan(scan, results, offset + size >= scan.items.length);
					next(offset + size);
				})
				.catch(function () {
					if (seq === state.scanSeq && state.open) {
						setBusy(false);
						state.listEl.appendChild(el('p', 'automlp-fi__hint', i18n.scanError || 'Error'));
					}
				});
		}
		next(0);
	}

	function renderScan(scan, results, done) {
		var groups = Object.create(null);
		var order = [];
		var count = 0;
		results.forEach(function (res, i) {
			if (!res || !scan.nodes[i]) {
				return;
			}
			var untranslated = res.translated === false || res.found === false;
			if (!untranslated) {
				return;
			}
			var label = res.found === false ? (i18n.notFound || 'Source unknown') : (res.source_type_label || res.source_type || '—');
			if (!groups[label]) {
				groups[label] = [];
				order.push(label);
			}
			groups[label].push({ text: scan.items[i].t, node: scan.nodes[i], res: res });
			scan.nodes[i].classList.add('automlp-fi-listed');
			count++;
		});

		state.listEl.innerHTML = '';
		state.listEl.appendChild(el('p', 'automlp-fi__scan-summary', format(i18n.scanSummary || '%1$d / %2$d', count, results.length)));
		setBusy(!done);
		if (!done) {
			state.listEl.appendChild(skeleton(3));
		}
		if (scan.total > scan.limit) {
			state.listEl.appendChild(el('p', 'automlp-fi__hint', format(i18n.scanCapped || '', scan.limit)));
		}
		if (!count) {
			if (done) {
				state.listEl.appendChild(el('p', 'automlp-fi__hint', i18n.scanEmpty || ''));
			}
			return;
		}
		order.forEach(function (label) {
			var details = el('details', 'automlp-fi__group');
			details.open = true;
			var summary = el('summary', 'automlp-fi__group-title', label + ' (' + groups[label].length + ')');
			details.appendChild(summary);
			var ul = el('ul', 'automlp-fi__items');
			groups[label].forEach(function (entry) {
				var li = el('li', '');
				var btn = el('button', 'automlp-fi__item', entry.text.length > 90 ? entry.text.slice(0, 87) + '…' : entry.text);
				btn.type = 'button';
				btn.title = entry.res.status_label || '';
				btn.addEventListener('click', function () {
					focusEntry(entry);
				});
				li.appendChild(btn);
				ul.appendChild(li);
			});
			details.appendChild(ul);
			state.listEl.appendChild(details);
		});
	}

	function focusEntry(entry) {
		var node = entry.node;
		if (!node.isConnected) {
			return;
		}
		node.scrollIntoView({ behavior: 'smooth', block: 'center' });
		select(node);
		node.classList.remove('automlp-fi-flash');
		void node.offsetWidth; // Restart the flash animation.
		node.classList.add('automlp-fi-flash');
		renderResult(entry.text, entry.res);
	}

	/* ---------- toggle ---------- */

	function onPageClick(event) {
		if (!state.open || isInspectorUi(event.target)) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		var node = event.target;
		while (node && node !== document.body && !(node.classList && node.classList.contains('automlp-fi-mark'))) {
			node = node.parentElement;
		}
		if (!node || node === document.body) {
			node = event.target;
			while (node.children && node.children.length === 1) {
				node = node.children[0];
			}
		}
		if (node && node.nodeType === 1 && !isInspectorUi(node)) {
			resolveNode(node);
		}
	}

	function setAdminBar(open) {
		var link = adminBarLink();
		if (link) {
			link.textContent = open ? (i18n.closeLabel || 'Close') : (i18n.openLabel || 'Text Inspector');
			link.setAttribute('aria-pressed', open ? 'true' : 'false');
		}
	}

	function open() {
		if (state.open || state.busy) {
			return;
		}
		state.busy = true;
		state.open = true;
		buildPanel();
		setAdminBar(true);
		document.addEventListener('click', onPageClick, true);
		remember(true);
		showSkeleton();
		state.detailEl.appendChild(el('p', 'automlp-fi__hint', i18n.loadingMap || 'Loading…'));

		loadMap()
			.then(function (map) {
				state.busy = false;
				if (!state.open) {
					return;
				}
				indexMap(map);
				state.detailEl.innerHTML = '';
				markPage();
				runScan();
			})
			.catch(function () {
				state.busy = false;
				if (state.open) {
					state.detailEl.innerHTML = '';
					if (state.listEl) {
						state.listEl.innerHTML = '';
						setBusy(false);
					}
					state.detailEl.appendChild(el('p', 'automlp-fi__hint', i18n.mapError || 'Error'));
				}
			});
	}

	function close() {
		if (!state.open) {
			return;
		}
		state.open = false;
		state.scanSeq++;
		remember(false);
		document.removeEventListener('click', onPageClick, true);
		unmarkPage();
		if (state.panel) {
			state.panel.remove();
		}
		state.panel = state.detailEl = state.listEl = null;
		document.body.classList.remove('automlp-fi-open');
		setAdminBar(false);
		// Drop ?automlp-inspect=1 from the address bar without reloading.
		var url = new URL(window.location.href);
		if (url.searchParams.has(cfg.queryArg) && window.history && window.history.replaceState) {
			url.searchParams.delete(cfg.queryArg);
			window.history.replaceState(window.history.state, '', url.toString());
		}
	}

	/**
	 * Never run inside a builder / editor canvas iframe (server already skips
	 * known builders; this covers anything it could not detect).
	 */
	function inEditorFrame() {
		if (window === window.top) {
			return false;
		}
		var re = /[?&](elementor-preview|et_fb|fl_builder|ct_builder|oxygen_iframe|vc_editable|breakdance|bricks=run|customize_changeset_uuid)/;
		if (re.test(window.location.search)) {
			return true;
		}
		var cls = (document.body && document.body.className) || '';
		if (/\b(elementor-editor-active|et-fb|fl-builder-edit|wp-customizer|brx-body)\b/.test(cls)) {
			return true;
		}
		try {
			var top = window.top.location;
			return /(post\.php|post-new\.php|site-editor\.php|customize\.php|widgets\.php)/.test(top.pathname) || /action=elementor|et_fb|fl_builder|bricks=run|ct_builder/.test(top.search);
		} catch (e) {
			// Cross-origin parent: treat as an embed and stay out.
			return true;
		}
	}

	function init() {
		if (inEditorFrame()) {
			return;
		}
		var link = adminBarLink();
		if (link) {
			link.setAttribute('role', 'button');
			link.addEventListener('click', function (event) {
				event.preventDefault();
				if (state.open) {
					close();
				} else {
					open();
				}
			});
		}
		// Stay on across reloads / navigation until the admin closes it.
		if (cfg.autoOpen || remembered()) {
			open();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
