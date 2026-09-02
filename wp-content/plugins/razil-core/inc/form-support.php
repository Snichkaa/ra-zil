<?php
/**
 * Общее для форм сайта.
 *
 * Формы на сайте устроены одинаково: отправка на admin-post.php, четыре
 * дешёвые серверные проверки от ботов, возврат заполненного при ошибке
 * и сообщение после редиректа. Всё это писалось для формы отзывов; когда
 * появилась вторая форма, обратный звонок, общие куски переехали сюда,
 * а в файлах форм осталось только то, чем они действительно различаются:
 * поля, тексты и что делать с принятыми данными.
 *
 * Все функции параметризованы префиксом. Префикс задаёт и имена полей
 * запроса, и ключи транзиентов, поэтому две формы на одной странице
 * не мешают друг другу.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ссылка на страницу по слагу с запасным вариантом на прямой адрес.
 *
 * По слагу, а не по идентификатору: идентификаторы у страниц согласий
 * на разных копиях сайта разные, а слаги одинаковые. Опция
 * wp_page_for_privacy_policy на этом сайте указывает на несуществующую
 * запись, поэтому get_privacy_policy_url() использовать нельзя.
 *
 * @param string $slug Слаг страницы.
 */
function razil_form_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );

	return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
}

/**
 * Ключ транзиента: 32 знака из wp_generate_uuid4 без дефисов.
 */
function razil_form_token(): string {
	return str_replace( '-', '', wp_generate_uuid4() );
}

/**
 * Проверка ключа перед обращением к транзиенту.
 *
 * Ключ приходит из адресной строки, поэтому проверяется строго: без этого
 * по подставленному ключу можно было бы заглядывать в чужие транзиенты.
 *
 * @param string $key Ключ из запроса.
 */
function razil_form_token_valid( string $key ): bool {
	return 1 === preg_match( '/^[a-f0-9]{32}$/', $key );
}

/**
 * Сохранение заполненного на время редиректа.
 *
 * Форма отправляется на admin-post.php и возвращается редиректом, поэтому
 * ввод переживает только то, что лежит на сервере. Куки и скрытые поля тут
 * не годятся: ключ уходит в адрес, значения — в транзиент.
 *
 * Значения приходят уже очищенными: чистить их умеет только та форма,
 * которая знает свои поля.
 *
 * @param string               $prefix Префикс ключа транзиента.
 * @param array<string, mixed> $values Что сохранить.
 * @param int                  $ttl    Время жизни, секунды.
 *
 * @return string Ключ или пустая строка, если сохранять нечего.
 */
function razil_form_stash( string $prefix, array $values, int $ttl ): string {
	$filled = false;

	foreach ( $values as $value ) {
		if ( '' !== $value && 0 !== $value && '0' !== $value ) {
			$filled = true;
			break;
		}
	}

	if ( ! $filled ) {
		return '';
	}

	$key = razil_form_token();
	set_transient( $prefix . $key, $values, $ttl );

	return $key;
}

/**
 * Чтение сохранённого для подстановки в форму.
 *
 * Одноразово: транзиент удаляется при первом же чтении, иначе повторное
 * открытие адреса с тем же ключом снова наполняло бы форму.
 *
 * @param string               $prefix   Префикс ключа транзиента.
 * @param array<string, mixed> $defaults Пустые значения полей.
 * @param string               $param    Имя параметра адреса с ключом.
 *
 * @return array<string, mixed>
 */
