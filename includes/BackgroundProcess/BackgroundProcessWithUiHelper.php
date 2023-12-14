<?php

namespace TeraPixelNewsGenerator\BackgroundProcess;

use Stackonet\WP\Framework\BackgroundProcessing\BackgroundProcess;

defined( 'ABSPATH' ) || exit;

/**
 * BackgroundProcessWithUiHelper class
 */
abstract class BackgroundProcessWithUiHelper extends BackgroundProcess {
	/**
	 * Show admin notice or not
	 *
	 * @var bool
	 */
	protected $show_admin_notice = true;

	/**
	 * Admin notice heading
	 *
	 * @var string
	 */
	protected $admin_notice_heading = 'A background task is running to process {{total_items}} items.';

	/**
	 * Capability required to perform view/clear operation
	 *
	 * @var string
	 */
	protected $capability = 'manage_options';

	/**
	 * Class constructor
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'shutdown', [ $this, 'save_and_dispatch' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
		add_action( 'wp_ajax_run_manual_task_' . $this->action, [ $this, 'run_manual_task' ] );
	}

	/**
	 * Save and run background on shutdown of all code
	 */
	public function save_and_dispatch() {
		if ( ! empty( $this->data ) ) {
			$this->save();
		}
	}

	/**
	 * Show admin status notice
	 *
	 * @return void
	 */
	public function admin_notices() {
		if ( false === $this->show_admin_notice ) {
			return;
		}
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}
		$total_items = count( $this->get_pending_items() );
		if ( $total_items < 1 ) {
			return;
		}

		$message = str_replace( '{{total_items}}', $total_items, $this->admin_notice_heading );

		?>
		<div class="notice notice-info is-dismissible">
			<p><?php echo esc_html( $message ); ?></p>
			<p>
				<a href="<?php echo esc_url( $this->get_task_view_url() ); ?>" class="button button-primary">View</a>
				<a href="<?php echo esc_url( $this->get_task_clear_url() ); ?>" class="button is-error">Clear Task</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Run a manual tasks
	 *
	 * @return void
	 */
	public function run_manual_task() {
		if (
			current_user_can( $this->capability ) &&
			isset( $_GET['_token'] ) &&
			wp_verify_nonce( $_GET['_token'], 'background_process_manual_action' )
		) {
			$task       = isset( $_GET['task'] ) ? sanitize_text_field( $_GET['task'] ) : '';
			$batch_key  = isset( $_GET['batch'] ) ? sanitize_text_field( $_GET['batch'] ) : '';
			$item_index = isset( $_GET['item_index'] ) ? intval( $_GET['item_index'] ) : '';
			$batches    = $this->get_batches_as_array();

			if ( 'view_process' === $task ) {
				if ( count( $batches ) ) {
					$this->view_pending_tasks( $batches );
				} else {
					$task = 'redirect_back';
				}
			}
			if ( 'clear_process' === $task ) {
				$this->delete_all();
			}
			if ( 'clear_process_batch' === $task ) {
				if ( isset( $batches[ $batch_key ] ) ) {
					$this->delete( $batch_key );
				}
			}
			if ( 'clear_process_item' === $task ) {
				$this->remove_from_batches( $batch_key, $item_index );
			}
			if ( 'run_process_batch' === $task ) {
				$this->handle_batch( $batch_key );
			}
			if ( 'run_process_item' === $task ) {
				$payload = $batches[ $batch_key ] ?? [];
				if ( ! empty( $payload[ $item_index ] ) ) {
					$task = $this->task( $payload[ $item_index ] );
					if ( false === $task ) {
						$this->remove_from_batches( $batch_key, $item_index );
					} else {
						$attempt = isset( $_GET['attempt'] ) ? intval( $_GET['attempt'] ) : 1;
						// Refresh current page.
						if ( $attempt < 3 ) {
							$refresh_url = add_query_arg(
								[ 'attempt' => strval( $attempt + 1 ) ],
								site_url( $_SERVER['REQUEST_URI'] )
							);
							wp_safe_redirect( $refresh_url );
							exit();
						}
					}
				}
			}

			if ( 'view_process' !== $task ) {
				$_referer    = isset( $_GET['_referer'] ) ? urldecode( $_GET['_referer'] ) : '';
				$redirect_to = $_referer ? site_url( $_referer ) : admin_url();
				wp_safe_redirect( $redirect_to );
				exit();
			}
		}
		die;
	}

	/**
	 * Get task clear url
	 *
	 * @return string
	 */
	public function get_task_clear_url(): string {
		return $this->build_ajax_url( 'clear_process' );
	}

	/**
	 * Get task view url
	 *
	 * @return string
	 */
	public function get_task_view_url(): string {
		return $this->build_ajax_url( 'view_process' );
	}

	/**
	 * Get run now action url.
	 *
	 * @param string|int $batch_key Batch key.
	 * @param string|int $index Item index.
	 *
	 * @return string
	 */
	public function get_run_process_item_url( $batch_key, $index ): string {
		return $this->build_ajax_url(
			'run_process_item',
			[
				'batch'      => $batch_key,
				'item_index' => $index,
			]
		);
	}

