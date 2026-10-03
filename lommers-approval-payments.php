<?php
/**
 * Plugin Name: Lommers – Approval Before Payment
 * Description: Handmatige goedkeuring vóór betaling. Checkout kan beperkt worden tot "Betaalverzoek (na goedkeuring)". Na goedkeuring ontvangt de klant een /order-pay link. Inclusief: testmodus, debug logging, Mollie-failsafes, uitnodiging & herinnering per e-mail, handmatige acties en planning.
 * Author: Jouw naam
 * Version: 1.7.0
 * Text Domain: lommers-approval
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 */

if (!defined('ABSPATH'))
    exit;

define('LAP_VERSION', '1.7.0');
define('LAP_PLUGIN_FILE', __FILE__);
define('LAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LAP_PLUGIN_URL', plugin_dir_url(__FILE__));

/** Declareer HPOS (High-Performance Order Storage) compatibiliteit */
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', LAP_PLUGIN_FILE, true);
    }
});

require_once LAP_PLUGIN_DIR . 'includes/class-lap-settings.php';
require_once LAP_PLUGIN_DIR . 'includes/class-lap-dashboard.php';

/** ------------------------------------------------------------------------
 * Logging helper (altijd beschikbaar)
 * -------------------------------------------------------------------------*/
if (!function_exists('lap_log')) {
    function lap_log($message, $level = 'info')
    {
        // Alleen loggen als debug logging is ingeschakeld, of bij errors/warnings
        if (!get_option('lap_debug_log') && $level === 'info') {
            return;
        }
        if (!function_exists('wc_get_logger'))
            return;
        $logger = wc_get_logger();
        $msg = is_scalar($message) ? $message : wp_json_encode($message);
        $logger->log($level, $msg, array('source' => 'lommers-approval'));
    }
}

/**
 * Helper: geef een HTML link terug voor track & trace (of plain text als geen URL)
 *
 * @param string $tracking Track & trace code of URL
 * @return string HTML (escaped)
 */
if (!function_exists('lap_tracking_link')) {
    function lap_tracking_link($tracking)
    {
        if (empty($tracking)) {
            return '';
        }
        if (strpos($tracking, 'http://') === 0 || strpos($tracking, 'https://') === 0) {
            return '<a href="' . esc_url($tracking) . '" target="_blank" rel="noopener">' . esc_html($tracking) . '</a>';
        }
        return esc_html($tracking);
    }
}

/** Admin bar badge in testmodus (alleen admins) */
add_action('admin_bar_menu', function ($bar) {
    if (!get_option('lap_test_mode'))
        return;
    if (!current_user_can('manage_woocommerce'))
        return;
    $bar->add_node(array(
        'id' => 'lap-testmode',
        'title' => '🧪 TESTMODE AAN',
        'href' => admin_url('admin.php?page=lap-settings'),
        'meta' => array('class' => 'lap-testmode-badge'),
    ));
}, 1000);
add_action('admin_head', function () {
    if (!get_option('lap_test_mode'))
        return;
    echo '<style>#wpadminbar #wp-admin-bar-lap-testmode>.ab-item{background:#d63638;color:#fff;font-weight:600}</style>';
});

/** ------------------------------------------------------------------------
 * Deactivation: cron events opruimen
 * -------------------------------------------------------------------------*/
register_deactivation_hook(LAP_PLUGIN_FILE, function () {
    wp_clear_scheduled_hook('lap_daily_reminders');
    wp_clear_scheduled_hook('lap_daily_auto_cancel');
    // Dashboard cache opruimen
    delete_transient('lap_stats_week');
    delete_transient('lap_stats_month');
    delete_transient('lap_stats_year');
    delete_transient('lap_revenue_bars_week');
    delete_transient('lap_revenue_bars_month');
    delete_transient('lap_revenue_bars_year');
    delete_transient('lap_activity_bars_week');
    delete_transient('lap_activity_bars_month');
    delete_transient('lap_activity_bars_year');
    lap_log('Plugin gedeactiveerd – cron events en caches opgeruimd.', 'info');
});

/** Dashboard statistieken cache invalideren bij statuswijzigingen */
add_action('woocommerce_order_status_changed', function () {
    delete_transient('lap_stats_week');
    delete_transient('lap_stats_month');
    delete_transient('lap_stats_year');
    delete_transient('lap_revenue_bars_week');
    delete_transient('lap_revenue_bars_month');
    delete_transient('lap_revenue_bars_year');
    delete_transient('lap_activity_bars_week');
    delete_transient('lap_activity_bars_month');
    delete_transient('lap_activity_bars_year');
}, 999);

/** ------------------------------------------------------------------------
 * Versie-migratie: voer upgrades uit bij versiewijziging
 * -------------------------------------------------------------------------*/
add_action('admin_init', function () {
    if (!current_user_can('manage_woocommerce')) return;

    $stored = get_option('lap_version', '0');
    if (version_compare($stored, LAP_VERSION, '>=')) return;

    // Toekomstige migraties hier toevoegen:
    // if (version_compare($stored, '1.8.0', '<')) { /* migratie code */ }

    update_option('lap_version', LAP_VERSION);
    lap_log(sprintf('Plugin bijgewerkt van %s naar %s', $stored, LAP_VERSION), 'info');
});

