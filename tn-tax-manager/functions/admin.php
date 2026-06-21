<?php
/**
 * TN Tax Manager - Admin
 */

if (!defined('ABSPATH')) exit;

/*
|--------------------------------------------------------------------------
| Admin menu
|--------------------------------------------------------------------------
*/

add_action('admin_menu', 'tn801_ttm_admin_menu');

function tn801_ttm_admin_menu() {

	add_menu_page(
		'Tax Manager',
		'Tax Manager',
		'manage_options',
		'tn801-ttm-tax-manager',
		'tn801_ttm_render_admin_page',
		'dashicons-category',
		58
	);
}


/*
|--------------------------------------------------------------------------
| Register settings
|--------------------------------------------------------------------------
*/

add_action('admin_init', 'tn801_ttm_register_settings');

function tn801_ttm_register_settings() {

	register_setting('tn801_ttm_settings', TN801_TTM_MODE_OPTION);
	register_setting('tn801_ttm_settings', TN801_TTM_OPENAI_KEY_OPTION);
	register_setting('tn801_ttm_settings', TN801_TTM_TAXONOMY_OPTION);
}


/*
|--------------------------------------------------------------------------
| Admin page
|--------------------------------------------------------------------------
*/

function tn801_ttm_render_admin_page() {

	$mode     = get_option(TN801_TTM_MODE_OPTION, TN801_TTM_DEFAULT_MODE);
	$api_key  = get_option(TN801_TTM_OPENAI_KEY_OPTION, '');
	$taxonomy = get_option(TN801_TTM_TAXONOMY_OPTION, TN801_TTM_DEFAULT_TAXONOMY);

	$candidate = tn801_ttm_get_latest_unparented_term();
	$terms     = get_terms(array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	));

	if (is_wp_error($terms)) {
		$terms = array();
	}

	?>
	<div class="wrap tn801-ttm-admin">
		<h1>Tax Manager</h1>

		<style>
			.tn801-ttm-card {
				background: #fff;
				border: 1px solid #dcdcde;
				border-radius: 8px;
				padding: 18px;
				margin: 16px 0;
				max-width: 1000px;
			}

			.tn801-ttm-candidate {
				border-left: 5px solid #2271b1;
				display: flex;
				justify-content: space-between;
				gap: 18px;
				align-items: center;
			}

			.tn801-ttm-label {
				text-transform: uppercase;
				letter-spacing: .04em;
				font-size: 11px;
				color: #646970;
				font-weight: 700;
			}

			.tn801-ttm-name {
				font-size: 24px;
				font-weight: 700;
				line-height: 1.2;
				margin-top: 4px;
			}

			.tn801-ttm-meta {
				margin-top: 6px;
				color: #646970;
			}

			.tn801-ttm-actions {
				display: flex;
				gap: 8px;
				align-items: center;
			}

			.tn801-ttm-tree {
				max-height: 560px;
				overflow: auto;
				background: #fff;
				border: 1px solid #dcdcde;
				padding: 12px;
				margin-top: 10px;
			}

			.tn801-ttm-node-wrap {
				margin: 2px 0;
			}

			.tn801-ttm-node {
				display: flex;
				align-items: center;
				gap: 6px;
				min-height: 30px;
				border-radius: 6px;
				cursor: pointer;
				padding: 3px 7px;
				user-select: none;
			}

			.tn801-ttm-node:hover {
				background: #f0f6fc;
			}

			.tn801-ttm-node.is-selected {
				background: #2271b1;
				color: #fff;
			}

			.tn801-ttm-toggle {
				width: 22px;
				height: 22px;
				border: 1px solid #c3c4c7;
				border-radius: 50%;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				font-weight: 700;
				font-size: 14px;
				line-height: 1;
				background: #fff;
				color: #1d2327;
				flex: 0 0 22px;
			}

			.tn801-ttm-toggle.is-empty {
				border-color: transparent;
				background: transparent;
				color: transparent;
			}

			.tn801-ttm-children {
				display: none;
				margin-left: 24px;
				border-left: 1px solid #dcdcde;
				padding-left: 8px;
			}

			.tn801-ttm-node-wrap.is-open > .tn801-ttm-children {
				display: block;
			}

			.tn801-ttm-selected-text {
				color: #646970;
				margin-top: 8px;
			}

			.tn801-ttm-selected-text strong {
				color: #1d2327;
			}
		</style>

		<div class="tn801-ttm-card">
			<h2>Settings</h2>

			<form method="post" action="options.php">
				<?php settings_fields('tn801_ttm_settings'); ?>

				<table class="form-table">
					<tr>
						<th scope="row">Display mode</th>
						<td>
							<select name="<?php echo esc_attr(TN801_TTM_MODE_OPTION); ?>">
								<option value="compact" <?php selected($mode, 'compact'); ?>>Compact admin bar</option>
								<option value="detailed" <?php selected($mode, 'detailed'); ?>>Detailed floating widget</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">Taxonomy</th>
						<td>
							<select name="<?php echo esc_attr(TN801_TTM_TAXONOMY_OPTION); ?>">
								<?php foreach (get_taxonomies(array('public' => true), 'objects') as $tax) : ?>
									<option value="<?php echo esc_attr($tax->name); ?>" <?php selected($taxonomy, $tax->name); ?>>
										<?php echo esc_html($tax->label . ' (' . $tax->name . ')'); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row">OpenAI API Key</th>
						<td>
							<input
								type="password"
								class="regular-text"
								name="<?php echo esc_attr(TN801_TTM_OPENAI_KEY_OPTION); ?>"
								value="<?php echo esc_attr($api_key); ?>"
								autocomplete="off"
							>
						</td>
					</tr>
				</table>

				<?php submit_button('Save Settings', 'secondary', 'submit', false); ?>
			</form>
		</div>

		<div class="tn801-ttm-card">
			<h2>Recent unparented terms</h2>

			<?php if (!$candidate) : ?>

				<p><strong>All done.</strong> No recently created top-level terms need review.</p>

			<?php else : ?>

				<form method="post" id="tn801-ttm-admin-form">
					<?php wp_nonce_field('tn801_ttm_assign_parent', 'tn801_ttm_nonce'); ?>
					<input type="hidden" name="tn801_ttm_admin_action" value="assign_parent">
					<input type="hidden" name="term_id" value="<?php echo esc_attr($candidate->term_id); ?>">
					<input type="hidden" name="parent_id" id="tn801-ttm-parent-id" value="">

					<div class="tn801-ttm-card tn801-ttm-candidate">
						<div>
							<div class="tn801-ttm-label">New top-level term</div>
							<div class="tn801-ttm-name"><?php echo esc_html($candidate->name); ?></div>
							<div class="tn801-ttm-meta">
								<?php echo intval($candidate->count); ?> posts
								<?php
								$created = (int) get_term_meta($candidate->term_id, '_tn801_ttm_created_at', true);
								if ($created) {
									echo ' · Created ' . esc_html(human_time_diff($created, time())) . ' ago';
								}
								?>
							</div>
						</div>

						<div class="tn801-ttm-actions">
							<button type="submit" class="button button-primary" id="tn801-ttm-assign-btn" disabled>
								Assign
							</button>

							<button type="submit" class="button" name="skip" value="1">
								Skip
							</button>
						</div>
					</div>

					<p class="tn801-ttm-selected-text" id="tn801-ttm-selected-text">Nothing selected yet.</p>

					<div class="tn801-ttm-tree" id="tn801-ttm-tree">
						<?php tn801_ttm_render_term_tree($terms, 0, (int) $candidate->term_id); ?>
					</div>
				</form>

			<?php endif; ?>
		</div>

		<script>
		(function() {
			var tree = document.getElementById('tn801-ttm-tree');
			var parentInput = document.getElementById('tn801-ttm-parent-id');
			var selectedText = document.getElementById('tn801-ttm-selected-text');
			var assignBtn = document.getElementById('tn801-ttm-assign-btn');
			var form = document.getElementById('tn801-ttm-admin-form');

			if (!tree || !parentInput || !selectedText || !assignBtn || !form) {
				return;
			}

			tree.addEventListener('click', function(e) {
				var toggle = e.target.closest('.tn801-ttm-toggle');

				if (toggle && !toggle.classList.contains('is-empty')) {
					e.preventDefault();
					e.stopPropagation();

					var wrapper = toggle.closest('.tn801-ttm-node-wrap');

					if (!wrapper) return;

					var isOpen = wrapper.classList.toggle('is-open');
					toggle.textContent = isOpen ? '−' : '+';
					return;
				}

				var node = e.target.closest('.tn801-ttm-node');

				if (!node) return;

				e.preventDefault();

				var id = node.getAttribute('data-term-id');
				var label = node.getAttribute('data-term-name');

				parentInput.value = id;
				assignBtn.disabled = false;

				tree.querySelectorAll('.tn801-ttm-node.is-selected').forEach(function(el) {
					el.classList.remove('is-selected');
				});

				node.classList.add('is-selected');
				selectedText.innerHTML = 'Selected parent: <strong>' + label + '</strong>';
			});

			form.addEventListener('submit', function(e) {
				var clicked = document.activeElement;

				if (clicked && clicked.name === 'skip') {
					return;
				}

				if (!parentInput.value) {
					e.preventDefault();
					alert('Choose a parent term first, or click Skip.');
				}
			});
		})();
		</script>
	</div>
	<?php
}


