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
	return razil_form_state( 'rzcb', array( 'err', 'captcha', 'captcha_expired', 'throttle' ) );
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
			'service' => 0,
			'topic'   => 'general',
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
			'err' => 'Не удалось отправить заявку. Проверьте телефон и согласие на обработку данных, '
				. 'и попробуйте ещё раз. Введённое сохранено.',
			'captcha' => 'Проверка не пройдена, заявка не отправлена. Попробуйте ещё раз. Введённое сохранено.',
			'captcha_expired' => 'Страница была открыта слишком давно, и проверка устарела. Обновите страницу '
				. 'и отправьте заявку ещё раз. Введённое сохранено.',
			'throttle' => sprintf(
				'Заявка недавно уже была отправлена. Повторить отправку можно через несколько минут. '
					. 'Введённое сохранено. Если нужна срочная помощь, позвоните: %s.',
				razil_org( 'phone' )
			),
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
	$id_hint    = 'rz-cb-phone-hint-' . $uid;
	$id_error   = 'rz-cb-phone-error-' . $uid;

	$out = '<form class="rz-form rz-cb-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$out .= razil_form_hidden_fields( RAZIL_CALLBACK_ACTION, $p );
	$context = razil_callback_context( $was['service'] ?: ( is_singular( 'services' ) ? get_queried_object_id() : 0 ), $was['topic'] );
	$out .= '<input type="hidden" name="rz_cb_request" value="' . esc_attr( wp_generate_uuid4() ) . '" />';
	$out .= '<input type="hidden" name="rz_cb_service" value="' . (int) $context['service_id'] . '" />';
	$out .= '<input type="hidden" name="rz_cb_topic" value="' . esc_attr( $context['topic'] ) . '" />';
	$out .= '<p class="rz-cb-form__context" data-rz-cb-context>' . esc_html( $context['topic_label'] . ( $context['service'] ? ': ' . $context['service'] : '' ) ) . '</p>';
	$out .= razil_form_honeypot( 'rz-cb-website-' . $uid, $p . 'website' );
	$out .= '<p class="rz-cb-form__schedule">' . esc_html( razil_callback_note() ) . '</p>';
	$out .= '<p class="rz-cb-form__urgent">Нужна помощь сейчас? <a href="' . esc_url( razil_org_tel_from( razil_org( 'phone' ) ) ) . '">'
		. esc_html( razil_org( 'phone' ) ) . '</a> — круглосуточно.</p>';

	/*
	 * Страница, с которой пришла заявка. Нужна агенту: по ней видно,
	 * о какой услуге человек читал перед тем, как попросить звонок.
	 */
	$out .= '<input type="hidden" name="' . esc_attr( $p . 'page' ) . '" value="'
		. esc_attr( razil_callback_current_url() ) . '" />';

	// --- имя
	$out .= '<p class="rz-form__row">'
		. '<label class="rz-form__label" for="' . esc_attr( $id_name ) . '">Как к вам обращаться <span class="rz-form__optional">(необязательно)</span></label>'
		. '<input class="rz-form__input" type="text" id="' . esc_attr( $id_name ) . '"'
		. ' name="' . esc_attr( $p . 'name' ) . '" maxlength="' . esc_attr( (string) RAZIL_CALLBACK_NAME_MAX ) . '"'
		. ' autocomplete="name"'
		. ' value="' . esc_attr( (string) $was['name'] ) . '" />'
		. '</p>';

	/*
	 * --- телефон
	 *
	 * type="tel", а не number: номер — не число, и у number ломается ввод
	 * плюса и скобок. inputmode подсказывает телефонную клавиатуру.
	 * Новый номер начинается с +7. Сохранённое после ошибки значение
	 * показываем как ввёл посетитель, включая другой код страны.
	 */
	$separator = '[\s\(\)\.\-]*';
	$digit     = $separator . '[0-9]';
	$pattern   = '(?:(?:\+?' . $separator . '7|' . $separator . '8)(?:' . $digit . '){10}|(?:' . $digit . '){10}|\+' . $separator . '[1-68-9](?:' . $digit . '){5,14})' . $separator;
	$phone     = '' === (string) $was['phone'] ? '+7' : $was['phone'];
	$out .= '<p class="rz-form__row">'
		. '<label class="rz-form__label" for="' . esc_attr( $id_phone ) . '">Телефон</label>'
		. '<input class="rz-form__input" type="tel" id="' . esc_attr( $id_phone ) . '"'
		. ' name="' . esc_attr( $p . 'phone' ) . '" maxlength="' . esc_attr( (string) RAZIL_CALLBACK_PHONE_MAX ) . '"'
		. ' inputmode="tel" autocomplete="tel"'
		. ' pattern="' . esc_attr( $pattern ) . '" placeholder="+7 (___) ___-__-__"'
		. ' aria-describedby="' . esc_attr( $id_hint . ' ' . $id_error ) . '"'
		. ' value="' . esc_attr( $phone ) . '" required />'
		. '<span class="rz-form__hint" id="' . esc_attr( $id_hint ) . '">Введите 10 цифр после +7. Для иностранного номера замените +7 кодом страны.</span>'
		. '<span class="rz-form__error" id="' . esc_attr( $id_error ) . '" aria-live="polite"></span>'
		. '</p>';

	// --- согласие, разметка общая с формой отзывов
	$out .= razil_form_consent( $id_consent, $p . 'consent', 1 === (int) $was['consent'] );

	// Невидимая капча: разметки не даёт и места не занимает, пока не настроена.
	$out .= razil_captcha_widget( $uid );

	$out .= '<p class="rz-form__row">'
		. '<button type="submit" class="wp-element-button rz-form__submit">Заказать звонок</button>'
		. '</p>';

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
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/';

	return home_url( $path );
}

