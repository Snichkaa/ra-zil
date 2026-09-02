<?php
/**
 * Форма обратного звонка.
 *
 * Человек оставляет имя и телефон, агент перезванивает. Больше ничего
 * не спрашиваем: это персональные данные, и лишнее поле здесь означало бы
 * лишнюю ответственность за его хранение.
 *
 * Одна разметка выводится двумя способами: шорткодом прямо в странице
 * и в модальном окне по кнопке. Окно — надстройка на JavaScript; без него
 * работает шорткод, отправляющийся обычным POST на admin-post.php.
 *
 * Проверки от ботов, возврат заполненного при ошибке и разметка согласия —
 * общие для форм сайта, см. inc/form-support.php. Константы у формы свои:
 * порог скорости и пауза между заявками у обратного звонка не обязаны
 * совпадать с отзывами.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Действие формы. Одна строка на nonce, хук и скрытое поле.
 */
const RAZIL_CALLBACK_ACTION = 'razil_callback_submit';

/**
 * Минимальное время заполнения, секунды.
 *
 * Меньше, чем у отзыва: там пишут текст, здесь — два поля, и человек
 * успевает быстро.
 */
const RAZIL_CALLBACK_MIN_SECONDS = 3;

/**
 * Пауза между заявками с одного адреса, секунды.
 */
const RAZIL_CALLBACK_THROTTLE = 300;

/**
 * Время жизни заполненных полей, сохранённых при ошибке, секунды.
 */
const RAZIL_CALLBACK_REFILL_TTL = 1800;

/**
 * Префикс имён полей формы.
 *
 * Свой, не совпадающий с отзывами: обе формы могут оказаться на одной
 * странице, и поля не должны сталкиваться.
 */
const RAZIL_CALLBACK_PREFIX = 'rz_cb_';

/**
 * Максимальная длина имени.
 */
const RAZIL_CALLBACK_NAME_MAX = 60;

/**
 * Максимальная длина телефона в том виде, как его набрал человек.
 */
const RAZIL_CALLBACK_PHONE_MAX = 32;

/**
 * Состояние страницы после редиректа.
 */
function razil_callback_state(): string {
	return razil_form_state( 'rzcb', array( 'err' ) );
}

/**
 * Заполненное с прошлой попытки.
 *
 * @return array<string, mixed>
 */
function razil_callback_refill(): array {
	return razil_form_refill(
		'razil_callback_refill_',
		array(
			'name'    => '',
			'phone'   => '',
			'consent' => 0,
		),
		'rzcbk'
	);
}

/**
 * Сообщение об ошибке над формой.
 *
 * Успеха здесь нет намеренно: после удачной отправки человек уходит
 * на страницу «Спасибо», а не возвращается к форме.
 *
 * Выводится один раз на страницу. Форма может оказаться и в модальном окне,
 * и шорткодом в тексте; два сообщения об одной ошибке — это уже не забота,
 * а шум. Первый обратившийся получает сообщение, остальные пустую строку.
 */
function razil_callback_notice(): string {
	static $done = false;

	if ( $done ) {
		return '';
	}

	$out = razil_form_notice(
		razil_callback_state(),
		array(
			'err' => 'Не удалось отправить заявку. Проверьте имя, телефон и согласие на обработку данных, '
				. 'и попробуйте ещё раз. Введённое сохранено.',
		),
		'ok',
		array( 'rzcb', 'rzcbk' )
	);

	if ( '' !== $out ) {
		$done = true;
	}

	return $out;
}

/**
 * Разметка формы.
 *
 * Идентификаторы полей получают суффикс: форма может оказаться на странице
 * дважды — шорткодом в тексте и внутри модального окна, — а два одинаковых
 * id ломают связь label с полем.
 *
 * @param string $uid Суффикс идентификаторов.
 */
