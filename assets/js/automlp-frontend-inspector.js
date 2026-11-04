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
	var STRING_KINDS = { gettext: 1, option: 1, theme_mod: 1, term: 1 };
	var PRIORITY = { widget: 10, nav_menu_item: 9, elementor: 8, post_title: 7, post_content: 6, gettext: 5, term: 4, theme_mod: 2, option: 1 };
	var HINT_KEYS = ['d', 'o', 'c', 'n', 'p', 'eid', 'wid', 'id', 'did', 'u', 'ph', 'cd', 'cs', 'sq', 'tax'];

	var state = {
		open: false,
		busy: false,
		map: null,
		byText: null,
		patterns: [],
		panel: null,
		detailEl: null,
		listEl: null,
		selectedEl: null,
		scanSeq: 0,
		ct: null,
		scanEntries: [],
		openKey: null,
		resolveCache: Object.create(null),
		ptrDown: null,
		resizing: false,
		mo: null,
		moPaused: 0,
		onWinResize: null,
		onPtrUp: null,
		onPtrCancel: null,
		onBlur: null
	};

	var WIDTH_KEY = 'automlpFiWidth:' + (cfg.userId || 0);
	var MIN_WIDTH = 280;
	var DEFAULT_WIDTH = 360;

	/* ---------- JS-added text (layer: browser) ---------- */

	var added = window.WeakSet ? new WeakSet() : null;
	var moQueued = null;

	function isOurNode(node) {
		if (!node) {
			return true;
		}
		if (node.nodeType === 3) {
			node = node.parentElement;
		}
		if (!node || node.nodeType !== 1) {
			return true;
		}
		if (node.id === 'automlp-fi' || (node.classList && node.classList.contains('automlp-fi__resize'))) {
			return true;
		}
		return !!(node.closest && (node.closest('#automlp-fi') || node.closest('#wpadminbar')));
	}

	function pauseMo() {
		state.moPaused++;
		if (state.mo && state.moPaused === 1) {
			try {
				state.mo.disconnect();
			} catch (e) {
				// ignore
			}
		}
	}

	function resumeMo() {
		if (state.moPaused > 0) {
			state.moPaused--;
		}
		if (state.mo && state.moPaused === 0 && document.body) {
			try {
				state.mo.observe(document.body, { childList: true, subtree: true, characterData: true });
			} catch (e) {
				// ignore
			}
		}
	}

	function withMoPaused(fn) {
		pauseMo();
		try {
			return fn();
		} finally {
			resumeMo();
		}
	}

	if (added && window.MutationObserver) {
		state.mo = new MutationObserver(function (records) {
			if (state.moPaused || !state.open) {
				return;
			}
			// Throttle bursts (e.g. large panel re-renders) to one microtask.
			if (moQueued) {
				return;
			}
			moQueued = records;
			Promise.resolve().then(function () {
				var batch = moQueued || [];
				moQueued = null;
				if (state.moPaused || !added) {
					return;
				}
				batch.forEach(function (r) {
					if (r.type === 'characterData') {
						if (!isOurNode(r.target)) {
							added.add(r.target);
						}
						return;
					}
					Array.prototype.forEach.call(r.addedNodes, function (n) {
						if (isOurNode(n)) {
							return;
						}
						if (n.nodeType === 3 || n.nodeType === 1) {
							added.add(n);
						}
					});
				});
			});
		});
		state.mo.observe(document.body, { childList: true, subtree: true, characterData: true });
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
		if (!node) {
			return false;
		}
		if (node.nodeType === 3) {
			node = node.parentElement;
		}
		if (!node || node.nodeType !== 1) {
			return false;
		}
		if (node.id === 'automlp-fi' || (node.classList && node.classList.contains('automlp-fi__resize'))) {
			return true;
		}
		return !!(node.closest && (node.closest('#automlp-fi') || node.closest('#wpadminbar')));
	}

	function adminBarLink() {
		var item = cfg.adminBarId ? document.getElementById(cfg.adminBarId) : null;
		return item ? item.querySelector('a') : null;
	}

	function cloneHint(h) {
		if (!h) {
			return null;
		}
		var out = { k: h.k };
		HINT_KEYS.forEach(function (key) {
			if (typeof h[key] !== 'undefined' && h[key] !== null && h[key] !== '') {
				out[key] = h[key];
			}
		});
		return out;
	}

	function fieldsForHint(text, hint) {
		var fields = { action: cfg.action, text: text, hint: hint ? JSON.stringify(hint) : '' };
		if (hint) {
			fields.hint_k = String(hint.k || '');
			HINT_KEYS.forEach(function (key) {
				if (typeof hint[key] !== 'undefined' && hint[key] !== null && hint[key] !== '') {
					fields['hint_' + key] = String(hint[key]);
				}
			});
		}
		return fields;
	}

	function domainsFromChain(chain) {
		var out = [];
		(chain || []).forEach(function (c) {
			// Skip coarse template regions (archive/header); keep tight render containers only.
			if (c && c.td && c.type && c.type !== 'template') {
				out.push(String(c.td));
			}
		});
		return out;
	}

	function gettextScore(h, ctxDomains, maxSq) {
		var s = 0;
		if (!h || h.k !== 'gettext') {
			return 0;
		}
		// Prefer strings recorded while the template was rendering over init/registration.
		if (h.ph === 'r' || h.ph === 1 || h.ph === '1') {
			s += 100;
		}
		// Caller file's text domain matches the gettext domain.
		if (h.cd && h.d && String(h.cd) === String(h.d)) {
			s += 50;
		}
		// Theme callers commonly override plugin strings via filters â prefer theme when tied.
		if (h.cs === 't') {
			s += 25;
		}
		// Tight rendering container (elementor/shortcode/block/widget) text domain matches.
		if (h.d && ctxDomains && ctxDomains.indexOf(String(h.d)) !== -1) {
			s += 80;
		}
		// Later gettext for the same visible text wins (filter overrides, actual echo path).
		var sq = parseInt(h.sq, 10) || 0;
		if (maxSq > 0 && sq > 0) {
			s += Math.floor((40 * sq) / maxSq);
		}
		return s;
	}
	function pickHint(list, ctxDomains) {
		if (!list || !list.length) {
			return null;
		}
		var maxSq = 0;
		for (var m = 0; m < list.length; m++) {
			if (list[m].k === 'gettext') {
				var q = parseInt(list[m].sq, 10) || 0;
				if (q > maxSq) {
					maxSq = q;
				}
			}
		}
		var best = null;
		var bestP = -1;
		var bestS = -1;
		for (var i = 0; i < list.length; i++) {
			var h = list[i];
			var p = PRIORITY[h.k] || 0;
			var s = h.k === 'gettext' ? gettextScore(h, ctxDomains, maxSq) : 0;
			if (!best || p > bestP || (p === bestP && s > bestS)) {
				best = h;
				bestP = p;
				bestS = s;
			}
		}
		if (!best) {
			return null;
		}
		// Ambiguous gettext: same kind-priority band and equal generic score -> keep all.
		if (best.k === 'gettext') {
			var alts = [];
			for (var j = 0; j < list.length; j++) {
				var o = list[j];
				if (o.k !== 'gettext') {
					continue;
				}
				if (String(o.d || '') === String(best.d || '') && String(o.o || '') === String(best.o || '') && String(o.c || '') === String(best.c || '')) {
					continue;
				}
				if (gettextScore(o, ctxDomains, maxSq) === bestS) {
					alts.push(o);
				}
			}
			if (alts.length) {
				var out = cloneHint(best);
				out.alts = alts;
				return out;
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
		info = info || originOf(node, text);
		var matched = matchNode(node, text);
		var key = matched.text || text;
		var ctx = domainsFromChain(info.chain);
		// Tight containers only (region is always a coarse template - skip).
		if (info.region && info.region.td && info.region.type && info.region.type !== 'template' && ctx.indexOf(String(info.region.td)) === -1) {
			ctx.push(String(info.region.td));
		}
		// Re-rank from the live map with container/phase signals (ignore stale mark hint).
		var list = state.byText && state.byText[key];
		var hint = pickHint(list, ctx);
		var fromChain = hintFromChain(info.chain);
		// A string hit (gettext / Admin Text / theme_mod / term) always wins; containers stay in "Rendered by".
		if (hint && STRING_KINDS[hint.k]) {
			return hint;
		}
		// Same as click: sprintf/HTML gettext matched via patterns when exact map key differs.
		if (matched.hint && STRING_KINDS[matched.hint.k]) {
			return matched.hint;
		}
		if (!hint && matched.hint) {
			hint = matched.hint;
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
		var patterns = [];
		(Array.isArray(map.patterns) ? map.patterns : []).forEach(function (entry) {
			if (!entry || !entry.re) {
				return;
			}
			try {
				patterns.push({
					re: new RegExp('^' + entry.re + '$'),
					hint: entry
				});
			} catch (e) {
				// Skip a bad pattern rather than breaking the inspector.
			}
		});
		state.map = map;
		state.byText = byText;
		state.patterns = patterns;
	}

	function blockOf(node) {
		if (!node || !node.closest) {
			return node;
		}
		return node.closest('p,li,td,th,h1,h2,h3,h4,h5,h6,blockquote,label,button,figcaption,dt,dd,summary,legend,article,section') || node;
	}

	function matchPattern(text) {
		if (!text || !state.patterns || !state.patterns.length) {
			return null;
		}
		for (var i = 0; i < state.patterns.length; i++) {
			var item = state.patterns[i];
			if (item.re.test(text)) {
				return item.hint;
			}
		}
		return null;
	}

	/**
	 * Resolve visible text + hint for a node: exact map, then ancestor block, then patterns.
	 */
	function matchNode(node, preferredText) {
		// <select> textContent concatenates every option — never treat that as one string.
		if (node && node.tagName === 'SELECT' && !preferredText) {
			var selected = node.options && node.options[node.selectedIndex];
			if (selected) {
				var selText = normalize(selected.getAttribute('data-automlp-fi-text') || selected.label || selected.text || selected.textContent || '');
				if (selText) {
					return matchNode(selected, selText);
				}
			}
			return { text: '', hint: null, node: node };
		}
		var text = preferredText || (node && node.getAttribute && node.getAttribute('data-automlp-fi-text')) || normalize(node && node.textContent);
		var stored = node && hintOf(node);
		var list = state.byText && state.byText[text];
		var ctx = [];
		try {
			var info0 = originOf(node, text);
			ctx = domainsFromChain(info0.chain);
			if (info0.region && info0.region.td && info0.region.type && info0.region.type !== 'template' && ctx.indexOf(String(info0.region.td)) === -1) {
				ctx.push(String(info0.region.td));
			}
		} catch (e) {
			ctx = [];
		}
		var hint = (list && list.length > 1) ? pickHint(list, ctx) : (stored || pickHint(list, ctx));
		if (hint) {
			return { text: text, hint: hint, node: node };
		}
		var block = blockOf(node);
		var blockText = normalize(block && block.textContent);
		if (blockText && blockText !== text) {
			hint = pickHint(state.byText && state.byText[blockText], ctx);
			if (hint) {
				return { text: blockText, hint: hint, node: block };
			}
			hint = matchPattern(blockText);
			if (hint) {
				return { text: blockText, hint: hint, node: block };
			}
		}
		hint = matchPattern(text);
		if (hint) {
			return { text: text, hint: hint, node: node };
		}
		return { text: text, hint: null, node: node };
	}

	/* ---------- marking ---------- */

	function applyMark(el, text, hint) {
		if (!el || !el.classList || el.classList.contains('automlp-fi-mark')) {
			return false;
		}
		el.classList.add('automlp-fi-mark');
		el.setAttribute('data-automlp-fi-text', text);
		if (hint) {
			el.setAttribute('data-automlp-fi-hint', JSON.stringify(hint));
			if (hint.u) {
				el.classList.add('automlp-fi-untranslated');
			}
		}
		return true;
	}

	function markPage() {
		withMoPaused(function () {
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
			var text = normalize(node.nodeValue);
			var hint = pickHint(byText[text]);
			if (applyMark(parent, text, hint)) {
				marked++;
			}
		}
		// Block-level: HTML/placeholder gettext renders across multiple text nodes.
		var blocks = document.body.querySelectorAll('p,li,td,th,h1,h2,h3,h4,h5,h6,blockquote,label,button,figcaption,dt,dd,summary,legend');
		for (var i = 0; i < blocks.length && marked <= 2000; i++) {
			var el = blocks[i];
			if (isInspectorUi(el) || el.classList.contains('automlp-fi-mark')) {
				continue;
			}
			var btext = normalize(el.textContent);
			if (btext.length < 12) {
				continue;
			}
			var bhint = pickHint(byText[btext]) || matchPattern(btext);
			if (bhint && applyMark(el, btext, bhint)) {
				marked++;
			}
		}
			// Form-control / attribute texts.
			var controls = document.body.querySelectorAll('select, option, optgroup, input, textarea, button, [placeholder], [title], [aria-label]');
			for (var c = 0; c < controls.length && marked <= 2000; c++) {
				var ctrl = controls[c];
				if (isInspectorUi(ctrl)) {
					continue;
				}
				if (ctrl.tagName === 'OPTION') {
					var otext = normalize(ctrl.getAttribute('data-automlp-fi-text') || ctrl.label || ctrl.text || ctrl.textContent || '');
					if (otext.length > 1 && byText[otext] && applyMark(ctrl, otext, pickHint(byText[otext]))) {
						marked++;
					}
					continue;
				}
				var attrs = attrCandidates(ctrl);
				for (var a = 0; a < attrs.length && marked <= 2000; a++) {
					var at = attrs[a];
					if (!byText[at.text]) {
						continue;
					}
					if (ctrl.classList.contains('automlp-fi-mark')) {
						break;
					}
					if (applyMark(ctrl, at.text, pickHint(byText[at.text]))) {
						marked++;
					}
					break;
				}
			}
		});
	}

	function unmarkPage() {
		withMoPaused(function () {
		var nodes = document.querySelectorAll('.automlp-fi-mark, .automlp-fi-selected, .automlp-fi-flash, .automlp-fi-listed');
		Array.prototype.forEach.call(nodes, function (node) {
			node.classList.remove('automlp-fi-mark', 'automlp-fi-untranslated', 'automlp-fi-selected', 'automlp-fi-flash', 'automlp-fi-listed');
			node.removeAttribute('data-automlp-fi-text');
			node.removeAttribute('data-automlp-fi-hint');
		});
		state.selectedEl = null;
		});
	}

	/**
	 * Element used for visibility checks. <option>/<optgroup> have no box when
	 * the dropdown is closed; use their owning <select> instead.
	 */
	function visibilityProbe(node) {
		if (!node || node.nodeType !== 1) {
			return null;
		}
		var tag = node.tagName;
		if (tag === 'OPTION' || tag === 'OPTGROUP') {
			return node.closest ? (node.closest('select') || node.parentElement) : node.parentElement;
		}
		return node;
	}

	function isVisible(node) {
		var probe = visibilityProbe(node) || node;
		if (!probe || !probe.getClientRects) {
			return false;
		}
		if (!probe.getClientRects().length) {
			return false;
		}
		var style = window.getComputedStyle(probe);
		return style.visibility !== 'hidden' && style.display !== 'none';
	}

	/**
	 * Visible attribute texts on form controls / labeled elements (generic).
	 * Skips free-text input values so typed user content is never marked.
	 */
	function attrCandidates(el) {
		var out = [];
		if (!el || !el.getAttribute) {
			return out;
		}
		var tag = el.tagName;
		var attrs = ['placeholder', 'title', 'aria-label'];
		if (tag === 'OPTGROUP') {
			attrs = ['label', 'title', 'aria-label'];
		}
		attrs.forEach(function (name) {
			var v = normalize(el.getAttribute(name));
			if (v.length > 1) {
				out.push({ node: el, text: v, attr: name });
			}
		});
		if (tag === 'INPUT') {
			var type = String(el.getAttribute('type') || 'text').toLowerCase();
			if (/^(button|submit|reset)$/.test(type)) {
				var val = normalize(el.getAttribute('value') || el.value || '');
				if (val.length > 1) {
					out.push({ node: el, text: val, attr: 'value' });
				}
			}
		}
		return out;
	}

	/**
	 * Option / optgroup entries inside a <select> (each is its own string).
	 */
	function selectEntries(select) {
		var out = [];
		if (!select || select.tagName !== 'SELECT') {
			return out;
		}
		Array.prototype.forEach.call(select.querySelectorAll('optgroup'), function (g) {
			var label = normalize(g.getAttribute('data-automlp-fi-text') || g.getAttribute('label') || '');
			if (label.length > 1) {
				out.push({ node: g, text: label, selected: false });
			}
		});
		Array.prototype.forEach.call(select.options || [], function (opt) {
			var text = normalize(opt.getAttribute('data-automlp-fi-text') || opt.label || opt.text || opt.textContent || '');
			if (text.length > 1) {
				out.push({ node: opt, text: text, selected: !!opt.selected });
			}
		});
		attrCandidates(select).forEach(function (a) {
			out.unshift({ node: select, text: a.text, selected: false, attr: a.attr });
		});
		return out;
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
		initResize(panel);
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


	function entryKey(text, res) {
		var r = res || {};
		return String(text || '') + '\0' + String(r.source_type || '') + '\0' + String(r.fix_url || '');
	}

	function fillResultFields(c, data, info) {
		if (!data) {
			c.appendChild(el('p', 'automlp-fi__value', i18n.error || 'Error'));
			return;
		}
		if (!data.found && info) {
			if (info.js) {
				data.source_type_label = i18n.byJs || 'Added by JavaScript';
				data.status_label = i18n.byJsStatus || '';
				data.translated = null;
			} else if (info.chain && info.chain.length) {
				data.source_type_label = info.chain[0].label || data.source_type_label;
				data.status_label = i18n.containerOnly || data.status_label;
				data.translated = null;
			}
		}
		c.appendChild(el('span', 'automlp-fi__label', i18n.source || 'Source'));
		c.appendChild(el('p', 'automlp-fi__value', data.source_type_label || data.source_type || '\u2014'));

		c.appendChild(el('span', 'automlp-fi__label', i18n.status || 'Status'));
		var statusWrap = el('p', 'automlp-fi__value');
		var badge = el('span', 'automlp-fi__badge', data.status_label || '\u2014');
		if (data.translated === false) {
			badge.classList.add('is-bad');
		} else if (data.translated === true) {
			badge.classList.add('is-ok');
		}
		statusWrap.appendChild(badge);
		c.appendChild(statusWrap);

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
		if (data.alternates && data.alternates.length) {
			c.appendChild(el('span', 'automlp-fi__label', i18n.alsoPossible || 'Also possible'));
			data.alternates.forEach(function (alt) {
				var row = el('div', 'automlp-fi__alt');
				var label = (alt.source_type_label || alt.source_type || '') + '';
				if (alt.identifiers && alt.identifiers.domain) {
					label = label || String(alt.identifiers.domain);
				}
				row.appendChild(el('p', 'automlp-fi__value', label || '\u2014'));
				if (alt.status_label) {
					var b = el('span', 'automlp-fi__badge', alt.status_label);
					if (alt.translated === false) {
						b.classList.add('is-bad');
					} else if (alt.translated === true) {
						b.classList.add('is-ok');
					}
					row.appendChild(b);
				}
				if (alt.fix_url) {
					var alink = el('a', 'automlp-fi__fix', alt.fix_label || i18n.openFix || 'Open');
					alink.href = alt.fix_url;
					alink.target = '_blank';
					alink.rel = 'noopener noreferrer';
					row.appendChild(alink);
				}
				c.appendChild(row);
			});
		}
		renderOrigin(c, info);
	}

	function renderResult(text, data, info) {
		if (!state.detailEl) {
			return null;
		}
		state.detailEl.innerHTML = '';
		var c = card(text);
		fillResultFields(c, data, info);
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


	function resolveOne(target, text, hint, info) {
		var alts = (hint && hint.alts) ? hint.alts.slice() : [];
		var primary = hint ? cloneHint(hint) : null;
		if (primary && primary.alts) {
			delete primary.alts;
		}
		var jobs = [post(fieldsForHint(text, primary))];
		alts.forEach(function (a) {
			jobs.push(post(fieldsForHint(text, a)));
		});
		return Promise.all(jobs).then(function (results) {
			var json = results[0];
			if (!json || !json.success) {
				return {
					text: text,
					node: target,
					info: info,
					data: {
						source_type_label: i18n.error || 'Error',
						status_label: (json && json.data && json.data.message) || i18n.error || 'Error'
					}
				};
			}
			var data = json.data || {};
			var altData = [];
			for (var i = 1; i < results.length; i++) {
				if (results[i] && results[i].success && results[i].data) {
					altData.push(results[i].data);
				}
			}
			if (altData.length) {
				data.alternates = altData;
			}
			return { text: text, node: target, info: info, data: data };
		});
	}

	/**
	 * Clicking a <select> resolves each option (and labeled attrs) separately.
	 */
	function resolveSelect(selectEl) {
		var entries = selectEntries(selectEl);
		if (!entries.length) {
			return;
		}
		select(selectEl);
		renderLoading(i18n.controlOptions || 'Options in this control');

		var jobs = entries.map(function (entry) {
			var info = originOf(entry.node, entry.text);
			var hint = hintFor(entry.node, entry.text, info) || hintOf(entry.node) || pickHint(state.byText && state.byText[entry.text]);
			return resolveOne(entry.node, entry.text, hint, info).then(function (row) {
				row.selected = !!entry.selected;
				row.attr = entry.attr || '';
				return row;
			});
		});
		Promise.all(jobs)
			.then(function (rows) {
				renderControlResults(rows);
			})
			.catch(function () {
				renderResult(i18n.controlOptions || 'Options in this control', null);
			});
	}

	function renderControlResults(rows) {
		if (!state.detailEl) {
			return;
		}
		state.detailEl.innerHTML = '';
		state.detailEl.appendChild(el('span', 'automlp-fi__label', i18n.controlOptions || 'Options in this control'));
		var ordered = rows.slice().sort(function (a, b) {
			return (b.selected ? 1 : 0) - (a.selected ? 1 : 0);
		});
		ordered.forEach(function (row) {
			var wrap = el('div', 'automlp-fi__control-item' + (row.selected ? ' is-selected' : ''));
			var c = card(row.text);
			fillResultFields(c, row.data, row.info);
			wrap.appendChild(c);
			wrap.addEventListener('click', function (ev) {
				if (ev.target && ev.target.closest && ev.target.closest('a')) {
					return;
				}
				focusEntry({ node: row.node, text: row.text, res: row.data });
			});
			state.detailEl.appendChild(wrap);
		});
	}

	/**
	 * Multi-card for a container that has several marked descendants (empty-space click).
	 */
	function renderMarkedChildren(container, marks) {
		if (!state.detailEl || !marks || !marks.length) {
			return;
		}
		// Do not highlight the whole container (would look like concatenated selection).
		clearPageSelection();
		if (state.selectedEl) {
			state.selectedEl.classList.remove('automlp-fi-selected');
			state.selectedEl = null;
		}
		state.detailEl.innerHTML = '';
		state.detailEl.appendChild(el('span', 'automlp-fi__label', (i18n.containsTexts || 'Contains %d texts').replace('%d', String(marks.length))));
		marks.forEach(function (mark) {
			var text = mark.getAttribute('data-automlp-fi-text') || normalize(mark.textContent);
			var wrap = el('div', 'automlp-fi__control-item');
			var c = card(text);
			c.appendChild(el('p', 'automlp-fi__hint', i18n.clickToResolve || 'Click to resolve'));
			wrap.appendChild(c);
			wrap.addEventListener('click', function (ev) {
				if (ev.target && ev.target.closest && ev.target.closest('a')) {
					return;
				}
				resolveNode(mark);
			});
			state.detailEl.appendChild(wrap);
		});
	}

	function resolveNode(node) {
		if (!node) {
			return;
		}
		if (node.tagName === 'SELECT') {
			resolveSelect(node);
			return;
		}
		if (node.tagName === 'OPTGROUP') {
			var owner = node.closest ? node.closest('select') : null;
			if (owner) {
				resolveSelect(owner);
				return;
			}
		}
		if ((node.tagName === 'INPUT' || node.tagName === 'TEXTAREA') && !node.getAttribute('data-automlp-fi-text')) {
			var attrs = attrCandidates(node);
			for (var i = 0; i < attrs.length; i++) {
				if (state.byText && state.byText[attrs[i].text]) {
					node = attrs[i].node;
					node.setAttribute('data-automlp-fi-text', attrs[i].text);
					break;
				}
			}
		}
		var matched = matchNode(node);
		var text = matched.text;
		var target = matched.node || node;
		if (!text) {
			return;
		}
		var info = originOf(target, text);
		var hint = hintFor(target, text, info) || matched.hint;
		var highlight = visibilityProbe(target) || target;
		select(highlight);
		if ((target.tagName === 'OPTION' || target.tagName === 'OPTGROUP') && target !== highlight) {
			target.classList.add('automlp-fi-selected');
		}

		// Listed untranslated: expand that sidebar accordion instead of the detail panel.
		var listed = findScanEntry(target, text);
		if (listed) {
			if (state.detailEl) {
				state.detailEl.innerHTML = '';
			}
			expandScanEntry(listed, true);
			return;
		}

		var cacheKey = String(text || '') + '\0' + String((hint && hint.k) || '') + '\0' + String((hint && hint.id) || '');
		if (state.resolveCache[cacheKey]) {
			renderResult(text, state.resolveCache[cacheKey], info);
			return;
		}
		renderLoading(text);

		resolveOne(target, text, hint, info)
			.then(function (row) {
				state.resolveCache[cacheKey] = row.data;
				renderResult(row.text, row.data, row.info);
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
			var matched = matchNode(node, text);
			var key = matched.text || text;
			if (seen[key]) {
				return;
			}
			var target = matched.node || node;
			var info = originOf(target, key);
			var hint = hintFor(target, key, info);
			// Same rule as a click: only server-known text with a resolvable hint.
			if (!hint || info.js) {
				return;
			}
			seen[text] = true;
			seen[key] = true;
			items.push({ t: key, h: hint });
			nodes.push(target);
		}
		Array.prototype.forEach.call(document.querySelectorAll('.automlp-fi-mark'), function (node) {
			add(node, node.getAttribute('data-automlp-fi-text'), true);
		});
		// Block-level pattern hits that were marked on the <p>/<li> itself.
		Array.prototype.forEach.call(document.querySelectorAll('.automlp-fi-mark[data-automlp-fi-hint]'), function (node) {
			var t = node.getAttribute('data-automlp-fi-text');
			if (t && t.length > 40) {
				add(node, t, true);
			}
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

	/* ---------- resize + persistence + skeleton ---------- */

	function clampWidth(width) {
		var max = Math.max(MIN_WIDTH, Math.floor(window.innerWidth * 0.6));
		return Math.round(Math.min(Math.max(width, MIN_WIDTH), max));
	}

	function setPanelWidth(width) {
		var w = clampWidth(width);
		document.documentElement.style.setProperty('--automlp-fi-width', w + 'px');
		return w;
	}

	function readSavedWidth() {
		try {
			var saved = parseInt(window.localStorage.getItem(WIDTH_KEY), 10);
			if (saved) {
				return saved;
			}
		} catch (e) {
			// Storage blocked; keep the default width.
		}
		return DEFAULT_WIDTH;
	}

	function persistWidth() {
		try {
			var raw = document.documentElement.style.getPropertyValue('--automlp-fi-width');
			var n = parseInt(raw, 10);
			if (n) {
				window.localStorage.setItem(WIDTH_KEY, String(n));
			}
		} catch (e) {
			// Storage blocked; the width just won't be remembered.
		}
	}

	function endResize(handle) {
		if (!state.resizing) {
			document.body.classList.remove('automlp-fi-resizing');
			return;
		}
		state.resizing = false;
		document.body.classList.remove('automlp-fi-resizing');
		persistWidth();
		if (handle && handle.releasePointerCapture) {
			try {
				// release all possible captures; ignore if none
				if (typeof handle.hasPointerCapture === 'function') {
					// no-op helper — callers pass pointerId when known
				}
			} catch (e) {
				// ignore
			}
		}
	}

	/**
	 * Drag the left edge to resize; panel + body margin both use --automlp-fi-width.
	 * Always tear down on pointerup / pointercancel / blur / lostpointercapture.
	 */
	function initResize(panel) {
		var handle = el('button', 'automlp-fi__resize');
		handle.type = 'button';
		handle.setAttribute('aria-label', i18n.resizeHandle || 'Drag to resize panel');
		handle.setAttribute('aria-orientation', 'vertical');
		handle.setAttribute('aria-valuemin', String(MIN_WIDTH));
		handle.setAttribute('role', 'separator');
		panel.appendChild(handle);

		var applied = setPanelWidth(readSavedWidth());
		handle.setAttribute('aria-valuenow', String(applied));
		handle.setAttribute('aria-valuemax', String(Math.max(MIN_WIDTH, Math.floor(window.innerWidth * 0.6))));

		var activePointerId = null;

		function finish(event) {
			if (activePointerId !== null && handle.releasePointerCapture) {
				try {
					if (handle.hasPointerCapture(activePointerId)) {
						handle.releasePointerCapture(activePointerId);
					}
				} catch (e) {
					// ignore
				}
			}
			activePointerId = null;
			endResize(handle);
			if (event && event.pointerId !== undefined) {
				// ensure class cleared even if lostpointercapture already ran
				document.body.classList.remove('automlp-fi-resizing');
			}
		}

		handle.addEventListener('pointerdown', function (event) {
			if (event.button !== 0 && event.pointerType === 'mouse') {
				return;
			}
			// Only start resize from the handle itself.
			if (event.target !== handle && !(handle.contains && handle.contains(event.target))) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			activePointerId = event.pointerId;
			state.resizing = true;
			document.body.classList.add('automlp-fi-resizing');
			try {
				handle.setPointerCapture(event.pointerId);
			} catch (e) {
				// Capture may fail; window listeners still finish the gesture.
			}
			clearPageSelection();
		});

		handle.addEventListener('pointermove', function (event) {
			if (!state.resizing) {
				return;
			}
			if (activePointerId !== null && event.pointerId !== activePointerId) {
				return;
			}
			var w = setPanelWidth(window.innerWidth - event.clientX);
			handle.setAttribute('aria-valuenow', String(w));
		});

		handle.addEventListener('lostpointercapture', function () {
			activePointerId = null;
			endResize(handle);
		});

		handle.addEventListener('pointerup', finish);
		handle.addEventListener('pointercancel', finish);

		state.onPtrUp = function (event) {
			if (!state.resizing) {
				return;
			}
			if (activePointerId !== null && event.pointerId !== activePointerId) {
				return;
			}
			finish(event);
		};
		state.onPtrCancel = state.onPtrUp;
		state.onBlur = function () {
			if (state.resizing) {
				finish(null);
			}
		};
		window.addEventListener('pointerup', state.onPtrUp, true);
		window.addEventListener('pointercancel', state.onPtrCancel, true);
		window.addEventListener('blur', state.onBlur);

		handle.addEventListener('keydown', function (event) {
			var step = event.shiftKey ? 40 : 16;
			var cur = parseInt(document.documentElement.style.getPropertyValue('--automlp-fi-width'), 10) || DEFAULT_WIDTH;
			var next = cur;
			if (event.key === 'ArrowLeft' || event.key === 'Left') {
				next = cur + step;
			} else if (event.key === 'ArrowRight' || event.key === 'Right') {
				next = cur - step;
			} else if (event.key === 'Home') {
				next = MIN_WIDTH;
			} else if (event.key === 'End') {
				next = Math.floor(window.innerWidth * 0.6);
			} else {
				return;
			}
			event.preventDefault();
			var w = setPanelWidth(next);
			handle.setAttribute('aria-valuenow', String(w));
			persistWidth();
		});

		state.onWinResize = function () {
			if (!state.open) {
				return;
			}
			var cur = parseInt(document.documentElement.style.getPropertyValue('--automlp-fi-width'), 10) || DEFAULT_WIDTH;
			var w = setPanelWidth(cur);
			handle.setAttribute('aria-valuenow', String(w));
			handle.setAttribute('aria-valuemax', String(Math.max(MIN_WIDTH, Math.floor(window.innerWidth * 0.6))));
		};
		window.addEventListener('resize', state.onWinResize);
	}

	function clearPageSelection() {
		try {
			var sel = window.getSelection && window.getSelection();
			if (sel && sel.removeAllRanges) {
				sel.removeAllRanges();
			}
		} catch (e) {
			// ignore
		}
	}

	function isEditableTarget(node) {
		if (!node || node.nodeType !== 1) {
			return false;
		}
		var tag = node.tagName;
		if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'OPTION') {
			return true;
		}
		if (node.isContentEditable) {
			return true;
		}
		return !!(node.closest && node.closest('input, textarea, select, [contenteditable="true"]'));
	}

	function selectionIsDrag() {
		try {
			var sel = window.getSelection && window.getSelection();
			if (!sel || sel.isCollapsed || !sel.toString()) {
				return false;
			}
			return sel.toString().length > 0;
		} catch (e) {
			return false;
		}
	}

	function onPagePointerDown(event) {
		if (!state.open || isInspectorUi(event.target)) {
			state.ptrDown = null;
			return;
		}
		if (event.button !== 0 && event.pointerType === 'mouse') {
			state.ptrDown = null;
			return;
		}
		state.ptrDown = {
			x: event.clientX,
			y: event.clientY,
			id: event.pointerId,
			t: Date.now()
		};
		// Inspect-mode: do not start a native text selection on the page.
		if (!isEditableTarget(event.target)) {
			event.preventDefault();
			clearPageSelection();
		}
	}

	function pointerMoved(event) {
		var d = state.ptrDown;
		if (!d) {
			return false;
		}
		var dx = Math.abs((event.clientX || 0) - d.x);
		var dy = Math.abs((event.clientY || 0) - d.y);
		return (dx + dy) > 6;
	}

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
			var untranslated = res.found !== false && res.translated === false;
			if (!untranslated) {
				return;
			}
			var label = res.source_type_label || res.source_type || '—';
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
		state.scanEntries = [];
		var prevOpen = state.openKey;
		order.forEach(function (label) {
			var details = el('details', 'automlp-fi__group');
			details.open = true;
			var summary = el('summary', 'automlp-fi__group-title', label + ' (' + groups[label].length + ')');
			details.appendChild(summary);
			var ul = el('ul', 'automlp-fi__items');
			groups[label].forEach(function (entry, idx) {
				var key = entryKey(entry.text, entry.res) + '\0' + label + '\0' + String(idx);
				var panelId = 'automlp-fi-inline-' + state.scanEntries.length;
				var li = el('li', 'automlp-fi__item-wrap');
				var btn = el('button', 'automlp-fi__item', entry.text.length > 90 ? entry.text.slice(0, 87) + '\u2026' : entry.text);
				btn.type = 'button';
				btn.title = entry.res.status_label || '';
				btn.setAttribute('aria-expanded', 'false');
				btn.setAttribute('aria-controls', panelId);
				var panel = el('div', 'automlp-fi__inline');
				panel.id = panelId;
				panel.hidden = true;
				var row = {
					key: key,
					text: entry.text,
					node: entry.node,
					res: entry.res,
					btn: btn,
					panel: panel,
					li: li,
					info: null
				};
				state.resolveCache[entryKey(entry.text, entry.res)] = entry.res;
				state.scanEntries.push(row);
				btn.addEventListener('click', function () {
					focusEntry(row);
				});
				li.appendChild(btn);
				li.appendChild(panel);
				ul.appendChild(li);
			});
			details.appendChild(ul);
			state.listEl.appendChild(details);
		});
		if (prevOpen) {
			var keep = null;
			for (var si = 0; si < state.scanEntries.length; si++) {
				if (state.scanEntries[si].key === prevOpen) {
					keep = state.scanEntries[si];
					break;
				}
			}
			if (keep) {
				expandScanEntry(keep, false);
			} else {
				state.openKey = null;
			}
		}
	}

	function collapseScanEntry(row) {
		if (!row) {
			return;
		}
		row.btn.setAttribute('aria-expanded', 'false');
		row.btn.classList.remove('is-open');
		row.panel.hidden = true;
		row.panel.innerHTML = '';
		if (state.openKey === row.key) {
			state.openKey = null;
		}
	}

	function collapseAllScanEntries() {
		state.scanEntries.forEach(collapseScanEntry);
		state.openKey = null;
	}

	function renderInlineBody(row, data, info) {
		row.panel.innerHTML = '';
		var c = el('div', 'automlp-fi__card automlp-fi__card--inline');
		fillResultFields(c, data, info);
		row.panel.appendChild(c);
	}

	function expandScanEntry(row, fromPage) {
		if (!row || !row.btn) {
			return;
		}
		var wasOpen = state.openKey === row.key;
		if (wasOpen && !fromPage) {
			collapseScanEntry(row);
			return;
		}
		collapseAllScanEntries();
		state.openKey = row.key;
		row.btn.setAttribute('aria-expanded', 'true');
		row.btn.classList.add('is-open');
		row.panel.hidden = false;

		var info = row.info || originOf(row.node, row.text);
		row.info = info;
		var cacheKey = entryKey(row.text, row.res);
		var cached = state.resolveCache[cacheKey] || row.res;
		if (cached) {
			renderInlineBody(row, cached, info);
		} else {
			row.panel.innerHTML = '';
			row.panel.appendChild(skeleton(3));
			resolveOne(row.node, row.text, hintFor(row.node, row.text, info), info)
				.then(function (got) {
					if (state.openKey !== row.key) {
						return;
					}
					state.resolveCache[cacheKey] = got.data;
					row.res = got.data;
					renderInlineBody(row, got.data, got.info || info);
				})
				.catch(function () {
					if (state.openKey === row.key) {
						renderInlineBody(row, null, info);
					}
				});
		}

		try {
			row.li.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		} catch (e) {
			// Older browsers: skip.
		}

		var node = row.node;
		if (node && node.isConnected) {
			var probe = visibilityProbe(node) || node;
			if (fromPage) {
				// Page click already scrolled; keep highlight.
			} else {
				probe.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
			select(probe);
			if (node !== probe) {
				node.classList.add('automlp-fi-selected');
			}
			probe.classList.remove('automlp-fi-flash');
			void probe.offsetWidth;
			probe.classList.add('automlp-fi-flash');
		}
	}

	function findScanEntry(node, text) {
		var t = normalize(text || '');
		for (var i = 0; i < state.scanEntries.length; i++) {
			var row = state.scanEntries[i];
			if (!row.node || !row.node.isConnected) {
				continue;
			}
			if (row.node === node || (t && normalize(row.text) === t && (row.node.contains(node) || node.contains(row.node)))) {
				return row;
			}
		}
		// Soft match: same text mark on listed node.
		if (t) {
			for (var j = 0; j < state.scanEntries.length; j++) {
				if (normalize(state.scanEntries[j].text) === t) {
					return state.scanEntries[j];
				}
			}
		}
		return null;
	}

	function focusEntry(entry) {
		if (state.detailEl) {
			state.detailEl.innerHTML = '';
		}
		expandScanEntry(entry, false);
	}

	/* ---------- toggle ---------- */

	/**
	 * True when the element is a layout/container with multiple marked descendants
	 * and no single direct resolvable text of its own at the click point.
	 */
	function isBulkContainer(node) {
		if (!node || node.nodeType !== 1 || isInspectorUi(node)) {
			return false;
		}
		// Descendant marks only (querySelectorAll skips the node itself).
		// A marked wrapper that only groups child texts is still a bulk container.
		var marks = node.querySelectorAll ? node.querySelectorAll('.automlp-fi-mark') : [];
		return marks && marks.length >= 2;
	}

	function textNodeFromPoint(x, y) {
		try {
			if (document.caretRangeFromPoint) {
				var range = document.caretRangeFromPoint(x, y);
				if (range && range.startContainer && range.startContainer.nodeType === 3) {
					return range.startContainer;
				}
			}
			if (document.caretPositionFromPoint) {
				var pos = document.caretPositionFromPoint(x, y);
				if (pos && pos.offsetNode && pos.offsetNode.nodeType === 3) {
					return pos.offsetNode;
				}
			}
		} catch (e) {
			// ignore
		}
		return null;
	}

	/**
	 * Deepest non-bulk .automlp-fi-mark under the pointer (pierces stretched link overlays).
	 */
	function leafMarkFromPoint(x, y) {
		var stack = [];
		try {
			if (document.elementsFromPoint) {
				stack = document.elementsFromPoint(x, y) || [];
			} else {
				var top = document.elementFromPoint(x, y);
				if (top) {
					stack = [top];
				}
			}
		} catch (e) {
			stack = [];
		}
		for (var i = 0; i < stack.length; i++) {
			var el = stack[i];
			if (!el || isInspectorUi(el)) {
				continue;
			}
			if (el.classList && el.classList.contains('automlp-fi-mark') && !isBulkContainer(el)) {
				return el;
			}
			var inner = el.closest ? el.closest('.automlp-fi-mark') : null;
			if (inner && !isInspectorUi(inner) && !isBulkContainer(inner)) {
				return inner;
			}
		}
		return null;
	}

	/**
	 * Nearest resolvable unit under the pointer: marked ancestor of the caret text,
	 * else a single marked child, else null (caller may show multi-card).
	 */
	function resolveTargetFromEvent(event) {
		var raw = event.target;
		if (!raw || isInspectorUi(raw)) {
			return { kind: 'none' };
		}
		// Pierce overlays: deepest leaf mark under the pointer wins.
		var leafHit = leafMarkFromPoint(event.clientX, event.clientY);
		if (leafHit) {
			return { kind: 'node', node: leafHit };
		}
		// Prefer caret text under the pointer (empty padding / grid gaps).
		var tn = textNodeFromPoint(event.clientX, event.clientY);
		if (tn && tn.parentElement) {
			var mark = tn.parentElement.closest ? tn.parentElement.closest('.automlp-fi-mark') : null;
			// Leaf mark only — a marked wrapper with nested marks must not concatenate.
			if (mark && !isInspectorUi(mark) && !isBulkContainer(mark)) {
				return { kind: 'node', node: mark };
			}
			// Unmarked text sitting inside a multi-mark container -> multi-card (never concatenate).
			var bulk = tn.parentElement;
			while (bulk && bulk !== document.body && !isBulkContainer(bulk)) {
				bulk = bulk.parentElement;
			}
			if (bulk && isBulkContainer(bulk)) {
				var bulkMarks = Array.prototype.slice.call(bulk.querySelectorAll('.automlp-fi-mark')).filter(function (n) {
					return !isInspectorUi(n);
				});
				if (bulkMarks.length >= 2) {
					return { kind: 'multi', node: bulk, marks: bulkMarks };
				}
				if (bulkMarks.length === 1) {
					return { kind: 'node', node: bulkMarks[0] };
				}
			}
			// Truly isolated unmarked leaf text.
			var leaf = tn.parentElement;
			if (leaf && leaf.nodeType === 1 && !isInspectorUi(leaf) && !isBulkContainer(leaf)) {
				return { kind: 'node', node: leaf };
			}
		}
		// Hit a mark directly (innermost via closest). Wrapper marks with nested marks -> multi.
		var direct = raw.closest ? raw.closest('.automlp-fi-mark') : null;
		if (direct && !isInspectorUi(direct)) {
			if (isBulkContainer(direct)) {
				var nested = Array.prototype.slice.call(direct.querySelectorAll('.automlp-fi-mark')).filter(function (n) {
					return !isInspectorUi(n);
				});
				if (nested.length >= 2) {
					return { kind: 'multi', node: direct, marks: nested };
				}
				if (nested.length === 1) {
					return { kind: 'node', node: nested[0] };
				}
			}
			return { kind: 'node', node: direct };
		}
		// Closed <select>.
		var selectEl = raw.closest ? raw.closest('select') : null;
		if (selectEl && !isInspectorUi(selectEl)) {
			if (raw.tagName === 'OPTION' || raw.tagName === 'OPTGROUP') {
				return { kind: 'node', node: raw };
			}
			return { kind: 'select', node: selectEl };
		}
		// Empty space inside a multi-mark container -> multi-card, never concatenate.
		var container = raw;
		while (container && container !== document.body && !isBulkContainer(container)) {
			container = container.parentElement;
		}
		if (container && isBulkContainer(container)) {
			var kids = Array.prototype.slice.call(container.querySelectorAll('.automlp-fi-mark'));
			kids = kids.filter(function (n) { return !isInspectorUi(n); });
			if (kids.length >= 2) {
				return { kind: 'multi', node: container, marks: kids };
			}
			if (kids.length === 1) {
				return { kind: 'node', node: kids[0] };
			}
		}
		// Single-child unwrap only when not a bulk container.
		var node = raw;
		while (node && node.children && node.children.length === 1 && !isBulkContainer(node)) {
			node = node.children[0];
		}
		if (node && node.nodeType === 1 && !isInspectorUi(node) && !isBulkContainer(node)) {
			return { kind: 'node', node: node };
		}
		return { kind: 'none' };
	}

	function onPageClick(event) {
		if (!state.open || isInspectorUi(event.target)) {
			state.ptrDown = null;
			return;
		}
		// Drag / text-selection gesture: clear selection and do not resolve.
		if (state.resizing || pointerMoved(event) || selectionIsDrag()) {
			clearPageSelection();
			state.ptrDown = null;
			event.preventDefault();
			event.stopPropagation();
			return;
		}
		state.ptrDown = null;
		event.preventDefault();
		event.stopPropagation();
		clearPageSelection();
		var hit = resolveTargetFromEvent(event);
		if (hit.kind === 'select') {
			resolveSelect(hit.node);
			return;
		}
		if (hit.kind === 'multi') {
			renderMarkedChildren(hit.node, hit.marks);
			return;
		}
		if (hit.kind === 'node' && hit.node) {
			resolveNode(hit.node);
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
		withMoPaused(function () {
			buildPanel();
		});
		setAdminBar(true);
		document.addEventListener('click', onPageClick, true);
		document.addEventListener('pointerdown', onPagePointerDown, true);
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
		state.resizing = false;
		state.ptrDown = null;
		remember(false);
		document.removeEventListener('click', onPageClick, true);
		document.removeEventListener('pointerdown', onPagePointerDown, true);
		if (state.onPtrUp) {
			window.removeEventListener('pointerup', state.onPtrUp, true);
			state.onPtrUp = null;
		}
		if (state.onPtrCancel) {
			window.removeEventListener('pointercancel', state.onPtrCancel, true);
			state.onPtrCancel = null;
		}
		if (state.onBlur) {
			window.removeEventListener('blur', state.onBlur);
			state.onBlur = null;
		}
		if (state.onWinResize) {
			window.removeEventListener('resize', state.onWinResize);
			state.onWinResize = null;
		}
		unmarkPage();
		clearPageSelection();
		if (state.panel) {
			withMoPaused(function () {
				state.panel.remove();
			});
		}
		state.panel = state.detailEl = state.listEl = null;
		state.scanEntries = [];
		state.openKey = null;
		document.body.classList.remove('automlp-fi-open', 'automlp-fi-resizing');
		document.documentElement.style.removeProperty('--automlp-fi-width');
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