/**
 * Поясняющая строка из настроек.
 */
function razil_callback_note(): string {
	$note = function_exists( 'razil_org' ) ? razil_org( 'callback_note' ) : '';
	$previous_default = 'Перезвоним в рабочее время офиса, обычно в течение часа. Если нужно срочно — звоните, телефон работает круглосуточно.';
	// Upgrade the old default without replacing a deliberately customized note.
	if ( '' === $note || $previous_default === $note ) {
		return 'Перезвоним в часы работы офиса: ежедневно 9:00–17:00 (Хабаровск).';
	}
	return $note;
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
			'label' => 'Заказать обратный звонок',
			'topic' => 'general',
			'service' => is_singular( 'services' ) ? get_queried_object_id() : 0,
			// Оформление задаёт шаблон, а не этот файл: рядом с кнопкой
			// стоит телефон, и какая из двух главная — решение вёрстки.
			'class' => '',
		),
		is_array( $atts ) ? $atts : array()
	);

	$classes = trim( 'wp-element-button rz-cb-open ' . sanitize_html_class( $atts['class'] ) );
	if ( 'Уточнить стоимость' === $atts['label'] ) { $atts['label'] = 'Обсудить стоимость по телефону'; $atts['topic'] = 'price'; }
	$context = razil_callback_context( $atts['service'], (string) $atts['topic'] );

	/*
	 * Кнопка именно <button>, а не ссылка: без JavaScript ссылка вела бы
	 * в никуда. Здесь без JavaScript кнопка просто ничего не делает,
	 * а рядом в том же блоке стоит рабочая ссылка «позвонить».
	 */
	$out = '<button type="button" class="' . esc_attr( $classes ) . '" data-rz-cb-open data-rz-cb-topic="' . esc_attr( $context['topic'] ) . '" data-rz-cb-service="' . (int) $context['service_id'] . '" data-rz-cb-service-label="' . esc_attr( $context['service'] ) . '" aria-haspopup="dialog">'
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
		. '<button type="button" class="rz-cb-dialog__cancel rz-cb-dialog__cancel--top" data-rz-cb-close aria-label="Закрыть форму обратного звонка">Закрыть</button>'
		. '<p class="rz-cb-dialog__title" id="rz-cb-dialog-title">Заказать обратный звонок</p>'
		. razil_callback_notice()
		. razil_callback_form_markup( 'dialog' )
		. '<button type="button" class="rz-cb-dialog__cancel" data-rz-cb-close>Закрыть</button>'
		. '</div>'
		. '</dialog>';

	return $out;
}
add_shortcode( 'razil_callback_button', 'razil_callback_button_shortcode' );