function razil_form_refill( string $prefix, array $defaults, string $param ): array {
	static $cache = array();

	if ( isset( $cache[ $prefix ] ) ) {
		return $cache[ $prefix ];
	}

	$cache[ $prefix ] = $defaults;

	$key = isset( $_GET[ $param ] ) ? sanitize_key( wp_unslash( $_GET[ $param ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ( ! razil_form_token_valid( $key ) ) {
		return $cache[ $prefix ];
	}

	$saved = get_transient( $prefix . $key );
	delete_transient( $prefix . $key );

	if ( is_array( $saved ) ) {
		$cache[ $prefix ] = array_merge( $defaults, array_intersect_key( $saved, $defaults ) );
	}

	return $cache[ $prefix ];
}

/**
 * Состояние страницы после редиректа.
 *
 * @param string   $param   Имя параметра адреса.
 * @param string[] $allowed Допустимые значения.
 */
function razil_form_state( string $param, array $allowed ): string {
	$state = isset( $_GET[ $param ] ) ? sanitize_key( wp_unslash( $_GET[ $param ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	return in_array( $state, $allowed, true ) ? $state : '';
}

/**
 * Сообщение после отправки.
 *
 * Скрипт инлайновый: на фронте у темы своих скриптов для этого нет,
 * и заводить очередь ради десяти строк незачем. Параметры вычищаются
 * из адреса, иначе после обновления страницы сообщение появится снова.
 *
 * @param string                $state  Текущее состояние.
 * @param array<string, string> $texts  Тексты по состояниям.
 * @param string                $ok     Какое состояние считать успехом.
 * @param string[]              $params Параметры адреса, которые вычистить.
 */
function razil_form_notice( string $state, array $texts, string $ok, array $params ): string {
	if ( ! isset( $texts[ $state ] ) ) {
		return '';
	}

	$is_ok = ( $ok === $state );
	$text  = $texts[ $state ];

	$out = '<div class="rz-form-notice ' . ( $is_ok ? 'rz-form-notice--ok' : 'rz-form-notice--err' ) . '"'
		. ' role="' . ( $is_ok ? 'status' : 'alert' ) . '"'
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
		' . implode(
			"\n\t\t",
			array_map(
				static function ( string $p ): string {
					return 'url.searchParams.delete( "' . esc_js( $p ) . '" );';
				},
				$params
			)
		) . '
		window.history.replaceState( {}, "", url.pathname + url.search + url.hash );
	}
})();
</script>';

	return $out;
}

/**
 * Скрытые поля, общие для всех форм: действие, подпись и метка времени.
 *
 * @param string $action Имя действия.
 * @param string $prefix Префикс имён полей.
 */
function razil_form_hidden_fields( string $action, string $prefix ): string {
	return '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />'
		. wp_nonce_field( $action, $prefix . 'nonce', true, false )
		. '<input type="hidden" name="' . esc_attr( $prefix . 'time' ) . '" value="' . esc_attr( (string) time() ) . '" />';
}

/**
 * Ловушка для ботов.
 *
 * Скрыта позиционированием и clip-path в классе .rz-hp, а не display: none:
 * часть ботов пропускает поля с display: none, а такие заполняет.
 *
 * @param string $id   Идентификатор поля.
 * @param string $name Имя поля.
 */
function razil_form_honeypot( string $id, string $name ): string {
	return '<div class="rz-hp" aria-hidden="true">'
		. '<label for="' . esc_attr( $id ) . '">Сайт</label>'
		. '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" tabindex="-1" autocomplete="off" />'
		. '</div>';
}

/**
 * Галочка согласия со ссылками на страницы согласия и политики.
 *
 * Одна разметка на все формы: текст согласия — юридически значимая строка,
 * и расходиться в двух местах он не должен.
 *
 * @param string $id      Идентификатор поля.
 * @param string $name    Имя поля.
 * @param bool   $checked Отмечена ли галочка.
 */
function razil_form_consent( string $id, string $name, bool $checked ): string {
	return '<p class="rz-form__row rz-form__consent">'
		. '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"'
		. checked( true, $checked, false ) . ' required />'
		. '<label for="' . esc_attr( $id ) . '">Я даю '
		. '<a href="' . esc_url( razil_form_page_url( 'soglasie' ) ) . '" target="_blank" rel="noopener">согласие на обработку персональных данных</a>'
		. ' и ознакомлен с '
		. '<a href="' . esc_url( razil_form_page_url( 'politika' ) ) . '" target="_blank" rel="noopener">политикой обработки</a>.'
		. '</label>'
		. '</p>';
}

/**
 * Ключ транзиента лимита по адресу.
 *
 * Соль подмешивается, чтобы по имени опции нельзя было восстановить адрес:
 * ключ хранится в открытой таблице настроек.
 *
 * @param string $prefix Префикс ключа.
 */
function razil_form_throttle_key( string $prefix ): string {
	// Запасное «unknown», а не пустая строка: так ключ у запросов без адреса
	// один и тот же, и лимит на них тоже действует.
	$ip = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: 'unknown';

	return $prefix . md5( $ip . wp_salt() );
}

/**
 * Четыре дешёвые проверки от ботов, общие для всех форм.
 *
 * Возвращает пустую строку, если всё в порядке, иначе причину:
 *
 *   nonce    — подписи нет или она не подходит
 *   trap     — заполнена ловушка
 *   fast     — отправлено быстрее человеческого
 *   throttle — с этого адреса недавно уже отправляли
 *
 * Решение, что делать с причиной, остаётся за формой: например, при
 * заполненной ловушке правильно показать успех, чтобы бот не понял,
 * что его отсекли, а при остальных — ошибку.
 *
 * @param string $action       Имя действия для проверки подписи.
 * @param string $prefix       Префикс имён полей формы.
 * @param int    $min_seconds  Минимальное время заполнения.
 * @param string $throttle_key Ключ транзиента лимита.
 */
function razil_form_guard( string $action, string $prefix, int $min_seconds, string $throttle_key ): string {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$nonce = isset( $_POST[ $prefix . 'nonce' ] )
		? sanitize_text_field( wp_unslash( $_POST[ $prefix . 'nonce' ] ) )
		: '';

	if ( '' === $nonce || ! wp_verify_nonce( $nonce, $action ) ) {
		return 'nonce';
	}

	$trap = isset( $_POST[ $prefix . 'website' ] )
		? trim( (string) wp_unslash( $_POST[ $prefix . 'website' ] ) )
		: '';

	if ( '' !== $trap ) {
		return 'trap';
	}

	$started = isset( $_POST[ $prefix . 'time' ] ) ? (int) $_POST[ $prefix . 'time' ] : 0;

	if ( $started <= 0 || ( time() - $started ) < $min_seconds ) {
		return 'fast';
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( get_transient( $throttle_key ) ) {
		return 'throttle';
	}

	return '';
}
