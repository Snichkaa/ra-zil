<?php
/** Durable callback receipt, notification monitoring and operator actions. */
defined( 'ABSPATH' ) || exit;

// The standard editor otherwise changes our protected status to "pending"
// because callbacks deliberately cannot be published. Keep them in the queue.
add_filter( 'wp_insert_post_data', static function ( $data, $postarr ) {
	if ( RAZIL_CALLBACK_TYPE !== ( $data['post_type'] ?? '' ) || empty( $postarr['ID'] ) ) { return $data; }
	if ( in_array( $data['post_status'], array( 'draft', 'pending', 'publish', 'future' ), true ) ) {
		$previous = get_post_status( $postarr['ID'] );
		$data['post_status'] = in_array( $previous, array( RAZIL_CALLBACK_NEW, RAZIL_CALLBACK_DONE ), true ) ? $previous : RAZIL_CALLBACK_NEW;
	}
	return $data;
}, 10, 2 );

add_filter( 'gettext', static function ( $translation, $text, $domain ) {
	if ( 'default' === $domain && in_array( $text, array( 'Publish', 'Update', 'Submit for Review' ), true ) && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen && RAZIL_CALLBACK_TYPE === $screen->post_type ) { return 'Сохранить заявку'; }
	}
	return $translation;
}, 10, 3 );

function razil_callback_context( $service_id, string $topic ): array {
	$service = get_post( absint( $service_id ) );
	if ( ! $service || 'services' !== $service->post_type || 'publish' !== $service->post_status ) {
		$service = null;
	}
	$topics = array( 'general' => 'Обратный звонок', 'price' => 'Стоимость', 'hall' => 'Зал прощания', 'visit' => 'Согласование выезда' );
	$topic = isset( $topics[ $topic ] ) ? $topic : 'general';
	return array( 'service_id' => $service ? $service->ID : 0, 'service' => $service ? $service->post_title : '', 'service_slug' => $service ? $service->post_name : 'general', 'topic' => $topic, 'topic_label' => $topics[ $topic ] );
}