/** ------------------------------------------------------------------------
 * Bootstrap na Woo
 * -------------------------------------------------------------------------*/
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Lommers – Approval Before Payment</strong> vereist WooCommerce.</p></div>';
        });
        return;
    }

    /** A) Custom statussen */
    add_action('init', function () {
        register_post_status('wc-ter-goedkeuring', array(
            'label' => _x('Ter goedkeuring', 'Order status', 'lommers-approval'),
            'public' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Ter goedkeuring <span class="count">(%s)</span>', 'Ter goedkeuring <span class="count">(%s)</span>', 'lommers-approval'),
        ));
        register_post_status('wc-betaling-verzocht', array(
            'label' => _x('Betaling verzocht', 'Order status', 'lommers-approval'),
            'public' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Betaling verzocht <span class="count">(%s)</span>', 'Betaling verzocht <span class="count">(%s)</span>', 'lommers-approval'),
        ));
        register_post_status('wc-onderweg', array(
            'label' => _x('Onderweg', 'Order status', 'lommers-approval'),
            'public' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Onderweg <span class="count">(%s)</span>', 'Onderweg <span class="count">(%s)</span>', 'lommers-approval'),
        ));
    });
    add_filter('woocommerce_register_shop_order_post_statuses', function ($st) {
        $st['wc-ter-goedkeuring'] = array(
            'label' => _x('Ter goedkeuring', 'Order status', 'lommers-approval'),
            'public' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Ter goedkeuring <span class="count">(%s)</span>', 'Ter goedkeuring <span class="count">(%s)</span>', 'lommers-approval'),
        );
        $st['wc-betaling-verzocht'] = array(
            'label' => _x('Betaling verzocht', 'Order status', 'lommers-approval'),
            'public' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Betaling verzocht <span class="count">(%s)</span>', 'Betaling verzocht <span class="count">(%s)</span>', 'lommers-approval'),
        );
        $st['wc-onderweg'] = array(
            'label' => _x('Onderweg', 'Order status', 'lommers-approval'),
            'public' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Onderweg <span class="count">(%s)</span>', 'Onderweg <span class="count">(%s)</span>', 'lommers-approval'),
        );
        return $st;
    });
    add_filter('wc_order_statuses', function ($statuses) {
        $out = array();
        foreach ($statuses as $k => $v) {
            $out[$k] = $v;
            if ($k === 'wc-processing') {
                $out['wc-ter-goedkeuring'] = _x('Ter goedkeuring', 'Order status', 'lommers-approval');
                $out['wc-betaling-verzocht'] = _x('Betaling verzocht', 'Order status', 'lommers-approval');
            }
        }
        foreach (['wc-ter-goedkeuring', 'wc-betaling-verzocht', 'wc-onderweg'] as $m) {
            if (!isset($out[$m])) {
                $labels = array(
                    'wc-ter-goedkeuring' => _x('Ter goedkeuring', 'Order status', 'lommers-approval'),
                    'wc-betaling-verzocht' => _x('Betaling verzocht', 'Order status', 'lommers-approval'),
                    'wc-onderweg' => _x('Onderweg', 'Order status', 'lommers-approval'),
                );
                $out[$m] = $labels[$m];
            }
        }
        return $out;
    });

    /** B) Bij plaatsen met onze gateway → 'ter-goedkeuring' */
    add_action('woocommerce_checkout_order_processed', function ($order_id) {
        $order = wc_get_order($order_id);
        if (!$order)
            return;
        if ($order->get_payment_method() === 'lap_approval') {
            if ($order->get_status() !== 'ter-goedkeuring') {
                $order->set_status('ter-goedkeuring');
                $order->add_order_note(__('Order wacht op handmatige goedkeuring.', 'lommers-approval'));
                $order->save();
                lap_log("order#$order_id → ter-goedkeuring");
            }
        }
    }, 10);

    /** C) E-mails registreren */
    add_filter('woocommerce_email_classes', function ($emails) {
        require_once LAP_PLUGIN_DIR . 'includes/class-lap-email-base.php';
        require_once LAP_PLUGIN_DIR . 'includes/class-lap-email-request-payment.php';
        require_once LAP_PLUGIN_DIR . 'includes/class-lap-email-reminder.php';
        require_once LAP_PLUGIN_DIR . 'includes/class-lap-email-cancelled.php';
        require_once LAP_PLUGIN_DIR . 'includes/class-lap-email-order-confirmation.php';
        require_once LAP_PLUGIN_DIR . 'includes/class-lap-email-apology.php';
        if (class_exists('LAP_Email_Request_Payment')) {
            $emails['LAP_Email_Request_Payment'] = new LAP_Email_Request_Payment();
        }
        if (class_exists('LAP_Email_Reminder')) {
            $emails['LAP_Email_Reminder'] = new LAP_Email_Reminder();
        }
        if (class_exists('LAP_Email_Cancelled')) {
            $emails['LAP_Email_Cancelled'] = new LAP_Email_Cancelled();
        }
        if (class_exists('LAP_Email_Order_Confirmation')) {
            $emails['LAP_Email_Order_Confirmation'] = new LAP_Email_Order_Confirmation();
        }
        if (class_exists('LAP_Email_Apology')) {
            $emails['LAP_Email_Apology'] = new LAP_Email_Apology();
        }
        require_once LAP_PLUGIN_DIR . 'includes/class-lap-email-shipped.php';
        if (class_exists('LAP_Email_Shipped')) {
            $emails['LAP_Email_Shipped'] = new LAP_Email_Shipped();
        }
        return $emails;
    });

    /** D) Centrale mail-sender (force enabled + logging + fallback) */
    if (!function_exists('lap_send_wc_email')) {
        function lap_send_wc_email($email_id, $order, $reason = '')
        {
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }
            if (!$order instanceof WC_Order) {
                lap_log("email:$email_id SKIP no order", 'error');
                return false;
            }

            $recipient = $order->get_billing_email();
            if (!$recipient) {
                lap_log("email:$email_id SKIP no recipient #" . $order->get_id(), 'warning');
                return false;
            }

            $mailer = WC()->mailer();
            if (!$mailer) {
                lap_log("email:$email_id SKIP no mailer", 'error');
                return false;
            }
            $emails = $mailer->get_emails();

            // Zoek class instance op id
            $obj = null;
            foreach ($emails as $e) {
                if ($e instanceof WC_Email && $e->id === $email_id) {
                    $obj = $e;
                    break;
                }
            }

            if ($obj && method_exists($obj, 'trigger')) {
                $obj->enabled = 'yes'; // force aan
                $obj->trigger($order->get_id(), $order);
                $order->add_order_note(sprintf(__('E-mail verzonden: %s', 'lommers-approval'), $obj->title));
                lap_log("email:$email_id sent #" . $order->get_id() . " ($reason)");
                return true;
            }

            // Fallback: basic wc_mail
            $payment_url = $order->get_checkout_payment_url();
            $subject = ($email_id === 'request_payment_reminder')
                ? sprintf(__('Herinnering – bestelling #%s', 'lommers-approval'), $order->get_order_number())
                : sprintf(__('Bestelling #%s – betaalinstructies', 'lommers-approval'), $order->get_order_number());

            $amount = wp_strip_all_tags(wc_price($order->get_total(), array('currency' => $order->get_currency())));
            $body = ($email_id === 'request_payment_reminder')
                ? "We hebben je bestelling nog niet als betaald geregistreerd.\nBetaalverzoek: $payment_url\nBedrag: $amount"
                : "Bedankt voor je bestelling. Je kunt direct betalen via: $payment_url\nBedrag: $amount";

            wc_mail($recipient, $subject, $body);
            $order->add_order_note(sprintf(__('E-mail (fallback) verzonden: %s', 'lommers-approval'), $subject));
            lap_log("email:$email_id FALLBACK sent #" . $order->get_id() . " ($reason)");
            return true;
        }
    }

    /** E) Admin orderacties */
    add_filter('woocommerce_order_actions', function ($actions) {
        global $theorder;
        $actions['lap_approve_and_request_payment'] = __('Keur goed & vraag betaling (mail)', 'lommers-approval');
        $actions['lap_send_invitation_now'] = __('Stuur uitnodiging (betaallink) nu', 'lommers-approval');
        $actions['lap_send_reminder_now'] = __('Stuur betalingsherinnering (nu)', 'lommers-approval');
        if (get_option('lap_test_mode') && current_user_can('manage_woocommerce')) {
            $actions['lap_mark_paid_test'] = __('TEST: Markeer als betaald (simulatie)', 'lommers-approval');
        }
        // Toon "forceer betaald" alleen op relevante statussen
        $show_force = true;
        if ($theorder instanceof WC_Order) {
            $force_statuses = array('betaling-verzocht', 'on-hold', 'pending', 'failed', 'ter-goedkeuring');
            if (!in_array($theorder->get_status(), $force_statuses, true)) {
                $show_force = false;
            }
        }
        if ($show_force) {
            $actions['lap_force_paid_bump_action'] = __('Forceer betaald-status (failsafe)', 'lommers-approval');
        }
        // 'Onderweg' actie alleen tonen op betaalde/processing orders
        if ($theorder instanceof WC_Order && in_array($theorder->get_status(), array('processing', 'betaling-verzocht', 'on-hold'), true)) {
            $actions['lap_mark_shipped'] = __('Markeer als onderweg (incl. track & trace)', 'lommers-approval');
        }
        return $actions;
    });
    add_action('woocommerce_order_action_lap_approve_and_request_payment', function ($order) {
        if (is_numeric($order))
            $order = wc_get_order($order);
        if (!$order instanceof WC_Order)
            return;

        $order->set_status('betaling-verzocht');
        $order->add_order_note(__('Order goedgekeurd; betaalverzoek verzonden.', 'lommers-approval'));
        $order->save();
        // Email wordt getriggerd via woocommerce_order_status_changed hook (sectie H)
    });
    add_action('woocommerce_order_action_lap_send_invitation_now', function ($order) {
        lap_send_wc_email('request_payment', $order, 'manual');
    });
    add_action('woocommerce_order_action_lap_send_reminder_now', function ($order) {
        if (is_numeric($order))
            $order = wc_get_order($order);
        if ($order instanceof WC_Order && !$order->is_paid()) {
            if (lap_send_wc_email('request_payment_reminder', $order, 'manual')) {
                $order->update_meta_data('_lap_reminder_sent', wp_date('Y-m-d H:i:s'));
                $order->add_order_note(__('Herinnering handmatig verstuurd.', 'lommers-approval'));
                $order->save();
            }
        }
    });
    add_action('woocommerce_order_action_lap_mark_paid_test', function ($order) {
        if (!get_option('lap_test_mode') || !current_user_can('manage_woocommerce'))
            return;
        if (is_numeric($order))
            $order = wc_get_order($order);
        if (!$order instanceof WC_Order)
            return;

        $order->add_order_note(__('TEST: betaald gemarkeerd via admin-actie.', 'lommers-approval'));
        $order->payment_complete('lap_test_admin_' . time());
        $final = $order->needs_processing() ? 'processing' : 'completed';
        if ($order->get_status() !== $final) {
            $order->set_status($final);
        }
        $order->save(); // Altijd opslaan na payment_complete
        lap_log("test-paid bump #" . $order->get_id() . " -> " . $final);
    });
    add_action('woocommerce_order_action_lap_force_paid_bump_action', function ($order) {
        if (!current_user_can('manage_woocommerce'))
            return;
        if (is_numeric($order))
            $order = wc_get_order($order);
        if (!$order instanceof WC_Order)
            return;
        $final = $order->needs_processing() ? 'processing' : 'completed';
        $order->add_order_note(__('Handmatig: betaald-bump uitgevoerd.', 'lommers-approval'));
        $order->set_status($final);
        $order->save();
        lap_log(sprintf('Manual paid bump → #%d → %s', $order->get_id(), $final));
    });

    /** E2) Order actie: markeer als onderweg (verzonden) */
    add_action('woocommerce_order_action_lap_mark_shipped', function ($order) {
        if (!current_user_can('manage_woocommerce'))
            return;
        if (is_numeric($order))
            $order = wc_get_order($order);
        if (!$order instanceof WC_Order)
            return;

        $tracking = isset($_POST['lap_track_and_trace']) ? sanitize_text_field(wp_unslash($_POST['lap_track_and_trace'])) : '';
        if ($tracking) {
            $order->update_meta_data('_lap_track_and_trace', $tracking);
        }

        $order->set_status('onderweg');
        $order->add_order_note(sprintf(
            __('Order gemarkeerd als onderweg. Track & trace: %s', 'lommers-approval'),
            $tracking ? $tracking : __('niet opgegeven', 'lommers-approval')
        ));
        $order->save();
        lap_log(sprintf('Shipped → #%d (tracking: %s)', $order->get_id(), $tracking ? $tracking : 'none'));
    });

    /** F1) Meta box: Verzendingsinformatie (track & trace) op order pagina */
    add_action('add_meta_boxes', function () {
        add_meta_box(
            'lap_shipping_info',
            __('Verzendingsinformatie', 'lommers-approval'),
            function ($post) {
                $order = wc_get_order($post->ID);
                if (!$order) return;
                $tracking = $order->get_meta('_lap_track_and_trace');
                $status = $order->get_status();
                $is_processing = in_array($status, array('processing', 'betaling-verzocht', 'on-hold'), true);
                ?>
                <div style="padding:8px 0;">
                    <?php if ($tracking): ?>
                        <p>
                            <strong><?php esc_html_e('Track & trace:', 'lommers-approval'); ?></strong><br>
                            <code style="font-size:14px;"><?php echo esc_html($tracking); ?></code>
                        </p>
                    <?php endif; ?>
                    <?php if ($status === 'onderweg'): ?>
                        <p style="color:#16a34a;font-weight:600;">
                            &#9989; <?php esc_html_e('Deze order is onderweg.', 'lommers-approval'); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($is_processing): ?>
                        <p>
                            <label for="lap_track_and_trace">
                                <strong><?php esc_html_e('Track & trace code:', 'lommers-approval'); ?></strong>
                            </label>
                        </p>
                        <p>
                            <input type="text" id="lap_track_and_trace" name="lap_track_and_trace"
                                value="<?php echo esc_attr($tracking); ?>"
                                placeholder="<?php esc_attr_e('Bijv. PostNL, DHL, DPD...', 'lommers-approval'); ?>"
                                style="width:100%;padding:8px;font-size:14px;">
                        </p>
                        <p>
                            <button type="button" id="lap_mark_shipped_btn" class="button button-primary"
                                style="background:#16a34a;border-color:#16a34a;">
                                &#128666; <?php esc_html_e('Markeer als onderweg', 'lommers-approval'); ?>
                            </button>
                            <span id="lap_shipped_spinner" style="display:none;margin-left:8px;">
                                <span class="spinner is-active"></span>
                            </span>
                        </p>
                        <script>
                        document.getElementById('lap_mark_shipped_btn')?.addEventListener('click', function() {
                            var btn = this;
                            var spinner = document.getElementById('lap_shipped_spinner');
                            var tracking = document.getElementById('lap_track_and_trace').value;
                            if (!confirm('<?php echo esc_js(__('Ordermarkering als onderweg? De klant ontvangt een e-mail met track & trace.', 'lommers-approval')); ?>')) return;
                            btn.disabled = true;
                            spinner.style.display = 'inline-block';
                            var formData = new FormData();
                            formData.append('action', 'lap_mark_shipped');
                            formData.append('order_id', <?php echo intval($post->ID); ?>);
                            formData.append('track_and_trace', tracking);
                            formData.append('_ajax_nonce', '<?php echo wp_create_nonce('lap_mark_shipped_' . intval($post->ID)); ?>');
                            fetch(ajaxurl, { method: 'POST', body: formData })
                                .then(function(r) { return r.json(); })
                                .then(function(data) {
                                    if (data.success) {
                                        location.reload();
                                    } else {
                                        alert(data.data || 'Fout bij verwerken.');
                                        btn.disabled = false;
                                        spinner.style.display = 'none';
                                    }
                                })
                                .catch(function() {
                                    alert('Netwerkfout.');
                                    btn.disabled = false;
                                    spinner.style.display = 'none';
                                });
                        });
                        </script>
                    <?php endif; ?>
                </div>
                <?php
            },
            'shop_order',
            'side',
            'high'
        );
    });

    /** F2) AJAX handler: markeer als onderweg via meta box */
    add_action('wp_ajax_lap_mark_shipped', function () {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Geen rechten.');
        }
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error('Ongeldig order ID.');
        }
        check_ajax_referer('lap_mark_shipped_' . $order_id);

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order niet gevonden.');
        }

        $tracking = isset($_POST['track_and_trace']) ? sanitize_text_field(wp_unslash($_POST['track_and_trace'])) : '';
        if ($tracking) {
            $order->update_meta_data('_lap_track_and_trace', $tracking);
        }

        $order->set_status('onderweg');
        $order->add_order_note(sprintf(
            __('Order gemarkeerd als onderweg. Track & trace: %s', 'lommers-approval'),
            $tracking ? $tracking : __('niet opgegeven', 'lommers-approval')
        ));
        $order->save();
        // E-mail wordt via woocommerce_order_status_onderweg hook (F3) verstuurd

        lap_log(sprintf('Shipped (AJAX) → #%d (tracking: %s)', $order_id, $tracking ? $tracking : 'none'));
        wp_send_json_success();
    });

    /** F3) Status change → onderweg: stuur shipped e-mail */
    add_action('woocommerce_order_status_onderweg', function ($order_id, $order) {
        if (is_numeric($order))
            $order = wc_get_order($order);
        if (!$order instanceof WC_Order)
            return;

        // Alleen als eligible (via onze flow)
        if (!$order->get_meta('_lap_eligible_for_emails')) {
            lap_log("shipped email skip not-eligible #$order_id");
            return;
        }

        // Voorkom dubbele e-mails
        if ($order->get_meta('_lap_shipped_email_sent')) {
            lap_log("shipped email skip duplicate #$order_id");
            return;
        }

        if (lap_send_wc_email('lap_order_shipped', $order, 'status-onderweg')) {
            $order->update_meta_data('_lap_shipped_email_sent', wp_date('Y-m-d H:i:s'));
            $order->save();
        }
    }, 20, 2);

    /** F) Betaalbare statussen uitbreiden (cancelled bewust niet opgenomen - voorkom ongewenst reactiveren) */
    add_filter('woocommerce_valid_order_statuses_for_payment', function ($statuses, $order) {
        $extra = array('betaling-verzocht', 'on-hold', 'pending', 'failed');
        return array_unique(array_merge($statuses, $extra));
    }, 10, 2);

    /** G) Gateways registreren */
    if (class_exists('WC_Payment_Gateway')) {
        require_once LAP_PLUGIN_DIR . 'includes/class-lap-gateway-approval.php';
        add_filter('woocommerce_payment_gateways', function ($gws) {
            $gws[] = 'LAP_Gateway_Approval';
            if (get_option('lap_test_mode')) {
                require_once LAP_PLUGIN_DIR . 'includes/class-lap-gateway-testpay.php';
                $gws[] = 'LAP_Gateway_TestPay';
            }
            return $gws;
        });
    }

    /** H) Gateways zichtbaarheidsregels (classic + order-pay + blocks) */
    add_filter('woocommerce_available_payment_gateways', function ($gateways) {
        if (is_admin() && !wp_doing_ajax())
            return $gateways;

        $is_checkout = function_exists('is_checkout') && is_checkout();
        $is_order_pay = function_exists('is_checkout_pay_page') && is_checkout_pay_page();
        $force = (bool) get_option('lap_force_approval_checkout', 1);

        if ($is_checkout && !$is_order_pay) {
            if ($force) {
                lap_log('Gateways @checkout → alleen lap_approval');
                return array_intersect_key((array) $gateways, array('lap_approval' => true));
            }
            return $gateways;
        }
        if ($is_order_pay) {
            $order_id = absint(get_query_var('order-pay'));
            $order = $order_id ? wc_get_order($order_id) : false;
            if (!$order)
                return array();
            $allowed = array('betaling-verzocht', 'on-hold', 'pending', 'failed');
            if (!$order->has_status($allowed)) {
                lap_log('Gateways @order-pay → geblokkeerd (status niet toegestaan)');
                return array();
            }
            if (isset($gateways['lap_approval']))
                unset($gateways['lap_approval']);
            if (get_option('lap_test_admin_only') && isset($gateways['lap_testpay']) && !current_user_can('manage_woocommerce')) {
                unset($gateways['lap_testpay']);
            }
            return $gateways;
        }
        return $gateways;
    }, 9999);

    add_filter('woocommerce_blocks_payment_method_type_registration', function ($pm_types) {
        if (is_admin() && !wp_doing_ajax())
            return $pm_types;

        $is_checkout = function_exists('is_checkout') && is_checkout();
        $is_order_pay = function_exists('is_checkout_pay_page') && is_checkout_pay_page();
        $force = (bool) get_option('lap_force_approval_checkout', 1);

        if ($is_checkout && !$is_order_pay) {
            if ($force) {
                lap_log('Blocks: geen online methods op checkout');
                return array();
            }
            return $pm_types;
        }
        if ($is_order_pay) {
            $order_id = absint(get_query_var('order-pay'));
            $order = $order_id ? wc_get_order($order_id) : false;
            if (!$order)
                return array();
            $allowed = array('betaling-verzocht', 'on-hold', 'pending', 'failed');
            if (!$order->has_status($allowed))
                return array();
            return $pm_types;
        }
        return $pm_types;
    }, 9999);

    /** I) E-mail extra zeker bij statuswijziging (met duplicate preventie) */
    add_action('woocommerce_order_status_betaling-verzocht', function ($order_id, $order) {
        if (is_numeric($order))
            $order = wc_get_order($order);
        if (!$order instanceof WC_Order)
            return;
        // Alleen orders die via de goedkeuringsflow zijn verlopen
        if (!$order->get_meta('_lap_eligible_for_emails')) {
            lap_log("payment email skip not-eligible #$order_id");
            return;
        }
        // Voorkom dubbele emails
        if ($order->get_meta('_lap_payment_email_sent')) {
            lap_log("payment email skip duplicate #$order_id");
            return;
        }
        if (lap_send_wc_email('request_payment', $order, 'status-betaling-verzocht')) {
            $order->update_meta_data('_lap_payment_email_sent', wp_date('Y-m-d H:i:s'));
            $order->save();
        }
    }, 20, 2);

    /** J) Webhook-simulatie (testmodus + admin) */
    add_action('init', function () {
        if (empty($_GET['lap_sim_webhook']))
            return;
        if (!get_option('lap_test_mode') || !current_user_can('manage_woocommerce'))
            return;
        $order_id = absint($_GET['lap_sim_webhook']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!$order_id || !wp_verify_nonce($nonce, 'lap_sim_webhook_' . $order_id))
            return;
        $order = wc_get_order($order_id);
        if (!$order)
            wp_die('Order niet gevonden.');
        $order->add_order_note(__('TEST: simulatie-webhook → payment_complete', 'lommers-approval'));
        $order->payment_complete('lap_webhook_sim_' . time());
        $final = $order->needs_processing() ? 'processing' : 'completed';
        if ($order->get_status() !== $final) {
            $order->set_status($final);
        }
        $order->save(); // Altijd opslaan na payment_complete
        lap_log("Sim webhook → order #$order_id → {$final}");
        wp_safe_redirect(admin_url('post.php?post=' . $order_id . '&action=edit'));
        exit;
    });

    /** K) Safety net: payment_complete → bump indien nodig */
    add_action('woocommerce_payment_complete', function ($order_id) {
        $order = wc_get_order($order_id);
        if (!$order)
            return;
        $unpaidish = array('betaling-verzocht', 'on-hold', 'pending', 'failed', 'cancelled', 'ter-goedkeuring');
        if (in_array($order->get_status(), $unpaidish, true)) {
            $final = $order->needs_processing() ? 'processing' : 'completed';
            $order->set_status($final);
            $order->add_order_note(sprintf(__('Status automatisch doorgezet naar %s na voltooide betaling.', 'lommers-approval'), $final));
            $order->save();
            lap_log("payment_complete bump → #{$order_id} → {$final}");
        }
    }, 20);

    /** L) Status changed: paid bump indien txn/paid */
    $lap_paid_bump_running = false;
    add_action('woocommerce_order_status_changed', function ($order_id, $from, $to, $order) use (&$lap_paid_bump_running) {
        if ($lap_paid_bump_running)
            return;
        if (!get_option('lap_force_paid_bump', 1))
            return;
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if (!$order)
            return;
        $unpaidish = array('betaling-verzocht', 'on-hold', 'pending', 'failed', 'ter-goedkeuring');
        $has_txn = $order->get_transaction_id();
        if (($order->is_paid() || $has_txn) && in_array($to, $unpaidish, true)) {
            $final = $order->needs_processing() ? 'processing' : 'completed';
            $lap_paid_bump_running = true;
            $order->set_status($final);
            $order->add_order_note(sprintf(__('Status automatisch doorgezet naar %s (paid-bump).', 'lommers-approval'), $final));
            $order->save();
            $lap_paid_bump_running = false;
            lap_log("paid-bump on status_changed #{$order_id}: {$from} -> {$to} => {$final}");
        }
    }, 999, 4);

    /** M) Mollie-detecties: order notes + thankyou */
    if (!function_exists('lap_force_paid_bump_now')) {
        function lap_force_paid_bump_now($order, $reason = 'auto')
        {
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }
            if (!$order instanceof WC_Order)
                return false;
            $unpaidish = array('betaling-verzocht', 'on-hold', 'pending', 'failed', 'cancelled', 'ter-goedkeuring');
            if (!in_array($order->get_status(), $unpaidish, true))
                return false;
            $final = $order->needs_processing() ? 'processing' : 'completed';
            $order->set_status($final);
            $order->add_order_note(sprintf(__('Status automatisch doorgezet naar %s (%s).', 'lommers-approval'), $final, $reason));
            $order->save();
            lap_log("force bump → #{$order->get_id()} → {$final} ({$reason})");
            return true;
        }
    }
    add_action('wp_insert_comment', function ($comment_id, $comment) {
        try {
            if (empty($comment) || !is_object($comment))
                return;
            if (isset($comment->comment_type) and $comment->comment_type !== 'order_note')
                return;
            $post_id = intval($comment->comment_post_ID);
            if (!$post_id)
                return;
            $content = isset($comment->comment_content) ? strtolower($comment->comment_content) : '';
            if (!$content)
                return;
            $paid_markers = array(
                'bestelling afgerond met gebruik van mollie',
                'payment completed with mollie',
                'payment completed',
                'status: paid',
                'betalen afgerond',
                'mollie – ideal betaling',
                'mollie - ideal betaling'
            );
            foreach ($paid_markers as $mk) {
                if (strpos($content, $mk) !== false) {
                    $order = wc_get_order($post_id);
                    if ($order)
                        lap_force_paid_bump_now($order, 'mollie-note');
                    break;
                }
            }
        } catch (\Throwable $e) {
            lap_log('wp_insert_comment error: ' . $e->getMessage(), 'error');
        }
    }, 10, 2);
    add_action('template_redirect', function () {
        if (!(function_exists('is_order_received_page') && is_order_received_page()))
            return;
        if (!get_option('lap_force_paid_bump', 1))
            return;
        $order_id = absint(get_query_var('order-received'));
        if (!$order_id && !empty($_GET['key'])) {
            $order_id = wc_get_order_id_by_order_key(wc_clean(wp_unslash($_GET['key'])));
        }
        if (!$order_id)
            return;
        $order = wc_get_order($order_id);
        if (!$order)
            return;
        $pm = $order->get_payment_method();
        $is_mollie = is_string($pm) && strpos($pm, 'mollie') !== false;
        if (!$is_mollie)
            return;
        $has_txn = (string) $order->get_transaction_id();
        $has_meta = $order->get_meta('_mollie_payment_id') || $order->get_meta('_mollie_order_id');
        if ($order->is_paid() || $has_txn || $has_meta) {
            lap_force_paid_bump_now($order, 'thankyou-mollie');
        }
    });

    /** N) REMINDER planner + runner (werkdagen) */
    if (!function_exists('lap_workdays_ago_timestamp')) {
        function lap_workdays_ago_timestamp($days)
        {
            $days = max(0, intval($days));
            $ts = current_datetime()->getTimestamp();
            $count = 0;
            while ($count < $days) {
                $ts -= DAY_IN_SECONDS;
                $w = (int) wp_date('w', $ts);
                if ($w >= 1 && $w <= 5) {
                    $count++;
                }
            }
            return $ts;
        }
    }
    if (!function_exists('lap_schedule_reminders')) {
        function lap_schedule_reminders()
        {
            if (!get_option('lap_reminder_enabled', 0))
                return;
            if (!wp_next_scheduled('lap_daily_reminders')) {
                // Bereken UTC timestamp voor 09:10 lokale tijd
                $local_time = wp_date('Y-m-d 09:10:00');
                $base = strtotime(get_gmt_from_date($local_time));
                if ($base <= time())
                    $base += DAY_IN_SECONDS;
                wp_schedule_event($base, 'daily', 'lap_daily_reminders');
            }
        }
    }
    if (!function_exists('lap_schedule_auto_cancel')) {
        function lap_schedule_auto_cancel()
        {
            if (!get_option('lap_auto_cancel_enabled', 0))
                return;
            if (!wp_next_scheduled('lap_daily_auto_cancel')) {
                // Bereken UTC timestamp voor 09:30 lokale tijd
                $local_time = wp_date('Y-m-d 09:30:00');
                $base = strtotime(get_gmt_from_date($local_time));
                if ($base <= time())
                    $base += DAY_IN_SECONDS;
                wp_schedule_event($base, 'daily', 'lap_daily_auto_cancel');
            }
        }
    }
    register_activation_hook(LAP_PLUGIN_FILE, function () {
        // Wordt na plugins_loaded uitgevoerd, maar schedules worden via init hook opgepikt
        wp_clear_scheduled_hook('lap_daily_reminders');
        wp_clear_scheduled_hook('lap_daily_auto_cancel');
    });
    add_action('init', 'lap_schedule_reminders');
    add_action('init', 'lap_schedule_auto_cancel');

    // daadwerkelijke runner
    add_action('lap_daily_reminders', function () {
        $enabled = get_option('lap_reminder_enabled', 0);
        lap_log('reminders: start (enabled=' . ($enabled ? 'yes' : 'no') . ')');
        if (!$enabled)
            return;

        $days = max(1, intval(get_option('lap_reminder_days', 4)));
        $cut = lap_workdays_ago_timestamp($days);

        $args = array(
            'status' => array('betaling-verzocht', 'on-hold', 'pending'),
            'limit' => 50,
            'return' => 'objects',
            'meta_query' => array(
                array(
                    'key'     => '_lap_eligible_for_emails',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        $paged = 1;
        do {
            $args['page'] = $paged;
            $orders = wc_get_orders($args);
            if (empty($orders))
                break;
            foreach ($orders as $order) {
                // Skip orders die al betaald zijn
                if ($order->is_paid()) {
                    lap_log('reminder skip paid #' . $order->get_id());
                    continue;
                }
                // Skip orders met een transaction ID (betaling is doorgekomen)
                if ($order->get_transaction_id()) {
                    lap_log('reminder skip has-txn #' . $order->get_id());
                    continue;
                }
                // Skip orders in processing/completed status (veiligheidsnet)
                $status = $order->get_status();
                if (in_array($status, array('processing', 'completed'), true)) {
                    lap_log('reminder skip status=' . $status . ' #' . $order->get_id());
                    continue;
                }
                // Skip orders die nog niet oud genoeg zijn (te recent)
                $order_date = $order->get_date_created();
                if ($order_date && $order_date->getTimestamp() > $cut) {
                    lap_log('reminder skip too-recent #' . $order->get_id() . ' (created after cutoff)');
                    continue;
                }
                // Skip als herinnering al verstuurd is
                if ($order->get_meta('_lap_reminder_sent')) {
                    lap_log('reminder skip already-sent #' . $order->get_id());
                    continue;
                }
                if (lap_send_wc_email('request_payment_reminder', $order, 'cron')) {
                    $order->update_meta_data('_lap_reminder_sent', wp_date('Y-m-d H:i:s'));
                    $order->add_order_note(sprintf(__('Herinnering gestuurd na %d werkdagen zonder betaling.', 'lommers-approval'), $days));
                    $order->save();
                    lap_log("reminder sent #" . $order->get_id());
                } else {
                    lap_log("reminder failed #" . $order->get_id(), 'error');
                }
            }
            $paged++;
        } while (count($orders) >= 50);

        lap_log('reminders: done');
    });

    // AUTO-CANCEL runner (dagelijks)
    add_action('lap_daily_auto_cancel', function () {
        $enabled = get_option('lap_auto_cancel_enabled', 0);
        lap_log('auto-cancel: start (enabled=' . ($enabled ? 'yes' : 'no') . ')');
        if (!$enabled)
            return;

        $days = max(1, intval(get_option('lap_auto_cancel_days', 14)));
        $cutoff_timestamp = strtotime("-{$days} days", current_datetime()->getTimestamp());
        $cutoff_date = wp_date('Y-m-d H:i:s', $cutoff_timestamp);

        lap_log("auto-cancel: cutoff = {$cutoff_date} ({$days} days ago)");

        $args = array(
            'status' => array('betaling-verzocht', 'on-hold', 'pending'),
            'limit' => 50,
            'date_created' => '<' . $cutoff_date,
            'meta_query' => array(
                array(
                    'key'     => '_lap_eligible_for_emails',
                    'compare' => 'EXISTS',
                ),
            ),
            'return' => 'objects',
        );

        $paged = 1;
        $cancelled_count = 0;
        do {
            $args['page'] = $paged;
            $orders = wc_get_orders($args);
            if (empty($orders))
                break;

            foreach ($orders as $order) {
                // Extra veiligheid: skip betaalde orders
                if ($order->is_paid()) {
                    lap_log('auto-cancel skip paid #' . $order->get_id());
                    continue;
                }

                // Skip orders met transaction ID
                if ($order->get_transaction_id()) {
                    lap_log('auto-cancel skip has-txn #' . $order->get_id());
                    continue;
                }

                // Skip als al geannuleerd
                if ($order->get_status() === 'cancelled') {
                    lap_log('auto-cancel skip already-cancelled #' . $order->get_id());
                    continue;
                }

                // Annuleer de order (WooCommerce herstelt voorraad automatisch bij cancellation)
                $order->update_status('cancelled', sprintf(__('Automatisch geannuleerd na %d dagen zonder betaling.', 'lommers-approval'), $days));

                // Expliciet voorraad herstellen als dat nog niet is gebeurd
                if (function_exists('wc_increase_stock_levels')) {
                    wc_increase_stock_levels($order);
                }

                $order->update_meta_data('_lap_auto_cancelled', wp_date('Y-m-d H:i:s'));
                $order->save();

                lap_log("auto-cancel: cancelled order #" . $order->get_id() . " (created: " . $order->get_date_created()->format('Y-m-d') . ")");
                $cancelled_count++;
            }
            $paged++;
        } while (count($orders) >= 50);

        lap_log("auto-cancel: done ({$cancelled_count} orders cancelled)");
    });

    // Handmatige run via URL
    add_action('init', function () {
        if (empty($_GET['lap_run_reminders']))
            return;
        if (!current_user_can('manage_woocommerce'))
            return;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lap_run_reminders'))
            return;
        do_action('lap_daily_reminders');
        wp_die('Reminders uitgevoerd. Check Woo → Status → Logs (lommers-approval).');
    });

    // Handmatige run auto-cancel via URL
    add_action('init', function () {
        if (empty($_GET['lap_run_auto_cancel']))
            return;
        if (!current_user_can('manage_woocommerce'))
            return;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lap_run_auto_cancel'))
            return;
        do_action('lap_daily_auto_cancel');
        wp_die('Auto-cancel uitgevoerd. Check Woo → Status → Logs (lommers-approval).');
    });

    /**
     * Test excuusmail: verstuur naar één e-mailadres om de opmaak te controleren.
     * Gebruik: ?lap_test_apology=1&lap_test_email=...&_wpnonce=...
     */
    add_action('wp', function () {
        if (empty($_GET['lap_test_apology']))
            return;
        if (!current_user_can('manage_woocommerce'))
            return;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lap_test_apology'))
            return;

        $recipient = isset($_GET['lap_test_email']) ? sanitize_email(wp_unslash($_GET['lap_test_email'])) : '';
        if (!is_email($recipient)) {
            wp_die('Ongeldig e-mailadres.');
        }

        $emails = WC()->mailer()->get_emails();
        if (empty($emails['LAP_Email_Apology'])) {
            wp_die('LAP_Email_Apology niet gevonden in WooCommerce mailer. Check of de email class correct geregistreerd is.');
        }
        $result = $emails['LAP_Email_Apology']->trigger($recipient);

        if ($result) {
            wp_die(esc_html('Test excuusmail verstuurd naar ' . $recipient . '. Check je inbox.'));
        } else {
            wp_die(esc_html('Versturen mislukt naar ' . $recipient . '. Check Woo → Status → Logs (lommers-approval).'));
        }
    });

    /**
     * Bulk excuusmail: verstuur naar alle adressen in email.csv (in de plugin map).
     * Gebruik: ?lap_send_apology=1&_wpnonce=...
     * Veilig om meerdere keren te triggeren — adressen die al een mail hebben
     * ontvangen (bijgehouden in optie lap_apology_sent_emails) worden overgeslagen.
     */
    add_action('wp', function () {
        if (empty($_GET['lap_send_apology']))
            return;
        if (!current_user_can('manage_woocommerce'))
            return;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lap_send_apology'))
            return;

        $csv_file = LAP_PLUGIN_DIR . 'email.csv';
        if (!file_exists($csv_file)) {
            wp_die('Bestand email.csv niet gevonden in de plugin map.');
        }

        $already_sent = get_option('lap_apology_sent_emails', array());
        if (!is_array($already_sent)) {
            $already_sent = array();
        }

        $emails = WC()->mailer()->get_emails();
        if (empty($emails['LAP_Email_Apology'])) {
            wp_die('LAP_Email_Apology niet gevonden in WooCommerce mailer.');
        }
        $email_class = $emails['LAP_Email_Apology'];

        $handle = fopen($csv_file, 'r');
        if (!$handle) {
            wp_die('Kon email.csv niet openen.');
        }

        $sent    = 0;
        $skipped = 0;
        $failed  = 0;
        $header  = true;
        $batch_size = 50; // max per run om rate limiting te voorkomen

        while (($row = fgetcsv($handle)) !== false) {
            // Sla headerregel over
            if ($header) {
                $header = false;
                continue;
            }
            $email = isset($row[0]) ? sanitize_email(trim($row[0], " \t\n\r\0\x0B\"")) : '';
            if (!is_email($email)) {
                $failed++;
                continue;
            }
            // Skip als al verstuurd
            if (in_array($email, $already_sent, true)) {
                $skipped++;
                continue;
            }
            // Stop als batch vol is (al-verzonden telt niet mee)
            if ($sent >= $batch_size) {
                break;
            }
            if ($email_class->trigger($email)) {
                $already_sent[] = $email;
                $sent++;
                lap_log('apology sent: ' . $email);
                usleep(200000); // 0.2 sec pauze tussen mails
            } else {
                $failed++;
                lap_log('apology failed: ' . $email, 'warning');
            }
        }
        fclose($handle);

        // Sla bijgewerkte lijst op
        update_option('lap_apology_sent_emails', $already_sent);

        $total_sent_ever = count($already_sent);
        wp_die(
            esc_html(sprintf(
                'Batch klaar: %d verstuurd, %d overgeslagen (al verstuurd), %d mislukt. Totaal ooit verstuurd: %d. Klik terug en druk opnieuw op de knop als er nog adressen over zijn.',
                $sent,
                $skipped,
                $failed,
                $total_sent_ever
            ))
        );
    });

    /**
     * Test shipped-email: verstuur naar één e-mailadres met optionele track & trace code.
     * Gebruik: ?lap_test_shipped=1&lap_test_email=...&lap_test_tracking=3SABC123&_wpnonce=...
     */
    add_action('wp', function () {
        if (empty($_GET['lap_test_shipped'])) {
            return;
        }
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lap_test_shipped')) {
            return;
        }

        $recipient = isset($_GET['lap_test_email']) ? sanitize_email(wp_unslash($_GET['lap_test_email'])) : '';
        if (!is_email($recipient)) {
            wp_die('Ongeldig e-mailadres.');
        }

        $tracking = isset($_GET['lap_test_tracking']) ? sanitize_text_field(wp_unslash($_GET['lap_test_tracking'])) : '';

        // Maak een tijdelijk WC_Order object aan zodat trigger() een recipient heeft
        // en de placeholders logisch worden ingevuld. We maken een virtuele order
        // met een ordernummer placeholder.
        $fake_order = wc_create_order(array(
            'status' => 'pending',
            'customer_id' => 0,
        ));
        if (!$fake_order) {
            wp_die('Kon geen testorder aanmaken.');
        }
        $fake_order->set_billing_first_name('Test');
        $fake_order->set_billing_email($recipient);
        $fake_order->set_total(99.95);
        $fake_order->set_currency('EUR');
        if ($tracking) {
            $fake_order->update_meta_data('_lap_track_and_trace', $tracking);
        }
        $fake_order->save();

        $emails = WC()->mailer()->get_emails();
        if (empty($emails['LAP_Email_Shipped'])) {
            wp_die('LAP_Email_Shipped niet gevonden in WooCommerce mailer.');
        }

        // Forceer recipient naar opgegeven adres (ook als de order een ander adres heeft)
        $emails['LAP_Email_Shipped']->recipient = $recipient;
        $result = $emails['LAP_Email_Shipped']->trigger($fake_order->get_id(), $fake_order);

        // Verwijder de fake order na het versturen
        wp_delete_post($fake_order->get_id(), true);

        if ($result) {
            wp_die(esc_html('Test "Onderweg"-mail verstuurd naar ' . $recipient . ($tracking ? ' (T&T: ' . $tracking . ')' : '') . '. Check je inbox.'));
        } else {
            wp_die(esc_html('Versturen mislukt naar ' . $recipient . '. Check Woo → Status → Logs (lommers-approval).'));
        }
    });

    /**
     * Eenmalige cleanup: verwijder _lap_managed_order van orders die NIET via
     * de goedkeuringsflow zijn verlopen. Dit corrigeert orders die per abuis
     * het meta kregen via de (nu verwijderde) woocommerce_new_order hook.
     *
     * Gebruik: voeg ?lap_cleanup_managed_meta=1&_wpnonce=... toe aan een admin URL.
     * Veilig om meerdere keren uit te voeren (idempotent).
     */
    add_action('init', function () {
        if (empty($_GET['lap_cleanup_managed_meta']))
            return;
        if (!current_user_can('manage_woocommerce'))
            return;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lap_cleanup_managed_meta'))
            return;

        // Goedkeuringsflow statussen: orders die ooit deze status hadden zijn legitiem
        $lap_statuses = array('ter-goedkeuring', 'betaling-verzocht');

        $cleaned = 0;
        $skipped = 0;
        $paged   = 1;
        do {
            $orders = wc_get_orders(array(
                'limit'      => 50,
                'page'       => $paged,
                'return'     => 'objects',
                'meta_query' => array(
                    array(
                        'key'     => '_lap_managed_order',
                        'compare' => 'EXISTS',
                    ),
                ),
            ));
            if (empty($orders))
                break;
            foreach ($orders as $order) {
                $status = $order->get_status();

                // Huidige status is al een goedkeuringsstatus -> zeker legitiem, overslaan
                if (in_array($status, $lap_statuses, true)) {
                    $skipped++;
                    continue;
                }

                // Controleer order notes (type 'system' = statuswijzigingen door WooCommerce)
                // op aanwijzingen dat de order ooit via de goedkeuringsflow is verlopen.
                $all_notes = wc_get_order_notes(array('order_id' => $order->get_id(), 'type' => 'any'));
                $is_lap_order = false;
                foreach ($all_notes as $note) {
                    $content = strtolower($note->content);
                    if (
                        strpos($content, 'ter-goedkeuring') !== false ||
                        strpos($content, 'ter goedkeuring') !== false ||
                        strpos($content, 'betaling-verzocht') !== false ||
                        strpos($content, 'betaling verzocht') !== false ||
                        strpos($content, 'goedgekeurd') !== false ||
                        strpos($content, 'betaalverzoek') !== false ||
                        strpos($content, 'herinnering') !== false
                    ) {
                        $is_lap_order = true;
                        break;
                    }
                }
                if ($is_lap_order) {
                    $skipped++;
                    lap_log("cleanup: keeping #" . $order->get_id() . " (lap notes found, status={$status})");
                    continue;
                }

                // Geen enkel spoor van de goedkeuringsflow -> meta verwijderen
                $order->delete_meta_data('_lap_managed_order');
                $order->save();
                $cleaned++;
                lap_log("cleanup: removed _lap_managed_order from #" . $order->get_id() . " (status={$status})");
            }
            $paged++;
        } while (count($orders) >= 50);

        wp_die(
            esc_html(
                sprintf(
                    'Cleanup voltooid: %d orders opgeschoond, %d legitieme LAP-orders overgeslagen. Check Woo → Status → Logs (lommers-approval) voor details.',
                    $cleaned,
                    $skipped
                )
            )
        );
    });

}, 0);