function razil_callback_form_markup( string $uid ): string {
	$was = razil_callback_refill();
	$p   = RAZIL_CALLBACK_PREFIX;

	$id_name    = 'rz-cb-name-' . $uid;
	$id_phone   = 'rz-cb-phone-' . $uid;
	$id_consent = 'rz-cb-consent-' . $uid;

	$out = '<form class="rz-form rz-cb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$out .= razil_form_hidden_fields( RAZIL_CALLBACK_ACTION, $p );
	$out .= razil_form_honeypot( 'rz-cb-website-' . $uid, $p . 'website' );

	/*
	 * Страница, с которой пришла заявка. Нужна агенту: по ней видно,
	 * о какой услуге человек читал перед тем, как попросить звонок.
	 */
	$out .= '<input type="hidden" name="' . esc_attr( $p . 'page' ) . '" value="'
		. esc_attr( razil_callback_current_url() ) . '" />';

	// --- имя
	$out .= '<p class="rz-form__row">'
		. '<label class="rz-form__label" for="' . esc_attr( $id_name ) . '">Как к вам обращаться</label>'
		. '<input class="rz-form__input" type="text" id="' . esc_attr( $id_name ) . '"'
		. ' name="' . esc_attr( $p . 'name' ) . '" maxlength="' . esc_attr( (string) RAZIL_CALLBACK_NAME_MAX ) . '"'
		. ' autocomplete="name"'
		. ' value="' . esc_attr( (string) $was['name'] ) . '" required />'
		. '</p>';

	/*
	 * --- телефон
	 *
	 * type="tel", а не number: номер — не число, и у number ломается ввод
	 * плюса и скобок. inputmode подсказывает телефонную клавиатуру.
	 * Никакого pattern: проверку делает сервер, а строгий шаблон в браузере
	 * отказал бы человеку из-за лишнего пробела.
	 */
	$out .= '<p class="rz-form__row">'
		. '<label class="rz-form__label" for="' . esc_attr( $id_phone ) . '">Телефон</label>'
		. '<input class="rz-form__input" type="tel" id="' . esc_attr( $id_phone ) . '"'
		. ' name="' . esc_attr( $p . 'phone' ) . '" maxlength="' . esc_attr( (string) RAZIL_CALLBACK_PHONE_MAX ) . '"'
		. ' inputmode="tel" autocomplete="tel"'
		. ' value="' . esc_attr( (string) $was['phone'] ) . '" required />'
		. '</p>';

	// --- согласие, разметка общая с формой отзывов
	$out .= razil_form_consent( $id_consent, $p . 'consent', 1 === (int) $was['consent'] );

	$out .= '<p class="rz-form__row">'
		. '<button type="submit" class="wp-element-button rz-form__submit">Жду звонка</button>'
		. '</p>';

	$note = razil_callback_note();

	if ( '' !== $note ) {
		$out .= '<p class="rz-cb-form__note">' . esc_html( $note ) . '</p>';
	}

	$out .= '</form>';

	return $out;
}

/**
 * Адрес текущей страницы для поля «откуда заявка».
 *
 * Собираем из запрошенного пути, а не из get_permalink: форма может стоять
 * и на архиве, у которого своей записи нет.
 */
function razil_callback_current_url(): string {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

	return home_url( $path );
}

/**
 * Поясняющая строка из настроек.
 */
function razil_callback_note(): string {
	return function_exists( 'razil_org' ) ? razil_org( 'callback_note' ) : '';
}

/**
 * Шорткод формы для вставки в страницу: [razil_callback_form].
 */
function razil_callback_form_shortcode(): string {
	return '<div class="rz-cb-form-wrap">' . razil_callback_notice() . razil_callback_form_markup( 'inline' ) . '</div>';
}
add_shortcode( 'razil_callback_form', 'razil_callback_form_shortcode' );

/**
 * Шорткод кнопки с модальным окном: [razil_callback_button].
 *
 * Кнопка и окно выводятся вместе, одним шорткодом. Окно можно ставить где
 * угодно в разметке: showModal поднимает его в верхний слой независимо
 * от места в дереве. Так размещение целиком остаётся в шаблоне, и нет
 * второй точки, о которой можно забыть.
 *
 * Окно выводится один раз на страницу, даже если кнопок несколько:
 * два элемента с одним id сломали бы и подписи полей, и открытие.
 *
 * @param array<string, string>|string $atts Атрибуты шорткода.
 */
