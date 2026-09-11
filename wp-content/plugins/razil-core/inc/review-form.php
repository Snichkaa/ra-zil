<?php
/**
 * Форма отправки отзыва.
 *
 * Отзыв с формы создаётся черновиком и попадает на сайт только после
 * проверки: публикация без модерации на ритуальном сайте недопустима.
 *
 * Боты отсекаются четырьмя дешёвыми серверными проверками: подпись формы,
 * ловушка, скорость заполнения и повтор с одного IP. Пятая — капча
 * Яндекса, и она требует JavaScript: без него токен взять неоткуда
 * и форма отправлена не будет. Заполненное при ошибке возвращается в форму.
 *
 * Сами проверки, возврат заполненного и разметка согласия — общие для форм
 * сайта и живут в inc/form-support.php. Здесь остаётся то, чем отзыв
 * отличается от других форм: поля, тексты и запись в базу.
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
 * Адрес страницы отзывов — туда возвращаем после отправки.
 */
function razil_review_return_url(): string {
	return razil_form_page_url( 'otzyvy' );
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
 * Сохранение заполненного на время редиректа. Поля свои, механика общая.
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

	return razil_form_stash( 'razil_review_refill_', $fields, RAZIL_REVIEW_REFILL_TTL );
}

/**
 * Чтение сохранённого для подстановки в форму.
 */
function razil_review_refill(): array {
	return razil_form_refill(
		'razil_review_refill_',
		array(
			'name'    => '',
			'text'    => '',
			'rating'  => 0,
			'consent' => 0,
		),
		'rzk'
	);
}

/**
 * Состояние страницы после редиректа: ok, err или пустая строка,
 * если форма открыта обычным заходом.
 */
function razil_review_state(): string {
	return razil_form_state(
		'rz',
		array(
			'ok',
			'err',
			'captcha',
			'captcha_expired',
			'no_rating',
			'short_text',
			'short_name',
			'no_consent',
			'throttle',
		)
	);
}

/**
 * Сообщение после отправки.
 *
 * Скрипт инлайновый: на фронте у темы своих скриптов нет, и заводить
 * очередь ради семи строк незачем. Параметр rz вычищается из адреса,
 * иначе после обновления страницы сообщение появится снова.
 */
function razil_review_notice(): string {
	return razil_form_notice(
		razil_review_state(),
		array(
			'ok'              => 'Спасибо. Отзыв отправлен и появится на сайте после проверки.',
			'err'             => 'Не удалось отправить отзыв. Проверьте, что все поля заполнены, и попробуйте ещё раз. Написанное сохранено.',
			'captcha'         => 'Проверка не пройдена, отзыв не отправлен. Попробуйте отправить ещё раз. Написанное сохранено.',
			'captcha_expired' => 'Страница была открыта слишком давно, и проверка устарела. Обновите страницу '
				. 'и отправьте отзыв ещё раз. Написанное сохранено.',
			'no_rating'       => 'Выберите оценку от одной до пяти звёзд. Написанное сохранено.',
			'short_text'      => 'Отзыв слишком короткий. Напишите хотя бы несколько слов. Написанное сохранено.',
			'short_name'      => 'Укажите имя — хотя бы две буквы. Написанное сохранено.',
			'no_consent'      => 'Отметьте согласие на обработку персональных данных. Написанное сохранено.',
			'throttle'        => 'Вы недавно уже отправляли отзыв. Попробуйте через несколько минут.',
		),
		'ok',
		array( 'rz', 'rzk' )
	);
}

/**
 * Форма отправки отзыва. Регистрируется шорткодом [razil_review_form].
 */
function razil_review_form(): string {
	$star = razil_review_star_svg();

	// Заполненное с прошлой попытки — для подстановки значений.
	$was = razil_review_refill();

	$out = razil_review_notice();

	$out .= '<form class="rz-form rz-review-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$out .= razil_form_hidden_fields( RAZIL_REVIEW_ACTION, 'rz_' );
	$out .= razil_form_honeypot( 'rz-website', 'rz_website' );

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
	$out .= razil_form_consent( 'rz-consent', 'rz_consent', 1 === (int) $was['consent'] );

	// Невидимая капча: разметки не даёт и места не занимает, пока не настроена.
	$out .= razil_captcha_widget( 'review' );

	$out .= '<p class="rz-form__row">'
		. '<button type="submit" class="wp-element-button rz-form__submit">Отправить отзыв</button>'
		. '</p>';

	$out .= '</form>';

	$out .= razil_review_validation_js();

	return $out;
}
/**
 * Подсказки браузера до отправки.
 *
 * Дублирует серверные проверки, но не заменяет их: скрипт можно обойти,
 * сервер - нет. Смысл в другом: человек видит, что исправить, сразу под
 * полем и не теряет круг с перезагрузкой страницы.
 *
 * Пороги те же, что на сервере: имя от двух букв, отзыв от семи знаков.
 * Разойдутся - человек получит подсказку от браузера, отправит, и всё
 * равно упрётся в отказ сервера.
 */
