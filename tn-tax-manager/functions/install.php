<?php
/**
 * TN Tax Manager - Install
 */

if (!defined('ABSPATH')) exit;

/**
 * Runs on plugin activation.
 */
function tn801_ttm_install() {

	if (get_option(TN801_TTM_MODE_OPTION) === false) {
		add_option(TN801_TTM_MODE_OPTION, TN801_TTM_DEFAULT_MODE);
	}

	if (get_option(TN801_TTM_OPENAI_KEY_OPTION) === false) {
		add_option(TN801_TTM_OPENAI_KEY_OPTION, '');
	}

	if (get_option(TN801_TTM_TAXONOMY_OPTION) === false) {
		add_option(TN801_TTM_TAXONOMY_OPTION, TN801_TTM_DEFAULT_TAXONOMY);
	}
}