/*
|--------------------------------------------------------------------------
| Candidate
|--------------------------------------------------------------------------
*/

function tn801_ttm_get_latest_unparented_term() {

	$taxonomy = tn801_ttm_get_taxonomy();

	$terms = get_terms(array(
		'taxonomy'   => $taxonomy,
		'parent'     => 0,
		'hide_empty' => false,
	));

	if (empty($terms) || is_wp_error($terms)) {
		return null;
	}

	$cutoff = time() - (7 * DAY_IN_SECONDS);
	$candidates = array();

	foreach ($terms as $term) {

		$created = (int) get_term_meta($term->term_id, '_tn801_ttm_created_at', true);
		$skipped = (int) get_term_meta($term->term_id, '_tn801_ttm_skipped', true);

		if ($skipped) continue;
		if (!$created) continue;
		if ($created < $cutoff) continue;

		$candidates[] = array(
			'term'    => $term,
			'created' => $created,
		);
	}

	if (empty($candidates)) {
		return null;
	}

	usort($candidates, function($a, $b) {
		return $b['created'] <=> $a['created'];
	});

	return $candidates[0]['term'];
}


/*
|--------------------------------------------------------------------------
| Tree
|--------------------------------------------------------------------------
*/

function tn801_ttm_render_term_tree($terms, $parent = 0, $exclude_term_id = 0) {

	foreach ($terms as $term) {

		if ((int) $term->parent !== (int) $parent) continue;
		if ((int) $term->term_id === (int) $exclude_term_id) continue;

		$has_children = tn801_ttm_term_has_children($terms, $term->term_id, $exclude_term_id);

		echo '<div class="tn801-ttm-node-wrap">';

		echo '<div class="tn801-ttm-node" data-term-id="' . esc_attr($term->term_id) . '" data-term-name="' . esc_attr($term->name) . '">';

		if ($has_children) {
			echo '<span class="tn801-ttm-toggle">+</span>';
		} else {
			echo '<span class="tn801-ttm-toggle is-empty">·</span>';
		}

		echo '<span>' . esc_html($term->name) . '</span>';
		echo '<small>(' . intval($term->count) . ')</small>';

		echo '</div>';

		if ($has_children) {
			echo '<div class="tn801-ttm-children">';
			tn801_ttm_render_term_tree($terms, $term->term_id, $exclude_term_id);
			echo '</div>';
		}

		echo '</div>';
	}
}

