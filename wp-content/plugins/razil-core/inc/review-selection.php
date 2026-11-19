<?php
/** Staff-managed, explicitly reviewed 2GIS selection for the home carousel. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function razil_review_mentions_staff( string $text ): bool {
    return (bool) preg_match( '/(?<![\p{L}])(?:агент(?:а|у|ом|е|ы|ов|ам|ами|ах)?)(?![\p{L}])/iu', $text );
}

function razil_review_can_feature( WP_Post $post ): bool {
    return 'reviews' === $post->post_type && 'publish' === $post->post_status
        && in_array( (int) get_post_meta( $post->ID, '_razil_rating', true ), array( 4, 5 ), true )
        && '2ГИС' === get_post_meta( $post->ID, '_razil_source', true )
        && ! razil_review_mentions_staff( $post->post_content );
}

function razil_featured_reviews( int $limit = 0 ): array {
    $posts = get_posts( array(
        'post_type' => 'reviews', 'post_status' => 'publish', 'posts_per_page' => -1,
        'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ),
        'meta_key' => '_razil_home_approved', 'meta_value' => '1',
    ) );
    $posts = array_values( array_filter( $posts, 'razil_review_can_feature' ) );
    return $limit > 0 ? array_slice( $posts, 0, $limit ) : $posts;
}

/** Editing a previously approved quote requires another human check. */
add_action( 'post_updated', static function ( $id, $after, $before ) {
    if ( 'reviews' === $after->post_type && ( $after->post_content !== $before->post_content || $after->post_title !== $before->post_title ) ) {
        update_post_meta( $id, '_razil_home_approved', '0' );
    }
}, 10, 3 );

add_action( 'admin_menu', static function () {
    add_submenu_page( 'edit.php?post_type=reviews', 'Отзывы на главной', 'Отзывы на главной', 'publish_posts', 'razil-review-selection', 'razil_review_selection_page' );
} );

