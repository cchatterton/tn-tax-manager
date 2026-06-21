<?php
/**
 * TN Tax Manager - GitHub updater
 */

if (!defined('ABSPATH')) exit;

class TN801_TTM_GitHub_Updater {

	private const OWNER = 'cchatterton';
	private const REPO = 'tn-tax-manager';
	private const SLUG = 'tn-tax-manager';
	private const ASSET_NAME = 'tn-tax-manager.zip';
	private const RELEASE_TRANSIENT = 'tn801_ttm_github_latest_release';
	private const ERROR_TRANSIENT = 'tn801_ttm_github_latest_release_error';

	private $plugin_file;

	public function __construct() {
		$this->plugin_file = plugin_basename(TN801_TTM_PLUGIN_FILE);
	}

	public function register() {
		add_filter('pre_set_site_transient_update_plugins', array($this, 'add_update_data'));
		add_filter('site_transient_update_plugins', array($this, 'add_update_data'));
		add_filter('plugins_api', array($this, 'plugin_information'), 10, 3);
		add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
		add_action('admin_init', array($this, 'handle_manual_update_check'));
		add_action('upgrader_process_complete', array($this, 'clear_cache_after_update'), 10, 2);
	}

	public function add_update_data($transient) {
		if (!is_object($transient)) {
			return $transient;
		}

		$transient->response = isset($transient->response) && is_array($transient->response) ? $transient->response : array();
		$transient->no_update = isset($transient->no_update) && is_array($transient->no_update) ? $transient->no_update : array();

		$release = $this->get_latest_release($this->is_forced_update_check());

		if (!$release) {
			unset($transient->response[$this->plugin_file]);
			unset($transient->no_update[$this->plugin_file]);
			return $transient;
		}

		$version = $this->release_version($release);
		$package = $this->release_asset_url($release);

		if (!$version || !$package || !version_compare($version, TN801_TTM_VERSION, '>')) {
			unset($transient->response[$this->plugin_file]);
			unset($transient->no_update[$this->plugin_file]);
			return $transient;
		}

		$transient->response[$this->plugin_file] = (object) array(
			'id'           => $this->repo_url(),
			'slug'         => self::SLUG,
			'plugin'       => $this->plugin_file,
			'new_version'  => $version,
			'url'          => $this->release_url($release),
			'package'      => $package,
			'requires'     => '6.0',
			'requires_php' => '7.4',
		);

		unset($transient->no_update[$this->plugin_file]);

		return $transient;
	}

	public function plugin_information($result, $action, $args) {
		if ('plugin_information' !== $action || empty($args->slug) || self::SLUG !== $args->slug) {
			return $result;
		}

		$release = $this->get_latest_release($this->is_forced_update_check());
		if (!$release) {
			return $result;
		}

		$version = $this->release_version($release);
		$package = $this->release_asset_url($release);

		if (!$version || !$package) {
			return $result;
		}

		return (object) array(
			'name'          => 'TN Tax Manager',
			'slug'          => self::SLUG,
			'version'       => $version,
			'author'        => 'Techn',
			'homepage'      => $this->repo_url(),
			'download_link' => $package,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'sections'      => array(
				'description' => 'Lightweight front-end taxonomy management with AI suggestions and guided taxonomy cleanup.',
				'changelog'   => wp_kses_post((string) ($release['body'] ?? '')),
			),
		);
	}

	public function plugin_row_meta($links, $file) {
		if ($file !== $this->plugin_file || !current_user_can('update_plugins')) {
			return $links;
		}

		$plugins_url = is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php');
		$check_url = wp_nonce_url(
			add_query_arg('tn801_ttm_check_updates', '1', $plugins_url),
			'tn801_ttm_check_updates'
		);

		$links[] = '<a href="' . esc_url($this->repo_url()) . '">GitHub</a>';
		$links[] = '<a href="' . esc_url($check_url) . '">Check for updates</a>';

		return $links;
	}

