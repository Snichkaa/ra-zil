<?php
/**
 * Форма отправки отзыва.
 *
 * Отзыв с формы создаётся черновиком и попадает на сайт только после
 * проверки: публикация без модерации на ритуальном сайте недопустима.
 *
 * Боты отсекаются тремя дешёвыми проверками: ловушка, скорость заполнения
 * и повтор с одного IP. Все три серверные: без JavaScript форма работает
 * полностью. Заполненное при ошибке возвращается в форму.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Действие формы. Одна строка на nonce, хук и скрытое поле —
 * рассинхронизировать их нельзя.
 */
const RAZIL_REVIEW_ACTION = 'razil_review_submit';

/**
 * Минимальное время заполнения, секунды. Человек не успеет быстрее,
 * бот отправляет мгновенно.
 */
const RAZIL_REVIEW_MIN_SECONDS = 4;

/**
 * Пауза между отзывами с одного адреса, секунды.
 */
const RAZIL_REVIEW_THROTTLE = 600;

/**
 * Запасной адрес уведомления о новом отзыве.
 *
 * Фактический адрес берётся из Настройки → Организация, см. фильтр
 * ниже. Константа остаётся на случай пустой опции или отключённого
 * модуля настроек: письмо о новом отзыве не должно пропасть
 * из-за незаполненной формы в админке.
 */
const RAZIL_REVIEW_NOTIFY = 'naumova@ra-zil.ru';

/**
 * Подмена получателя на адрес из настроек.
 *
 * Через тот же фильтр, что и для любого другого переопределения:
 * отдельного пути для настроек заводить не стали. Приоритет по
 * умолчанию, чтобы любой позднее навешенный фильтр мог перебить настройку.
 *
 * @param string $to Адрес по умолчанию.
 */
function razil_review_notify_from_settings( string $to ): string {
	if ( ! function_exists( 'razil_org' ) ) {
		return $to;
	}

	$email = razil_org( 'email' );

	return '' !== $email ? $email : $to;
}
add_filter( 'razil_review_notify', 'razil_review_notify_from_settings' );

/**
 * Время жизни заполненных полей, сохранённых при ошибке, секунды.
 */
const RAZIL_REVIEW_REFILL_TTL = 1800;

/**
 * Ссылка на страницу по слагу с запасным вариантом на прямой адрес.
 *
 * Опция wp_page_for_privacy_policy на этом сайте указывает на несуществующую
 * запись, поэтому get_privacy_policy_url() использовать нельзя.
 *
 * @param string $slug Слаг страницы.
 */
function razil_review_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );

	return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
}

/**
 * Адрес страницы отзывов — туда возвращаем после отправки.
 */
function razil_review_return_url(): string {
	return razil_review_page_url( 'otzyvy' );
}

/**
 * Разметка звезды для переключателя оценки.
 */
