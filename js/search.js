// Smart search suggestions for medicine inputs
(function() {
	const apiUrl = (window.APP_SEARCH_API || '/search_api.php');
	const isAuth = !!window.IS_AUTH;
	const loginUrl = window.LOGIN_URL || '/login.php?next=1';
	const signupUrl = window.REGISTER_URL || ((window.APP_BASE || '') + '/register.php');
	const inputs = document.querySelectorAll('[data-suggest]');
	attachAuthGuards();

	inputs.forEach((input) => {
		const container = findSuggestionsContainer(input);
		if (!container) return;

		let debounceTimer;
		let lastQuery = '';

		input.addEventListener('input', () => {
			const query = input.value.trim();
			if (query.length < 2) {
				hide(container);
				return;
			}

			if (query === lastQuery) return;
			lastQuery = query;

			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(() => fetchSuggestions(query, input, container), 220);
		});

		input.addEventListener('focus', () => {
			if (container.children.length > 0) show(container);
		});

		document.addEventListener('click', (e) => {
			if (!container.contains(e.target) && !input.contains(e.target)) {
				hide(container);
			}
		});
	});

	function fetchSuggestions(query, input, container) {
		if (!isAuth) {
			renderUnauthorized(container);
			return;
		}
		const url = `${apiUrl}?action=suggest&q=${encodeURIComponent(query)}`;
		fetch(url)
			.then((res) => {
				if (res.status === 401) {
					renderUnauthorized(container);
					return null;
				}
				if (!res.ok) return Promise.reject();
				return res.json();
			})
			.then((data) => { if (data) renderSuggestions(data, input, container); })
			.catch(() => hide(container));
	}

	function renderSuggestions(data, input, container) {
		container.innerHTML = '';
		if (!data || !data.items || data.items.length === 0) {
			if (data && data.suggested) {
				const item = document.createElement('div');
				item.className = 'suggestion-item';
				item.innerHTML = `<i class="fas fa-magic"></i><div><div>Did you mean <strong>${escapeHtml(data.suggested)}</strong>?</div></div>`;
				item.addEventListener('click', () => {
					input.value = data.suggested;
					hide(container);
					submitIfInsideForm(input);
				});
				container.appendChild(item);
				show(container);
			} else {
				hide(container);
			}
			return;
		}

		data.items.forEach((item) => {
			const row = document.createElement('div');
			row.className = 'suggestion-item';
			row.innerHTML = `
				<img src="${escapeAttribute(item.image || '')}" alt="" style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0;">
				<div style="flex:1;">
					<div style="font-weight:700; color: var(--clinical-text, #0f172a);">${escapeHtml(item.name)}</div>
					<div style="font-size:0.9rem; color: var(--clinical-text-light, #475569);">${escapeHtml(item.generic || '')}</div>
				</div>
				<div style="text-align:right; font-size:0.85rem; color: var(--clinical-text-light, #475569);">
					${item.pharmacies || 0} pharmacies
					${item.min_price ? `<div style="font-weight:700; color: var(--clinical-accent, #2563eb);">from ${item.min_price}</div>` : ''}
				</div>
			`;
			row.addEventListener('click', () => {
				input.value = item.name;
				hide(container);
				submitIfInsideForm(input);
			});
			container.appendChild(row);
		});
		show(container);
	}

	function renderUnauthorized(container) {
		container.innerHTML = '';
		const row = document.createElement('div');
		row.className = 'suggestion-item';
		row.innerHTML = `
			<i class="fas fa-lock" style="color: var(--clinical-accent, #2563eb);"></i>
			<div style="flex:1;">
				<div style="font-weight:700; color: var(--clinical-text, #0f172a);">Login required</div>
				<div style="font-size:0.9rem; color: var(--clinical-text-light, #475569);">Sign in or sign up to search medicines.</div>
			</div>
			<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
				<a href="${loginUrl}" style="font-weight:700; color: var(--clinical-accent, #2563eb);">Sign in</a>
				<span style="color: var(--clinical-text-light, #475569);">or</span>
				<a href="${signupUrl}" style="font-weight:700; color: var(--clinical-accent, #2563eb);">Sign up</a>
			</div>
		`;
		container.appendChild(row);
		show(container);
	}

	function findSuggestionsContainer(input) {
		if (input.dataset.for) {
			return document.querySelector(`[data-for="${input.dataset.for}"]`);
		}
		const parent = input.parentElement;
		if (!parent) return null;
		const container = parent.querySelector('.search-suggestions');
		return container;
	}

	function show(el) { el.classList.add('show'); }
	function hide(el) { el.classList.remove('show'); }

	function submitIfInsideForm(input) {
		const form = input.closest('form');
		if (form) {
			form.submit();
		}
	}

	function escapeHtml(str) {
		if (!str) return '';
		return str
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function escapeAttribute(str) {
		return escapeHtml(str || '');
	}

	function attachAuthGuards() {
		if (isAuth) return;
		document.querySelectorAll('[data-requires-auth]').forEach((form) => {
			form.addEventListener('submit', (e) => {
				e.preventDefault();
				showAuthPrompt();
			});
		});
	}

	function showAuthPrompt() {
		const message = 'Please sign in or sign up to search medicines.';
		if (confirm(`${message}\n\nOK = Sign in, Cancel = Sign up`)) {
			window.location.href = loginUrl;
			return;
		}
		window.location.href = signupUrl;
	}
})();