function tn801_ttm_term_has_children($terms, $parent_id, $exclude_term_id = 0) {

	foreach ($terms as $term) {
		if ((int) $term->parent === (int) $parent_id && (int) $term->term_id !== (int) $exclude_term_id) {
			return true;
		}
	}

	return false;
}


/*
|--------------------------------------------------------------------------
| Handle assign / skip
|--------------------------------------------------------------------------
*/

add_action('admin_init', 'tn801_ttm_handle_admin_action');

function tn801_ttm_handle_admin_action() {

	if (!isset($_POST['tn801_ttm_admin_action'])) return;

	check_admin_referer('tn801_ttm_assign_parent', 'tn801_ttm_nonce');

	$taxonomy = tn801_ttm_get_taxonomy();
	$term_id  = absint($_POST['term_id']);

	if (!$term_id) {
		wp_redirect(admin_url('admin.php?page=tn801-ttm-tax-manager'));
		exit;
	}

	if (isset($_POST['skip'])) {
		update_term_meta($term_id, '_tn801_ttm_skipped', 1);
		wp_redirect(admin_url('admin.php?page=tn801-ttm-tax-manager'));
		exit;
	}

	$parent_id = absint($_POST['parent_id']);

	if ($parent_id && $parent_id !== $term_id) {
		wp_update_term($term_id, $taxonomy, array(
			'parent' => $parent_id,
		));
	}

	wp_redirect(admin_url('admin.php?page=tn801-ttm-tax-manager'));
	exit;
}