function razil_callback_button_shortcode( $atts = array() ): string {
	static $dialog_done = false;

	$atts = shortcode_atts(
		array(
			'label' => 'Перезвоните мне',
			// Оформление задаёт шаблон, а не этот файл: рядом с кнопкой
			// стоит телефон, и какая из двух главная — решение вёрстки.
			'class' => '',
		),
		is_array( $atts ) ? $atts : array()
	);

	$classes = trim( 'wp-element-button rz-cb-open ' . sanitize_html_class( $atts['class'] ) );

	/*
	 * Кнопка именно <button>, а не ссылка: без JavaScript ссылка вела бы
	 * в никуда. Здесь без JavaScript кнопка просто ничего не делает,
	 * а рядом в том же блоке стоит рабочая ссылка «позвонить».
	 */
	$out = '<button type="button" class="' . esc_attr( $classes ) . '" data-rz-cb-open aria-haspopup="dialog">'
		. esc_html( $atts['label'] )
		. '</button>';

	if ( $dialog_done ) {
		return $out;
	}

	$dialog_done = true;

	/*
	 * При ошибке окно открывается само: заявку отправляли из него, и человек
	 * вернулся на ту же страницу с закрытым окном. Без этого он увидел бы
	 * страницу как ни в чём не бывало и не понял, что заявка не ушла.
	 */
	$autoopen = '' !== razil_callback_state() ? ' data-rz-cb-autoopen' : '';

	$out .= '<dialog class="rz-cb-dialog" id="rz-cb-dialog" aria-labelledby="rz-cb-dialog-title"' . $autoopen . '>'
		. '<div class="rz-cb-dialog__box">'
		. '<p class="rz-cb-dialog__title" id="rz-cb-dialog-title">Перезвоните мне</p>'
		. razil_callback_notice()
		. razil_callback_form_markup( 'dialog' )
		. '<button type="button" class="rz-cb-dialog__cancel" data-rz-cb-close>Закрыть</button>'
		. '</div>'
		. '</dialog>';

	return $out;
}
add_shortcode( 'razil_callback_button', 'razil_callback_button_shortcode' );

/**
 * Минимум и максимум цифр в номере.
 *
 * Нижняя граница — короткий городской номер вроде 60-52-90: в Хабаровске
 * их семь знаков, и человек вполне может набрать только его. Верхняя — предел
 * международного формата E.164. Между ними не придираемся: форма не должна
 * спорить с человеком, которому сейчас не до неё.
 */
const RAZIL_CALLBACK_PHONE_MIN_DIGITS = 6;
const RAZIL_CALLBACK_PHONE_MAX_DIGITS = 15;

/**
 * Запасной адрес для уведомлений, если в настройках почта пуста.
 */
const RAZIL_CALLBACK_NOTIFY = 'naumova@ra-zil.ru';

/**
 * Приведение телефона к единому виду.
 *
 * Принимаем как угодно: с восьмёркой, с плюсом, со скобками, дефисами
 * и пробелами. Российский номер в любом написании становится +7XXXXXXXXXX,
 * чтобы две заявки с одного номера выглядели одинаково и в списке,
 * и в ссылке для набора.
 *
 * Всё, что на номер не похоже совсем, отсекаем — но только по числу цифр.
 * Проверять оператора, коды регионов и прочее нельзя: любая такая проверка
 * рано или поздно откажет живому человеку с непривычным номером.
 *
 * @param string $raw Что набрал человек.
 *
 * @return string Нормализованный номер или пустая строка, если не номер.
 */