/** ------------------------------------------------------------------------
 * _lap_managed_order wordt alleen gezet via de woocommerce_order_status_changed
 * hook hieronder (als order naar 'ter-goedkeuring' gaat). De eerdere
 * woocommerce_new_order hook is verwijderd omdat die het meta veld op ALLE
 * nieuwe orders zette, ook orders die direct betaald worden via gewone
 * gateways — waardoor de reminder runner ze ten onrechte oppakte.
 * -------------------------------------------------------------------------*/


add_action('woocommerce_order_status_changed', function ($order_id, $old_status, $new_status) {

    if ($new_status !== 'ter-goedkeuring') {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order)
        return;

    // Zet _lap_managed_order meta als nog niet gezet (dashboard + statistieken)
    if (!$order->get_meta('_lap_managed_order')) {
        $order->update_meta_data('_lap_managed_order', current_time('mysql'));
        lap_log("Order marked with _lap_managed_order meta (via ter-goedkeuring status): #" . $order_id);
    }

    // Zet _lap_eligible_for_emails: de enige poortwachter voor alle plugin-mails.
    // Alleen orders die ooit ter-goedkeuring zijn geweest mogen mails ontvangen.
    if (!$order->get_meta('_lap_eligible_for_emails')) {
        $order->update_meta_data('_lap_eligible_for_emails', wp_date('Y-m-d H:i:s'));
        lap_log("Order marked _lap_eligible_for_emails: #" . $order_id);
    }

    $order->save();

    // Stuur bevestigingsmail via WooCommerce email systeem
    lap_send_wc_email('lap_order_confirmation', $order, 'status-ter-goedkeuring');

}, 10, 3);


