<?php

namespace DuckDev;

// If this file is called directly, abort.
defined( 'WPINC' ) || die;

/**
 * Class WP_Flash_Notices
 *
 * Class that habdles admin notices registration and rendering using transient API.
 *
 * @author  Joel James
 * @link    http://duckdev.com
 * @package JoelJames\WP
 */
class WP_Flash_Notices {

	/**
	 * Notice types supported by WP.
	 *
	 * See https://codex.wordpress.org/Plugin_API/Action_Reference/admin_notices
	 *
	 * @var array $types
	 *
	 * @since 1.0.0
	 */
	protected $types = [ 'success', 'info', 'warning', 'error', ];

	/**
	 * Notice items queue to be saved later.
	 *
	 * @var array $notices
	 *
	 * @since 1.0.0
	 */
	protected $notices = [];

	/**
	 * Notice items queue to be saved later for network admin.
	 *
	 * @var array $network_notices
	 *
	 * @since 1.0.0
	 */
	protected $network_notices = [];

	/**
	 * Transient name for the notices item.
	 *
	 * @var string $transient
	 *
	 * @since 1.0.0
	 */
	protected $transient;

	/**
	 * WP_Flash_Notices constructor.
	 *
	 * Initialize the class, setup all registered notices and
	 * render all notices found in transient.
	 *
	 * @param string $transient Transient name (use a custom name for your plugin).
	 *
	 * @since 1.0.0
	 */
	public function __construct( $transient = 'duckdev_wp_flash_notices' ) {
		// Make sure you set this unique, otherwise all notices will mixup.
		$this->transient = $transient;

		/**
		 * Filter hook to add new notice types.
		 *
		 * @param array  $types     Notice types (default wp notices).
		 * @param string $transient Transient name.
		 *
		 * @since 1.0.1
		 */
		$this->types = apply_filters(
			'wp_flash_notices_notice_types',
			$this->types,
			$this->transient
		);

		// Render notices using WP action.
		add_action( 'admin_notices', [ $this, 'render' ] );
		add_action( 'front_notices', [ $this, 'render' ] );
		add_action( 'network_admin_notices', [ $this, 'render' ] );

		// Save all queued notices to transient.
		add_action( 'shutdown', [ $this, 'save' ] );

		// Automatically clear all rendered notices.
		add_action( 'wp_flash_notices_after_render', [ $this, 'clear_after_render' ], 10, 2 );
	}

	/**
	 * Add a new notice to the notices queue.
	 *
	 * We will save to the transient only once which is using shutdown hook,
	 * so that we can save all notices at once without multiple db calls.
	 *
	 * @param string $key         Notice key.
	 * @param string $message     Notice content.
	 * @param string $type        Notice type (should match only supported types).
	 * @param bool   $dismissible Is a dismissible notice (Will add a close icon).
	 * @param bool   $network     Is this a network admin notice? (Only for multisite).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add( $key, $message, $type = 'info', $dismissible = true, $network = false ) {
		// Prepare notice.
		$notice = [
			'message'     => $message,
			'type'        => in_array( $type, $this->types ) ? $type : 'info', // Only supported items.
			'dismissible' => $dismissible,
		];

		/**
		 * Filter hook to modify notice item before adding to queue.
		 *
		 * @param string $key       Notice key.
		 * @param array  $notice    Notice item.
		 * @param string $transient Transient name to identify the plugin.
		 *
		 * @since 1.0.0
		 */
		apply_filters( 'wp_flash_notices_notice_item', $key, $notice, $this->transient );

		// Multisite network admin notices.
		if ( is_multisite() && $network ) {
			$this->network_notices[ $key ] = $notice;
		} else {
			// Single or subsite notices.
			$this->notices[ $key ] = $notice;
		}

