document.addEventListener('DOMContentLoaded', function () {

	var root = document.getElementById('tn801-ttm-wrap') || document.getElementById('tn801-ttm-detail');
	if (!root) return;

	var postId = root.getAttribute('data-post-id');

	var form = document.getElementById('tn801-ttm-add-form');
	var input = document.getElementById('tn801-ttm-input');
	var fill = document.getElementById('tn801-ttm-fill');
	var aiList = document.getElementById('tn801-ttm-ai-list');

	var toggle = document.getElementById('tn801-ttm-toggle');
	var detailToggle = document.getElementById('tn801-ttm-detail-toggle');
	var detail = document.getElementById('tn801-ttm-detail');

	var currentMatch = '';
	var lookupTimer = null;

	if (detailToggle && detail) {
		detailToggle.addEventListener('click', function () {
			detail.classList.toggle('is-open');
		});
	}

	if (!form || !input || !fill || !aiList) return;

	if (toggle) {
		toggle.addEventListener('click', function () {
			var hidden = form.style.display === 'none' || form.style.display === '';

			if (hidden) {
				form.style.display = 'inline-flex';
				input.focus();
			} else {
				form.style.display = 'none';
				input.value = '';
				fill.textContent = '';
				currentMatch = '';
			}
		});
	}

	function acceptMatch() {
		if (!currentMatch) return;

		input.value = currentMatch;
		fill.textContent = '';
		currentMatch = '';
	}

    function updateFill() {
    	var q = input.value.trim();
    
    	fill.textContent = '';
    	currentMatch = '';
    
    	if (!q.length || !window.tn801_ttm || !Array.isArray(tn801_ttm.terms)) return;
    
    	var match = tn801_ttm.terms.find(function (term) {
    		return term.toLowerCase().indexOf(q.toLowerCase()) === 0;
    	});
    
    	if (!match) return;
    
    	currentMatch = match;
    	fill.textContent = input.value + match.slice(q.length);
    }

	input.addEventListener('input', updateFill);

	input.addEventListener('keydown', function (e) {
		if ((e.key === 'Tab' || e.key === 'ArrowRight') && currentMatch) {
			e.preventDefault();
			acceptMatch();
		}

		if (e.key === 'Escape') {
			fill.textContent = '';
			currentMatch = '';
		}
	});

	fill.addEventListener('click', acceptMatch);

	aiList.innerHTML = '<em>Loading suggestions…</em>';

	var data = new FormData();
	data.append('action', 'tn801_ttm_ai_suggest');
	data.append('post_id', postId);
	data.append('nonce', tn801_ttm.ai_nonce);

	fetch(tn801_ttm.ajax_url, {
		method: 'POST',
		credentials: 'same-origin',
		body: data
	})
	.then(function (res) { return res.json(); })
	.then(function (res) {

		aiList.innerHTML = '';

		if (!res || !res.success) {
			aiList.innerHTML = '<em>' + (res && res.data ? res.data : 'Could not load suggestions.') + '</em>';
			return;
		}

		var terms = res.data;

		if (!Array.isArray(terms) || !terms.length) {
			aiList.innerHTML = '<em>No suggestions found.</em>';
			return;
		}

		terms.forEach(function (termName) {
			var button = document.createElement('button');
			var name = document.createElement('span');
			var add = document.createElement('span');

			button.type = 'button';
			button.className = 'tn801-ttm-pill-ai';

			name.className = 'tn801-ttm-name';
			name.textContent = termName;

			add.className = 'tn801-ttm-add-btn';
			add.textContent = 'Add';

			button.appendChild(name);
			button.appendChild(add);

			button.addEventListener('click', function () {
				input.value = termName;
				form.submit();
			});

			aiList.appendChild(button);
		});
	})
	.catch(function () {
		aiList.innerHTML = '<em>Could not load suggestions.</em>';
	});

	if (window.tn801_ttm && tn801_ttm.tax_manager_url) {
		var url = new URL(window.location.href);

		if (url.searchParams.has('tn801_ttm_new_term')) {
			window.open(tn801_ttm.tax_manager_url, '_blank');
			url.searchParams.delete('tn801_ttm_new_term');
			window.history.replaceState({}, document.title, url.toString());
		}
	}
});