/**
 * Toon track & trace in admin order lijst kolom
 */
add_filter('manage_edit-shop_order_columns', function ($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'order_status') {
            $new_columns['lap_tracking'] = __('Track & trace', 'lommers-approval');
        }
    }
    return $new_columns;
});

add_action('manage_shop_order_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'lap_tracking') {
        return;
    }
    $order = wc_get_order($post_id);
    if (!$order) {
        return;
    }
    $tracking = $order->get_meta('_lap_track_and_trace');
    if ($tracking) {
        echo '<code>' . wp_kses_post(lap_tracking_link($tracking)) . '</code>';
    } else {
        echo '<span style="color:#999;">&mdash;</span>';
    }
}, 10, 2);

/**
 * Toon track & trace in order admin details (HPOS compatible)
 */
add_action('woocommerce_admin_order_data_after_order_details', function ($order) {
    $tracking = $order->get_meta('_lap_track_and_trace');
    if ($tracking) {
        echo '<p class="form-field form-field-wide" style="clear:left;margin-top:12px;">';
        echo '<strong>' . esc_html__('Track & trace:', 'lommers-approval') . '</strong> ';
        if (strpos($tracking, 'http://') === 0 || strpos($tracking, 'https://') === 0) {
            echo '<a href="' . esc_url($tracking) . '" target="_blank" rel="noopener" style="font-size:14px;">' . esc_html($tracking) . '</a>';
        } else {
            echo '<code style="font-size:14px;padding:4px 8px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:4px;">' . esc_html($tracking) . '</code>';
        }
        echo '</p>';
    }
});