	public function handle_manual_update_check() {
		if (empty($_GET['tn801_ttm_check_updates'])) {
			return;
		}

		if (!current_user_can('update_plugins')) {
			wp_die(esc_html__('You do not have permission to check for plugin updates.', 'tn-tax-manager'));
		}

		check_admin_referer('tn801_ttm_check_updates');

		$this->clear_release_cache();
		delete_site_transient('update_plugins');
		wp_update_plugins();

		wp_safe_redirect(is_multisite() ? network_admin_url('plugins.php') : admin_url('plugins.php'));
		exit;
	}

	public function clear_cache_after_update($upgrader, $options) {
		if (empty($options['action']) || 'update' !== $options['action']) return;
		if (empty($options['type']) || 'plugin' !== $options['type']) return;

		$plugins = isset($options['plugins']) && is_array($options['plugins']) ? $options['plugins'] : array();
		if (!in_array($this->plugin_file, $plugins, true)) return;

		$this->clear_release_cache();
	}

	private function get_latest_release($force = false) {
		if ($force) {
			$this->clear_release_cache();
		}

		$cached = get_site_transient(self::RELEASE_TRANSIENT);
		if (is_array($cached)) {
			return $cached;
		}

		$url = 'https://api.github.com/repos/' . self::OWNER . '/' . self::REPO . '/releases/latest';
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'TN-Tax-Manager/' . TN801_TTM_VERSION,
				),
			)
		);

		if (is_wp_error($response)) {
			$this->store_release_error('wp_error', $response->get_error_message());
			return false;
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if (200 !== $code) {
			$this->store_release_error(
				'http_error',
				wp_remote_retrieve_response_message($response),
				array(
					'code' => $code,
					'body' => substr($body, 0, 500),
				)
			);
			return false;
		}

		$release = json_decode($body, true);
		if (!is_array($release)) {
			$this->store_release_error('json_error', 'GitHub release response could not be decoded.');
			return false;
		}

		$version = $this->release_version($release);
		if (!$version) {
			$this->store_release_error('version_error', 'GitHub release tag did not contain a valid version.');
			return false;
		}

		delete_site_transient(self::ERROR_TRANSIENT);
		$cache_seconds = version_compare($version, TN801_TTM_VERSION, '>') ? 6 * HOUR_IN_SECONDS : 5 * MINUTE_IN_SECONDS;
		set_site_transient(self::RELEASE_TRANSIENT, $release, $cache_seconds);

		return $release;
	}

	private function release_version($release) {
		$version = ltrim((string) ($release['tag_name'] ?? ''), 'vV');
		return preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $version) ? $version : '';
	}

	private function release_asset_url($release) {
		if (empty($release['assets']) || !is_array($release['assets'])) {
			return '';
		}

		foreach ($release['assets'] as $asset) {
			if (self::ASSET_NAME === ($asset['name'] ?? '') && !empty($asset['browser_download_url'])) {
				return esc_url_raw((string) $asset['browser_download_url']);
			}
		}

		return '';
	}

	private function release_url($release) {
		return !empty($release['html_url']) ? esc_url_raw((string) $release['html_url']) : $this->repo_url();
	}

	private function repo_url() {
		return 'https://github.com/' . self::OWNER . '/' . self::REPO;
	}

	private function is_forced_update_check() {
		if (!current_user_can('update_plugins')) {
			return false;
		}

		$action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

		return isset($_REQUEST['force-check'])
			|| in_array($action, array('update-selected', 'upgrade-plugin', 'do-plugin-upgrade'), true);
	}

	private function store_release_error($type, $message, $extra = array()) {
		delete_site_transient(self::RELEASE_TRANSIENT);

		set_site_transient(
			self::ERROR_TRANSIENT,
			array_merge(
				array(
					'type'       => $type,
					'message'    => sanitize_text_field($message),
					'checked_at' => time(),
				),
				$extra
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	private function clear_release_cache() {
		delete_site_transient(self::RELEASE_TRANSIENT);
		delete_site_transient(self::ERROR_TRANSIENT);
	}
}

function tn801_ttm_boot_github_updater() {
	$updater = new TN801_TTM_GitHub_Updater();
	$updater->register();
}
tn801_ttm_boot_github_updater();
