<?php
/**
 * Recover missing WC Price History records without modifying the vendor plugin.
 *
 * @package Meditrendy_Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const MEDITRENDY_PRICE_HISTORY_RECOVERY_ACTION = 'meditrendy_price_history_recovery_batch';
const MEDITRENDY_PRICE_HISTORY_RECOVERY_GROUP  = 'meditrendy-price-history';
const MEDITRENDY_PRICE_HISTORY_RECOVERY_OPTION = 'meditrendy_price_history_recovery_state';
const MEDITRENDY_PRICE_HISTORY_RECOVERY_BATCH  = 100;

function meditrendy_price_history_recovery_is_available(): bool {
	return class_exists( '\\PriorPrice\\HistoryStorage' ) && function_exists( 'wc_get_product' );
}

function meditrendy_price_history_recovery_schedule(): void {
	if ( function_exists( 'as_has_scheduled_action' ) && function_exists( 'as_schedule_single_action' ) ) {
		if ( ! as_has_scheduled_action( MEDITRENDY_PRICE_HISTORY_RECOVERY_ACTION, [], MEDITRENDY_PRICE_HISTORY_RECOVERY_GROUP ) ) {
			as_schedule_single_action( time() + 5, MEDITRENDY_PRICE_HISTORY_RECOVERY_ACTION, [], MEDITRENDY_PRICE_HISTORY_RECOVERY_GROUP, true );
		}
		return;
	}

	if ( ! wp_next_scheduled( MEDITRENDY_PRICE_HISTORY_RECOVERY_ACTION ) ) {
		wp_schedule_single_event( time() + 5, MEDITRENDY_PRICE_HISTORY_RECOVERY_ACTION );
	}
}

function meditrendy_price_history_recovery_get_missing_ids( int $last_id, \PriorPrice\HistoryStorage $storage ): array {
	global $wpdb;

	$price_meta = $wpdb->postmeta;
	$posts      = $wpdb->posts;
	$limit      = MEDITRENDY_PRICE_HISTORY_RECOVERY_BATCH;

	if ( $storage->should_use_tables() ) {
		$history_table = $wpdb->prefix . 'wc_price_history';
		$sql = "SELECT DISTINCT p.ID
			FROM {$posts} p
			INNER JOIN {$price_meta} price_meta ON p.ID = price_meta.post_id
			LEFT JOIN {$history_table} history ON p.ID = history.product_id AND history.include_in_history = 1
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status = 'publish'
			AND price_meta.meta_key = %s
			AND CAST(price_meta.meta_value AS DECIMAL(10,2)) > 0
			AND p.ID > %d
			AND history.id IS NULL
			ORDER BY p.ID ASC
			LIMIT {$limit}";
	} else {
		$sql = "SELECT DISTINCT p.ID
			FROM {$posts} p
			INNER JOIN {$price_meta} price_meta ON p.ID = price_meta.post_id AND price_meta.meta_key = %s
			LEFT JOIN {$price_meta} history ON p.ID = history.post_id AND history.meta_key = '_wc_price_history'
			WHERE p.post_type IN ('product', 'product_variation')
			AND p.post_status = 'publish'
			AND CAST(price_meta.meta_value AS DECIMAL(10,2)) > 0
			AND p.ID > %d
			AND history.meta_id IS NULL
			ORDER BY p.ID ASC
			LIMIT {$limit}";
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql, '_price', $last_id ) ) );
}

function meditrendy_price_history_recovery_process_batch(): void {
	$state = get_option( MEDITRENDY_PRICE_HISTORY_RECOVERY_OPTION, [] );

	if ( empty( $state['running'] ) || ! meditrendy_price_history_recovery_is_available() ) {
		return;
	}

	$storage = new \PriorPrice\HistoryStorage();
	$last_id = isset( $state['last_id'] ) ? (int) $state['last_id'] : 0;
	$ids     = meditrendy_price_history_recovery_get_missing_ids( $last_id, $storage );

	if ( empty( $ids ) ) {
		$state['running']      = false;
		$state['completed_at'] = current_time( 'mysql' );
		update_option( MEDITRENDY_PRICE_HISTORY_RECOVERY_OPTION, $state, false );
		return;
	}

	foreach ( $ids as $product_id ) {
		$storage->fill_empty_history( $product_id, [] );
	}

	$state['last_id']   = max( $ids );
	$state['processed'] = (int) ( $state['processed'] ?? 0 ) + count( $ids );
	update_option( MEDITRENDY_PRICE_HISTORY_RECOVERY_OPTION, $state, false );
	meditrendy_price_history_recovery_schedule();
}
add_action( MEDITRENDY_PRICE_HISTORY_RECOVERY_ACTION, 'meditrendy_price_history_recovery_process_batch' );

function meditrendy_price_history_recovery_admin_menu(): void {
	add_submenu_page(
		'woocommerce',
		__( 'Price History Recovery', 'meditrendy-core' ),
		__( 'Price History Recovery', 'meditrendy-core' ),
		'manage_woocommerce',
		'meditrendy-price-history-recovery',
		'meditrendy_price_history_recovery_page'
	);
}
add_action( 'admin_menu', 'meditrendy_price_history_recovery_admin_menu', 99 );

function meditrendy_price_history_recovery_page(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	if ( isset( $_POST['meditrendy_price_history_recovery_start'] ) ) {
		check_admin_referer( 'meditrendy_price_history_recovery_start' );
		update_option( MEDITRENDY_PRICE_HISTORY_RECOVERY_OPTION, [
			'running'   => true,
			'last_id'   => 0,
			'processed' => 0,
			'started_at' => current_time( 'mysql' ),
		], false );
		meditrendy_price_history_recovery_schedule();
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Recovery was scheduled. Reload this page to see progress.', 'meditrendy-core' ) . '</p></div>';
	}

	$state = get_option( MEDITRENDY_PRICE_HISTORY_RECOVERY_OPTION, [] );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Price History Recovery', 'meditrendy-core' ); ?></h1>
		<p><?php esc_html_e( 'Backfills missing WC Price History records for products and variations in batches of 100. Existing history is never changed.', 'meditrendy-core' ); ?></p>
		<p><?php printf( esc_html__( 'Processed: %d', 'meditrendy-core' ), (int) ( $state['processed'] ?? 0 ) ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'meditrendy_price_history_recovery_start' ); ?>
			<?php submit_button( __( 'Start recovery', 'meditrendy-core' ), 'primary', 'meditrendy_price_history_recovery_start', false ); ?>
		</form>
	</div>
	<?php
}