/**
 * Границы для номеров с явно указанным иностранным кодом страны.
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
 * Проверяем число цифр и допустимые разделители; буквы не отбрасываем молча.
 * Проверять оператора, коды регионов и прочее нельзя: любая такая проверка
 * рано или поздно откажет живому человеку с непривычным номером.
 *
 * @param string $raw Что набрал человек.
 *
 * @return string Нормализованный номер или пустая строка, если не номер.
 */
function razil_callback_normalize_phone( string $raw ): string {
	$raw    = trim( $raw );
	if ( ! preg_match( '/^\+?[0-9\s().-]+$/u', $raw ) ) {
		return '';
	}
	$plus   = str_starts_with( $raw, '+' );
	$digits = (string) preg_replace( '/\D+/', '', $raw );
	$len    = strlen( $digits );

	if ( $len < RAZIL_CALLBACK_PHONE_MIN_DIGITS || $len > RAZIL_CALLBACK_PHONE_MAX_DIGITS ) {
		return '';
	}

	// 8 (4212) 60-52-90 и +7 4212 605290 — один и тот же номер.
	if ( 11 === $len && ( '7' === $digits[0] || ( ! $plus && '8' === $digits[0] ) ) ) {
		return '+7' . substr( $digits, 1 );
	}

	// Десять цифр без плюса — российский номер без кода страны.
	if ( 10 === $len && ! $plus ) {
		return '+7' . $digits;
	}

	if ( $plus && '7' !== $digits[0] && '0' !== $digits[0] ) {
		return '+' . $digits;
	}
	// В старых заявках встречается шестизначный городской номер без кода.
	if ( ! $plus && 6 === $len ) {
		return $digits;
	}
	return '';
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
			'service' => absint( $_POST[ $p . 'service' ] ?? 0 ),
			'topic'   => sanitize_key( $_POST[ $p . 'topic' ] ?? 'general' ),
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

	$path = wp_parse_url( $url, PHP_URL_PATH ) ?: '/';
	return home_url( $path );
}

/**
 * Возврат к форме с сообщением об ошибке и сохранённым вводом.
 */