function razil_callback_normalize_phone( string $raw ): string {
	$raw    = trim( $raw );
	$plus   = str_starts_with( $raw, '+' );
	$digits = (string) preg_replace( '/\D+/', '', $raw );
	$len    = strlen( $digits );

	if ( $len < RAZIL_CALLBACK_PHONE_MIN_DIGITS || $len > RAZIL_CALLBACK_PHONE_MAX_DIGITS ) {
		return '';
	}

	// 8 (4212) 60-52-90 и +7 4212 605290 — один и тот же номер.
	if ( 11 === $len && ( '8' === $digits[0] || '7' === $digits[0] ) ) {
		return '+7' . substr( $digits, 1 );
	}

	// Десять цифр без плюса — российский номер без кода страны.
	if ( 10 === $len && ! $plus ) {
		return '+7' . $digits;
	}

	return ( $plus ? '+' : '' ) . $digits;
}

/**
 * Сохранение заполненного на время редиректа.
 *
 * Телефон кладём в том виде, как его набрал человек, а не нормализованным:
 * если номер не прошёл проверку, показать надо именно введённое, иначе
 * человек не поймёт, что исправлять.
 */
function razil_callback_stash(): string {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$p = RAZIL_CALLBACK_PREFIX;

	return razil_form_stash(
		'razil_callback_refill_',
		array(
			'name'    => isset( $_POST[ $p . 'name' ] )
				? mb_substr( sanitize_text_field( wp_unslash( $_POST[ $p . 'name' ] ) ), 0, RAZIL_CALLBACK_NAME_MAX )
				: '',
			'phone'   => isset( $_POST[ $p . 'phone' ] )
				? mb_substr( sanitize_text_field( wp_unslash( $_POST[ $p . 'phone' ] ) ), 0, RAZIL_CALLBACK_PHONE_MAX )
				: '',
			'consent' => isset( $_POST[ $p . 'consent' ] ) ? 1 : 0,
		),
		RAZIL_CALLBACK_REFILL_TTL
	);
	// phpcs:enable WordPress.Security.NonceVerification.Missing
}

/**
 * Страница, на которую вернуть человека при ошибке.
 *
 * Форма может стоять где угодно, в том числе в модальном окне на архиве,
 * поэтому адрес приходит скрытым полем. Через wp_validate_redirect: поле
 * приходит из запроса, и без проверки узла его можно было бы подменить
 * на чужой сайт.
 */
function razil_callback_back_url(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	$sent = isset( $_POST[ RAZIL_CALLBACK_PREFIX . 'page' ] )
		? esc_url_raw( wp_unslash( $_POST[ RAZIL_CALLBACK_PREFIX . 'page' ] ) )
		: '';

	$url = wp_validate_redirect( $sent, '' );

	if ( '' === $url ) {
		$url = wp_validate_redirect( (string) wp_get_referer(), home_url( '/' ) );
	}

	return $url;
}

/**
 * Возврат к форме с сообщением об ошибке и сохранённым вводом.
 */
function razil_callback_fail(): void {
	$url = add_query_arg(
		array(
			'rzcb'  => 'err',
			'rzcbk' => razil_callback_stash(),
		),
		razil_callback_back_url()
	);

	wp_safe_redirect( $url . '#rz-cb-form' );
	exit;
}

/**
 * Уход на страницу «Спасибо».
 *
 * Отдельная страница, а не возврат с параметром: заявку оставляют один раз,
 * и человеку нужен ясный признак, что всё получилось, а не строчка над
 * пустой формой.
 */
function razil_callback_done(): void {
	$page = get_page_by_path( 'spasibo' );

	/*
	 * Если страницы нет, возвращаем на исходную с признаком ошибки:
	 * заявка при этом уже создана и письмо ушло, но молча оставлять
	 * человека на форме нельзя — он отправит ещё раз.
	 */
	$url = $page instanceof WP_Post ? get_permalink( $page ) : razil_callback_back_url();

	wp_safe_redirect( $url );
	exit;
}

/**
 * Письмо агенту о новой заявке.
 *
 * @param string $name  Имя.
 * @param string $phone Нормализованный телефон.
 * @param string $page  Страница, с которой отправлено.
 */
