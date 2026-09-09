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

/* ==========================================================================
   КАПЧА

   Yandex SmartCaptcha, невидимый режим. Пятый уровень защиты, добавленный
   к четырём прежним — подписи, ловушке, таймеру и лимиту по адресу.

   Ключи живут в wp-config.php: RAZIL_CAPTCHA_CLIENT для виджета
   и RAZIL_CAPTCHA_SECRET для проверки. Секретный ключ не покидает
   серверный код: ни в разметку, ни в сообщения, ни в лог он не попадает.

   Если констант нет — а на локальной машине их и нет, — формы работают
   ровно как раньше, без капчи и без единой ошибки.
   ========================================================================== */

/**
 * Адрес проверки токена.
 */
const RAZIL_CAPTCHA_ENDPOINT = 'https://smartcaptcha.yandexcloud.net/validate';

/**
 * Адрес скрипта виджета.
 *
 * render=onload с именем функции: виджет не рисуется сам, а ждёт, пока мы
 * скажем. Так мы получаем идентификатор виджета, без которого невидимый
 * режим не запустить.
 */
const RAZIL_CAPTCHA_SCRIPT = 'https://smartcaptcha.yandexcloud.net/captcha.js?render=onload&onload=razilCaptchaInit';

/**
 * Сколько ждать ответа от Яндекса, секунды.
 *
 * Коротко намеренно. Посетитель не должен ждать отправку формы десять
 * секунд из-за чужих неполадок, а при недоступности сервиса мы всё равно
 * пропускаем — см. razil_captcha_check.
 */
const RAZIL_CAPTCHA_TIMEOUT = 5;

/**
 * С какого возраста страницы считать отказ следствием просрочки.
 *
 * Столько же, сколько живёт сохранённое при ошибке заполнение.
 */
const RAZIL_CAPTCHA_STALE = 1800;

/**
 * Имя поля, в которое виджет кладёт токен.
 *
 * Задаётся не нами: так называет его сам SmartCaptcha. Префикса формы
 * здесь нет и не нужно — каждая форма отправляется отдельно и приносит
 * своё поле.
 */
const RAZIL_CAPTCHA_FIELD = 'smart-token';

/**
 * Настроена ли капча полностью.
 *
 * Обе константы проверяются вместе намеренно. Виджет без проверки
 * бесполезен, но безвреден; проверка без виджета отвергала бы живых людей,
 * потому что токен взять неоткуда. Включаем только когда есть обе.
 */
function razil_captcha_enabled(): bool {
	return defined( 'RAZIL_CAPTCHA_CLIENT' ) && '' !== (string) RAZIL_CAPTCHA_CLIENT
		&& defined( 'RAZIL_CAPTCHA_SECRET' ) && '' !== (string) RAZIL_CAPTCHA_SECRET;
}

/**
 * Разметка виджета для формы.
 *
 * Возвращает пустую строку, если капча не настроена: тогда в форме
 * не появляется ничего.
 *
 * Скрипт подключается прямо отсюда, в момент вывода формы. Раньше нельзя:
 * на wp_enqueue_scripts ещё неизвестно, будет ли на странице форма —
 * её выводит шорткод, а он отрабатывает позже. WordPress такое допускает,
 * скрипт уедет в подвал.
 *
 * @param string $uid Суффикс идентификатора: форма может быть на странице дважды.
 */
function razil_captcha_widget( string $uid ): string {
	if ( ! razil_captcha_enabled() ) {
		return '';
	}

	wp_enqueue_script(
		'razil-smartcaptcha',
		RAZIL_CAPTCHA_SCRIPT,
		array(),
		null,
		true
	);

	wp_add_inline_script( 'razil-smartcaptcha', razil_captcha_inline_js(), 'before' );

	/*
	 * Контейнер пустой: виджет рисует себя сам. В невидимом режиме он
	 * не занимает места в потоке, поэтому вёрстку не двигает — но CSS
	 * на всякий случай прижимает его к нулевой высоте, см. main.css.
	 */
	return '<div class="rz-captcha" data-rz-captcha data-sitekey="'
		. esc_attr( RAZIL_CAPTCHA_CLIENT ) . '" id="rz-captcha-' . esc_attr( $uid ) . '"></div>';
}

