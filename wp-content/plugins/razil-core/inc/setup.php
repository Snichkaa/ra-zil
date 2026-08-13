<?php
/**
 * Инструмент для создания начальной структуры сайта.
 *
 * Страница в админке позволяет за один клик создать все разделы,
 * услуги и страницы проекта.
 *
 * @package RazilCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрируем страницу меню в админке.
 */
function razil_setup_register_menu(): void {
	add_management_page(
		'Структура сайта',
		'Структура сайта',
		'manage_options',
		'razil-setup',
		'razil_setup_page'
	);
}
add_action( 'admin_menu', 'razil_setup_register_menu' );

/**
 * Вывод страницы с кнопкой и отчётом.
 */
function razil_setup_page(): void {
	// Проверяем права.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Недостаточно прав.' );
	}

	// Обрабатываем отправку формы.
	if ( isset( $_POST['razil_setup_nonce'] ) ) {
		check_admin_referer( 'razil_setup_action', 'razil_setup_nonce' );

		// Выполняем создание структуры.
		$report = razil_create_site_structure();

		// Выводим отчёт.
		echo '<div class="wrap">';
		echo '<h1>Структура сайта</h1>';
		echo '<div class="notice notice-success"><p>Структура создана!</p></div>';

		echo '<h2>Отчёт</h2>';
		echo '<table class="wp-list-table fixed striped">';
		echo '<thead><tr><th>Что</th><th>Статус</th></tr></thead>';
		echo '<tbody>';

		foreach ( $report as $item ) {
			$status_class = 'created' === $item['status'] ? 'success' : 'info';
			$status_text  = 'created' === $item['status'] ? '✓ Создано' : '⊘ Существует';
			echo '<tr>';
			echo '<td>' . esc_html( $item['name'] ) . '</td>';
			echo '<td><span class="badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_text ) . '</span></td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		return;
	}

	// Выводим форму.
	?>
	<div class="wrap">
		<h1>Структура сайта</h1>
		<p>Нажмите кнопку ниже, чтобы создать всю структуру сайта за раз.</p>
		<p>Операция идемпотентна: повторное нажатие не создаст дубликаты.</p>

		<form method="post">
			<?php wp_nonce_field( 'razil_setup_action', 'razil_setup_nonce' ); ?>
			<button type="submit" class="button button-primary button-large">
				Создать структуру сайта
			</button>
		</form>
	</div>
	<?php
}

/**
 * Создание всей структуры сайта.
 *
 * @return array Отчёт о созданных элементах.
 */
function razil_create_site_structure(): array {
	$report = array();

	// Создаём разделы каталога.
	$categories = array(
		'groby'             => 'Гробы',
		'venki'             => 'Венки',
		'pamyatniki'        => 'Памятники',
		'ogradki'           => 'Оградки',
		'keramika'          => 'Керамика',
		'stoly-i-lavki'     => 'Столы и лавки',
		'kresty'            => 'Кресты',
		'prinadlezhnosti'   => 'Принадлежности',
	);

	foreach ( $categories as $slug => $name ) {
		if ( term_exists( $slug, 'product_category' ) ) {
			$report[] = array(
				'name'   => "Раздел каталога: $name",
				'status' => 'exists',
			);
		} else {
			wp_insert_term( $name, 'product_category', array( 'slug' => $slug ) );
			$report[] = array(
				'name'   => "Раздел каталога: $name",
				'status' => 'created',
			);
		}
	}

	// Создаём услуги.
	$services = array(
		'organizaciya-pohoron'    => 'Организация похорон',
		'kremaciya'               => 'Кремация',
		'transportirovka'         => 'Транспортировка умерших',
		'blagoustroystvo'         => 'Благоустройство мест захоронения',
		'yuridicheskaya-pomoshch' => 'Юридическая помощь',
		'prizhiznennyy-dogovor'   => 'Прижизненный договор',
	);

	foreach ( $services as $slug => $title ) {
		if ( get_page_by_path( $slug, OBJECT, 'services' ) ) {
			$report[] = array(
				'name'   => "Услуга: $title",
				'status' => 'exists',
			);
		} else {
			wp_insert_post(
				array(
					'post_type'   => 'services',
					'post_title'  => $title,
					'post_name'   => $slug,
					'post_status' => 'draft',
				)
			);
			$report[] = array(
				'name'   => "Услуга: $title",
				'status' => 'created',
			);
		}
	}

	// Создаём страницы.
	$pages = array(
		'katalog'                  => array(
			'title'  => 'Каталог',
			'status' => 'publish',
		),
		'o-kompanii'               => array(
			'title'  => 'О компании',
			'status' => 'publish',
		),
		'kontakty'                 => array(
			'title'  => 'Контакты',
			'status' => 'publish',
		),
		'rekvizity'                => array(
			'title'  => 'Реквизиты',
			'status' => 'publish',
		),
		'otzyvy'                   => array(
			'title'  => 'Отзывы',
			'status' => 'publish',
		),
		'stati'                    => array(
			'title'  => 'Статьи',
			'status' => 'publish',
		),
		'nashi-raboty'             => array(
			'title'  => 'Наши работы',
			'status' => 'publish',
		),
		'vopros-otvet'             => array(
			'title'  => 'Вопрос-ответ',
			'status' => 'publish',
		),
		'spasibo'                  => array(
			'title'  => 'Спасибо',
			'status' => 'publish',
		),
		'politika'                 => array(
			'title'  => 'Политика обработки персональных данных',
			'status' => 'publish',
		),
		'soglasie'                 => array(
			'title'  => 'Согласие на обработку персональных данных',
			'status' => 'publish',
		),
		'amursk'                   => array(
			'title'  => 'Ритуальные услуги в Амурске',
			'status' => 'draft',
		),
		'birobidzhan'              => array(
			'title'  => 'Ритуальные услуги в Биробиджане',
			'status' => 'draft',
		),
	);

	foreach ( $pages as $slug => $page_data ) {
		if ( get_page_by_path( $slug ) ) {
			$report[] = array(
				'name'   => "Страница: {$page_data['title']}",
				'status' => 'exists',
			);
		} else {
			wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_title'  => $page_data['title'],
					'post_name'   => $slug,
					'post_status' => $page_data['status'],
				)
			);
			$report[] = array(
				'name'   => "Страница: {$page_data['title']}",
				'status' => 'created',
			);
		}
	}

	// Настраиваем блог на странице "Статьи".
	$stati_page = get_page_by_path( 'stati' );
	if ( $stati_page ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $stati_page->ID );
	}

	// Пересоздаём правила адресов.
	flush_rewrite_rules();

	return $report;
}
