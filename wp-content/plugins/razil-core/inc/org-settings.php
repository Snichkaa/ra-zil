<?php
/**
 * Контактные данные организации в одном месте.
 *
 * Телефоны и почта раньше лежали россыпью по шаблонам темы, по разметке
 * страниц и по коду плагина — пятнадцать точек на два номера. Здесь они
 * сведены в одну опцию, которую владелец сайта правит из админки:
 * Настройки → Организация.
 *
 * Хранится человеческое написание, ровно как на сайте: «+7 (4212) 60-52-90».
 * Адрес для ссылки (tel:+74212605290) не хранится, а вычисляется — иначе
 * два поля неизбежно разъедутся, и кнопка звонила бы не туда, куда написано.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Имя опции. Одна опция на все контакты: они правятся вместе и читаются
 * вместе, а три отдельные записи — это три запроса и три повода
 * рассинхронизироваться.
 */
const RAZIL_ORG_OPTION = 'razil_org';

/**
 * Значения по умолчанию.
 *
 * Это те самые значения, что были захардкожены в теме, снятые оттуда
 * дословно. Пока форму настроек ни разу не сохраняли, опции в базе нет,
 * и сайт работает на них — вид страниц от появления настроек не меняется.
 *
 * @return array<string, string>
 */
function razil_org_defaults(): array {
	return array(
		'phone'       => '+7 (4212) 60-52-90',
		'phone_legal' => '+7 (962) 151-42-57',
		'email'       => 'naumova@ra-zil.ru',
		/*
		 * Строка под формой обратного звонка. Обещание сдержанное
		 * намеренно: офис работает пн–сб 10:00–17:00 и вс 10:00–16:00,
		 * а дежурный телефон — круглосуточно. Обещать «перезвоним
		 * через пять минут» в три ночи было бы неправдой.
		 */
		'callback_note' => 'Перезвоним в рабочее время офиса, обычно в течение часа. '
			. 'Если нужно срочно — звоните, телефон работает круглосуточно.',
		/*
		 * Через сколько дней удалять отработанные заявки. Ноль отключает
		 * удаление. Хранить их вечно нельзя: это персональные данные,
		 * и каждый лишний день хранения — лишняя ответственность.
		 */
		'keep_days'     => 60,
	);
}

/**
 * Верхняя граница срока хранения, дни.
 *
 * Десять лет — не осмысленный срок для заявки на звонок, а защита от опечатки
 * вроде лишнего нуля: с таким значением удаление фактически не наступит,
 * и человек будет думать, что оно работает.
 */
const RAZIL_ORG_KEEP_DAYS_MAX = 3650;

/**
 * Все контакты разом: сохранённое поверх значений по умолчанию.
 *
 * Слияние именно так, а не через второй аргумент get_option: если в базе
 * лежит массив без какого-то ключа (опция сохранена версией плагина,
 * которая этого поля ещё не знала), недостающее возьмётся из умолчаний.
 *
 * @return array<string, string>
 */
function razil_org_all(): array {
	$saved = get_option( RAZIL_ORG_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, razil_org_defaults() );
}

if ( ! function_exists( 'razil_org' ) ) {
	/**
	 * Значение одного контакта.
	 *
	 * @param string $key     Ключ: phone, phone_legal или email.
	 * @param string $default Что вернуть, если ключа нет вовсе.
	 */
	function razil_org( string $key, string $default = '' ): string {
		$all = razil_org_all();

		// is_string намеренно: в опции живёт ещё и frozen — массив
		// идентификаторов, приводить его к строке нельзя.
		if ( ! isset( $all[ $key ] ) || ! is_string( $all[ $key ] ) || '' === $all[ $key ] ) {
			return $default;
		}

		return $all[ $key ];
	}
}

/**
 * Адрес ссылки из любого написания номера.
 *
 * Оставляем цифры и ведущий плюс, всё остальное — скобки, пробелы,
 * дефисы — отбрасываем. Плюс только в начале: внутри номера он значения
 * не имеет, а в адресе ссылки означал бы совсем другое.
 *
 * @param string $raw Человеческое написание, например «+7 (4212) 60-52-90».
 */