/**
 * Stuur standaard WooCommerce "Nieuwe bestelling" mail naar de admin
 * zodra een order de status "ter-goedkeuring" krijgt.
 */
add_action('woocommerce_order_status_pending_to_ter-goedkeuring', function ($order_id) {

    if (!function_exists('WC')) {
        return;
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();

    if (!empty($emails['WC_Email_New_Order'])) {
        $emails['WC_Email_New_Order']->trigger($order_id);
    }
});

add_action('woocommerce_order_status_changed', function ($order_id, $from, $to, $order) {

    if ($to !== 'processing') {
        return;
    }

    if (!$order instanceof WC_Order) {
        $order = wc_get_order($order_id);
    }
    if (!$order)
        return;

    // Controleer of deze order via onze flow is gegaan (ter-goedkeuring of betaling-verzocht)
    $relevant_from_statuses = array('ter-goedkeuring', 'betaling-verzocht', 'on-hold', 'pending');
    if (!in_array($from, $relevant_from_statuses, true)) {
        lap_log("processing email skip: from={$from} not relevant for #" . $order_id);
        return;
    }

    // Stuur standaard WooCommerce "processing" e-mail
    $mailer = WC()->mailer();
    $emails = $mailer->get_emails();
    if (!empty($emails['WC_Email_Customer_Processing_Order'])) {
        $emails['WC_Email_Customer_Processing_Order']->trigger($order_id);
        lap_log("Processing email sent for order #" . $order_id . " (from {$from})");
    } else {
        lap_log("Processing email class not found for order #" . $order_id, 'warning');
    }

}, 10, 4);

/**
 * Stuur cancelled/geannuleerd e-mail naar klant
 */
add_action('woocommerce_order_status_changed', function ($order_id, $from, $to, $order) {

    if ($to !== 'cancelled') {
        return;
    }

    if (!$order instanceof WC_Order) {
        $order = wc_get_order($order_id);
    }
    if (!$order)
        return;

    // Alleen orders die via de goedkeuringsflow zijn verlopen
    if (!$order->get_meta('_lap_eligible_for_emails')) {
        lap_log("cancelled email skip not-eligible #" . $order_id);
        return;
    }

    // Alleen versturen als het van een van onze custom statussen komt
    $relevant_statuses = array('ter-goedkeuring', 'betaling-verzocht', 'on-hold', 'pending');
    if (!in_array($from, $relevant_statuses, true)) {
        lap_log("cancelled email skip: from={$from} not relevant for #" . $order_id);
        return;
    }

    lap_log("Sending cancelled email for order #" . $order_id . " (from {$from})");

    // Gebruik onze eigen cancelled email class
    lap_send_wc_email('lap_order_cancelled', $order, 'status-cancelled');

}, 10, 4);