function razil_callback_fail( string $state = 'err' ): void {
	$url = add_query_arg(
		array(
			'rzcb'  => $state,
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
function razil_callback_done( int $id = 0 ): void {
	if ( $id ) { razil_callback_receipt( $id ); }
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
function razil_callback_notify( string $name, string $phone, string $page, int $id = 0, array $context = array() ): bool {
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
		. 'Номер заявки: ' . $id . "\n"
		. 'Тема: ' . ( $context['topic_label'] ?? 'Обратный звонок' ) . "\n"
		. 'Услуга: ' . ( $context['service'] ?? 'Общий вопрос' ) . "\n"
		. 'Имя: ' . $name . "\n"
		. 'Телефон: ' . $phone . "\n"
		. 'Время: ' . wp_date( 'd.m.Y H:i' ) . "\n"
		. 'Страница: ' . $page . "\n\n"
		. 'Список заявок: ' . admin_url( 'edit.php?post_type=' . RAZIL_CALLBACK_TYPE ) . "\n";

	return wp_mail( $to, 'Заявка на обратный звонок с сайта № ' . $id, $body );
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

	/*
	 * Капча не пройдена. Своё состояние вместо общего «не удалось»:
	 * человеку нужно знать, что дело не в полях, а при просрочке —
	 * что достаточно обновить страницу.
	 */
	if ( 'captcha' === $guard || 'captcha_expired' === $guard ) {
		razil_callback_fail( $guard );
	}

	// A replay of the same saved request is allowed through the rate limit.
	if ( '' !== $guard && 'throttle' !== $guard ) {
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
	$phone = mb_strlen( $phone_raw ) <= RAZIL_CALLBACK_PHONE_MAX
		? razil_callback_normalize_phone( $phone_raw ) : '';

	$consent = isset( $_POST[ $p . 'consent' ] ) && '1' === (string) wp_unslash( $_POST[ $p . 'consent' ] );
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$page = razil_callback_back_url();

	// Для новой заявки нужен полный номер с кодом страны.
	if ( ! str_starts_with( $phone, '+' ) || ! $consent ) {
		razil_callback_fail();
	}
	if ( '' === $name ) {
		$name = 'Имя не указано';
	}
	$request = sanitize_text_field( wp_unslash( $_POST[ $p . 'request' ] ?? '' ) );
	if ( ! preg_match( '/^[a-f0-9-]{36}$/i', $request ) ) { razil_callback_fail(); }
	$request_key = hash_hmac( 'sha256', $request . '|' . $phone, wp_salt( 'nonce' ) );
	global $wpdb;
	$lock = 'rzcb_' . substr( $request_key, 0, 50 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { razil_callback_fail(); }
	$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_razil_cb_request_key' AND pm.meta_value=%s AND p.post_type=%s LIMIT 1", $request_key, RAZIL_CALLBACK_TYPE ) );
	if ( $existing ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		razil_callback_done( $existing );
	}
	if ( 'throttle' === $guard ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		razil_callback_fail( 'throttle' );
	}
	$context = razil_callback_context( $_POST[ $p . 'service' ] ?? 0, sanitize_key( $_POST[ $p . 'topic' ] ?? 'general' ) );

	$id = wp_insert_post(
		array(
			'post_type'   => RAZIL_CALLBACK_TYPE,
			'post_status' => RAZIL_CALLBACK_NEW,
			// Заголовок — имя: в списке заявок именно оно должно быть видно.
			'post_title'  => $name,
			'meta_input' => array(
				RAZIL_CALLBACK_PHONE_KEY => $phone,
				RAZIL_CALLBACK_PAGE_KEY => $page,
				'_razil_cb_request_key' => $request_key,
				'_razil_cb_service_id' => $context['service_id'],
				'_razil_cb_topic' => $context['topic'],
				'_razil_cb_event_id' => wp_generate_uuid4(),
				'_razil_cb_notify_status' => 'pending',
			),
		),
		true
	);

	if ( is_wp_error( $id ) || ! $id ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		razil_callback_fail();
	}
	if ( $phone !== get_post_meta( $id, RAZIL_CALLBACK_PHONE_KEY, true ) || $request_key !== get_post_meta( $id, '_razil_cb_request_key', true ) ) {
		wp_delete_post( $id, true );
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		razil_callback_fail();
	}

	// Лимит ставим только после настоящей заявки: отклонённые попытки
	// не должны закрывать форму человеку, который ошибся в поле.
	set_transient( razil_form_throttle_key( 'razil_cb_' ), 1, RAZIL_CALLBACK_THROTTLE );
	// Create one receipt while holding the request lock, before another replay.
	razil_callback_receipt( $id );
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );

	razil_callback_notify_saved( $id );

	razil_callback_done( $id );
}
add_action( 'admin_post_nopriv_' . RAZIL_CALLBACK_ACTION, 'razil_callback_handle' );
add_action( 'admin_post_' . RAZIL_CALLBACK_ACTION, 'razil_callback_handle' );