function razil_org_tel_from( string $raw ): string {
	$raw = trim( $raw );

	if ( '' === $raw ) {
		return '';
	}

	$plus   = str_starts_with( $raw, '+' );
	$digits = (string) preg_replace( '/\D+/', '', $raw );

	if ( '' === $digits ) {
		return '';
	}

	return 'tel:' . ( $plus ? '+' : '' ) . $digits;
}

if ( ! function_exists( 'razil_org_tel' ) ) {
	/**
	 * Телефон из настроек в виде адреса ссылки:
	 * «+7 (4212) 60-52-90» → «tel:+74212605290».
	 *
	 * @param string $key Ключ телефона: phone или phone_legal.
	 */
	function razil_org_tel( string $key = 'phone' ): string {
		return razil_org_tel_from( razil_org( $key ) );
	}
}

if ( ! function_exists( 'razil_org_number' ) ) {
	/**
	 * Числовое значение настройки.
	 *
	 * Отдельно от razil_org: та возвращает только строки, потому что
	 * в опции живут и массив исключённых страниц, и число дней.
	 *
	 * @param string $key     Ключ.
	 * @param int    $default Значение, если ключа нет.
	 */
	function razil_org_number( string $key, int $default = 0 ): int {
		$all = razil_org_all();

		if ( ! isset( $all[ $key ] ) || ! is_scalar( $all[ $key ] ) ) {
			return $default;
		}

		return absint( $all[ $key ] );
	}
}

/**
 * Страницы, где контакты зафиксированы в тексте и не подставляются.
 *
 * Юридические документы: политика обработки персональных данных, согласие,
 * пользовательское соглашение. Реквизиты в них меняются только вручную
 * и вместе с редакцией документа — это условие обязательств перед субъектом
 * персональных данных, а не техническая деталь.
 *
 * Список живёт в настройках, а не в мете записей, намеренно. Мета означала бы
 * запись в те самые документы, которые мы договорились не трогать: история
 * правок документа должна оставаться историей его редакций, а не смесью
 * редакций с техническими флагами. Вдобавок список из настроек виден целиком
 * одним экраном, а мету пришлось бы искать по страницам поодиночке.
 *
 * @return int[] Идентификаторы записей.
 */
function razil_org_frozen(): array {
	$all = razil_org_all();

	if ( empty( $all['frozen'] ) || ! is_array( $all['frozen'] ) ) {
		return array();
	}

	return array_values( array_unique( array_map( 'absint', $all['frozen'] ) ) );
}

/**
 * Зафиксированы ли контакты на этой записи.
 *
 * @param int $post_id Идентификатор записи.
 */
function razil_org_is_frozen( int $post_id ): bool {
	return $post_id > 0 && in_array( $post_id, razil_org_frozen(), true );
}

/**
 * Санитизация перед записью в базу.
 *
 * Пустое поле не сохраняем: телефон выводится в шапке, в липкой панели
 * и в призыве, и пустая строка там означала бы страницу без единого
 * способа позвонить. Вместо этого возвращаем значение по умолчанию
 * и говорим об этом в админке — молча подменять введённое нельзя.
 *
 * @param mixed $input Данные из формы настроек.
 *
 * @return array<string, string>
 */
