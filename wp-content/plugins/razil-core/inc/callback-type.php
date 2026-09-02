<?php
/**
 * Заявки на обратный звонок: хранение и работа с ними в админке.
 *
 * Заявка — это имя и телефон человека, который просит перезвонить.
 * Персональные данные, поэтому тип записи закрыт наглухо: ни публичного
 * адреса, ни архива, ни выдачи в поиске, ни REST. Смотреть и удалять
 * может только тот, кто управляет сайтом.
 *
 * Создаются заявки программой из формы, а не руками в админке, поэтому
 * create_posts запрещён: пустая заявка, набранная в редакторе, означала бы
 * только путаницу в списке.
 *
 * Состояние обработки сделано статусами записи, а не метой. Так в списке
 * сами собой появляются ссылки «Новые (3) | Перезвонили (12)» со счётчиками,
 * а фильтровать мету пришлось бы вручную и без счётчиков.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Тип записи.
 */
const RAZIL_CALLBACK_TYPE = 'callback';

/**
 * Статус «новая»: заявка пришла, ей ещё не занимались.
 */
const RAZIL_CALLBACK_NEW = 'rz-cb-new';

/**
 * Статус «перезвонили»: заявка отработана.
 */
const RAZIL_CALLBACK_DONE = 'rz-cb-done';

/**
 * Ключ мета-поля с телефоном.
 *
 * С подчёркиванием: иначе поле вылезло бы на экран произвольных полей.
 */
const RAZIL_CALLBACK_PHONE_KEY = '_razil_cb_phone';

/**
 * Ключ мета-поля со страницей, с которой отправлена заявка.
 */
const RAZIL_CALLBACK_PAGE_KEY = '_razil_cb_page';

/**
 * Действие переключения статуса.
 */
const RAZIL_CALLBACK_TOGGLE = 'razil_cb_toggle';

/**
 * Регистрация типа записи.
 */