function razil_callback_notify( string $name, string $phone, string $page ): void {
	/**
	 * Адрес получателя заявок.
	 *
	 * По умолчанию из Настройки → Организация; константа остаётся запасной
	 * на случай пустой опции.
	 *
	 * @param string $to Адрес.
	 */
	$to = apply_filters( 'razil_callback_notify', razil_org( 'email', RAZIL_CALLBACK_NOTIFY ) );

	$body = "Новая заявка на обратный звонок.\n\n"
		. 'Имя: ' . $name . "\n"
		. 'Телефон: ' . $phone . "\n"
		. 'Время: ' . wp_date( 'd.m.Y H:i' ) . "\n"
		. 'Страница: ' . $page . "\n\n"
		. 'Список заявок: ' . admin_url( 'edit.php?post_type=' . RAZIL_CALLBACK_TYPE ) . "\n";

	wp_mail( $to, 'Заявка на обратный звонок с сайта', $body );
}

/**
 * Приём заявки.
 *
 * Порядок намеренный: сначала дешёвые проверки от ботов, только потом
 * разбор полей. Смысла чистить и проверять данные запроса, который всё
 * равно будет отклонён, нет.
 */
function razil_callback_handle(): void {
	$guard = razil_form_guard(
		RAZIL_CALLBACK_ACTION,
		RAZIL_CALLBACK_PREFIX,
		RAZIL_CALLBACK_MIN_SECONDS,
		razil_form_throttle_key( 'razil_cb_' )
	);

	/*
	 * Ловушка заполнена — показываем успех. Бот не должен понять, что его
	 * отсекли: поняв, он попробует иначе. Заявка при этом не создаётся.
	 */
	if ( 'trap' === $guard ) {
		razil_callback_done();
	}

	if ( '' !== $guard ) {
		razil_callback_fail();
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$p = RAZIL_CALLBACK_PREFIX;

	$name = isset( $_POST[ $p . 'name' ] )
		? trim( sanitize_text_field( wp_unslash( $_POST[ $p . 'name' ] ) ) )
		: '';
	$name = mb_substr( $name, 0, RAZIL_CALLBACK_NAME_MAX );

	$phone_raw = isset( $_POST[ $p . 'phone' ] )
		? sanitize_text_field( wp_unslash( $_POST[ $p . 'phone' ] ) )
		: '';
	$phone_raw = mb_substr( $phone_raw, 0, RAZIL_CALLBACK_PHONE_MAX );
	$phone     = razil_callback_normalize_phone( $phone_raw );

	$consent = isset( $_POST[ $p . 'consent' ] ) && '1' === (string) wp_unslash( $_POST[ $p . 'consent' ] );
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$page = razil_callback_back_url();

	if ( '' === $name || '' === $phone || ! $consent ) {
		razil_callback_fail();
	}

	$id = wp_insert_post(
		array(
			'post_type'   => RAZIL_CALLBACK_TYPE,
			'post_status' => RAZIL_CALLBACK_NEW,
			// Заголовок — имя: в списке заявок именно оно должно быть видно.
			'post_title'  => $name,
		),
		true
	);

	if ( is_wp_error( $id ) || ! $id ) {
		razil_callback_fail();
	}

	update_post_meta( $id, RAZIL_CALLBACK_PHONE_KEY, $phone );
	update_post_meta( $id, RAZIL_CALLBACK_PAGE_KEY, $page );

	// Лимит ставим только после настоящей заявки: отклонённые попытки
	// не должны закрывать форму человеку, который ошибся в поле.
	set_transient( razil_form_throttle_key( 'razil_cb_' ), 1, RAZIL_CALLBACK_THROTTLE );

	razil_callback_notify( $name, $phone, $page );

	razil_callback_done();
}
add_action( 'admin_post_nopriv_' . RAZIL_CALLBACK_ACTION, 'razil_callback_handle' );
add_action( 'admin_post_' . RAZIL_CALLBACK_ACTION, 'razil_callback_handle' );
