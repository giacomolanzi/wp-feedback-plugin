<?php

/**
 * Plugin Name: WP Feedback Plugin (AJAX)
 * Description: Form feedback via shortcode + lista admin con paginazione AJAX.
 * Version:     1.0.0
 * Author:      Giacomo Lanzi (Plan B Project)
 * License:     GPL-2.0+
 * Text Domain: wp-feedback
 */

if (! defined('ABSPATH')) {
    exit;
}

final class WP_Feedback_Plugin
{
    const VERSION     = '1.0.0';
    const SLUG        = 'wp-feedback';
    const TABLE_NAME  = 'wp_feedback_entries'; // verrà prefissata con $wpdb->prefix
    const NONCE_ACTION_FORM = 'wp_feedback_submit';
    const NONCE_ACTION_LIST = 'wp_feedback_list';

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));

        add_shortcode('feedback_form', array($this, 'render_form_shortcode'));
        add_shortcode('feedback_list', array($this, 'render_list_shortcode'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX: submit (pubblico)
        add_action('wp_ajax_wp_feedback_submit', array($this, 'ajax_submit'));
        add_action('wp_ajax_nopriv_wp_feedback_submit', array($this, 'ajax_submit'));

        // AJAX: lista + paginazione (solo admin)
        add_action('wp_ajax_wp_feedback_list', array($this, 'ajax_list'));

        // AJAX: dettaglio singolo (solo admin)
        add_action('wp_ajax_wp_feedback_get', array($this, 'ajax_get'));
    }

    public function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Crea tabella custom.
     */
    public function activate()
    {
        global $wpdb;
        $table = $this->table();

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			first_name VARCHAR(190) NOT NULL,
			last_name VARCHAR(190) NOT NULL,
			email VARCHAR(190) NOT NULL,
			subject VARCHAR(255) NOT NULL,
			message LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY created_at (created_at)
		) {$charset_collate};";
        dbDelta($sql);
    }

    /**
     * Enqueue CSS/JS e localizzazione.
     */
    public function enqueue_assets()
    {
        $base = plugin_dir_url(__FILE__);

        // CSS (desktop-only richiesto)
        wp_enqueue_style(
            self::SLUG,
            $base . 'assets/feedback.css',
            array(),
            self::VERSION
        );

        // JS
        wp_enqueue_script(
            self::SLUG,
            $base . 'assets/feedback.js',
            array('jquery'),
            self::VERSION,
            true
        );

        $current_user = wp_get_current_user();
        $prefill = array(
            'first_name' => is_user_logged_in() ? (string) $current_user->first_name : '',
            'last_name'  => is_user_logged_in() ? (string) $current_user->last_name : '',
            'email'      => is_user_logged_in() ? (string) $current_user->user_email : '',
        );

        wp_localize_script(
            self::SLUG,
            'WP_FEEDBACK',
            array(
                'ajaxUrl'      => admin_url('admin-ajax.php'),
                'nonceForm'    => wp_create_nonce(self::NONCE_ACTION_FORM),
                'nonceList'    => wp_create_nonce(self::NONCE_ACTION_LIST),
                'prefill'      => $prefill,
                'i18n'         => array(
                    'thanks'       => __('Thank you for sending us your feedback', 'wp-feedback'),
                    'unauthorized' => __('You are not authorized to view the content of this page.', 'wp-feedback'),
                    'loadEntries'  => __('Load entries', 'wp-feedback'),
                    'noEntries'    => __('No entries found.', 'wp-feedback'),
                    'error'        => __('An error occurred. Please try again.', 'wp-feedback'),
                ),
            )
        );
    }

    /**
     * Shortcode: Form feedback.
     */
    public function render_form_shortcode(): string
    {
        ob_start();
?>
        <div class="wpf-wrap">
            <h2 class="wpf-title"><?php echo esc_html__('Submit your feedback', 'wp-feedback'); ?></h2>

            <form id="wpf-form" class="wpf-form" autocomplete="on" novalidate>
                <div class="wpf-row">
                    <label for="wpf_first_name"><?php esc_html_e('Nome', 'wp-feedback'); ?></label>
                    <input type="text" id="wpf_first_name" name="first_name" required>
                </div>

                <div class="wpf-row">
                    <label for="wpf_last_name"><?php esc_html_e('Cognome', 'wp-feedback'); ?></label>
                    <input type="text" id="wpf_last_name" name="last_name" required>
                </div>

                <div class="wpf-row">
                    <label for="wpf_email"><?php esc_html_e('Email', 'wp-feedback'); ?></label>
                    <input type="email" id="wpf_email" name="email" required>
                </div>

                <div class="wpf-row">
                    <label for="wpf_subject"><?php esc_html_e('Oggetto', 'wp-feedback'); ?></label>
                    <input type="text" id="wpf_subject" name="subject" required>
                </div>

                <div class="wpf-row">
                    <label for="wpf_message"><?php esc_html_e('Messaggio', 'wp-feedback'); ?></label>
                    <textarea id="wpf_message" name="message" rows="6" required></textarea>
                </div>

                <input type="hidden" name="action" value="wp_feedback_submit">
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce(self::NONCE_ACTION_FORM)); ?>">

                <button type="submit" class="wpf-btn"><?php esc_html_e('Invia', 'wp-feedback'); ?></button>
            </form>

            <div id="wpf-response" class="wpf-response" aria-live="polite"></div>
        </div>
    <?php
        return (string) ob_get_clean();
    }

    /**
     * Shortcode: Lista entries (admin-only). Non precarica entries.
     */
    public function render_list_shortcode(): string
    {
        if (! current_user_can('manage_options')) {
            return '<div class="wpf-wrap"><p class="wpf-unauth">' . esc_html__('You are not authorized to view the content of this page.', 'wp-feedback') . '</p></div>';
        }

        ob_start();
    ?>
        <div class="wpf-wrap" id="wpf-list-wrap">
            <h2 class="wpf-title"><?php esc_html_e('Feedback entries', 'wp-feedback'); ?></h2>

            <button id="wpf-load" class="wpf-btn-outline"><?php esc_html_e('Load entries', 'wp-feedback'); ?></button>

            <div id="wpf-list" class="wpf-list" aria-live="polite"></div>
            <div id="wpf-pagination" class="wpf-pagination" role="navigation" aria-label="<?php esc_attr_e('Pagination', 'wp-feedback'); ?>"></div>

            <hr class="wpf-sep" />

            <div id="wpf-detail" class="wpf-detail" aria-live="polite"></div>
        </div>
<?php
        return (string) ob_get_clean();
    }

    /**
     * AJAX: submit form (pubblico).
     */
    public function ajax_submit()
    {
        check_ajax_referer(self::NONCE_ACTION_FORM);

        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name  = isset($_POST['last_name'])  ? sanitize_text_field(wp_unslash($_POST['last_name']))  : '';
        $email      = isset($_POST['email'])      ? sanitize_email(wp_unslash($_POST['email']))          : '';
        $subject    = isset($_POST['subject'])    ? sanitize_text_field(wp_unslash($_POST['subject']))   : '';
        $message    = isset($_POST['message'])    ? wp_kses_post(wp_unslash($_POST['message']))          : '';

        // --- NUOVA VALIDAZIONE --- //
        $labels = array(
            'first_name' => 'Nome',
            'last_name'  => 'Cognome',
            'email'      => 'Email',
            'subject'    => 'Oggetto',
            'message'    => 'Messaggio',
        );

        $errors = array();

        // Required generici.
        foreach (array('first_name', 'last_name', 'subject', 'message') as $field) {
            if ('' === ${$field}) {
                $errors[] = sprintf("Il campo %s è richiesto.", $labels[$field]);
            }
        }

        // Email: required + formato.
        if ('' === $email) {
            $errors[] = "Campo Email invalido.";
        } elseif (!is_email($email)) {
            $errors[] = "Campo Email invalido. Formato email non valido.";
        }

        if (! empty($errors)) {
            wp_send_json_error(
                array('errors' => $errors),
                400
            );
        }
        // --- FINE NUOVA VALIDAZIONE --- //


        global $wpdb;
        $table = $this->table();

        $inserted = $wpdb->insert(
            $table,
            array(
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'email'      => $email,
                'subject'    => $subject,
                'message'    => $message,
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        if (false === $inserted) {
            wp_send_json_error(array('message' => __('DB error', 'wp-feedback')), 500);
        }

        wp_send_json_success(array('message' => __('Thank you for sending us your feedback', 'wp-feedback')));
    }

    /**
     * AJAX: lista + paginazione (admin-only). Carica SOLO al clic.
     * Params: page (int, default 1), per_page (int, default 10)
     */
    public function ajax_list()
    {
        check_ajax_referer(self::NONCE_ACTION_LIST);
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'wp-feedback')), 403);
        }

        $page     = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? min(50, max(1, absint($_POST['per_page']))) : 10;

        global $wpdb;
        $table  = $this->table();
        $total  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $offset = ($page - 1) * $per_page;

        // Non precarichiamo: prendiamo solo questa pagina.
        $query  = $wpdb->prepare(
            "SELECT id, first_name, last_name, email, subject, created_at
			 FROM {$table}
			 ORDER BY created_at DESC, id DESC
			 LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        $rows = $wpdb->get_results($query, ARRAY_A);

        wp_send_json_success(
            array(
                'total'    => $total,
                'page'     => $page,
                'per_page' => $per_page,
                'rows'     => $rows,
            )
        );
    }

    /**
     * AJAX: dettaglio singolo (admin-only).
     * Param: id (int)
     */
    public function ajax_get()
    {
        check_ajax_referer(self::NONCE_ACTION_LIST);
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'wp-feedback')), 403);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (! $id) {
            wp_send_json_error(array('message' => __('Invalid ID', 'wp-feedback')), 400);
        }

        global $wpdb;
        $table = $this->table();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (! $row) {
            wp_send_json_error(array('message' => __('Not found', 'wp-feedback')), 404);
        }

        // Escape lato output JS/HTML nel client.
        wp_send_json_success(array('row' => $row));
    }
}

new WP_Feedback_Plugin();