function razil_org_sanitize( $input ): array {
	$defaults = razil_org_defaults();
	$input    = is_array( $input ) ? $input : array();
	$clean    = array();

	$labels = razil_org_field_labels();

	/*
	 * Телефон и почту пустыми оставлять нельзя, а поясняющую строку — можно:
	 * если её очистить, под формой просто не будет строки. Это оформление,
	 * а не способ связи.
	 */
	$may_be_empty = array( 'callback_note' );

	foreach ( $defaults as $key => $fallback ) {
		$raw = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';

		/*
		 * Срок хранения — число, и ноль здесь осмысленное значение:
		 * им удаление отключают. Поэтому не проходит через общую
		 * проверку на пустоту, которая вернула бы значение по умолчанию.
		 */
		if ( 'keep_days' === $key ) {
			$clean[ $key ] = min( absint( $raw ), RAZIL_ORG_KEEP_DAYS_MAX );

			continue;
		}

		$value = 'email' === $key
			? sanitize_email( $raw )
			: sanitize_text_field( $raw );

		if ( '' === trim( $value ) && in_array( $key, $may_be_empty, true ) ) {
			$clean[ $key ] = '';

			continue;
		}

		if ( '' === trim( $value ) ) {
			$clean[ $key ] = $fallback;

			// Колбэк санитизации срабатывает на любом update_option, а не
			// только на отправке формы, поэтому в админке мы можем и не быть:
			// add_settings_error живёт в wp-admin/includes/template.php.
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error(
					RAZIL_ORG_OPTION,
					'razil_org_' . $key,
					sprintf(
						'Поле «%s» нельзя оставить пустым или заполнить неверно — вернули прежнее значение по умолчанию: %s',
						$labels[ $key ],
						$fallback
					),
					'error'
				);
			}

			continue;
		}

		$clean[ $key ] = $value;
	}

	/*
	 * Список исключённых страниц. Пустой список — обычное состояние
	 * (все галочки сняты), поэтому подмены значением по умолчанию здесь нет.
	 *
	 * Несуществующие идентификаторы отбрасываем: страницу могли удалить
	 * уже после того, как её отметили, и мусорный ID в опции никто бы
	 * не заметил — он просто перестал бы на что-либо влиять.
	 */
	$frozen          = isset( $input['frozen'] ) && is_array( $input['frozen'] ) ? $input['frozen'] : array();
	$clean['frozen'] = array();

	foreach ( array_unique( array_map( 'absint', $frozen ) ) as $id ) {
		if ( $id > 0 && null !== get_post( $id ) ) {
			$clean['frozen'][] = $id;
		}
	}

	sort( $clean['frozen'] );

	return $clean;
}

/**
 * Подписи полей. Отдельной функцией: нужны и форме, и сообщениям об ошибке.
 *
 * @return array<string, string>
 */
function razil_org_field_labels(): array {
	return array(
		'phone'       => 'Круглосуточный телефон',
		'phone_legal' => 'Телефон по юридическим документам',
		'email'       => 'Почта для уведомлений об отзывах',
		'callback_note' => 'Когда перезвоним',
		'keep_days'     => 'Хранить отработанные заявки, дней',
	);
}

/**
 * Пояснения под полями: где именно на сайте это выводится.
 *
 * @return array<string, string>
 */
function razil_org_field_notes(): array {
	return array(
		'phone'       => 'Основной номер. Выводится в шапке, в мобильном меню, на кнопке «Вызвать агента», '
			. 'в липкой панели звонка, на первом экране главной, в блоке «Что делать, если смерть наступила дома», '
			. 'в призыве перед подвалом, в копирайте, на страницах товаров и цен и на странице «Контакты».',
		'phone_legal' => 'Второй номер. Выводится на странице «Контакты», строкой «только по вопросам '
			. 'юридических документов, в часы работы офиса». В политике, согласии и пользовательском '
			. 'соглашении этот номер тоже стоит, но там он зафиксирован в тексте и из настроек '
			. 'не подставляется — см. список ниже.',
		'email'       => 'Адрес, на который приходит письмо о новом отзыве, отправленном через форму на странице «Отзывы». '
			. 'На страницах сайта не показывается.',
		'keep_days'     => 'Через сколько дней удалять заявки, отмеченные как «Перезвонили». '
			. 'Срок считается от момента отметки, а не от даты обращения: заявка, на которую '
			. 'ответили сегодня, пролежит полный срок, сколько бы она ни ждала ответа. '
			. 'Удаление окончательное, минуя корзину, раз в сутки. Заявки со статусом «Новая» '
			. 'не удаляются никогда: пока на обращение не ответили, оно остаётся невыполненным '
			. 'обещанием. Ноль отключает удаление совсем.',
		'callback_note' => 'Строка под формой обратного звонка: чего ждать после отправки заявки. '
			. 'Если очистить поле, строки под формой не будет.',
	);
}

/**
 * Регистрация опции, раздела и полей.
 */