/**
 * Скрипт, связывающий виджет с отправкой формы.
 *
 * Порядок в невидимом режиме такой: нажатие на кнопку перехватывается,
 * запускается проверка, и только получив токен, форма уходит по-настоящему.
 *
 * Страховка по времени обязательна, но отправлять без токена нельзя:
 * сервер такие запросы отвергает, и человек потерял бы написанное впустую.
 * Поэтому по истечении ожидания кнопка разблокируется и показывается
 * причина. Повторное нажатие запускает проверку заново — к этому моменту
 * скрипт Яндекса обычно уже загрузился.
 */
function razil_captcha_inline_js(): string {
	return <<<'JS'
window.razilCaptchaInit = function () {
	if ( ! window.smartCaptcha ) {
		return;
	}

	var boxes = document.querySelectorAll( '[data-rz-captcha]' );

	Array.prototype.forEach.call( boxes, function ( box ) {
		var form = box.closest( 'form' );

		if ( ! form || form.dataset.rzCaptchaReady ) {
			return;
		}

		form.dataset.rzCaptchaReady = '1';

		var sent = false;
		var timer = null;

		var send = function () {
			if ( sent ) {
				return;
			}

			sent = true;

			if ( timer ) {
				window.clearTimeout( timer );
			}

			form.submit();
		};

		var widget = window.smartCaptcha.render( box, {
			sitekey: box.dataset.sitekey,
			invisible: true,
			hideShield: true,
			callback: send
		} );

		var btn = form.querySelector( '[type="submit"]' );
		var note = null;

		form.addEventListener( 'submit', function ( event ) {
			if ( sent ) {
				return;
			}

			event.preventDefault();

			if ( btn ) {
				btn.disabled = true;
			}

			/*
			 * Снимаем прошлое сообщение. Если оставить узел на месте,
			 * role="status" промолчит на второй и последующих попытках:
			 * объявляется только изменение содержимого, а не его наличие.
			 */
			if ( note ) {
				note.remove();
				note = null;
			}

			/*
			 * Не дождались ответа. Форму НЕ отправляем: без токена
			 * сервер её отвергнет, и написанное пропадёт. Возвращаем
			 * кнопку и объясняем, что произошло.
			 */
			timer = window.setTimeout( function () {
				if ( btn ) {
					btn.disabled = false;
				}

				note = document.createElement( 'p' );
				note.className = 'rz-form__note';
				note.setAttribute( 'role', 'status' );
				note.textContent = 'Проверка не отвечает. Попробуйте отправить ещё раз.';
				form.appendChild( note );
			}, 10000 );

			window.smartCaptcha.execute( widget );
		} );
	} );
};
JS;
}

/**
 * Проверка токена на стороне сервера.
 *
 * Возвращает пустую строку, если препятствий нет, иначе причину:
 *
 *   captcha          проверка не пройдена
 *   captcha_expired  страница была открыта слишком давно
 *
 * Два случая пропускаются намеренно, и оба — осознанный размен
 * в пользу живого посетителя:
 *
 *   1. Капча не настроена. Локальная машина, где констант нет.
 *
 *   2. Сервис не ответил за отведённое время. Иначе работа агентства
 *      зависела бы от чужого времени безотказной работы: Яндекс лёг —
 *      сайт перестал принимать заявки, и никто не узнает, пока
 *      не позвонит клиент.
 *
 * @param string $prefix Префикс имён полей формы: нужен для метки времени.
 */
