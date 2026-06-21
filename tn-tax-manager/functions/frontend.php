<?php
/**
 * TN Tax Manager - Frontend
 */

if (!defined('ABSPATH')) exit;

function tn801_ttm_boot_frontend_ui() {

	if (is_admin()) return;

	add_action('wp_enqueue_scripts', 'tn801_ttm_enqueue_assets');

	$mode = get_option(TN801_TTM_MODE_OPTION, TN801_TTM_DEFAULT_MODE);

	if ($mode === 'detailed') {
		add_action('wp_footer', 'tn801_ttm_render_detailed_widget', 98);
	} else {
		add_action('admin_bar_menu', 'tn801_ttm_add_admin_bar', 999);
	}
}
add_action('init', 'tn801_ttm_boot_frontend_ui');

function tn801_ttm_enqueue_assets() {

	if (!tn801_ttm_should_show()) return;

	$plugin_url = plugin_dir_url(dirname(__DIR__) . '/tn-tax-manager.php');

	wp_enqueue_style(
		'tn801-ttm-style',
		$plugin_url . 'assets/style.css',
		array(),
		TN801_TTM_VERSION
	);

	wp_enqueue_script(
		'tn801-ttm-script',
		$plugin_url . 'assets/script.js',
		array(),
		TN801_TTM_VERSION,
		true
	);

    wp_localize_script(
    	'tn801-ttm-script',
    	'tn801_ttm',
    	array(
    		'ajax_url'        => admin_url('admin-ajax.php'),
    		'ai_nonce'        => wp_create_nonce('tn801_ttm_ai_suggest'),
    		'current_nonce'   => wp_create_nonce('tn801_ttm_current_terms'),
    		'remove_nonce'    => wp_create_nonce('tn801_ttm_remove'),
    		'tax_manager_url' => admin_url('admin.php?page=tn801-ttm-tax-manager'),
    		'terms'           => tn801_ttm_get_all_term_names(),
    	)
    );
}

function tn801_ttm_render_autocomplete_input() {
	?>
	<div class="tn801-ttm-autocomplete">
		<input type="text" name="term_name" id="tn801-ttm-input" autocomplete="off">
		<span id="tn801-ttm-fill" class="tn801-ttm-fill"></span>
	</div>
	<?php
}

function tn801_ttm_render_current_term_pill($term, $post_id, $redirect, $remove_label) {
	$taxonomy = tn801_ttm_get_taxonomy();
	$taxonomy_object = get_taxonomy($taxonomy);
	$taxonomy_label = $taxonomy_object ? ($taxonomy_object->labels->singular_name ?? $taxonomy_object->label) : $taxonomy;
	?>
	<span class="tn801-ttm-pill" title="<?php echo esc_attr($taxonomy_label); ?>">
		<span class="tn801-ttm-name"><?php echo esc_html($term->name); ?></span>

		<form class="tn801-ttm-remove-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
			<input type="hidden" name="action" value="tn801_ttm_remove">
			<input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
			<input type="hidden" name="term_id" value="<?php echo esc_attr($term->term_id); ?>">
			<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
			<?php wp_nonce_field('tn801_ttm_remove', 'tn801_ttm_nonce'); ?>
			<button type="submit" class="tn801-ttm-remove-btn"><?php echo esc_html($remove_label); ?></button>
		</form>
	</span>
	<?php
}

function tn801_ttm_add_admin_bar($wp_admin_bar) {

	if (!tn801_ttm_should_show()) return;

	global $post;

	$post_id  = $post->ID;
	$terms    = tn801_ttm_get_terms($post_id);
	$redirect = get_permalink($post_id);

	ob_start();
	?>
		<div id="tn801-ttm-wrap" class="tn801-ttm-wrap" data-post-id="<?php echo esc_attr($post_id); ?>">
			<div class="tn801-ttm-list">
				<?php foreach ($terms as $term) : ?>
					<?php tn801_ttm_render_current_term_pill($term, $post_id, $redirect, '×'); ?>
			<?php endforeach; ?>
		</div>

		<button type="button" class="tn801-ttm-refresh-btn" hidden>Refresh</button>

		<button type="button" id="tn801-ttm-toggle" class="tn801-ttm-toggle">+</button>

		<form id="tn801-ttm-add-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
			<input type="hidden" name="action" value="tn801_ttm_add">
			<input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
			<?php wp_nonce_field('tn801_ttm_add', 'tn801_ttm_nonce'); ?>

			<?php tn801_ttm_render_autocomplete_input(); ?>
		</form>

		<div id="tn801-ttm-ai-list" class="tn801-ttm-ai-list"></div>
	</div>
	<?php

	$wp_admin_bar->add_node(array(
		'id'    => 'tn801-ttm-node',
		'title' => ob_get_clean(),
	));
}

function tn801_ttm_render_detailed_widget() {

	if (!tn801_ttm_should_show()) return;

	global $post;

	$post_id  = $post->ID;
	$terms    = tn801_ttm_get_terms($post_id);
	$redirect = get_permalink($post_id);

	?>
	<div id="tn801-ttm-detail" class="tn801-ttm-detail" data-post-id="<?php echo esc_attr($post_id); ?>">
        <button type="button" id="tn801-ttm-detail-toggle" class="tn801-ttm-detail-toggle">
        	<span class="tn801-ttm-toggle-label">Manage Tags</span>
        	<span class="tn801-ttm-toggle-icon" aria-hidden="true">+</span>
        </button>

		<div id="tn801-ttm-detail-panel" class="tn801-ttm-detail-panel">
			<div class="tn801-ttm-detail-section">
				<strong>Current Tags</strong>
				<button type="button" class="tn801-ttm-refresh-btn" hidden>Refresh page</button>

				<div class="tn801-ttm-detail-pills">
					<?php foreach ($terms as $term) : ?>
						<?php tn801_ttm_render_current_term_pill($term, $post_id, $redirect, 'Remove'); ?>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="tn801-ttm-detail-section">
				<strong>Add a Tag</strong>
				<p>Start typing to find an existing Tag,<br>or create a new one.</p>

				<form id="tn801-ttm-add-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
					<input type="hidden" name="action" value="tn801_ttm_add">
					<input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect); ?>">
					<?php wp_nonce_field('tn801_ttm_add', 'tn801_ttm_nonce'); ?>

					<?php tn801_ttm_render_autocomplete_input(); ?>

					<button type="submit">Add</button>
				</form>
			</div>

			<div class="tn801-ttm-detail-section">
				<strong>AI Suggested Tags</strong>
				<div id="tn801-ttm-ai-list" class="tn801-ttm-ai-list"></div>
			</div>
		</div>
	</div>
	<?php
}