	/**
	 * Get run now action url.
	 *
	 * @param string|int $batch_key Batch key.
	 * @param string|int $index Item index.
	 *
	 * @return string
	 */
	public function get_clear_process_item_url( $batch_key, $index ): string {
		return $this->build_ajax_url(
			'clear_process_item',
			[
				'batch'      => $batch_key,
				'item_index' => $index,
			]
		);
	}

	/**
	 * Get run now action url.
	 *
	 * @param string|int $batch_key Batch key.
	 *
	 * @return string
	 */
	public function get_clear_process_batch_url( $batch_key ): string {
		return $this->build_ajax_url( 'clear_process_batch', [ 'batch' => $batch_key ] );
	}

	/**
	 * Get run now action url.
	 *
	 * @param string|int $batch_key Batch key.
	 *
	 * @return string
	 */
	public function get_run_process_batch_url( $batch_key ): string {
		return $this->build_ajax_url( 'run_process_batch', [ 'batch' => $batch_key ] );
	}

	/**
	 * Get pending background task items
	 *
	 * @return array
	 */
	public function get_pending_items(): array {
		$batches = $this->get_batches_as_array();

		$tasks = [];
		foreach ( $batches as $result ) {
			foreach ( $result as $value ) {
				$tasks[] = $value;
			}
		}

		return $tasks;
	}

	/**
	 * Has pending items
	 *
	 * @return bool
	 */
	public function has_pending_items(): bool {
		return ! empty( $this->get_pending_items() );
	}