function razil_review_validation_js(): string {
	return <<<'JS'
<script>
( function () {
	/*
	 * Ищем от самого тега script, а не по документу: класс rz-form носит
	 * и форма обратного звонка, и querySelector взял бы ту, что выше
	 * в DOM. document.currentScript указывает на этот script в момент
	 * выполнения, а форма - его непосредственный предыдущий сосед,
	 * потому что шорткод печатает их подряд. Заодно это делает вывод
	 * безопасным при двух формах на странице: каждый скрипт найдёт свою.
	 */
	var tag = document.currentScript;
	var form = tag ? tag.previousElementSibling : null;

	if ( ! form || ! form.classList.contains( 'rz-review-form' ) ) {
		form = document.querySelector( '.rz-review-form' );
	}

	if ( ! form ) {
		return;
	}

	var name = form.querySelector( '#rz-name' );
	var text = form.querySelector( '#rz-text' );
	var stars = form.querySelectorAll( '.rz-stars__input' );

	var letters = function ( s ) {
		var m = s.match( /\p{L}/gu );
		return m ? m.length : 0;
	};

	var checkName = function () {
		var v = name.value.trim();

		if ( v.length < 2 || letters( v ) < 2 ) {
			name.setCustomValidity( 'Укажите имя — хотя бы две буквы.' );
		} else {
			name.setCustomValidity( '' );
		}
	};

	var checkText = function () {
		var v = text.value.trim();

		if ( v.length < 7 || letters( v ) < 5 ) {
			text.setCustomValidity( 'Отзыв слишком короткий. Напишите хотя бы несколько слов.' );
		} else {
			text.setCustomValidity( '' );
		}
	};

	var checkStars = function () {
		var picked = false;

		Array.prototype.forEach.call( stars, function ( s ) {
			if ( s.checked ) {
				picked = true;
			}
		} );

		Array.prototype.forEach.call( stars, function ( s ) {
			s.setCustomValidity( picked ? '' : 'Выберите оценку от одной до пяти звёзд.' );
		} );
	};

	if ( name ) {
		name.addEventListener( 'input', checkName );
		name.addEventListener( 'blur', checkName );
	}

	if ( text ) {
		text.addEventListener( 'input', checkText );
		text.addEventListener( 'blur', checkText );
	}

	Array.prototype.forEach.call( stars, function ( s ) {
		s.addEventListener( 'change', checkStars );
	} );

}() );
</script>
JS;
}

add_shortcode( 'razil_review_form', 'razil_review_form' );

/**
 * Ключ транзиента с ограничением по адресу.
 *
 * Хешируем адрес, а не храним как есть: сам IP нам не нужен,
 * достаточно признака «этот посетитель уже писал».
 */
function razil_review_throttle_key(): string {
	return razil_form_throttle_key( 'razil_review_' );
}

/**
 * Возврат на страницу отзывов с признаком результата.
 *
 * @param string $state  ok, err, captcha или captcha_expired.
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
	$key = razil_review_throttle_key();

	/*
	 * 1–4. Подпись, ловушка, скорость, повтор с адреса — общей проверкой.
	 *
	 * При заполненной ловушке уходим с признаком успеха: бот не должен
	 * понять, что его отсекли, иначе следующая попытка будет умнее.
	 */
	$stop = razil_form_guard( RAZIL_REVIEW_ACTION, 'rz_', RAZIL_REVIEW_MIN_SECONDS, $key );

	if ( 'trap' === $stop ) {
		razil_review_redirect( 'ok' );
	}

	/*
	 * Капча не пройдена — заполненное возвращаем в форму: терять
	 * написанный отзыв из-за проверки нельзя.
	 */
	if ( 'captcha' === $stop || 'captcha_expired' === $stop ) {
		razil_review_redirect( $stop, razil_review_stash() );
	}

	/*
	 * Повтор с одного адреса называем своим именем: человек должен понять,
	 * что дело во времени, а не в его заполнении. Остальные причины
	 * (подпись не сошлась, время не пришло) человеку не объяснить -
	 * они означают либо бота, либо сбой, и для них общее «не удалось».
	 *
	 * Заполненное сохраняем и здесь: при лимите отзыв написан целиком,
	 * терять его из-за паузы нельзя.
	 */
	if ( 'throttle' === $stop ) {
		razil_review_redirect( 'throttle', razil_review_stash() );
	}

	if ( '' !== $stop ) {
		razil_review_redirect( 'err', razil_review_stash() );
	}

	// 5. Разбор полей. Проверки ниже раздельные, у каждой свой код
	// состояния: человеку важно знать, что именно исправить.
	// Заполненное возвращаем в форму при любом отказе.
	$name    = isset( $_POST['rz_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rz_name'] ) ) : '';
	$name    = mb_substr( $name, 0, 60 );
	$text    = isset( $_POST['rz_text'] ) ? wp_kses_post( wp_unslash( $_POST['rz_text'] ) ) : '';
	$text    = mb_substr( wp_strip_all_tags( $text ), 0, 2000 );
	$rating  = isset( $_POST['rz_rating'] ) ? (int) $_POST['rz_rating'] : 0;
	$consent = ! empty( $_POST['rz_consent'] );

	/*
	 * Проверки раздельные: одно общее «проверьте поля» не говорит человеку,
	 * что именно исправить. Порядок сверху вниз совпадает с порядком полей
	 * в форме, чтобы сообщение указывало на первое непройденное сверху.
	 *
	 * Буквы считаем по \p{L} с флагом u: без него кириллица не совпадёт,
	 * и «Спасибо» не пройдёт проверку на две буквы в имени.
	 */
	$name_letters = preg_match_all( '/\p{L}/u', $name );
	$text_letters = preg_match_all( '/\p{L}/u', $text );

	if ( mb_strlen( trim( $name ) ) < 2 || $name_letters < 2 ) {
		razil_review_redirect( 'short_name', razil_review_stash() );
	}

	if ( $rating < 1 || $rating > 5 ) {
		razil_review_redirect( 'no_rating', razil_review_stash() );
	}

	if ( mb_strlen( trim( $text ) ) < 7 || $text_letters < 5 ) {
		razil_review_redirect( 'short_text', razil_review_stash() );
	}

	if ( ! $consent ) {
		razil_review_redirect( 'no_consent', razil_review_stash() );
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