/** An opaque receipt is issued once per saved request; no phone/name in URLs. */
function razil_callback_receipt( int $id ): void {
	$token = (string) get_post_meta( $id, '_razil_cb_receipt', true );
	if ( '' === $token ) {
		$token = bin2hex( random_bytes( 24 ) );
		update_post_meta( $id, '_razil_cb_receipt', $token );
		add_option( 'razil_cb_receipt_' . hash( 'sha256', $token ), array( 'id' => $id, 'expires' => time() + HOUR_IN_SECONDS ), '', false );
	}
	setcookie( 'razil_cb_receipt', $token, array( 'expires' => time() + HOUR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
}

function razil_callback_consume_receipt(): void {
	if ( ! is_page( 'spasibo' ) ) { return; }
	nocache_headers();
	if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	if ( empty( $_COOKIE['razil_cb_receipt'] ) ) { return; }
	$token = (string) $_COOKIE['razil_cb_receipt'];
	setcookie( 'razil_cb_receipt', '', array( 'expires' => time() - HOUR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	if ( ! preg_match( '/^[a-f0-9]{48}$/', $token ) ) { return; }
	$key = 'razil_cb_receipt_' . hash( 'sha256', $token );
	$receipt = get_option( $key );
	// The atomic database deletion lets only one concurrent page consume it.
	if ( ! is_array( $receipt ) || ! delete_option( $key ) || $receipt['expires'] < time() ) { return; }
	$id = absint( $receipt['id'] );
	if ( RAZIL_CALLBACK_TYPE !== get_post_type( $id ) ) { return; }
	$context = razil_callback_context( get_post_meta( $id, '_razil_cb_service_id', true ), (string) get_post_meta( $id, '_razil_cb_topic', true ) );
	$GLOBALS['razil_callback_receipt'] = array( 'event_id' => (string) get_post_meta( $id, '_razil_cb_event_id', true ), 'service' => $context['service_slug'], 'topic' => $context['topic'] );
	update_post_meta( $id, '_razil_cb_confirmation_at', gmdate( 'c' ) );
}
add_action( 'template_redirect', 'razil_callback_consume_receipt', 1 );

/** wp_mail success means transport acceptance, never confirmed human delivery. */
function razil_callback_notify_saved( int $id ): bool {
	if ( RAZIL_CALLBACK_TYPE !== get_post_type( $id ) ) { return false; }
	$context = razil_callback_context( get_post_meta( $id, '_razil_cb_service_id', true ), (string) get_post_meta( $id, '_razil_cb_topic', true ) );
	$attempts = (int) get_post_meta( $id, '_razil_cb_notify_attempts', true ) + 1;
	update_post_meta( $id, '_razil_cb_notify_attempts', $attempts );
	update_post_meta( $id, '_razil_cb_notify_at', gmdate( 'c' ) );
	update_post_meta( $id, '_razil_cb_notify_status', 'pending' );
	$error_code = '';
	$failed = static function ( $error ) use ( &$error_code ) { $error_code = sanitize_key( $error->get_error_code() ); };
	add_action( 'wp_mail_failed', $failed );
	try {
		$sent = razil_callback_notify( get_the_title( $id ), (string) get_post_meta( $id, RAZIL_CALLBACK_PHONE_KEY, true ), (string) get_post_meta( $id, RAZIL_CALLBACK_PAGE_KEY, true ), $id, $context );
	} catch ( Throwable $error ) {
		$sent = false;
		$error_code = 'transport_exception';
	} finally { remove_action( 'wp_mail_failed', $failed ); }
	update_post_meta( $id, '_razil_cb_notify_status', $sent ? 'accepted' : 'failed' );
	update_post_meta( $id, '_razil_cb_notify_error', $sent ? '' : ( $error_code ?: 'transport_rejected' ) );
	return $sent;
}

function razil_callback_workflow_labels(): array {
	return array( 'pending' => 'Ещё не обработана', 'contacted' => 'Связались', 'consultation' => 'Целевая консультация', 'not_target' => 'Нецелевое обращение', 'duplicate' => 'Повтор другого обращения', 'test' => 'Тестовая заявка' );
}

function razil_callback_workflow_box( WP_Post $post ): void {
	$context = razil_callback_context( get_post_meta( $post->ID, '_razil_cb_service_id', true ), (string) get_post_meta( $post->ID, '_razil_cb_topic', true ) );
	$status = (string) get_post_meta( $post->ID, '_razil_cb_notify_status', true );
	$labels = array( 'pending' => 'Уведомление ожидает отправки', 'accepted' => 'Почтовый транспорт принял письмо; получение оператором ещё не подтверждено', 'failed' => 'Ошибка уведомления. Заявка сохранена' );
	echo '<p><strong>Тема:</strong> ' . esc_html( $context['topic_label'] ) . '</p><p><strong>Услуга:</strong> ' . esc_html( $context['service'] ?: 'Общий вопрос' ) . '</p>';
	echo '<p><strong>Уведомление:</strong> ' . esc_html( $labels[ $status ] ?? 'Для старой заявки статус не записывался' ) . '</p>';
	echo '<p>Попыток: ' . (int) get_post_meta( $post->ID, '_razil_cb_notify_attempts', true ) . '. Последняя: ' . esc_html( (string) get_post_meta( $post->ID, '_razil_cb_notify_at', true ) ) . '</p>';
	// Controls belong to the WordPress post form; no nested forms.
	wp_nonce_field( 'razil_callback_workflow_' . $post->ID, 'razil_cb_workflow_nonce' );
	echo '<p><label for="razil-cb-outcome">Результат обработки</label><br><select id="razil-cb-outcome" name="razil_cb_outcome">';
	foreach ( razil_callback_workflow_labels() as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( get_post_meta( $post->ID, '_razil_cb_outcome', true ) ?: 'pending', $value, false ) . '>' . esc_html( $label ) . '</option>'; }
	echo '</select></p><p><label><input type="checkbox" name="razil_cb_retry" value="1"> Повторить уведомление при сохранении этой заявки</label></p>';
	echo '<p>Повтор уведомления не создаёт новую заявку или конверсию. Для результата «Целевая консультация» нужно подтверждение оператора.</p>';
}
add_action( 'add_meta_boxes_' . RAZIL_CALLBACK_TYPE, static function () { add_meta_box( 'razil-cb-workflow', 'Обработка и уведомление', 'razil_callback_workflow_box', RAZIL_CALLBACK_TYPE, 'normal', 'high' ); } );

add_action( 'save_post_' . RAZIL_CALLBACK_TYPE, static function ( $id ) {
	if ( ! current_user_can( 'manage_options' ) || empty( $_POST['razil_cb_workflow_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['razil_cb_workflow_nonce'] ) ), 'razil_callback_workflow_' . $id ) ) { return; }
	$outcome = sanitize_key( $_POST['razil_cb_outcome'] ?? '' );
	if ( isset( razil_callback_workflow_labels()[ $outcome ] ) ) {
		update_post_meta( $id, '_razil_cb_outcome', $outcome );
		update_post_meta( $id, '_razil_cb_operator_id', get_current_user_id() );
		update_post_meta( $id, '_razil_cb_processed_at', gmdate( 'c' ) );
	}
	if ( ! empty( $_POST['razil_cb_retry'] ) ) { razil_callback_notify_saved( $id ); }
} );

add_filter( 'manage_' . RAZIL_CALLBACK_TYPE . '_posts_columns', static function ( $columns ) { $columns['razil_cb_notification'] = 'Уведомление / результат'; return $columns; } );
add_action( 'manage_' . RAZIL_CALLBACK_TYPE . '_posts_custom_column', static function ( $column, $id ) {
	if ( 'razil_cb_notification' !== $column ) { return; }
	$status = get_post_meta( $id, '_razil_cb_notify_status', true );
	$labels = array( 'pending' => 'Ожидает отправки', 'accepted' => 'Принято почтой', 'failed' => 'ОШИБКА: повторить уведомление' );
	echo esc_html( $labels[ $status ] ?? 'Неизвестно' ) . '<br>';
	$outcomes = razil_callback_workflow_labels();
	echo esc_html( $outcomes[ get_post_meta( $id, '_razil_cb_outcome', true ) ?: 'pending' ] ?? '' );
}, 10, 2 );
add_action( 'admin_notices', static function () {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$query = new WP_Query( array( 'post_type' => RAZIL_CALLBACK_TYPE, 'post_status' => array( RAZIL_CALLBACK_NEW, RAZIL_CALLBACK_DONE ), 'posts_per_page' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_razil_cb_notify_status', 'value' => array( 'failed','pending' ), 'compare' => 'IN' ) ) ) );
	if ( $query->found_posts ) { echo '<div class="notice notice-error"><p>У ' . (int) $query->found_posts . ' заявок не подтверждена отправка уведомления. Заявки сохранены. <a href="' . esc_url( admin_url( 'edit.php?post_type=callback' ) ) . '">Откройте очередь и повторите уведомление</a>.</p></div>'; }
} );

add_action( RAZIL_CALLBACK_CLEANUP_HOOK, static function () {
	global $wpdb;
	$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'razil_cb_receipt_%' LIMIT 500" );
	foreach ( $names as $name ) { $row = get_option( $name ); if ( ! is_array( $row ) || ( $row['expires'] ?? 0 ) < time() ) { delete_option( $name ); } }
} );