	/**
	 * Get pending batches
	 *
	 * @return array
	 */
	public function get_batches_as_array(): array {
		global $wpdb;
		$table        = $wpdb->options;
		$column       = 'option_name';
		$key_column   = 'option_id';
		$value_column = 'option_value';
		if ( is_multisite() ) {
			$table        = $wpdb->sitemeta;
			$column       = 'meta_key';
			$key_column   = 'meta_id';
			$value_column = 'meta_value';
		}
		$key     = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$column} LIKE %s ORDER BY {$key_column} ASC",
				$key
			),
			ARRAY_A
		);

		$tasks = [];
		foreach ( $results as $result ) {
			$tasks[ $result[ $column ] ] = maybe_unserialize( $result[ $value_column ] );
		}

		return $tasks;
	}

	/**
	 * Handle next batch
	 *
	 * @return void
	 */
	public function handle_next_lifo_batch_item() {
		$this->lock_process();
		$batch = $this->get_batches_as_array();
		if ( count( array_keys( $batch ) ) > 1 ) {
			$batch = array_reverse( $batch );
		}
		foreach ( $batch as $batch_key => $batch_items ) {
			foreach ( $batch_items as $key => $value ) {
				$task = $this->task( $value );

				if ( false !== $task ) {
					$batch_items[ $key ] = $task;
				} else {
					unset( $batch_items[ $key ] );
				}

				// Keep the batch up to date while processing it.
				if ( ! empty( $batch_items ) ) {
					$this->update( $batch_key, $batch_items );
				}

				// Only process one item.
				break;
			}
			if ( empty( $batch_items ) ) {
				$this->delete( $batch_key );
			}
			// Only process first batch.
			break;
		}

		$this->unlock_process();

		// Start next batch or complete process.
		if ( ! $this->is_queue_empty() ) {
			$this->dispatch();
		} else {
			$this->complete();
		}
	}

	/**
	 * Handle next batch
	 *
	 * @return void
	 */
	public function handle_next_lifo_batch() {
		$batch = $this->get_batches_as_array();
		if ( count( array_keys( $batch ) ) > 1 ) {
			$batch = array_reverse( $batch );
		}
		foreach ( $batch as $batch_key => $value ) {
			$this->handle_batch( $batch_key );
			break;
		}
	}

	/**
	 * Process a batch
	 *
	 * @param string $batch_key The batch key.
	 *
	 * @return void
	 */
	protected function handle_batch( string $batch_key ) {
		$this->lock_process();

		do {
			$batch = $this->get_batches_as_array();
			$items = $batch[ $batch_key ] ?? [];

			foreach ( $items as $key => $value ) {
				$task = $this->task( $value );

				if ( false !== $task ) {
					$items[ $key ] = $task;
				} else {
					unset( $items[ $key ] );
				}

				// Keep the batch up to date while processing it.
				if ( ! empty( $items ) ) {
					$this->update( $batch_key, $items );
				}

				// Batch limits reached, or pause or cancel request.
				if ( $this->time_exceeded() || $this->memory_exceeded() || $this->is_paused() || $this->is_cancelled() ) {
					break;
				}
			}

			// Delete current batch if fully processed.
			if ( empty( $items ) ) {
				$this->delete( $batch_key );
			}
		} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() && ! $this->is_paused() && ! $this->is_cancelled() );

		$this->unlock_process();

		// Start next batch or complete process.
		if ( ! $this->is_queue_empty() ) {
			$this->dispatch();
		} else {
			$this->complete();
		}
	}

	/**
	 * Remove an item from batches
	 *
	 * @param string|int $batch_key Batch key name.
	 * @param string|int $item_index Item index.
	 *
	 * @return void
	 */
	public function remove_from_batches( $batch_key, $item_index ) {
		$batches     = $this->get_batches_as_array();
		$batch_items = $batches[ $batch_key ] ?? [];
		if ( isset( $batch_items[ $item_index ] ) ) {
			unset( $batch_items[ $item_index ] );
		}

		if ( empty( $batch_items ) ) {
			$this->delete( $batch_key );
		} else {
			$this->update( $batch_key, $batch_items );
		}
	}

	/**
	 * View pending task items list
	 *
	 * @param array $batches List of batches.
	 *
	 * @return void
	 */
	public function view_pending_tasks( array $batches ) {
		$_referer    = isset( $_GET['_referer'] ) ? urldecode( $_GET['_referer'] ) : '';
		$redirect_to = $_referer ? site_url( $_referer ) : admin_url();

		$html = $this->style();

		$html .= '<div class="container">';
		$html .= '<div class="mb-4">';
		$html .= sprintf(
			'<a class="button" href="%s" style="font-size: 18px">Back</a>',
			$redirect_to
		);
		$html .= '</div>' . PHP_EOL;

		foreach ( $batches as $batch_key => $tasks ) {
			$clear_process_batch_url = $this->get_clear_process_batch_url( $batch_key );
			$run_process_batch       = $this->get_run_process_batch_url( $batch_key );

			$html .= '<h2>' . $batch_key . '</h2>';
			$html .= '<div class="mb-4 flex space-between">';
			$html .= '<div>' . sprintf( 'Total items: %s', count( $tasks ) ) . '</div>';
			$html .= '<div class="flex space-x-2">' . PHP_EOL;
			$html .= sprintf(
				'<a class="button bg-green-800 text-white" href="%s">Run Batch</a>',
				$run_process_batch
			);
			$html .= sprintf(
				'<a class="button bg-red-600 text-white" href="%s">Delete %s items</a>',
				$clear_process_batch_url,
				count( $tasks )
			);
			$html .= '</div>' . PHP_EOL;
			$html .= '</div>' . PHP_EOL;
			foreach ( $tasks as $index => $task ) {
				$action_url     = $this->get_run_process_item_url( $batch_key, $index );
				$remove_now_url = $this->get_clear_process_item_url( $batch_key, $index );

				$html .= '<div class="card">' . PHP_EOL;

				$html .= '<div class="card__content">' . PHP_EOL;

				if ( method_exists( $this, 'process_item_card_content_html' ) ) {
					$html .= $this->process_item_card_content_html( $task );
				} else {
					$html .= '<pre class="m-0"><code>';
					$html .= wp_json_encode( $task, JSON_PRETTY_PRINT );
					$html .= '</code></pre>';
				}

				$html .= '</div>' . PHP_EOL;

				$html .= '<div class="card__actions flex flex-col space-y-2">' . PHP_EOL;
				$html .= '<a class="button" href="' . $action_url . '">Run Now</a>' . PHP_EOL;
				$html .= '<a class="button bg-red-600 text-white" href="' . $remove_now_url . '">Remove</a>' . PHP_EOL;
				$html .= '</div>' . PHP_EOL;

				$html .= '</div>' . PHP_EOL . PHP_EOL;
			}
		}
		$html .= '</div>';

		echo $html;
		wp_die();
	}

	/**
	 * Build ajax URL
	 *
	 * @param string $task Ajax task.
	 * @param array $args URI parameters.
	 *
	 * @return string
	 */
	protected function build_ajax_url( string $task, array $args = [] ): string {
		$parameters = [
			'action'   => 'run_manual_task_' . $this->action,
			'task'     => $task,
			'_referer' => rawurlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ),
		];

		return wp_nonce_url(
			add_query_arg( array_merge( $parameters, $args ), admin_url( 'admin-ajax.php' ) ),
			'background_process_manual_action',
			'_token'
		);
	}

	/**
	 * General style for pending task view
	 *
	 * @return string
	 */
	public function style(): string {
		$style = '<style type="text/css">';

		$style .= '
			.m-0 {margin:0}.mb-4{margin-bottom: 1rem}
			.flex {display:flex;}
			.flex-col{flex-direction:column;}
			.space-between {justify-content:space-between;}
			.space-x-2 > * + * {margin-left: 0.5rem}
			.space-y-2 > * + * {margin-top: 0.5rem}
			.bg-red-600	{background-color: rgb(220 38 38)}
			.bg-green-800 {background-color: rgb(22 101 52)}
			.text-white {color: #fff}
		';
		$style .= '.card {
		    display:flex;
		    justify-content:space-between;
		    align-items:center;
		    margin-bottom: 8px;
		    border: 1px solid rgba(0,0,0,.12);
		    padding:8px;
		}';
		$style .= '.button {
		    display:inline-flex;
		    border: 1px solid rgba(0,0,0,.12);
		    padding:8px;
		    border-radius:4px;
		    text-decoration:none;
		    line-height: 1em;
		    font-size: .75rem;
		}';
		$style .= '.container {
			margin:16px auto;
			max-width:960;
			display:block;
		}';
		$style .= '</style>';

		return $style;
	}
}