function razil_org_register(): void {
	register_setting(
		'razil_org_group',
		RAZIL_ORG_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'razil_org_sanitize',
			'default'           => razil_org_defaults(),
			'show_in_rest'      => false,
		)
	);

	add_settings_section(
		'razil_org_contacts',
		'Контакты',
		'razil_org_section_intro',
		'razil-org'
	);

	$labels = razil_org_field_labels();

	foreach ( array_keys( razil_org_defaults() ) as $key ) {
		add_settings_field(
			'razil_org_' . $key,
			$labels[ $key ],
			'razil_org_field',
			'razil-org',
			'razil_org_contacts',
			array(
				'key'       => $key,
				'label_for' => 'razil-org-' . $key,
			)
		);
	}

	add_settings_section(
		'razil_org_frozen',
		'Страницы, где контакты зафиксированы',
		'razil_org_frozen_intro',
		'razil-org'
	);

	// label_for здесь не указываем намеренно: это не одно поле, а набор
	// галочек, и указывать на какую-то одну из них подписью раздела неверно.
	add_settings_field(
		'razil_org_frozen',
		'Не подставлять контакты',
		'razil_org_frozen_field',
		'razil-org',
		'razil_org_frozen'
	);
}
add_action( 'admin_init', 'razil_org_register' );

/**
 * Вводный текст раздела.
 */
function razil_org_section_intro(): void {
	echo '<p>Эти значения подставляются в готовые страницы сайта. Телефон пишите так, '
		. 'как он должен выглядеть для посетителя, со скобками и дефисами: '
		. '<code>+7 (4212) 60-52-90</code>. Адрес для ссылки «позвонить» собирается из номера сам.</p>';
}

/**
 * Отрисовка одного поля.
 *
 * @param array<string, string> $args Аргументы из add_settings_field.
 */
function razil_org_field( array $args ): void {
	$key   = $args['key'];
	$all   = razil_org_all();
	$notes = razil_org_field_notes();

	$phones = array_keys( razil_org_source_numbers() );

	if ( 'keep_days' === $key ) {
		printf(
			'<input type="number" id="%s" name="%s[%s]" value="%s" class="small-text" min="0" max="%s" step="1" />',
			esc_attr( 'razil-org-' . $key ),
			esc_attr( RAZIL_ORG_OPTION ),
			esc_attr( $key ),
			esc_attr( (string) razil_org_number( 'keep_days' ) ),
			esc_attr( (string) RAZIL_ORG_KEEP_DAYS_MAX )
		);

		printf( '<p class="description">%s</p>', esc_html( $notes[ $key ] ) );

		return;
	}

	printf(
		'<input type="%s" id="%s" name="%s[%s]" value="%s" class="%s" />',
		'email' === $key ? 'email' : 'text',
		esc_attr( 'razil-org-' . $key ),
		esc_attr( RAZIL_ORG_OPTION ),
		esc_attr( $key ),
		esc_attr( $all[ $key ] ),
		// Поясняющая строка длиннее номера, ей нужна вся ширина.
		'callback_note' === $key ? 'large-text' : 'regular-text'
	);

	printf( '<p class="description">%s</p>', esc_html( $notes[ $key ] ) );

	// Для телефонов сразу показываем, что получится в ссылке: так видно,
	// что номер записан разборчиво, ещё до сохранения.
	if ( in_array( $key, $phones, true ) ) {
		printf(
			'<p class="description">Ссылка «позвонить»: <code>%s</code></p>',
			esc_html( razil_org_tel( $key ) )
		);
	}
}

/**
 * Вводный текст раздела с исключениями.
 */
function razil_org_frozen_intro(): void {
	echo '<p>На отмеченных страницах контакты не подставляются: они остаются такими, '
		. 'как написаны в тексте. Это нужно юридическим документам — политике обработки '
		. 'персональных данных, согласию, пользовательскому соглашению: реквизиты в них '
		. 'меняются вручную и вместе с редакцией документа.</p>';
	echo '<p>Отметка действует на всю страницу целиком, включая шапку и подвал: иначе '
		. 'в тексте документа стоял бы один номер, а в шапке над ним — другой.</p>';
}

/**
 * Список страниц с галочками.
 *
 * Показываем все записи, а не только отмеченные: новая страница появляется
 * здесь сама, отмечать её можно сразу, править код не нужно.
 */