function razil_captcha_check( string $prefix ): string {
	if ( ! razil_captcha_enabled() ) {
		return '';
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$token = isset( $_POST[ RAZIL_CAPTCHA_FIELD ] )
		? sanitize_text_field( wp_unslash( $_POST[ RAZIL_CAPTCHA_FIELD ] ) )
		: '';

	$started = isset( $_POST[ $prefix . 'time' ] ) ? (int) $_POST[ $prefix . 'time' ] : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	/*
	 * Токена нет вовсе. Это НЕ отказоустойчивость: виджет кладёт токен
	 * джаваскриптом, а бот с прямым POST его не получает никогда.
	 * Пропускать тут значит пропускать ровно тех, против кого капча
	 * и поставлена. Сюда доходим только при настроенной капче -
	 * razil_captcha_enabled() проверен выше.
	 */
	if ( '' === $token ) {
		return 'captcha';
	}

	$response = wp_remote_post(
		RAZIL_CAPTCHA_ENDPOINT,
		array(
			'timeout' => RAZIL_CAPTCHA_TIMEOUT,
			'body'    => array(
				'secret' => RAZIL_CAPTCHA_SECRET,
				'token'  => $token,
				'ip'     => razil_captcha_ip(),
			),
		)
	);

	/*
	 * Сюда попадаем и при таймауте, и при отказе сети. Объект ошибки
	 * НЕ логируем: в нём может оказаться тело запроса, а в теле — секретный
	 * ключ. Пропускаем молча.
	 */
	if ( is_wp_error( $response ) ) {
		return '';
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	/*
	 * Ответ не по протоколу — тот же случай, что и молчание.
	 */
	if ( ! is_array( $data ) ) {
		return '';
	}

	/*
	 * Код ответа различает две совершенно разные беды, и обходиться с ними
	 * надо по-разному.
	 *
	 * 200 — сервис отработал и вынес решение о ТОКЕНЕ. Решению верим.
	 *
	 * Не 200 — сервис отказался работать с НАМИ. Проверено вживую: при
	 * неверном секретном ключе приходит 403 и «Authentication failed.
	 * Invalid secret.» Виноват тут не посетитель, а наша настройка,
	 * и отвергать из-за неё всех подряд нельзя: форма закроется для живых
	 * людей, а причина будет видна только в чужой панели.
	 *
	 * Обратная сторона решения: при неверном ключе капча молча перестаёт
	 * работать, и снаружи это выглядит как будто она включена. Проверять
	 * настройку надо отправкой формы сразу после заливки ключей.
	 */
	if ( 200 !== $code ) {
		return '';
	}

	if ( isset( $data['status'] ) && 'ok' === $data['status'] ) {
		return '';
	}

	/*
	 * Проверка не пройдена. Осталось понять, назвать ли это просрочкой.
	 *
	 * Токен SmartCaptcha живёт недолго и годится один раз. Точную формулу
	 * ответа при просрочке я по документации не сверял и полагаться на текст
	 * сообщения не хочу: изменится формулировка — сломается ветка. Поэтому
	 * решаем по возрасту страницы, который у нас и так есть от проверки
	 * скорости заполнения.
	 *
	 * Если страница открыта полчаса и дольше, «обновите страницу» и вернее
	 * по существу, и полезнее человеку, чем «вы не прошли проверку».
	 */
	if ( $started > 0 && ( time() - $started ) >= RAZIL_CAPTCHA_STALE ) {
		return 'captcha_expired';
	}

	return 'captcha';
}

/**
 * Адрес посетителя для проверки токена.
 *
 * Яндекс принимает его необязательным параметром и сверяет с тем, откуда
 * пришёл токен. Пустая строка допустима.
 */
function razil_captcha_ip(): string {
	if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
		return '';
	}

	$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );

	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}


/**
 * Проверки от ботов, общие для всех форм.
 *
 * Возвращает пустую строку, если всё в порядке, иначе причину:
 *
 *   nonce           — подписи нет или она не подходит
 *   trap            — заполнена ловушка
 *   fast            — отправлено быстрее человеческого
 *   throttle        — с этого адреса недавно уже отправляли
 *   captcha         — капча не пройдена
 *   captcha_expired — страница была открыта слишком давно
 *
 * Порядок существенный: сначала четыре дешёвые проверки, которые стоят
 * микросекунды и не выходят за пределы сервера, и только потом капча —
 * она ходит по сети к чужому серверу и ждёт ответа. Поставить её раньше
 * значило бы на каждой попытке бота, которого и так отсечёт ловушка,
 * открывать сетевое соединение и ждать. При наплыве это превращается
 * в очередь висящих процессов, то есть в отказ обслуживания, устроенный
 * собственными руками.
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

	// Пятая и последняя: единственная, что ходит по сети.
	return razil_captcha_check( $prefix );
}