function razil_review_star_svg(): string {
	static $svg = null;

	if ( null !== $svg ) {
		return $svg;
	}

	$path = get_theme_file_path( 'assets/icons/icon-zvezda.svg' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$svg = is_readable( $path ) ? trim( (string) file_get_contents( $path ) ) : '';
	$svg = str_replace( '<svg ', '<svg class="rz-stars__star" ', $svg );

	return $svg;
}

/**
 * Ключ транзиента: 32 знака из wp_generate_uuid4 без дефисов.
 *
 * Ключ уходит в адресную строку, поэтому проверяется при чтении:
 * без строгой проверки по ключу из запроса можно было бы заглядывать
 * в произвольные транзиенты.
 */
function razil_review_token(): string {
	return str_replace( '-', '', wp_generate_uuid4() );
}

/**
 * Проверка ключа перед обращением к транзиенту.
 *
 * @param string $key Ключ из запроса.
 */
function razil_review_token_valid( string $key ): bool {
	return 1 === preg_match( '/^[a-f0-9]{32}$/', $key );
}

/**
 * Сохранение заполненного на время редиректа.
 *
 * Форма отправляется на admin-post.php и возвращается редиректом, поэтому
 * ввод переживает только то, что лежит на сервере. Куки и скрытые поля тут
 * не годятся: ключ уходит в адрес, значения — в транзиент.
 *
 * Возвращает ключ или пустую строку, если сохранять нечего.
 */
function razil_review_stash(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$fields = array(
		'name'    => isset( $_POST['rz_name'] )
			? mb_substr( sanitize_text_field( wp_unslash( $_POST['rz_name'] ) ), 0, 60 )
			: '',
		'text'    => isset( $_POST['rz_text'] )
			? mb_substr( wp_strip_all_tags( (string) wp_unslash( $_POST['rz_text'] ) ), 0, 2000 )
			: '',
		'rating'  => isset( $_POST['rz_rating'] ) ? (int) $_POST['rz_rating'] : 0,
		'consent' => empty( $_POST['rz_consent'] ) ? 0 : 1,
	);
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( '' === $fields['name'] && '' === $fields['text'] && 0 === $fields['rating'] && 0 === $fields['consent'] ) {
		return '';
	}

	$key = razil_review_token();
	set_transient( 'razil_review_refill_' . $key, $fields, RAZIL_REVIEW_REFILL_TTL );

	return $key;
}

/**
 * Чтение сохранённого для подстановки в форму.
 *
 * Одноразово: транзиент удаляется при первом же чтении, иначе повторное
 * открытие адреса с тем же ключом снова наполняло бы форму.
 */
function razil_review_refill(): array {
	static $fields = null;

	if ( null !== $fields ) {
		return $fields;
	}

	$fields = array(
		'name'    => '',
		'text'    => '',
		'rating'  => 0,
		'consent' => 0,
	);

	$key = isset( $_GET['rzk'] ) ? sanitize_key( wp_unslash( $_GET['rzk'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! razil_review_token_valid( $key ) ) {
		return $fields;
	}

	$saved = get_transient( 'razil_review_refill_' . $key );
	delete_transient( 'razil_review_refill_' . $key );

	if ( is_array( $saved ) ) {
		$fields = array_merge( $fields, array_intersect_key( $saved, $fields ) );
	}

	return $fields;
}

/**
 * Состояние страницы после редиректа: ok, err или пустая строка,
 * если форма открыта обычным заходом.
 */
function razil_review_state(): string {
	$state = isset( $_GET['rz'] ) ? sanitize_key( wp_unslash( $_GET['rz'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	return in_array( $state, array( 'ok', 'err' ), true ) ? $state : '';
}

/**
 * Сообщение после отправки.
 *
 * Скрипт инлайновый: на фронте у темы своих скриптов нет, и заводить
 * очередь ради семи строк незачем. Параметр rz вычищается из адреса,
 * иначе после обновления страницы сообщение появится снова.
 */
function razil_review_notice(): string {
	$state = razil_review_state();

	$texts = array(
		'ok'  => 'Спасибо. Отзыв отправлен и появится на сайте после проверки.',
		'err' => 'Не удалось отправить отзыв. Проверьте, что все поля заполнены, и попробуйте ещё раз. Написанное сохранено.',
	);

	if ( ! isset( $texts[ $state ] ) ) {
		return '';
	}

	$ok   = ( 'ok' === $state );
	$text = $texts[ $state ];

	$out = '<div class="rz-form-notice ' . ( $ok ? 'rz-form-notice--ok' : 'rz-form-notice--err' ) . '"'
		. ' role="' . ( $ok ? 'status' : 'alert' ) . '"'
		. ' tabindex="-1" data-rz-notice>'
		. '<p class="rz-form-notice__text">' . esc_html( $text ) . '</p>'
		. '<button type="button" class="rz-form-notice__close" aria-label="Закрыть сообщение" data-rz-notice-close>&times;</button>'
		. '</div>';

	$out .= '<script>
(function () {
	var box = document.querySelector( "[data-rz-notice]" );
	if ( ! box ) { return; }
	box.focus();
	var close = box.querySelector( "[data-rz-notice-close]" );
	if ( close ) { close.addEventListener( "click", function () { box.remove(); } ); }
	if ( window.history && window.history.replaceState ) {
		var url = new URL( window.location.href );
		url.searchParams.delete( "rz" );
		url.searchParams.delete( "rzk" );
		window.history.replaceState( {}, "", url.pathname + url.search + url.hash );
	}
})();
</script>';

	return $out;
}

/**
 * Форма отправки отзыва. Регистрируется шорткодом [razil_review_form].
 */
function razil_review_form(): string {
	$star = razil_review_star_svg();

	// Заполненное с прошлой попытки — для подстановки значений.
	$was = razil_review_refill();

	$out = razil_review_notice();

	$out .= '<form class="rz-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$out .= '<input type="hidden" name="action" value="' . esc_attr( RAZIL_REVIEW_ACTION ) . '" />';
	$out .= wp_nonce_field( RAZIL_REVIEW_ACTION, 'rz_nonce', true, false );
	$out .= '<input type="hidden" name="rz_time" value="' . esc_attr( (string) time() ) . '" />';

	/*
	 * Ловушка. Скрыта позиционированием и clip-path, а не display: none:
	 * часть ботов пропускает поля с display: none, а такие заполняет.
	 */
	$out .= '<div class="rz-hp" aria-hidden="true">'
		. '<label for="rz-website">Сайт</label>'
		. '<input type="text" id="rz-website" name="rz_website" tabindex="-1" autocomplete="off" />'
		. '</div>';

	// --- имя
	$out .= '<p class="rz-form__row">'
		. '<label class="rz-form__label" for="rz-name">Ваше имя</label>'
		. '<input class="rz-form__input" type="text" id="rz-name" name="rz_name" maxlength="60"'
		. ' value="' . esc_attr( $was['name'] ) . '" required />'
		. '</p>';

	// --- оценка
	$out .= '<fieldset class="rz-form__row rz-stars">';
	$out .= '<legend class="rz-form__label">Оценка</legend>';
	$out .= '<div class="rz-stars__set">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$id = 'rz-rating-' . $i;
		$out .= '<input class="rz-stars__input" type="radio" id="' . esc_attr( $id ) . '"'
			. ' name="rz_rating" value="' . esc_attr( (string) $i ) . '"'
			. checked( $was['rating'], $i, false ) . ' required />';
		$out .= '<label class="rz-stars__label" for="' . esc_attr( $id ) . '">'
			. $star
			. '<span class="screen-reader-text">' . esc_html( sprintf( 'Оценка %d из 5', $i ) ) . '</span>'
			. '</label>';
	}
	$out .= '</div></fieldset>';

	// --- текст
	$out .= '<p class="rz-form__row">'
		. '<label class="rz-form__label" for="rz-text">Отзыв</label>'
		. '<textarea class="rz-form__input rz-form__textarea" id="rz-text" name="rz_text" rows="6" maxlength="2000" required>'
		. esc_textarea( $was['text'] ) . '</textarea>'
		. '</p>';

	// --- согласие
	$out .= '<p class="rz-form__row rz-form__consent">'
		. '<input type="checkbox" id="rz-consent" name="rz_consent" value="1"'
		. checked( 1, $was['consent'], false ) . ' required />'
		. '<label for="rz-consent">Я даю '
		. '<a href="' . esc_url( razil_review_page_url( 'soglasie' ) ) . '" target="_blank" rel="noopener">согласие на обработку персональных данных</a>'
		. ' и ознакомлен с '
		. '<a href="' . esc_url( razil_review_page_url( 'politika' ) ) . '" target="_blank" rel="noopener">политикой обработки</a>.'
		. '</label>'
		. '</p>';

	$out .= '<p class="rz-form__row">'
		. '<button type="submit" class="wp-element-button rz-form__submit">Отправить отзыв</button>'
		. '</p>';

	$out .= '</form>';

	return $out;
}
add_shortcode( 'razil_review_form', 'razil_review_form' );

/**
 * Ключ транзиента с ограничением по адресу.
 *
 * Хешируем адрес, а не храним как есть: сам IP нам не нужен,
 * достаточно признака «этот посетитель уже писал».
 */
function razil_review_throttle_key(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: 'unknown';

	return 'razil_review_' . md5( $ip . wp_salt() );
}

/**
 * Возврат на страницу отзывов с признаком результата.
 *
 * @param string $state  ok или err.
 * @param string $refill Ключ сохранённых полей, если их надо подставить.
 */
function razil_review_redirect( string $state, string $refill = '' ): void {
	$url = add_query_arg( 'rz', $state, razil_review_return_url() );

	if ( '' !== $refill ) {
		$url = add_query_arg( 'rzk', $refill, $url );
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * Обработчик отправки формы.
 */
function razil_review_handle(): void {
	// 1. Подпись формы.
	$nonce = isset( $_POST['rz_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rz_nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, RAZIL_REVIEW_ACTION ) ) {
		razil_review_redirect( 'err' );
	}

	/*
	 * 2. Ловушка. Уходим с признаком успеха: бот не должен понять,
	 * что его отсекли, иначе следующая попытка будет умнее.
	 */
	$trap = isset( $_POST['rz_website'] ) ? trim( (string) wp_unslash( $_POST['rz_website'] ) ) : '';
	if ( '' !== $trap ) {
		razil_review_redirect( 'ok' );
	}

	// 3. Скорость заполнения.
	$started = isset( $_POST['rz_time'] ) ? (int) $_POST['rz_time'] : 0;
	if ( $started <= 0 || ( time() - $started ) < RAZIL_REVIEW_MIN_SECONDS ) {
		razil_review_redirect( 'err' );
	}

	// 4. Повтор с того же адреса.
	$key = razil_review_throttle_key();
	if ( get_transient( $key ) ) {
		razil_review_redirect( 'err' );
	}

	// 5. Обязательные поля. Заполненное возвращаем в форму:
	// терять написанный отзыв из-за незакрытой галочки нельзя.
	$name    = isset( $_POST['rz_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rz_name'] ) ) : '';
	$name    = mb_substr( $name, 0, 60 );
	$text    = isset( $_POST['rz_text'] ) ? wp_kses_post( wp_unslash( $_POST['rz_text'] ) ) : '';
	$text    = mb_substr( wp_strip_all_tags( $text ), 0, 2000 );
	$rating  = isset( $_POST['rz_rating'] ) ? (int) $_POST['rz_rating'] : 0;
	$consent = ! empty( $_POST['rz_consent'] );

	if ( '' === trim( $name ) || '' === trim( $text ) || ! $consent || $rating < 1 || $rating > 5 ) {
		razil_review_redirect( 'err', razil_review_stash() );
	}

	// --- запись
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'reviews',
			'post_status'  => 'draft',
			'post_title'   => $name,
			'post_content' => $text,
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		razil_review_redirect( 'err' );
	}

	update_post_meta( $post_id, '_razil_rating', $rating );
	update_post_meta( $post_id, '_razil_source', 'Сайт' );
	update_post_meta( $post_id, '_razil_from_form', 1 );

	set_transient( $key, 1, RAZIL_REVIEW_THROTTLE );

	// --- уведомление. Не удалось отправить — отзыв всё равно сохранён.
	$edit = get_edit_post_link( $post_id, 'raw' );
	$body = "Имя: $name\n"
		. "Оценка: $rating из 5\n\n"
		. "Текст:\n$text\n\n"
		. 'Проверить и опубликовать: ' . $edit . "\n";

	/**
	 * Адрес уведомления. По умолчанию сюда приходит адрес
	 * из Настройки → Организация: фильтр навешен выше в этом же
	 * файле. Константа остаётся запасным значением.
	 *
	 * @param string $to Адрес получателя.
	 */
	$to = apply_filters( 'razil_review_notify', RAZIL_REVIEW_NOTIFY );

	wp_mail( $to, 'Новый отзыв на сайте, ожидает проверки', $body );

	razil_review_redirect( 'ok' );
}
add_action( 'admin_post_nopriv_' . RAZIL_REVIEW_ACTION, 'razil_review_handle' );
add_action( 'admin_post_' . RAZIL_REVIEW_ACTION, 'razil_review_handle' );