		/**
		 * Action hook fired after insering one notice to the queue.
		 *
		 * Note: The notice item is not yet added to the transient when this
		 * action hook is fired.
		 *
		 * @param array  $notice          Notice item inserted.
		 * @param string $transient       Transient name to identify the plugin.
		 * @param array  $notices         Current notice queue.
		 * @param array  $network_notices Current network notices queue.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wp_flash_notices_after_queue_insert',
			$notice,
			$this->transient,
			$this->notices,
			$this->network_notices
		);
	}

	/**
	 * Save all queued notices to the transient option.
	 *
	 * This method is automatically called during shutdown action.
	 * All queued notices will be added to the single transient entry.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save() {
		// Single site notices.
		if ( count( $this->notices ) > 0 ) {
			set_transient( $this->transient, $this->notices );
		}

		// Network admin notices.
		if ( count( $this->network_notices ) > 0 ) {
			set_site_transient( $this->transient, $this->notices );
		}

		/**
		 * Action hook fired after saving queued notices to transient.
		 *
		 * @param string $transient       Transient name to identify the plugin.
		 * @param array  $notices         Notice queue.
		 * @param array  $network_notices Network notices queue.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wp_flash_notices_after_save',
			$this->transient,
			$this->notices,
			$this->network_notices
		);
	}

	/**
	 * Get all notices from transient option.
	 *
	 * @param bool $network Is network notice?.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get( $key, $network = false ) {
		// Get notices.
		$notices = $this->fetch( $network );

		// Get notice item if found.
		$notice = isset( $notices[ $key ] ) ? $notices[ $key ] : [];

		/**
		 * Filter hook to modify the notice item.
		 *
		 * @param array  $notice    Current notice.
		 * @param string $transient Transient name to identify the plugin.
		 * @param array  $notices   Notice list.
		 * @param bool   $network   Is network notice?.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'wp_flash_notices_get',
			$notice,
			$this->transient,
			$notices,
			$network
		);
	}

	/**
	 * Get all notices from transient option.
	 *
	 * @param bool $network Is network notice?.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function fetch( $network = false ) {
		// Multisite network admin notices.
		if ( is_multisite() && $network ) {
			$notices = get_site_transient( $this->transient );
		} else {
			// Single or subsite notices.
			$notices = get_transient( $this->transient );
		}

		/**
		 * Filter hook to modify the fetched notices array.
		 *
		 * @param array  $notices   Notice list.
        	 * @param string $transient Transient name to identify the plugin.
		 * @param bool   $network   Is network notice?.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'wp_flash_notices_fetch',
			$notices,
			$this->transient,
			$network
		);
	}

	/**
	 * Delete all notices by deleting the transient data.
	 *
	 * @param bool $network Is network notice?.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clear( $network = false ) {
		// Multisite network admin notices.
		if ( is_multisite() && $network ) {
			// Delete all notices.
			delete_site_transient( $this->transient );
		} else {
			// Single or subsite notices.
			delete_transient( $this->transient );
		}

		/**
		 * Action hook fired after clearing notices from transient.
		 *
		 * @param string $transient Transient name to identify the plugin.
		 * @param bool   $network   Network notice?.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wp_flash_notices_after_clear', $this->transient, $network );
	}

	/**
	 * Render all registered notices from transient.
	 *
	 * This is a core feature of the library. We will render
	 * all admin notices using WordPress admin notice style.
	 * Still you can disable this feature using the filter and
	 * display the notices using your own custom notice system.
	 * When you disable this, make sure to clear the notices using
	 * the `clear` method.
	 * See https://codex.wordpress.org/Plugin_API/Action_Reference/admin_notices
	 * See https://codex.wordpress.org/Plugin_API/Action_Reference/network_admin_notices
	 *
	 * @since 3.2.0
	 */
	public function render() {
		/**
		 * Filter hook to disable auto rendering of admin notices.
		 *
		 * @param bool   $enable    Should render.
		 * @param string $transient Transient name to identify the plugin.
		 *
		 * @since 1.0.0
		 */
		if ( apply_filters( 'wp_flash_notices_auto_render', true, $this->transient ) ) {
			$network = is_network_admin();

			// Get all notices.
			$notices = $this->fetch( $network );

			// Loop through and render all notices
			if ( $notices && is_array( $notices ) ) {
				// Set allowed tags.
				$allowed_html      = wp_kses_allowed_html();
				$allowed_html['p'] = [];

				// Loop through each notices.
				foreach ( $notices as $notice ) {
					// We need notice content.
					if ( isset( $notice['message'] ) ) {
						// The default notice type is info.
						$type = isset( $notice['type'] ) ? $notice['type'] : 'info';
						// Add a dismissible button.
						$is_dismissible = empty( $notice['dismissible'] ) ? '' : 'is-dismissible';
						printf(
							'<div class="%s">%s</div>',
							esc_attr( "notice notice-{$type} {$is_dismissible}" ),
							wp_kses( wpautop( $notice['message'] ), $allowed_html )
						);
					}
				}
			}

			/**
			 * Action hook fired after rendering all notices by default.
			 *
			 * We are using this hook to clear all notices after rendering.
			 *
			 * @param string $transient Transient name to identify the plugin.
			 * @param array  $notices   Notices array.
			 * @param bool   $network   Is network admin?.
			 *
			 * @since 1.0.0
			 */
			do_action( 'wp_flash_notices_after_render',
				$this->transient,
				$notices,
				$network
			);
		}
	}

	/**
	 * Clear all admin notices after rendering it.
	 *
	 * This will clear all admin notices once we render them
	 * using `render` method. If you have disabled automatic render
	 * this method will not be executed.
	 *
	 * @param array $notices Notices array.
	 * @param bool  $network Is network admin?.
	 *
	 * @since 1.0.0
	 */
	public function clear_after_render( $notices, $network ) {
		/**
		 * Filter hook to disable automatic clearing of notices.
		 *
		 * NOTE: If you disable using this, make sure you clear the
		 * notices using `clear` method. Otherwise notices will be there
		 * and there no point in using this library anymore.
		 *
		 * @param bool   $enable    Should clear automatically after render?.
		 * @param string $transient Transient name to identify the plugin.
		 *
		 * @since 1.0.0
		 */
		if ( apply_filters( 'wp_flash_notices_auto_clear', true, $this->transient ) ) {
			// Clear all notices.
			$this->clear( $network );
		}
	}
}