function razil_register_callback_type(): void {
	register_post_type(
		RAZIL_CALLBACK_TYPE,
		array(
			'labels'              => array(
				'name'          => 'Обратный звонок',
				'singular_name' => 'Заявка на звонок',
				'edit_item'     => 'Заявка на обратный звонок',
				'view_item'     => 'Заявка на обратный звонок',
				'search_items'  => 'Искать заявки',
				'not_found'     => 'Заявок нет',
				'menu_name'     => 'Обратный звонок',
				'all_items'     => 'Все заявки',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			// Заявка не должна попасть ни в REST, ни в поиск, ни в адреса.
			'show_in_rest'        => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-phone',
			'menu_position'       => 25,
			'hierarchical'        => false,
			'supports'            => array( 'title' ),
			/*
			 * Всё под manage_options: заявки видит и удаляет только тот,
			 * кто управляет сайтом. map_meta_cap намеренно false — тогда
			 * edit_post, read_post и delete_post берутся из этого же списка
			 * буквально, без промежуточного отображения, и правило
			 * «только администратор» читается одним взглядом.
			 *
			 * do_not_allow, а не false: false здесь означал бы проверку
			 * несуществующего права и работал бы случайно.
			 */
			'capabilities'        => array(
				'create_posts'        => 'do_not_allow',
				'publish_posts'       => 'do_not_allow',
				'edit_post'           => 'manage_options',
				'read_post'           => 'manage_options',
				'delete_post'         => 'manage_options',
				'edit_posts'          => 'manage_options',
				'edit_others_posts'   => 'manage_options',
				'delete_posts'        => 'manage_options',
				'delete_others_posts' => 'manage_options',
				'read_private_posts'  => 'manage_options',
			),
			'map_meta_cap'        => false,
		)
	);

	register_post_status(
		RAZIL_CALLBACK_NEW,
		array(
			'label'                     => 'Новая',
			'public'                    => false,
			'internal'                  => false,
			/*
			 * protected обязателен. WP_Query в админке подбирает записи
			 * только по публичным и защищённым статусам: без этого флага
			 * счётчики «Новые (3)» считались бы, а список оставался пустым.
			 * Ядро регистрирует draft и pending точно так же.
			 */
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s — число заявок. */
			'label_count'               => _n_noop( 'Новая <span class="count">(%s)</span>', 'Новые <span class="count">(%s)</span>' ),
		)
	);

	register_post_status(
		RAZIL_CALLBACK_DONE,
		array(
			'label'                     => 'Перезвонили',
			'public'                    => false,
			'internal'                  => false,
			/*
			 * protected обязателен. WP_Query в админке подбирает записи
			 * только по публичным и защищённым статусам: без этого флага
			 * счётчики «Новые (3)» считались бы, а список оставался пустым.
			 * Ядро регистрирует draft и pending точно так же.
			 */
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s — число заявок. */
			'label_count'               => _n_noop( 'Перезвонили <span class="count">(%s)</span>', 'Перезвонили <span class="count">(%s)</span>' ),
		)
	);
}
add_action( 'init', 'razil_register_callback_type', 5 );

/**
 * Регистрация мета-полей заявки.
 *
 * show_in_rest намеренно false: сам тип записи из REST исключён, и телефон
 * не должен утечь через выдачу мета-полей.
 */
function razil_register_callback_meta(): void {
	foreach ( array( RAZIL_CALLBACK_PHONE_KEY, RAZIL_CALLBACK_PAGE_KEY ) as $key ) {
		register_post_meta(
			RAZIL_CALLBACK_TYPE,
			$key,
			array(
				'type'          => 'string',
				'single'        => true,
				'default'       => '',
				'show_in_rest'  => false,
				'auth_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}
}
add_action( 'init', 'razil_register_callback_meta', 10 );

/**
 * Человеческое название статуса.
 *
 * @param string $status Внутреннее имя статуса.
 */
function razil_callback_status_label( string $status ): string {
	$map = array(
		RAZIL_CALLBACK_NEW  => 'Новая',
		RAZIL_CALLBACK_DONE => 'Перезвонили',
	);

	return $map[ $status ] ?? $status;
}

/**
 * Колонки списка заявок.
 *
 * Собираем свой набор, а не дополняем штатный: у этого типа записи нет
 * ни автора, ни рубрик, ни комментариев, а колонка даты у нестандартного
 * статуса показывает «Последнее изменение» вместо времени заявки.
 *
 * @param array<string, string> $columns Штатные колонки.
 *
 * @return array<string, string>
 */
function razil_callback_columns( array $columns ): array {
	return array(
		'cb'               => $columns['cb'] ?? '',
		'title'            => 'Имя',
		'razil_cb_phone'   => 'Телефон',
		'razil_cb_status'  => 'Статус',
		'razil_cb_date'    => 'Получена',
	);
}
add_filter( 'manage_' . RAZIL_CALLBACK_TYPE . '_posts_columns', 'razil_callback_columns' );

/**
 * Содержимое колонок.
 *
 * @param string $column  Ключ колонки.
 * @param int    $post_id Идентификатор заявки.
 */
function razil_callback_column_content( string $column, int $post_id ): void {
	if ( 'razil_cb_phone' === $column ) {
		$phone = (string) get_post_meta( $post_id, RAZIL_CALLBACK_PHONE_KEY, true );

		if ( '' === $phone ) {
			echo '—';

			return;
		}

		// Ссылка tel: прямо из списка: набирать номер руками, глядя
		// в экран, — лишний повод ошибиться цифрой.
		printf(
			'<a href="%s">%s</a>',
			esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $phone ) ),
			esc_html( $phone )
		);

		return;
	}

	if ( 'razil_cb_status' === $column ) {
		$status = get_post_status( $post_id );

		printf(
			'<span class="razil-cb-status razil-cb-status--%s">%s</span>',
			esc_attr( RAZIL_CALLBACK_DONE === $status ? 'done' : 'new' ),
			esc_html( razil_callback_status_label( (string) $status ) )
		);

		return;
	}

	if ( 'razil_cb_date' === $column ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			echo '—';

			return;
		}

		echo esc_html( get_date_from_gmt( $post->post_date_gmt, 'd.m.Y H:i' ) );

		/*
		 * Заявка без ответа дольше двух недель помечается прямо в строке.
		 * Счётчика над списком мало: он говорит, что забытые есть,
		 * но не показывает, какие именно.
		 *
		 * Текстом, а не цветом: своей таблицы стилей у плагина в админке
		 * нет, а надпись читается в любом оформлении.
		 */
		$waiting = razil_callback_waiting_days( $post );

		if ( $waiting >= RAZIL_CALLBACK_STALE_DAYS ) {
			printf(
				'<br /><strong>%d %s без ответа</strong>',
				(int) $waiting,
				esc_html( razil_callback_days_word( $waiting ) )
			);
		}
	}
}
add_action( 'manage_' . RAZIL_CALLBACK_TYPE . '_posts_custom_column', 'razil_callback_column_content', 10, 2 );

/**
 * Сортировка списка: сначала свежие.
 *
 * @param WP_Query $query Запрос списка.
 */
function razil_callback_admin_order( WP_Query $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( RAZIL_CALLBACK_TYPE !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( '' === (string) $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'date' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'razil_callback_admin_order' );

/**
 * Действие в строке списка: переключить статус.
 *
 * @param array<string, string> $actions Ссылки строки.
 * @param WP_Post               $post    Заявка.
 *
 * @return array<string, string>
 */
function razil_callback_row_actions( array $actions, WP_Post $post ): array {
	if ( RAZIL_CALLBACK_TYPE !== $post->post_type ) {
		return $actions;
	}

	/*
	 * Быструю правку убираем: править в заявке нечего, а она предлагает
	 * менять заголовок и дату. Просмотр — тоже: публичного адреса нет.
	 * «Изменить» оставляем, это единственный способ открыть одну заявку
	 * целиком; на её экране метабокс ниже показывает телефон и страницу.
	 */
	unset( $actions['inline hide-if-no-js'], $actions['view'] );

	if ( ! current_user_can( 'manage_options' ) ) {
		return $actions;
	}

	$done = RAZIL_CALLBACK_DONE === $post->post_status;

	$url = wp_nonce_url(
		add_query_arg(
			array(
				'action' => RAZIL_CALLBACK_TOGGLE,
				'post'   => $post->ID,
			),
			admin_url( 'admin-post.php' )
		),
		RAZIL_CALLBACK_TOGGLE . '_' . $post->ID
	);

	$toggle = array(
		'razil_cb_toggle' => sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			$done ? 'Вернуть в новые' : 'Отметить: перезвонили'
		),
	);

	// Переключатель первым: это основное действие в списке.
	return $toggle + $actions;
}
add_filter( 'post_row_actions', 'razil_callback_row_actions', 10, 2 );

/**
 * Обработчик переключения статуса.
 */
function razil_callback_toggle(): void {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

	if ( ! $post_id || ! wp_verify_nonce( $nonce, RAZIL_CALLBACK_TOGGLE . '_' . $post_id ) ) {
		wp_die( 'Ссылка устарела. Обновите список заявок и попробуйте снова.', '', array( 'response' => 403 ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Недостаточно прав.', '', array( 'response' => 403 ) );
	}

	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post || RAZIL_CALLBACK_TYPE !== $post->post_type ) {
		wp_die( 'Заявка не найдена.', '', array( 'response' => 404 ) );
	}

	$next = RAZIL_CALLBACK_DONE === $post->post_status ? RAZIL_CALLBACK_NEW : RAZIL_CALLBACK_DONE;

	wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => $next,
		)
	);

	wp_safe_redirect(
		add_query_arg(
			array(
				'post_type'       => RAZIL_CALLBACK_TYPE,
				'razil_cb_status' => $next,
			),
			admin_url( 'edit.php' )
		)
	);
	exit;
}
add_action( 'admin_post_' . RAZIL_CALLBACK_TOGGLE, 'razil_callback_toggle' );

/**
 * Сообщение после переключения статуса.
 */
function razil_callback_toggle_notice(): void {
	if ( ! isset( $_GET['razil_cb_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'edit-' . RAZIL_CALLBACK_TYPE !== $screen->id ) {
		return;
	}

	$status = sanitize_key( wp_unslash( $_GET['razil_cb_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $status, array( RAZIL_CALLBACK_NEW, RAZIL_CALLBACK_DONE ), true ) ) {
		return;
	}

	printf(
		'<div class="notice notice-success is-dismissible"><p>Статус заявки изменён: %s.</p></div>',
		esc_html( razil_callback_status_label( $status ) )
	);
}
add_action( 'admin_notices', 'razil_callback_toggle_notice' );

/**
 * Метабокс с данными заявки на экране её правки.
 *
 * Читать, а не править: телефон и страница обращения — это то, что прислал
 * посетитель, и менять их в админке незачем. Поэтому не поля ввода, а текст.
 */
function razil_callback_meta_box(): void {
	add_meta_box(
		'razil-callback-data',
		'Данные заявки',
		'razil_callback_render_meta_box',
		RAZIL_CALLBACK_TYPE,
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'razil_callback_meta_box' );

/**
 * Содержимое метабокса.
 *
 * @param WP_Post $post Заявка.
 */
function razil_callback_render_meta_box( WP_Post $post ): void {
	$phone = (string) get_post_meta( $post->ID, RAZIL_CALLBACK_PHONE_KEY, true );
	$page  = (string) get_post_meta( $post->ID, RAZIL_CALLBACK_PAGE_KEY, true );

	echo '<p><strong>Телефон</strong><br />';

	if ( '' !== $phone ) {
		printf(
			'<a href="%s">%s</a>',
			esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $phone ) ),
			esc_html( $phone )
		);
	} else {
		echo '—';
	}

	echo '</p>';

	printf(
		'<p><strong>Получена</strong><br />%s</p>',
		esc_html( get_date_from_gmt( $post->post_date_gmt, 'd.m.Y в H:i' ) )
	);

	printf(
		'<p><strong>Статус</strong><br />%s</p>',
		esc_html( razil_callback_status_label( (string) $post->post_status ) )
	);

	echo '<p><strong>Страница обращения</strong><br />';

	if ( '' !== $page ) {
		printf( '<a href="%1$s">%1$s</a>', esc_url( $page ) );
	} else {
		echo '—';
	}

	echo '</p>';
}

/* ==================================================== СРОК ХРАНЕНИЯ */

/**
 * Имя события ежедневной уборки.
 */
const RAZIL_CALLBACK_CLEANUP_HOOK = 'razil_callback_cleanup';

/**
 * Сколько дней без ответа считать долгим.
 *
 * Заявка на звонок срочная по своей природе: человек просит перезвонить
 * сегодня, а не когда-нибудь. Две недели тишины — это уже не «не успели»,
 * а «потеряли», и такую заявку в списке надо видеть сразу.
 */
const RAZIL_CALLBACK_STALE_DAYS = 14;

/**
 * Сколько заявок удалять за один проход.
 *
 * Ограничение от разового долгого запроса: если уборка почему-то
 * не выполнялась месяцами, разгребать накопившееся она будет за несколько
 * суток, по порции в день, а не одним запросом на всю таблицу.
 */
const RAZIL_CALLBACK_CLEANUP_BATCH = 200;

/**
 * Срок хранения из настроек, дни. Ноль — не удалять.
 */
function razil_callback_keep_days(): int {
	return razil_org_number( 'keep_days', 60 );
}

/**
 * Постановка ежедневной уборки в расписание.
 *
 * На init, а не только при активации: плагин уже активен, и на активацию
 * рассчитывать нельзя — событие не появилось бы до следующего включения.
 * Проверка wp_next_scheduled делает вызов безопасным при каждом запросе.
 */
function razil_callback_schedule_cleanup(): void {
	if ( wp_next_scheduled( RAZIL_CALLBACK_CLEANUP_HOOK ) ) {
		return;
	}

	wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', RAZIL_CALLBACK_CLEANUP_HOOK );
}
add_action( 'init', 'razil_callback_schedule_cleanup', 20 );

/**
 * Ежедневная уборка отработанных заявок.
 *
 * Удаляются только заявки со статусом «Перезвонили». Со статусом «Новая»
 * не удаляются никогда, сколько бы ни лежали: пока на обращение никто
 * не ответил, это невыполненное обещание человеку, и молча исчезать оно
 * не должно. Отработанная заявка — уже история, и её дальнейшее хранение
 * только копит персональные данные без нужды.
 *
 * Срок считается от момента отметки «перезвонили», а не от даты обращения.
 * Иначе получалась бы дикость: заявка, пролежавшая без ответа 55 дней,
 * исчезала бы через пять дней после того, как на неё наконец ответили, —
 * чем дольше игнорировали, тем быстрее пропадёт.
 *
 * Технически это post_modified_gmt, то есть «последняя правка». Отметка
 * статуса идёт через wp_update_post и его обновляет; правка имени или
 * телефона руками — тоже, и это скорее верно: заявку трогали, значит
 * она ещё в работе.
 *
 * Удаление окончательное, минуя корзину: корзина — это то же хранение,
 * только незаметное.
 */
function razil_callback_cleanup(): void {
	$days = razil_callback_keep_days();

	if ( $days <= 0 ) {
		return;
	}

	$ids = get_posts(
		array(
			'post_type'        => RAZIL_CALLBACK_TYPE,
			'post_status'      => RAZIL_CALLBACK_DONE,
			'numberposts'      => RAZIL_CALLBACK_CLEANUP_BATCH,
			'fields'           => 'ids',
			'orderby'          => 'date',
			'order'            => 'ASC',
			'suppress_filters' => false,
			'date_query'       => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ),
				),
			),
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
}
add_action( RAZIL_CALLBACK_CLEANUP_HOOK, 'razil_callback_cleanup' );

/**
 * Сколько дней заявка лежит без ответа. Ноль, если она уже отработана.
 *
 * @param WP_Post $post Заявка.
 */
function razil_callback_waiting_days( WP_Post $post ): int {
	if ( RAZIL_CALLBACK_NEW !== $post->post_status ) {
		return 0;
	}

	$age = time() - (int) get_post_time( 'U', true, $post );

	return (int) floor( $age / DAY_IN_SECONDS );
}

/**
 * Число необработанных заявок, лежащих дольше допустимого.
 */
function razil_callback_stale_count(): int {
	$q = new WP_Query(
		array(
			'post_type'              => RAZIL_CALLBACK_TYPE,
			'post_status'            => RAZIL_CALLBACK_NEW,
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'date_query'             => array(
				array(
					'column' => 'post_date_gmt',
					'before' => gmdate( 'Y-m-d H:i:s', time() - RAZIL_CALLBACK_STALE_DAYS * DAY_IN_SECONDS ),
				),
			),
		)
	);

	return (int) $q->found_posts;
}

/**
 * Строка о сроке хранения над списком заявок и предупреждение о забытых.
 *
 * Объяснять надо обязательно: человек, который видит исчезающие записи
 * и не знает правила, решит, что база теряет данные.
 */
function razil_callback_retention_notice(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'edit-' . RAZIL_CALLBACK_TYPE !== $screen->id ) {
		return;
	}

	$days = razil_callback_keep_days();
	$link = sprintf(
		'<a href="%s">Настройки → Организация</a>',
		esc_url( admin_url( 'options-general.php?page=razil-org' ) )
	);

	if ( $days > 0 ) {
		$text = sprintf(
			'Заявки со статусом «%s» удаляются автоматически через %d %s после того, как их так отметили. '
				. 'Заявки со статусом «%s» не удаляются никогда, сколько бы ни лежали. Срок — в %s.',
			esc_html( razil_callback_status_label( RAZIL_CALLBACK_DONE ) ),
			(int) $days,
			esc_html( razil_callback_days_word( $days ) ),
			esc_html( razil_callback_status_label( RAZIL_CALLBACK_NEW ) ),
			$link
		);
	} else {
		$text = sprintf(
			'Автоудаление заявок отключено — они хранятся бессрочно. Включить срок можно в %s.',
			$link
		);
	}

	printf( '<div class="notice notice-info"><p>%s</p></div>', wp_kses_post( $text ) );

	$stale = razil_callback_stale_count();

	if ( $stale < 1 ) {
		return;
	}

	printf(
		'<div class="notice notice-warning"><p><strong>Без ответа дольше %d дней: %d %s.</strong> '
			. 'Такие заявки не удалятся сами — на них нужно ответить. <a href="%s">Показать, начиная с самых старых</a>.</p></div>',
		(int) RAZIL_CALLBACK_STALE_DAYS,
		(int) $stale,
		esc_html( razil_callback_leads_word( $stale ) ),
		esc_url(
			add_query_arg(
				array(
					'post_type'   => RAZIL_CALLBACK_TYPE,
					'post_status' => RAZIL_CALLBACK_NEW,
					'orderby'     => 'date',
					'order'       => 'asc',
				),
				admin_url( 'edit.php' )
			)
		)
	);
}
add_action( 'admin_notices', 'razil_callback_retention_notice' );

/**
 * «День», «дня», «дней» по числу.
 *
 * @param int $n Число.
 */
function razil_callback_days_word( int $n ): string {
	return razil_callback_plural( $n, 'день', 'дня', 'дней' );
}

/**
 * «Заявка», «заявки», «заявок» по числу.
 *
 * @param int $n Число.
 */
function razil_callback_leads_word( int $n ): string {
	return razil_callback_plural( $n, 'заявка', 'заявки', 'заявок' );
}

/**
 * Русское склонение по числу.
 *
 * @param int    $n     Число.
 * @param string $one   Форма для 1.
 * @param string $few   Форма для 2–4.
 * @param string $many  Форма для 5–20 и круглых.
 */
function razil_callback_plural( int $n, string $one, string $few, string $many ): string {
	$n = abs( $n ) % 100;
	$d = $n % 10;

	if ( $n > 10 && $n < 20 ) {
		return $many;
	}

	if ( $d > 1 && $d < 5 ) {
		return $few;
	}

	if ( 1 === $d ) {
		return $one;
	}

	return $many;
}