/** Validate the complete input before creating any record. No external requests. */
function razil_add_selected_review( array $input ) {
    $name = sanitize_text_field( (string) ( $input['review_name'] ?? '' ) );
    $text = sanitize_textarea_field( (string) ( $input['review_text'] ?? '' ) );
    $date = (string) ( $input['review_date'] ?? '' );
    $rating = (int) ( $input['review_rating'] ?? 0 );
    $url = esc_url_raw( (string) ( $input['review_url'] ?? '' ), array( 'https' ) );
    $parsed_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
    if ( '' === $name || mb_strlen( $name ) > 120 || mb_strlen( $text ) < 10 || mb_strlen( $text ) > 10000
        || ! in_array( $rating, array( 4, 5 ), true ) || empty( $input['review_confirm'] )
        || ! $parsed_date || $parsed_date->format( 'Y-m-d' ) !== $date || $date > current_time( 'Y-m-d' ) ) {
        return new WP_Error( 'invalid', 'Заполните автора, полный текст, дату и оценку 4 или 5. Подтвердите отсутствие упоминаний агентов. Дата не может быть в будущем.' );
    }
    if ( razil_review_mentions_staff( $text ) ) {
        return new WP_Error( 'staff', 'В тексте есть упоминание ритуального агента. Выберите другой отзыв; не вырезайте слова автора.' );
    }
    if ( '2gis.ru' !== wp_parse_url( $url, PHP_URL_HOST ) || ! preg_match( '~^/khabarovsk/firm/4926340373441419(?:/|$)~', (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) {
        return new WP_Error( 'source', 'Укажите ссылку на отзыв или карточку нашего агентства в 2ГИС.' );
    }
    // Also catches records added through the normal WordPress review editor.
    foreach ( get_posts( array( 'post_type' => 'reviews', 'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' ), 'posts_per_page' => -1 ) ) as $existing ) {
        if ( trim( wp_strip_all_tags( $existing->post_content ) ) === trim( $text ) ) {
            return new WP_Error( 'duplicate', 'Этот отзыв уже есть в разделе «Отзывы». Найдите его в списке и проверьте перед добавлением на главную.' );
        }
    }
    $stamp = $date . ' 00:00:00';
    return wp_insert_post( wp_slash( array(
        'post_type' => 'reviews', 'post_status' => 'publish', 'post_title' => $name,
        'post_content' => $text, 'post_date' => $stamp, 'post_date_gmt' => get_gmt_from_date( $stamp ),
        'meta_input' => array( '_razil_rating' => $rating, '_razil_source' => '2ГИС', '_razil_source_url' => $url, '_razil_home_approved' => '1' ),
    ) ), true );
}

add_action( 'admin_post_razil_review_selection', static function () {
    if ( ! current_user_can( 'publish_posts' ) ) { wp_die( 'Недостаточно прав.', '', array( 'response' => 403 ) ); }
    check_admin_referer( 'razil_review_selection' );
    $input = wp_unslash( $_POST );
    $result = null;
    if ( 'add' === ( $input['operation'] ?? '' ) ) {
        $result = razil_add_selected_review( $input );
    } elseif ( 'select' === ( $input['operation'] ?? '' ) ) {
        $selected = array_map( 'absint', (array) ( $input['selected'] ?? array() ) );
        foreach ( array_map( 'absint', (array) ( $input['visible'] ?? array() ) ) as $id ) {
            $post = get_post( $id );
            if ( ! $post || 'reviews' !== $post->post_type || ! current_user_can( 'edit_post', $id ) ) { continue; }
            if ( in_array( $id, $selected, true ) && razil_review_can_feature( $post ) ) {
                update_post_meta( $id, '_razil_home_approved', '1' );
            } else {
                update_post_meta( $id, '_razil_home_approved', '0' );
            }
        }
    } else { wp_die( 'Неизвестное действие.', '', array( 'response' => 400 ) ); }
    if ( is_wp_error( $result ) ) {
        // Preserve the staff member's input on validation errors.
        set_transient( 'razil_review_selection_' . get_current_user_id(), array( 'error' => $result->get_error_message(), 'input' => $input ), 300 );
    }
    wp_safe_redirect( admin_url( 'edit.php?post_type=reviews&page=razil-review-selection&saved=' . ( is_wp_error( $result ) ? '0' : '1' ) ) );
    exit;
} );

function razil_review_selection_page(): void {
    if ( ! current_user_can( 'publish_posts' ) ) { return; }
    $state = get_transient( 'razil_review_selection_' . get_current_user_id() );
    delete_transient( 'razil_review_selection_' . get_current_user_id() );
    $input = $state['input'] ?? array();
    $page = max( 1, absint( $_GET['paged'] ?? 1 ) );
    $query = new WP_Query( array( 'post_type' => 'reviews', 'post_status' => 'publish', 'posts_per_page' => 20, 'paged' => $page, 'orderby' => array( 'date' => 'DESC', 'ID' => 'DESC' ), 'meta_key' => '_razil_source', 'meta_value' => '2ГИС' ) );
    $source = 'https://2gis.ru/khabarovsk/firm/4926340373441419/tab/reviews';
    ?>
    <div class="wrap">
        <h1>Отзывы на главной</h1>
        <p>На главной показываются все отмеченные отзывы из 2ГИС с оценкой 4 или 5, от новых к старым. Добавление нового отзыва не скрывает предыдущие.</p>
        <p><a class="button" href="<?php echo esc_url( $source ); ?>" target="_blank" rel="noopener noreferrer">Открыть свежие отзывы в 2ГИС</a></p>
        <p>Обновление вручную: выберите отзыв без упоминания агентов, их имён и фамилий, скопируйте полный текст и заполните форму ниже. Тексты не сокращайте и не переписывайте. Автоматической синхронизации с 2ГИС нет.</p>
        <?php if ( ! empty( $state['error'] ) ) : ?><div class="notice notice-error"><p><?php echo esc_html( $state['error'] ); ?></p></div>
        <?php elseif ( '1' === ( $_GET['saved'] ?? '' ) ) : ?><div class="notice notice-success"><p>Сохранено. Подборка на главной обновлена.</p></div><?php endif; ?>
        <h2>Подборка для главной</h2>
        <p>Галочка включает показ на главной. Прежние отзывы сохранены; для новых выбирайте тексты без упоминаний конкретных агентов. Снимите галочку, чтобы убрать отзыв только с главной.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="razil_review_selection"><input type="hidden" name="operation" value="select">
            <?php wp_nonce_field( 'razil_review_selection' ); ?>
            <table class="widefat striped"><thead><tr><th style="width:120px">На главной</th><th>Отзыв</th></tr></thead><tbody>
            <?php foreach ( $query->posts as $post ) : $eligible = razil_review_can_feature( $post ); ?>
                <tr><td><input type="hidden" name="visible[]" value="<?php echo (int) $post->ID; ?>">
                    <label><input type="checkbox" name="selected[]" value="<?php echo (int) $post->ID; ?>" <?php checked( '1', get_post_meta( $post->ID, '_razil_home_approved', true ) ); disabled( ! $eligible || ! current_user_can( 'edit_post', $post->ID ) ); ?>> Показывать</label>
                </td><td><strong><?php echo esc_html( $post->post_title ); ?></strong> · <?php echo esc_html( get_the_date( 'd.m.Y', $post ) ); ?> · <?php echo (int) get_post_meta( $post->ID, '_razil_rating', true ); ?>/5
                    <p style="white-space:pre-wrap;max-width:900px"><?php echo esc_html( wp_strip_all_tags( $post->post_content ) ); ?></p>
                    <?php if ( ! $eligible ) : ?><p><strong>Не подходит: нужна оценка 4–5 и текст без упоминаний ритуальных агентов.</strong></p><?php endif; ?>
                    <a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>">Редактировать отзыв</a>
                </td></tr>
            <?php endforeach; ?>
            <?php if ( ! $query->posts ) : ?><tr><td colspan="2">Пока нет отзывов из 2ГИС. Добавьте первый ниже.</td></tr><?php endif; ?>
            </tbody></table>
            <?php submit_button( 'Сохранить подборку' ); ?>
        </form>
        <?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'total' => $query->max_num_pages, 'current' => $page ) ) ); ?>
        <h2>Добавить отзыв из 2ГИС</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:800px">
            <input type="hidden" name="action" value="razil_review_selection"><input type="hidden" name="operation" value="add">
            <?php wp_nonce_field( 'razil_review_selection' ); ?>
            <p><label for="review_name">Имя автора, как в 2ГИС</label><br><input class="regular-text" id="review_name" name="review_name" maxlength="120" required value="<?php echo esc_attr( $input['review_name'] ?? '' ); ?>"></p>
            <p><label for="review_date">Дата отзыва в 2ГИС</label><br><input type="date" id="review_date" name="review_date" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( $input['review_date'] ?? '' ); ?>" required></p>
            <p><label for="review_rating">Оценка в 2ГИС</label><br><select id="review_rating" name="review_rating" required><option value="">Выберите оценку</option><?php foreach ( array( 4, 5 ) as $rating ) : ?><option value="<?php echo $rating; ?>" <?php selected( (string) $rating, (string) ( $input['review_rating'] ?? '' ) ); ?>><?php echo $rating; ?> звёзд</option><?php endforeach; ?></select></p>
            <p><label for="review_url">Ссылка на отзыв или карточку в 2ГИС</label><br><input type="url" class="large-text" id="review_url" name="review_url" required value="<?php echo esc_attr( $input['review_url'] ?? $source ); ?>"></p>
            <p><label for="review_text">Полный текст отзыва</label><br><textarea class="large-text" rows="8" id="review_text" name="review_text" minlength="10" maxlength="10000" required><?php echo esc_textarea( $input['review_text'] ?? '' ); ?></textarea></p>
            <p><label><input type="checkbox" name="review_confirm" value="1" required> Текст скопирован без изменений; упоминаний агентов, их имён и фамилий нет.</label></p>
            <?php submit_button( 'Добавить и показать на главной' ); ?>
        </form>
    </div>
    <?php
}