function razil_org_frozen_field(): void {
	$frozen = razil_org_frozen();

	$posts = get_posts(
		array(
			'post_type'   => 'page',
			'numberposts' => -1,
			'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);

	// Отмеченное не должно исчезнуть из списка только потому, что запись
	// не страница или не в этом статусе: иначе галочку нельзя было бы снять.
	$known = wp_list_pluck( $posts, 'ID' );

	foreach ( $frozen as $id ) {
		if ( ! in_array( $id, $known, true ) ) {
			$extra = get_post( $id );

			if ( $extra instanceof WP_Post ) {
				$posts[] = $extra;
			}
		}
	}

	echo '<fieldset>';
	echo '<legend class="screen-reader-text">Страницы, на которых контакты зафиксированы в тексте</legend>';

	foreach ( $posts as $post ) {
		$has = razil_org_post_has_number( $post );

		printf(
			'<label style="display:block;margin-bottom:.35rem"><input type="checkbox" name="%s[frozen][]" value="%d" %s /> %s <code>%s</code>%s</label>',
			esc_attr( RAZIL_ORG_OPTION ),
			(int) $post->ID,
			checked( in_array( (int) $post->ID, $frozen, true ), true, false ),
			esc_html( $post->post_title ),
			esc_html( $post->post_name ),
			$has ? ' <span class="description">— контакты в тексте есть</span>' : ''
		);
	}

	echo '</fieldset>';
}

/**
 * Пункт меню: Настройки → Организация.
 */
function razil_org_menu(): void {
	add_options_page(
		'Организация',
		'Организация',
		'manage_options',
		'razil-org',
		'razil_org_page'
	);
}
add_action( 'admin_menu', 'razil_org_menu' );

/**
 * Страница настроек.
 */
function razil_org_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="wrap">';
	echo '<h1>Организация</h1>';

	settings_errors( RAZIL_ORG_OPTION );

	echo '<form action="options.php" method="post">';
	settings_fields( 'razil_org_group' );
	do_settings_sections( 'razil-org' );
	submit_button( 'Сохранить' );
	echo '</form>';
	echo '</div>';
}

/* ==================================================================
 * ПОДСТАНОВКА ТЕЛЕФОНОВ В ГОТОВУЮ СТРАНИЦУ
 * ================================================================== */

/**
 * Меньше скольких цифр номер считается служебным.
 *
 * Порог тот же, что в теме: assets/js/header.js, SHORT_NUMBER_DIGITS.
 * Там по нему не перехватываются 112, 103, 102, 03 и 02 в блоке «что
 * делать, если смерть наступила дома», здесь по нему же они исключаются
 * из подстановки. Признак — длина, а не перечень номеров: список пришлось
 * бы держать в двух местах и он всё равно бы разошёлся.
 */
const RAZIL_ORG_SHORT_NUMBER_DIGITS = 6;

/**
 * Номера, которые ищутся в готовой странице.
 *
 * Это значения по умолчанию, то есть ровно те строки, что лежат
 * захардкоженными в файлах темы и в разметке страницы «Контакты».
 * Короткие служебные номера сюда не попадают по порогу длины.
 *
 * @return array<string, string> Ключ настройки => написание номера.
 */
function razil_org_source_numbers(): array {
	$out = array();

	foreach ( array( 'phone', 'phone_legal' ) as $key ) {
		$number = razil_org_defaults()[ $key ];

		if ( strlen( (string) preg_replace( '/\D+/', '', $number ) ) < RAZIL_ORG_SHORT_NUMBER_DIGITS ) {
			continue;
		}

		$out[ $key ] = $number;
	}

	return $out;
}

/**
 * Что на что менять в готовой странице.
 *
 * Ключ — строка, захардкоженная в теме и в разметке страниц, то есть
 * значение по умолчанию. Значение — то, что стоит в настройках.
 * Совпадающие пары в карту не попадают: пустая карта означает,
 * что страницу трогать не надо вовсе.
 *
 * ---
 *
 * ЗАЧЕМ ЭТО ВООБЩЕ И ГДЕ ПРАВИТЬ НОМЕР
 *
 * Номера захардкожены в шести файлах темы: parts/header.html (три раза),
 * parts/footer.html (два), parts/sticky-call.html, templates/front-page.html
 * (два), templates/page-price.html, templates/single-products.html — плюс
 * два раза в разметке страницы «Контакты» в базе.
 *
 * Все эти написания — резервные. Они показываются, пока в админке не задано
 * другое значение, и подменяются здесь на лету при выводе страницы.
 * Поэтому править номер надо в Настройки → Организация, а не в файлах:
 * правка в файле меняет только запасное значение и на сайте не видна,
 * пока настройка не совпадает с ней.
 *
 * В самих файлах на этот счёт оставлена однострочная отсылка сюда.
 * Подробности держим в PHP намеренно: комментарий в части шаблона
 * попадает в исходный код каждой страницы, и четыре развёрнутых пояснения
 * стоили около 1.8 КБ на просмотр.
 *
 * @return array<string, string>
 */
function razil_org_replacements(): array {
	$map = array();

	foreach ( razil_org_source_numbers() as $key => $old ) {
		$new = razil_org( $key );

		if ( $old !== $new && '' !== $new ) {
			$map[ $old ] = $new;
		}

		// Адрес ссылки берётся вместе с href= и кавычками: так замена
		// не заденет тот же tel: где-нибудь в тексте или в атрибуте data-.
		$old_href = 'href="' . razil_org_tel_from( $old ) . '"';
		$new_href = 'href="' . razil_org_tel( $key ) . '"';

		if ( $old_href !== $new_href && 'href=""' !== $new_href ) {
			$map[ $old_href ] = $new_href;
		}
	}

	return $map;
}

/**
 * Замена в готовом HTML.
 *
 * Один проход одним регулярным выражением по всем искомым строкам сразу.
 * Это не украшательство, а требование задачи: на странице «Контакты» оба
 * номера начинаются одинаково, «+7 (», и последовательные замены могли бы
 * зацепить результат предыдущей. Один проход съедает каждую позицию строки
 * ровно один раз, поэтому замены независимы по построению.
 *
 * Искомые строки экранируются preg_quote целиком: плюс, скобки и дефис
 * в номере — метасимволы регулярного выражения.
 *
 * Содержимое <script> и <style> вырезается из обработки: там номер может
 * стоять в другом синтаксисе, а замена внутри кода — это уже не подстановка
 * значения, а правка кода.
 *
 * @param string $html Готовая страница.
 */
function razil_org_apply( string $html ): string {
	$map = razil_org_replacements();

	// Настройки совпадают с тем, что в файлах: возвращаем ту же строку,
	// не притрагиваясь к ней. Байт в байт гарантируется этой веткой.
	if ( ! $map ) {
		return $html;
	}

	$needles = array_keys( $map );

	// Длинные вперёд: href="tel:…" содержит в себе tel:…, и короткая
	// строка не должна перехватывать начало длинной.
	usort(
		$needles,
		static function ( string $a, string $b ): int {
			return strlen( $b ) <=> strlen( $a );
		}
	);

	$pattern = '/' . implode( '|', array_map( static fn( string $n ): string => preg_quote( $n, '/' ), $needles ) ) . '/';

	$parts = preg_split(
		'#(<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>)#is',
		$html,
		-1,
		PREG_SPLIT_DELIM_CAPTURE
	);

	if ( ! is_array( $parts ) ) {
		return $html;
	}

	foreach ( $parts as $i => $part ) {
		// Нечётные куски — это сами <script> и <style> вместе с содержимым.
		if ( 1 === $i % 2 ) {
			continue;
		}

		$parts[ $i ] = preg_replace_callback(
			$pattern,
			static function ( array $m ) use ( $map ): string {
				return $map[ $m[0] ];
			},
			$part
		);
	}

	return implode( '', $parts );
}

/**
 * Включение подстановки на публичной части сайта.
 *
 * Хук — template_redirect с буферизацией вывода.
 *
 * Почему именно он. Номера стоят не в содержимом записей, а в частях
 * шаблона: шапка, подвал, липкая панель, разметка шаблонов страниц.
 * Фильтры содержимого (the_content) их не видят вовсе. render_block
 * увидел бы, но вызывался бы сотни раз на страницу и всё равно требовал
 * бы той же возни со <script> внутри блоков. Буфер на template_redirect
 * трогает собранную страницу один раз, в одном месте, и именно на нём
 * проще всего гарантировать побайтовое совпадение при неизменных
 * настройках: пустая карта замен возвращает буфер как есть.
 *
 * template_redirect не срабатывает ни в админке, ни в REST, поэтому
 * редактор шаблонов и редактор блоков, которые тянут части шаблона
 * через REST, подстановки не видят и правят настоящие файлы темы.
 * Проверки ниже добавлены явно — на случай ленты, встраивания
 * и фонового запроса.
 */
function razil_org_buffer(): void {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}

	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_is_json_request() ) {
		return;
	}

	if ( is_feed() || is_embed() ) {
		return;
	}

	/*
	 * Исключённая страница выводится целиком без подстановки: буфер здесь
	 * просто не открывается.
	 *
	 * Именно вся страница, а не только область контента. Шапка и подвал
	 * документом не являются, и подставить номер в них казалось бы
	 * безобидным — но тогда на странице согласия оказались бы два разных
	 * номера сразу: зафиксированный в тексте и подставленный в шапке.
	 * Документ, который противоречит сам себе на одном экране, хуже
	 * документа с чуть устаревшим номером; обязательство даётся текстом,
	 * и разнобой вокруг него обесценивает как раз текст.
	 *
	 * is_singular обязателен: у архива get_queried_object_id вернёт
	 * идентификатор термина, а он может совпасть с идентификатором
	 * исключённой страницы по чистой случайности.
	 */
	if ( is_singular() && razil_org_is_frozen( get_queried_object_id() ) ) {
		return;
	}

	ob_start( 'razil_org_apply' );
}
add_action( 'template_redirect', 'razil_org_buffer' );

/* ==================================================================
 * ПОМЕТКА В РЕДАКТОРЕ
 * ================================================================== */

/**
 * Есть ли в разметке записи номер, который подставляется на лету.
 *
 * Ищем и человеческое написание, и адрес ссылки: в контенте может
 * оказаться только href, без видимого номера.
 *
 * @param WP_Post $post Запись.
 */
function razil_org_post_has_number( WP_Post $post ): bool {
	foreach ( razil_org_source_numbers() as $number ) {
		if ( str_contains( $post->post_content, $number ) ) {
			return true;
		}

		$tel = razil_org_tel_from( $number );

		if ( '' !== $tel && str_contains( $post->post_content, $tel ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Панель в редакторе на записи, где номер лежит прямо в разметке.
 *
 * Такая запись сейчас одна — страница «Контакты», — но привязки к её
 * идентификатору здесь нет намеренно: если номер попадёт в текст другой
 * страницы, пометка появится и там, без правки кода.
 *
 * В самой разметке записи ничего не меняется: пометка живёт только
 * в редакторе. Комментарий в post_content Гутенберг при первом же
 * сохранении превратил бы в «Классический блок».
 *
 * Сообщение уходит в хранилище core/notices, а не в admin_notices:
 * в блочном редакторе admin_notices не показывается.
 */
function razil_org_editor_notice(): void {
	$screen = get_current_screen();

	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$frozen = razil_org_is_frozen( $post->ID );

	// Исключённой странице панель нужна, даже если контактов в тексте пока
	// нет: автор должен знать, что подстановка здесь отключена, ещё до того,
	// как впишет номер.
	if ( ! $frozen && ! razil_org_post_has_number( $post ) ) {
		return;
	}

	$text = $frozen
		? 'Контакты на этой странице зафиксированы в тексте: подстановка из Настройки → Организация '
			. 'здесь отключена, и отключена для всей страницы, включая шапку и подвал. Правьте номер '
			. 'прямо в тексте и вместе с ним обновляйте редакцию документа.'
		: 'Телефоны на этой странице подставляются из Настройки → Организация. '
			. 'Правка номера здесь меняет только резервное значение.';

	wp_register_script( 'razil-org-editor-notice', false, array( 'wp-data', 'wp-notices' ), RAZIL_CORE_VERSION, true );
	wp_enqueue_script( 'razil-org-editor-notice' );

	wp_add_inline_script(
		'razil-org-editor-notice',
		sprintf(
			'( function ( wp ) {
	if ( ! wp || ! wp.data || ! wp.data.dispatch( "core/notices" ) ) {
		return;
	}

	wp.data.dispatch( "core/notices" ).createNotice(
		"info",
		%s,
		{
			id: "razil-org-phone",
			isDismissible: true,
			actions: [ { url: %s, label: "Открыть настройки" } ]
		}
	);
}( window.wp ) );',
			wp_json_encode( $text, JSON_UNESCAPED_UNICODE ),
			wp_json_encode( admin_url( 'options-general.php?page=razil-org' ), JSON_UNESCAPED_SLASHES )
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'razil_org_editor_notice' );
