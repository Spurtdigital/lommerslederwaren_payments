<?php
if (!defined('ABSPATH'))
    exit;

class LAP_Dashboard
{

    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'add_menu_page'));
        add_action('admin_init', array(__CLASS__, 'handle_quick_actions'));
    }

    public static function add_menu_page()
    {
        if (!current_user_can('manage_woocommerce'))
            return;

        add_menu_page(
            'Goedkeuren & Betalen',
            'Goedkeuren & Betalen',
            'manage_woocommerce',
            'lap-dashboard',
            array(__CLASS__, 'render_dashboard'),
            'dashicons-yes-alt',
            56
        );

        // Voeg submenu toe
        add_submenu_page(
            'lap-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_woocommerce',
            'lap-dashboard',
            array(__CLASS__, 'render_dashboard')
        );

        add_submenu_page(
            'lap-dashboard',
            'Instellingen',
            'Instellingen',
            'manage_woocommerce',
            'lap-settings',
            array('LAP_Settings', 'render_page')
        );
    }

    /**
     * Verwerk quick actions vanuit het dashboard (goedkeuren, herinnering)
     */
    public static function handle_quick_actions()
    {
        if (!isset($_GET['lap_dash_action'])) return;
        if (!current_user_can('manage_woocommerce')) return;

        $action = sanitize_text_field($_GET['lap_dash_action']);
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (!$order_id || !wp_verify_nonce($nonce, 'lap_dash_' . $action . '_' . $order_id)) {
            wp_die(__('Ongeldige beveiligingstoken.', 'lommers-approval'));
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order niet gevonden.', 'lommers-approval'));
        }

        $redirect = admin_url('admin.php?page=lap-dashboard');

        switch ($action) {
            case 'approve':
                if ($order->get_status() === 'ter-goedkeuring') {
                    $order->update_status('betaling-verzocht', __('Goedgekeurd via dashboard', 'lommers-approval'));
                    $redirect = add_query_arg('lap_notice', 'approved', $redirect);
                    lap_log("Dashboard quick-action: order #{$order_id} goedgekeurd", 'info');
                }
                break;

            case 'reminder':
                if ($order->get_status() === 'betaling-verzocht') {
                    if (function_exists('lap_send_wc_email')) {
                        lap_send_wc_email('request_payment_reminder', $order, 'dashboard-quick-action');
                    }
                    $redirect = add_query_arg('lap_notice', 'reminder_sent', $redirect);
                    lap_log("Dashboard quick-action: herinnering verstuurd voor order #{$order_id}", 'info');
                }
                break;

            case 'ship':
                // Redirect naar order bewerk pagina voor track & trace invoer
                $redirect = admin_url('post.php?post=' . $order_id . '&action=edit');
                break;
        }

        wp_safe_redirect($redirect);
        exit;
    }

    public static function render_dashboard()
    {
        // Haal huidige periode op uit GET parameter (default: maand)
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
        if (!in_array($period, array('week', 'month', 'year'))) {
            $period = 'month';
        }

        // Toon success notices
        if (isset($_GET['lap_notice'])) {
            $notice = sanitize_text_field($_GET['lap_notice']);
            $messages = array(
                'approved' => 'Order goedgekeurd en betaalverzoek verstuurd.',
                'reminder_sent' => 'Betalingsherinnering verstuurd.',
            );
            if (isset($messages[$notice])) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
            }
        }

        echo '<div class="wrap lap-wrap">';
        echo '<div class="lap-header">';
        echo '<h1>Goedkeuren & Betalen</h1>';

        // Periode selector
        echo '<div class="lap-period-selector">';
        $periods = array(
            'week' => 'Week',
            'month' => 'Maand',
            'year' => 'Jaar'
        );
        foreach ($periods as $key => $label) {
            $active = ($key === $period) ? ' active' : '';
            $url = add_query_arg('period', $key, admin_url('admin.php?page=lap-dashboard'));
            echo '<a href="' . esc_url($url) . '" class="lap-period-btn' . $active . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';
        echo '</div>';

        // Toon dummy data waarschuwing als actief
        if (get_option('lap_dashboard_dummy_data')) {
            echo '<div class="lap-notice-banner">';
            echo '<span class="lap-notice-icon">&#127922;</span>';
            echo '<span>Dashboard toont testdata. <a href="' . esc_url(admin_url('admin.php?page=lap-settings')) . '">Uitschakelen</a></span>';
            echo '</div>';
        }

        self::render_styles();
        self::render_scripts();

        $stats = self::get_statistics($period);

        // ── KPI cards ──
        echo '<div class="lap-kpi-grid">';

        self::render_kpi_card('Ter goedkeuring', $stats['awaiting_approval'], 'warning', array(
            'icon' => 'clock',
            'link' => admin_url('edit.php?post_status=wc-ter-goedkeuring&post_type=shop_order'),
            'link_text' => 'Bekijk',
            'show_link' => $stats['awaiting_approval'] > 0,
        ));

        self::render_kpi_card('Betaling verzocht', $stats['payment_requested'], 'amber', array(
            'icon' => 'credit-card',
            'subtitle' => $stats['payment_requested'] > 0 ? 'Gem. ' . esc_html(number_format($stats['avg_payment_age'], 1)) . 'd open' : '',
        ));

        self::render_kpi_card('Conversie', $stats['conversion_rate'] . '%', 'success', array(
            'icon' => 'check-circle',
            'subtitle' => esc_html($stats['paid_count']) . ' / ' . esc_html($stats['total_7d']) . ' betaald',
        ));

        $approval_display = $stats['avg_approval_hours'] > 0 ? esc_html($stats['avg_approval_hours']) . 'u' : '-';
        self::render_kpi_card('Goedkeuringstijd', $approval_display, 'primary', array(
            'icon' => 'zap',
            'subtitle' => $stats['avg_approval_hours'] > 0 ? 'Gemiddeld' : '',
        ));

        self::render_kpi_card('Verwachte omzet', wp_kses_post($stats['expected_revenue_formatted']), 'purple', array(
            'icon' => 'trending-up',
            'subtitle' => 'Ter goedkeuring + verzocht',
            'is_html' => true,
        ));

        self::render_kpi_card('Verzendklaar', $stats['ready_to_ship'], 'teal', array(
            'icon' => 'package',
            'link' => admin_url('edit.php?post_status=wc-processing&post_type=shop_order'),
            'link_text' => 'Bekijk',
            'show_link' => $stats['ready_to_ship'] > 0,
        ));

        self::render_kpi_card('Onderweg', $stats['on_the_way'], 'primary', array(
            'icon' => 'truck',
            'link' => admin_url('edit.php?post_status=wc-onderweg&post_type=shop_order'),
            'link_text' => 'Bekijk',
            'show_link' => $stats['on_the_way'] > 0,
        ));

        $overdue_class = $stats['overdue_count'] > 0 ? 'danger' : 'success';
        self::render_kpi_card('Verlopen', $stats['overdue_percentage'] . '%', $overdue_class, array(
            'icon' => 'alert-triangle',
            'subtitle' => $stats['overdue_count'] > 0 ? esc_html($stats['overdue_count']) . ' orders > ' . esc_html($stats['overdue_days']) . 'd' : 'Alles op schema',
        ));

        echo '</div>';

        // ── Hoofdcontent: 3 kolommen ──
        echo '<div class="lap-grid-3">';

        // KOLOM 1: Order lijsten
        echo '<div class="lap-col">';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Ter goedkeuring</h2></div>';
        echo '<div class="lap-card-body">';
        self::render_recent_orders();
        echo '</div></div>';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Betaling verzocht</h2></div>';
        echo '<div class="lap-card-body">';
        self::render_pending_payments();
        echo '</div></div>';

        echo '</div>';

        // KOLOM 2: Grafieken
        echo '<div class="lap-col">';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Omzet <span class="lap-card-period">' . esc_html($stats['period_label']) . '</span></h2></div>';
        echo '<div class="lap-card-body">';
        self::render_revenue_chart($stats);
        echo '</div></div>';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Activiteit <span class="lap-card-period">' . esc_html($stats['period_label']) . '</span></h2></div>';
        echo '<div class="lap-card-body">';
        self::render_activity_chart($stats);
        echo '</div></div>';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Statusverdeling</h2></div>';
        echo '<div class="lap-card-body">';
        self::render_status_donut($stats);
        echo '</div></div>';

        echo '</div>';

        // KOLOM 3: Stats + Config
        echo '<div class="lap-col">';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Verzendklaar</h2></div>';
        echo '<div class="lap-card-body">';
        self::render_ready_to_ship();
        echo '</div></div>';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Onderweg</h2></div>';
        echo '<div class="lap-card-body">';
        self::render_on_the_way();
        echo '</div></div>';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Statistieken</h2></div>';
        echo '<div class="lap-card-body">';
        self::render_extra_stats($stats);
        echo '</div></div>';

        echo '<div class="lap-card">';
        echo '<div class="lap-card-header"><h2>Configuratie</h2></div>';
        echo '<div class="lap-card-body">';
        self::render_settings_status();
        echo '</div></div>';

        echo '</div>';
        echo '</div>'; // end grid-3

        echo '</div>'; // end wrap
    }

    // ═══════════════════════════════════════════
    // KPI card helper
    // ═══════════════════════════════════════════

    private static function render_kpi_card($label, $value, $color = 'primary', $opts = array())
    {
        $icon = isset($opts['icon']) ? $opts['icon'] : '';
        $subtitle = isset($opts['subtitle']) ? $opts['subtitle'] : '';
        $link = isset($opts['link']) ? $opts['link'] : '';
        $link_text = isset($opts['link_text']) ? $opts['link_text'] : 'Bekijk';
        $show_link = isset($opts['show_link']) ? $opts['show_link'] : false;
        $is_html = isset($opts['is_html']) ? $opts['is_html'] : false;

        echo '<div class="lap-kpi ' . esc_attr($color) . '">';
        if ($icon) {
            echo '<div class="lap-kpi-icon">' . self::get_svg_icon($icon) . '</div>';
        }
        echo '<div class="lap-kpi-body">';
        if ($is_html) {
            echo '<div class="lap-kpi-value">' . $value . '</div>';
        } else {
            echo '<div class="lap-kpi-value">' . esc_html($value) . '</div>';
        }
        echo '<div class="lap-kpi-label">' . esc_html($label) . '</div>';
        if ($subtitle) {
            echo '<div class="lap-kpi-sub">' . $subtitle . '</div>';
        }
        if ($show_link && $link) {
            echo '<a href="' . esc_url($link) . '" class="lap-kpi-link">' . esc_html($link_text) . ' &rarr;</a>';
        }
        echo '</div></div>';
    }

    // ═══════════════════════════════════════════
    // SVG Icons (inline, geen externe deps)
    // ═══════════════════════════════════════════

    private static function get_svg_icon($name)
    {
        $icons = array(
            'clock' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            'credit-card' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
            'check-circle' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            'zap' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
            'trending-up' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
            'package' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
            'alert-triangle' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            'send' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
            'check' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
            'copy' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
            'truck' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
            'arrow-right' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
        );

        return isset($icons[$name]) ? $icons[$name] : '';
    }

    // ═══════════════════════════════════════════
    // Styles
    // ═══════════════════════════════════════════

    private static function render_styles()
    {
        echo '<style>
            :root {
                --lap-primary: #3858e9;
                --lap-primary-light: #eef1fd;
                --lap-success: #16a34a;
                --lap-success-light: #ecfdf5;
                --lap-warning: #dc2626;
                --lap-warning-light: #fef2f2;
                --lap-amber: #d97706;
                --lap-amber-light: #fffbeb;
                --lap-purple: #7c3aed;
                --lap-purple-light: #f5f3ff;
                --lap-teal: #0d9488;
                --lap-teal-light: #f0fdfa;
                --lap-danger: #dc2626;
                --lap-danger-light: #fef2f2;
                --lap-gray-50: #f9fafb;
                --lap-gray-100: #f3f4f6;
                --lap-gray-200: #e5e7eb;
                --lap-gray-300: #d1d5db;
                --lap-gray-400: #9ca3af;
                --lap-gray-500: #6b7280;
                --lap-gray-600: #4b5563;
                --lap-gray-700: #374151;
                --lap-gray-800: #1f2937;
                --lap-gray-900: #111827;
                --lap-radius: 12px;
                --lap-radius-sm: 8px;
                --lap-shadow: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
                --lap-shadow-md: 0 4px 6px -1px rgba(0,0,0,.07), 0 2px 4px -1px rgba(0,0,0,.04);
                --lap-shadow-hover: 0 10px 15px -3px rgba(0,0,0,.08), 0 4px 6px -2px rgba(0,0,0,.04);
                --lap-transition: 0.2s cubic-bezier(.4,0,.2,1);
            }

            .lap-wrap { max-width: 1600px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

            /* Header */
            .lap-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
            .lap-header h1 { margin: 0; font-size: 22px; font-weight: 700; color: var(--lap-gray-900); letter-spacing: -0.02em; }

            .lap-period-selector { display: flex; gap: 4px; background: var(--lap-gray-100); padding: 4px; border-radius: var(--lap-radius-sm); }
            .lap-period-btn { padding: 7px 16px; background: transparent; border: none; border-radius: 6px; text-decoration: none; color: var(--lap-gray-500); font-size: 13px; font-weight: 500; transition: var(--lap-transition); }
            .lap-period-btn:hover { color: var(--lap-gray-700); background: rgba(255,255,255,.6); }
            .lap-period-btn.active { background: #fff; color: var(--lap-gray-900); box-shadow: 0 1px 2px rgba(0,0,0,.06); font-weight: 600; }

            /* Notice banner */
            .lap-notice-banner { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--lap-radius-sm); margin-bottom: 20px; font-size: 13px; color: #92400e; }
            .lap-notice-banner a { color: #92400e; font-weight: 600; }
            .lap-notice-icon { font-size: 18px; }

            /* KPI Grid */
            .lap-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }

            .lap-kpi { background: #fff; padding: 20px; border-radius: var(--lap-radius); box-shadow: var(--lap-shadow); display: flex; align-items: flex-start; gap: 14px; transition: var(--lap-transition); border: 1px solid var(--lap-gray-100); position: relative; overflow: hidden; }
            .lap-kpi:hover { box-shadow: var(--lap-shadow-hover); transform: translateY(-2px); }
            .lap-kpi::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px; }

            .lap-kpi.primary::before { background: var(--lap-primary); }
            .lap-kpi.primary .lap-kpi-icon { color: var(--lap-primary); background: var(--lap-primary-light); }
            .lap-kpi.success::before { background: var(--lap-success); }
            .lap-kpi.success .lap-kpi-icon { color: var(--lap-success); background: var(--lap-success-light); }
            .lap-kpi.warning::before { background: var(--lap-warning); }
            .lap-kpi.warning .lap-kpi-icon { color: var(--lap-warning); background: var(--lap-warning-light); }
            .lap-kpi.amber::before { background: var(--lap-amber); }
            .lap-kpi.amber .lap-kpi-icon { color: var(--lap-amber); background: var(--lap-amber-light); }
            .lap-kpi.purple::before { background: var(--lap-purple); }
            .lap-kpi.purple .lap-kpi-icon { color: var(--lap-purple); background: var(--lap-purple-light); }
            .lap-kpi.teal::before { background: var(--lap-teal); }
            .lap-kpi.teal .lap-kpi-icon { color: var(--lap-teal); background: var(--lap-teal-light); }
            .lap-kpi.danger::before { background: var(--lap-danger); }
            .lap-kpi.danger .lap-kpi-icon { color: var(--lap-danger); background: var(--lap-danger-light); }

            .lap-kpi-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
            .lap-kpi-body { flex: 1; min-width: 0; }
            .lap-kpi-value { font-size: 24px; font-weight: 700; line-height: 1.1; color: var(--lap-gray-900); margin-bottom: 4px; word-break: break-word; }
            .lap-kpi-label { font-size: 12px; color: var(--lap-gray-500); font-weight: 500; text-transform: uppercase; letter-spacing: 0.04em; line-height: 1.3; }
            .lap-kpi-sub { font-size: 11px; color: var(--lap-gray-400); margin-top: 4px; }
            .lap-kpi-link { font-size: 12px; color: var(--lap-primary); text-decoration: none; margin-top: 6px; display: inline-block; font-weight: 500; }
            .lap-kpi-link:hover { text-decoration: underline; }

            /* Grid layout */
            .lap-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }

            /* Cards */
            .lap-card { background: #fff; border-radius: var(--lap-radius); box-shadow: var(--lap-shadow); border: 1px solid var(--lap-gray-100); transition: var(--lap-transition); margin-bottom: 20px; overflow: hidden; }
            .lap-card:hover { box-shadow: var(--lap-shadow-md); }
            .lap-card:last-child { margin-bottom: 0; }
            .lap-card-header { padding: 16px 20px 0 20px; }
            .lap-card-header h2 { margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: var(--lap-gray-800); letter-spacing: -0.01em; padding-bottom: 12px; border-bottom: 1px solid var(--lap-gray-100); display: flex; align-items: center; gap: 8px; }
            .lap-card-period { font-size: 11px; font-weight: 500; background: var(--lap-gray-100); color: var(--lap-gray-500); padding: 2px 8px; border-radius: 4px; }
            .lap-card-body { padding: 0 20px 20px 20px; }

            /* Order list */
            .lap-order-list { list-style: none; margin: 0; padding: 0; }
            .lap-order-item { padding: 12px 0; border-bottom: 1px solid var(--lap-gray-100); display: flex; justify-content: space-between; align-items: center; gap: 12px; }
            .lap-order-item:last-child { border-bottom: none; }
            .lap-order-info { flex: 1; min-width: 0; }
            .lap-order-info strong a { color: var(--lap-gray-800); text-decoration: none; font-size: 13px; font-weight: 600; }
            .lap-order-info strong a:hover { color: var(--lap-primary); }
            .lap-order-meta { font-size: 12px; color: var(--lap-gray-400); margin-top: 2px; }
            .lap-order-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
            .lap-order-amount { font-weight: 600; color: var(--lap-gray-700); font-size: 13px; white-space: nowrap; }

            /* Quick action buttons */
            .lap-quick-actions { display: flex; gap: 4px; }
            .lap-qa-btn { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--lap-gray-200); background: #fff; color: var(--lap-gray-400); cursor: pointer; transition: var(--lap-transition); text-decoration: none; }
            .lap-qa-btn:hover { background: var(--lap-gray-50); color: var(--lap-gray-700); border-color: var(--lap-gray-300); }
            .lap-qa-btn.approve:hover { background: var(--lap-success-light); color: var(--lap-success); border-color: #bbf7d0; }
            .lap-qa-btn.reminder:hover { background: var(--lap-amber-light); color: var(--lap-amber); border-color: #fde68a; }
            .lap-qa-btn.copy:hover { background: var(--lap-primary-light); color: var(--lap-primary); border-color: #c7d2fe; }
            .lap-qa-btn[title] { position: relative; }

            /* Tooltip */
            .lap-tooltip { position: relative; }
            .lap-tooltip::after { content: attr(data-tooltip); position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%); padding: 5px 10px; background: var(--lap-gray-800); color: #fff; font-size: 11px; border-radius: 6px; white-space: nowrap; opacity: 0; pointer-events: none; transition: opacity .15s; z-index: 10; }
            .lap-tooltip::before { content: ""; position: absolute; bottom: calc(100% + 2px); left: 50%; transform: translateX(-50%); border: 4px solid transparent; border-top-color: var(--lap-gray-800); opacity: 0; pointer-events: none; transition: opacity .15s; z-index: 10; }
            .lap-tooltip:hover::after, .lap-tooltip:hover::before { opacity: 1; }

            /* View all button */
            .lap-view-all { display: block; text-align: center; padding: 10px; margin-top: 8px; color: var(--lap-primary); font-size: 13px; font-weight: 500; text-decoration: none; border-radius: var(--lap-radius-sm); transition: var(--lap-transition); }
            .lap-view-all:hover { background: var(--lap-primary-light); color: var(--lap-primary); }

            /* Empty state */
            .lap-empty { text-align: center; padding: 24px 16px; color: var(--lap-gray-400); font-size: 13px; }
            .lap-empty-icon { font-size: 32px; margin-bottom: 8px; opacity: .4; }

            /* Charts */
            .lap-chart-bars { display: flex; align-items: flex-end; justify-content: space-between; height: 180px; gap: 6px; padding-top: 8px; }
            .lap-chart-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0; position: relative; }
            .lap-chart-bar { width: 100%; border-radius: 4px 4px 0 0; min-height: 4px; max-height: 140px; transition: var(--lap-transition); cursor: default; position: relative; }
            .lap-chart-bar.revenue { background: linear-gradient(to top, #16a34a, #4ade80); }
            .lap-chart-bar.activity { background: linear-gradient(to top, var(--lap-primary), #818cf8); }
            .lap-chart-bar:hover { filter: brightness(1.1); }
            .lap-chart-label { font-size: 10px; text-align: center; margin-top: 6px; color: var(--lap-gray-400); line-height: 1.3; font-weight: 500; }
            .lap-chart-val { font-size: 10px; color: var(--lap-gray-500); font-weight: 600; }

            /* Revenue total */
            .lap-chart-total { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--lap-gray-100); text-align: center; }
            .lap-chart-total-label { font-size: 12px; color: var(--lap-gray-400); margin-bottom: 4px; }
            .lap-chart-total-value { font-size: 26px; font-weight: 700; color: var(--lap-success); }
            .lap-chart-comparison { font-size: 12px; margin-top: 4px; font-weight: 600; }

            /* Donut chart */
            .lap-donut-wrap { display: flex; align-items: center; gap: 24px; padding: 8px 0; }
            .lap-donut-svg { flex-shrink: 0; }
            .lap-donut-legend { flex: 1; }
            .lap-donut-item { display: flex; align-items: center; gap: 10px; padding: 6px 0; font-size: 13px; }
            .lap-donut-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
            .lap-donut-item-label { color: var(--lap-gray-600); flex: 1; }
            .lap-donut-item-value { font-weight: 600; color: var(--lap-gray-800); }

            /* Stats list */
            .lap-stat-list { list-style: none; margin: 0; padding: 0; }
            .lap-stat-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--lap-gray-100); gap: 8px; }
            .lap-stat-item:last-child { border-bottom: none; }
            .lap-stat-item-label { font-size: 13px; color: var(--lap-gray-600); display: flex; align-items: center; gap: 6px; }
            .lap-stat-item-value { display: flex; align-items: center; gap: 6px; }

            /* Badges */
            .lap-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
            .lap-badge.success { background: var(--lap-success-light); color: var(--lap-success); }
            .lap-badge.warning { background: #fff3cd; color: #92400e; }
            .lap-badge.danger { background: var(--lap-danger-light); color: var(--lap-danger); }

            /* Comparison arrows */
            .lap-comp { font-size: 11px; font-weight: 600; }
            .lap-comp.up { color: var(--lap-success); }
            .lap-comp.down { color: var(--lap-danger); }
            .lap-comp.up-bad { color: var(--lap-danger); }
            .lap-comp.down-good { color: var(--lap-success); }

            /* Config list */
            .lap-config-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--lap-gray-100); }
            .lap-config-item:last-child { border-bottom: none; }
            .lap-config-item span:first-child { font-size: 13px; color: var(--lap-gray-600); }
            .lap-config-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
            .lap-config-dot.on { background: var(--lap-success); }
            .lap-config-dot.off { background: var(--lap-gray-300); }
            .lap-config-status { font-size: 12px; font-weight: 500; color: var(--lap-gray-600); display: flex; align-items: center; }

            /* Responsive */
            @media (max-width: 1400px) {
                .lap-grid-3 { grid-template-columns: 1fr 1fr; }
            }
            @media (max-width: 1024px) {
                .lap-grid-3 { grid-template-columns: 1fr; }
                .lap-kpi-grid { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
            }
            @media (max-width: 768px) {
                .lap-kpi-grid { grid-template-columns: 1fr 1fr; }
                .lap-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            }

            /* Copy feedback */
            .lap-copied { position: fixed; top: 40px; left: 50%; transform: translateX(-50%); background: var(--lap-gray-800); color: #fff; padding: 8px 16px; border-radius: var(--lap-radius-sm); font-size: 13px; font-weight: 500; z-index: 99999; opacity: 0; transition: opacity .2s; pointer-events: none; }
            .lap-copied.show { opacity: 1; }
        </style>';
    }

    // ═══════════════════════════════════════════
    // Scripts (copy to clipboard)
    // ═══════════════════════════════════════════

    private static function render_scripts()
    {
        echo '<div class="lap-copied" id="lap-copied-toast">Betaallink gekopieerd</div>';
        echo '<script>
        function lapCopyPayUrl(url, btn) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function() { lapShowCopied(btn); });
            } else {
                var t = document.createElement("textarea");
                t.value = url;
                document.body.appendChild(t);
                t.select();
                document.execCommand("copy");
                document.body.removeChild(t);
                lapShowCopied(btn);
            }
            return false;
        }
        function lapShowCopied(btn) {
            var toast = document.getElementById("lap-copied-toast");
            toast.classList.add("show");
            setTimeout(function(){ toast.classList.remove("show"); }, 1500);
        }
        </script>';
    }

    // ═══════════════════════════════════════════
    // Order lists
    // ═══════════════════════════════════════════

    private static function render_recent_orders()
    {
        // Check dummy data mode
        if (get_option('lap_dashboard_dummy_data')) {
            echo '<ul class="lap-order-list">';
            $dummy_orders = array(
                array('number' => '1243', 'name' => 'Jan de Vries', 'time' => '2 uur', 'total' => '145,00'),
                array('number' => '1242', 'name' => 'Maria Jansen', 'time' => '5 uur', 'total' => '189,50'),
                array('number' => '1241', 'name' => 'Piet Bakker', 'time' => '1 dag', 'total' => '234,95'),
                array('number' => '1238', 'name' => 'Sophie van Dam', 'time' => '2 dagen', 'total' => '167,00'),
                array('number' => '1235', 'name' => 'Lucas Smit', 'time' => '3 dagen', 'total' => '199,00'),
            );

            foreach ($dummy_orders as $order) {
                echo '<li class="lap-order-item">';
                echo '<div class="lap-order-info">';
                echo '<strong>#' . esc_html($order['number']) . '</strong>';
                echo '<div class="lap-order-meta">' . esc_html($order['name']) . ' &middot; ' . esc_html($order['time']) . ' geleden</div>';
                echo '</div>';
                echo '<div class="lap-order-right">';
                echo '<div class="lap-order-amount">&euro; ' . esc_html($order['total']) . '</div>';
                echo '<div class="lap-quick-actions">';
                echo '<span class="lap-qa-btn approve lap-tooltip" data-tooltip="Goedkeuren">' . self::get_svg_icon('check') . '</span>';
                echo '</div>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
            return;
        }

        $orders = wc_get_orders(array(
            'status' => 'ter-goedkeuring',
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (empty($orders)) {
            echo '<div class="lap-empty">';
            echo '<div class="lap-empty-icon">' . self::get_svg_icon('check-circle') . '</div>';
            echo '<p>Geen orders ter goedkeuring</p>';
            echo '</div>';
            return;
        }

        echo '<ul class="lap-order-list">';
        foreach ($orders as $order) {
            $approve_url = wp_nonce_url(
                add_query_arg(array(
                    'lap_dash_action' => 'approve',
                    'order_id' => $order->get_id(),
                ), admin_url('admin.php?page=lap-dashboard')),
                'lap_dash_approve_' . $order->get_id()
            );

            echo '<li class="lap-order-item">';
            echo '<div class="lap-order-info">';
            echo '<strong><a href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '">#' . esc_html($order->get_order_number()) . '</a></strong>';
            echo '<div class="lap-order-meta">' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . ' &middot; ' . esc_html(human_time_diff($order->get_date_created()->getTimestamp(), time())) . ' geleden</div>';
            echo '</div>';
            echo '<div class="lap-order-right">';
            echo '<div class="lap-order-amount">' . wp_kses_post($order->get_formatted_order_total()) . '</div>';
            echo '<div class="lap-quick-actions">';
            echo '<a href="' . esc_url($approve_url) . '" class="lap-qa-btn approve lap-tooltip" data-tooltip="Goedkeuren" onclick="return confirm(\'Order #' . esc_attr($order->get_order_number()) . ' goedkeuren?\');">' . self::get_svg_icon('check') . '</a>';
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';

        echo '<a href="' . esc_url(admin_url('edit.php?post_status=wc-ter-goedkeuring&post_type=shop_order')) . '" class="lap-view-all">Alle orders bekijken &rarr;</a>';
    }

    private static function render_pending_payments()
    {
        // Check dummy data mode
        if (get_option('lap_dashboard_dummy_data')) {
            echo '<ul class="lap-order-list">';
            $dummy_orders = array(
                array('number' => '1239', 'name' => 'Anna Visser', 'days' => '2.5', 'total' => '156,50'),
                array('number' => '1237', 'name' => 'Tom Peters', 'days' => '3.2', 'total' => '189,00'),
                array('number' => '1234', 'name' => 'Lisa de Jong', 'days' => '4.8', 'total' => '214,95'),
                array('number' => '1230', 'name' => 'Erik Vermeer', 'days' => '5.1', 'total' => '178,50'),
                array('number' => '1228', 'name' => 'Nina Koster', 'days' => '6.7', 'total' => '245,00'),
            );

            foreach ($dummy_orders as $order) {
                echo '<li class="lap-order-item">';
                echo '<div class="lap-order-info">';
                echo '<strong>#' . esc_html($order['number']) . '</strong>';
                echo '<div class="lap-order-meta">' . esc_html($order['name']) . ' &middot; ' . esc_html($order['days']) . 'd open</div>';
                echo '</div>';
                echo '<div class="lap-order-right">';
                echo '<div class="lap-order-amount">&euro; ' . esc_html($order['total']) . '</div>';
                echo '<div class="lap-quick-actions">';
                echo '<span class="lap-qa-btn reminder lap-tooltip" data-tooltip="Herinnering sturen">' . self::get_svg_icon('send') . '</span>';
                echo '<span class="lap-qa-btn copy lap-tooltip" data-tooltip="Betaallink kopiëren">' . self::get_svg_icon('copy') . '</span>';
                echo '</div>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
            return;
        }

        $orders = wc_get_orders(array(
            'status' => 'betaling-verzocht',
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (empty($orders)) {
            echo '<div class="lap-empty">';
            echo '<div class="lap-empty-icon">' . self::get_svg_icon('credit-card') . '</div>';
            echo '<p>Geen openstaande betalingen</p>';
            echo '</div>';
            return;
        }

        echo '<ul class="lap-order-list">';
        foreach ($orders as $order) {
            $days_old = round((time() - $order->get_date_created()->getTimestamp()) / DAY_IN_SECONDS, 1);
            $pay_url = $order->get_checkout_payment_url();

            $reminder_url = wp_nonce_url(
                add_query_arg(array(
                    'lap_dash_action' => 'reminder',
                    'order_id' => $order->get_id(),
                ), admin_url('admin.php?page=lap-dashboard')),
                'lap_dash_reminder_' . $order->get_id()
            );

            echo '<li class="lap-order-item">';
            echo '<div class="lap-order-info">';
            echo '<strong><a href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '">#' . esc_html($order->get_order_number()) . '</a></strong>';
            echo '<div class="lap-order-meta">' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . ' &middot; ' . esc_html($days_old) . 'd open</div>';
            echo '</div>';
            echo '<div class="lap-order-right">';
            echo '<div class="lap-order-amount">' . wp_kses_post($order->get_formatted_order_total()) . '</div>';
            echo '<div class="lap-quick-actions">';
            echo '<a href="' . esc_url($reminder_url) . '" class="lap-qa-btn reminder lap-tooltip" data-tooltip="Herinnering sturen" onclick="return confirm(\'Herinnering sturen voor #' . esc_attr($order->get_order_number()) . '?\');">' . self::get_svg_icon('send') . '</a>';
            echo '<a href="#" class="lap-qa-btn copy lap-tooltip" data-tooltip="Betaallink kopiëren" onclick="return lapCopyPayUrl(\'' . esc_attr($pay_url) . '\', this);">' . self::get_svg_icon('copy') . '</a>';
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';

        echo '<a href="' . esc_url(admin_url('edit.php?post_status=wc-betaling-verzocht&post_type=shop_order')) . '" class="lap-view-all">Alle betalingen bekijken &rarr;</a>';
    }

    private static function render_ready_to_ship()
    {
        // Check dummy data mode
        if (get_option('lap_dashboard_dummy_data')) {
            echo '<ul class="lap-order-list">';
            $dummy_orders = array(
                array('number' => '1232', 'name' => 'Daan Mulder', 'days' => '1', 'total' => '198,00'),
                array('number' => '1231', 'name' => 'Emma Brouwer', 'days' => '1', 'total' => '167,50'),
                array('number' => '1229', 'name' => 'Max Scholten', 'days' => '2', 'total' => '223,95'),
                array('number' => '1227', 'name' => 'Julia Hendriks', 'days' => '3', 'total' => '145,00'),
                array('number' => '1226', 'name' => 'Luuk Vermeulen', 'days' => '4', 'total' => '189,50'),
            );

            foreach ($dummy_orders as $order) {
                echo '<li class="lap-order-item">';
                echo '<div class="lap-order-info">';
                echo '<strong>#' . esc_html($order['number']) . '</strong>';
                $dagen_text = $order['days'] == '1' ? 'dag' : 'dagen';
                echo '<div class="lap-order-meta">' . esc_html($order['name']) . ' &middot; ' . esc_html($order['days']) . ' ' . $dagen_text . ' geleden betaald</div>';
                echo '</div>';
                echo '<div class="lap-order-right">';
                echo '<div class="lap-order-amount">&euro; ' . esc_html($order['total']) . '</div>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
            return;
        }

        $orders = wc_get_orders(array(
            'status' => 'processing',
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (empty($orders)) {
            echo '<div class="lap-empty">';
            echo '<div class="lap-empty-icon">' . self::get_svg_icon('package') . '</div>';
            echo '<p>Geen orders gereed voor verzending</p>';
            echo '</div>';
            return;
        }

        echo '<ul class="lap-order-list">';
        foreach ($orders as $order) {
            $days_since_paid = 0;
            if ($order->get_date_paid()) {
                $days_since_paid = round((time() - $order->get_date_paid()->getTimestamp()) / DAY_IN_SECONDS, 1);
            }

            echo '<li class="lap-order-item">';
            echo '<div class="lap-order-info">';
            echo '<strong><a href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '">#' . esc_html($order->get_order_number()) . '</a></strong>';
            echo '<div class="lap-order-meta">' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            if ($days_since_paid > 0) {
                echo ' &middot; ' . esc_html($days_since_paid) . ' dag' . ($days_since_paid != 1 ? 'en' : '') . ' geleden betaald';
            }
            echo '</div>';
            echo '</div>';
            echo '<div class="lap-order-right">';
            echo '<div class="lap-order-amount">' . wp_kses_post($order->get_formatted_order_total()) . '</div>';
            echo '<div class="lap-quick-actions">';
            $ship_url = wp_nonce_url(
                add_query_arg(array(
                    'lap_dash_action' => 'ship',
                    'order_id' => $order->get_id(),
                ), admin_url('admin.php?page=lap-dashboard')),
                'lap_dash_ship_' . $order->get_id()
            );
            echo '<a href="' . esc_url($ship_url) . '" class="lap-qa-btn ship lap-tooltip" data-tooltip="Markeer als onderweg" style="color:#16a34a;">' . self::get_svg_icon('package') . '</a>';
            echo '</div>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';

        echo '<a href="' . esc_url(admin_url('edit.php?post_status=wc-processing&post_type=shop_order')) . '" class="lap-view-all">Alle orders bekijken &rarr;</a>';
    }

    private static function render_on_the_way()
    {
        if (get_option('lap_dashboard_dummy_data')) {
            echo '<ul class="lap-order-list">';
            $dummy_orders = array(
                array('number' => '1235', 'name' => 'Sophie Bakker', 'days' => '1', 'total' => '178,50'),
                array('number' => '1233', 'name' => 'Lars Jansen', 'days' => '2', 'total' => '234,00'),
            );

            foreach ($dummy_orders as $order) {
                echo '<li class="lap-order-item">';
                echo '<div class="lap-order-info">';
                echo '<strong>#' . esc_html($order['number']) . '</strong>';
                $dagen_text = $order['days'] == '1' ? 'dag' : 'dagen';
                echo '<div class="lap-order-meta">' . esc_html($order['name']) . ' &middot; ' . esc_html($order['days']) . ' ' . $dagen_text . ' geleden verzonden</div>';
                echo '</div>';
                echo '<div class="lap-order-right">';
                echo '<div class="lap-order-amount">&euro; ' . esc_html($order['total']) . '</div>';
                echo '</div>';
                echo '</li>';
            }
            echo '</ul>';
            return;
        }

        $orders = wc_get_orders(array(
            'status' => 'onderweg',
            'limit' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        if (empty($orders)) {
            echo '<div class="lap-empty">';
            echo '<div class="lap-empty-icon">' . self::get_svg_icon('truck') . '</div>';
            echo '<p>Geen orders onderweg</p>';
            echo '</div>';
            return;
        }

        echo '<ul class="lap-order-list">';
        foreach ($orders as $order) {
            $tracking = $order->get_meta('_lap_track_and_trace');
            $days_ago = 0;
            $modified = $order->get_date_modified();
            if ($modified) {
                $days_ago = round((time() - $modified->getTimestamp()) / DAY_IN_SECONDS, 1);
            }

            echo '<li class="lap-order-item">';
            echo '<div class="lap-order-info">';
            echo '<strong><a href="' . esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')) . '">#' . esc_html($order->get_order_number()) . '</a></strong>';
            echo '<div class="lap-order-meta">' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            if ($days_ago > 0) {
                echo ' &middot; ' . esc_html($days_ago) . ' dag' . ($days_ago != 1 ? 'en' : '') . ' onderweg';
            }
            if ($tracking) {
                if (strpos($tracking, 'http://') === 0 || strpos($tracking, 'https://') === 0) {
                    echo ' &middot; <a href="' . esc_url($tracking) . '" target="_blank" rel="noopener" style="color:#16a34a;">&#128279; Volgen</a>';
                } else {
                    echo ' &middot; T&T: ' . esc_html($tracking);
                }
            }
            echo '</div>';
            echo '</div>';
            echo '<div class="lap-order-right">';
            echo '<div class="lap-order-amount">' . wp_kses_post($order->get_formatted_order_total()) . '</div>';
            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';

        echo '<a href="' . esc_url(admin_url('edit.php?post_status=wc-onderweg&post_type=shop_order')) . '" class="lap-view-all">Alle onderweg orders bekijken &rarr;</a>';
    }

    // ═══════════════════════════════════════════
    // Revenue chart
    // ═══════════════════════════════════════════

    private static function render_revenue_chart($stats)
    {
        $period = isset($stats['period']) ? $stats['period'] : 'month';
        $period_label = isset($stats['period_label']) ? $stats['period_label'] : 'maand';

        // Check dummy data mode
        if (get_option('lap_dashboard_dummy_data')) {
            $bars_data = self::get_dummy_revenue_bars($period);
        } else {
            // Transient caching voor chart data
            $cache_key = 'lap_revenue_bars_' . $period;
            $bars_data = get_transient($cache_key);
            if ($bars_data === false) {
                $bars_data = self::get_revenue_bars($period);
                set_transient($cache_key, $bars_data, 5 * MINUTE_IN_SECONDS);
            }
        }

        // Vind maximum voor schaling
        $max = max(array_column($bars_data, 'revenue'));
        $max = $max > 0 ? $max : 1;

        echo '<div class="lap-chart-bars">';
        foreach ($bars_data as $bar) {
            $height_percentage = ($bar['revenue'] / $max) * 100;
            $height_pixels = min(max(($height_percentage / 100) * 140, 4), 140);
            echo '<div class="lap-chart-bar-wrap">';
            echo '<div class="lap-chart-bar revenue lap-tooltip" data-tooltip="' . esc_attr(wp_strip_all_tags($bar['formatted'])) . '" style="height:' . esc_attr(round($height_pixels)) . 'px;"></div>';
            echo '<div class="lap-chart-label">' . esc_html($bar['label']) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Totaal omzet
        $total = array_sum(array_column($bars_data, 'revenue'));
        echo '<div class="lap-chart-total">';
        echo '<div class="lap-chart-total-label">Totale omzet (' . esc_html($period_label) . ')</div>';
        echo '<div class="lap-chart-total-value">' . wp_kses_post(wc_price($total)) . '</div>';

        // Vergelijking met vorige periode
        if (isset($stats['revenue_comparison']) && ($stats['revenue_comparison']['current'] > 0 || $stats['revenue_comparison']['previous'] > 0)) {
            $diff = $stats['revenue_comparison']['diff_percentage'];
            $arrow = $diff >= 0 ? '&#8593;' : '&#8595;';
            $class = $diff >= 0 ? 'up' : 'down';
            $comp_label = isset($stats['revenue_comparison']['label']) ? $stats['revenue_comparison']['label'] : 'vs vorige periode';
            echo '<div class="lap-chart-comparison lap-comp ' . esc_attr($class) . '">';
            echo $arrow . ' ' . esc_html(abs(round($diff, 1))) . '% ' . esc_html($comp_label);
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Genereer omzet bars data op basis van periode
     */
    private static function get_revenue_bars($period)
    {
        $bars_data = array();

        if ($period === 'week') {
            for ($i = 6; $i >= 0; $i--) {
                $date = strtotime("-{$i} days");
                $date_end = strtotime("-" . ($i - 1) . " days");
                $label = wp_date('D', $date);

                $orders = wc_get_orders(array(
                    'status' => array('processing', 'completed'),
                    'date_created' => wp_date('Y-m-d', $date) . '...' . wp_date('Y-m-d', $date_end),
                    'limit' => -1,
                    'return' => 'objects',
                    'meta_key' => '_lap_managed_order',
                    'meta_compare' => 'EXISTS',
                ));

                $revenue = 0;
                foreach ($orders as $order) {
                    $revenue += $order->get_total();
                }

                $bars_data[] = array(
                    'label' => $label,
                    'revenue' => $revenue,
                    'formatted' => wc_price($revenue),
                );
            }
        } elseif ($period === 'year') {
            $current_month = intval(wp_date('n'));
            for ($m = 1; $m <= $current_month; $m++) {
                $month_start = strtotime(wp_date('Y') . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-01');
                $month_end = strtotime('+1 month', $month_start);
                $label = wp_date('M', $month_start);

                $orders = wc_get_orders(array(
                    'status' => array('processing', 'completed'),
                    'date_created' => wp_date('Y-m-d', $month_start) . '...' . wp_date('Y-m-d', $month_end),
                    'limit' => -1,
                    'return' => 'objects',
                    'meta_key' => '_lap_managed_order',
                    'meta_compare' => 'EXISTS',
                ));

                $revenue = 0;
                foreach ($orders as $order) {
                    $revenue += $order->get_total();
                }

                $bars_data[] = array(
                    'label' => $label,
                    'revenue' => $revenue,
                    'formatted' => wc_price($revenue),
                );
            }
        } else {
            for ($i = 3; $i >= 0; $i--) {
                $week_start = strtotime("-" . ($i * 7) . " days");
                $week_end = strtotime("-" . (($i - 1) * 7) . " days");
                $label = wp_date('d/m', $week_start);

                $orders = wc_get_orders(array(
                    'status' => array('processing', 'completed'),
                    'date_created' => wp_date('Y-m-d', $week_start) . '...' . wp_date('Y-m-d', $week_end),
                    'limit' => -1,
                    'return' => 'objects',
                    'meta_key' => '_lap_managed_order',
                    'meta_compare' => 'EXISTS',
                ));

                $revenue = 0;
                foreach ($orders as $order) {
                    $revenue += $order->get_total();
                }

                $bars_data[] = array(
                    'label' => $label,
                    'revenue' => $revenue,
                    'formatted' => wc_price($revenue),
                );
            }
        }

        return $bars_data;
    }

    /**
     * Genereer dummy omzet bars data
     */
    private static function get_dummy_revenue_bars($period)
    {
        $bars_data = array();

        if ($period === 'week') {
            $day_names = array('Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo');
            for ($i = 6; $i >= 0; $i--) {
                $date = strtotime("-{$i} days");
                $day_of_week = intval(wp_date('N', $date)) - 1;
                $is_weekend = ($day_of_week >= 5);
                $revenue = $is_weekend ? rand(100, 400) : rand(300, 900);

                $bars_data[] = array(
                    'label' => $day_names[$day_of_week],
                    'revenue' => $revenue,
                    'formatted' => wc_price($revenue),
                );
            }
        } elseif ($period === 'year') {
            $months = array('Jan', 'Feb', 'Mrt', 'Apr', 'Mei', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec');
            $current_month = intval(wp_date('n'));
            for ($m = 0; $m < $current_month; $m++) {
                $revenue = rand(2000, 8000) + ($m * rand(100, 300));
                $bars_data[] = array(
                    'label' => $months[$m],
                    'revenue' => $revenue,
                    'formatted' => wc_price($revenue),
                );
            }
        } else {
            for ($i = 3; $i >= 0; $i--) {
                $week_start = strtotime("-" . ($i * 7) . " days");
                $label = wp_date('d/m', $week_start);
                $base_revenue = rand(1200, 2000);
                $growth_factor = (4 - $i) * 0.15;
                $revenue = $base_revenue * (1 + $growth_factor) + rand(-200, 400);

                $bars_data[] = array(
                    'label' => $label,
                    'revenue' => $revenue,
                    'formatted' => wc_price($revenue),
                );
            }
        }

        return $bars_data;
    }

    // ═══════════════════════════════════════════
    // Activity chart (stacked bars)
    // ═══════════════════════════════════════════

    private static function render_activity_chart($stats)
    {
        $period = isset($stats['period']) ? $stats['period'] : 'month';

        // Check dummy data mode
        if (get_option('lap_dashboard_dummy_data')) {
            $bars_data = self::get_dummy_activity_bars($period);
        } else {
            $cache_key = 'lap_activity_bars_' . $period;
            $bars_data = get_transient($cache_key);
            if ($bars_data === false) {
                $bars_data = self::get_activity_bars($period);
                set_transient($cache_key, $bars_data, 5 * MINUTE_IN_SECONDS);
            }
        }

        // Vind maximum voor schaling (totaal per bar)
        $max_total = 1;
        foreach ($bars_data as $bar) {
            $total = (isset($bar['paid']) ? $bar['paid'] : 0) + (isset($bar['cancelled']) ? $bar['cancelled'] : 0) + (isset($bar['pending']) ? $bar['pending'] : 0);
            if ($total > $max_total) $max_total = $total;
        }

        echo '<div class="lap-chart-bars">';
        foreach ($bars_data as $bar) {
            $paid = isset($bar['paid']) ? $bar['paid'] : 0;
            $cancelled = isset($bar['cancelled']) ? $bar['cancelled'] : 0;
            $pending = isset($bar['pending']) ? $bar['pending'] : 0;
            $total = $paid + $cancelled + $pending;

            // Stacked bar heights
            $max_h = 140;
            $paid_h = $total > 0 ? max(($paid / $max_total) * $max_h, ($paid > 0 ? 3 : 0)) : 0;
            $cancelled_h = $total > 0 ? max(($cancelled / $max_total) * $max_h, ($cancelled > 0 ? 3 : 0)) : 0;
            $pending_h = $total > 0 ? max(($pending / $max_total) * $max_h, ($pending > 0 ? 3 : 0)) : 0;

            $tooltip = esc_attr($bar['label'] . ': ' . $paid . ' betaald, ' . $cancelled . ' geannuleerd, ' . $pending . ' open');

            echo '<div class="lap-chart-bar-wrap">';
            echo '<div style="display:flex;flex-direction:column-reverse;align-items:stretch;height:140px;justify-content:flex-start;width:100%;" class="lap-tooltip" data-tooltip="' . $tooltip . '">';
            if ($paid > 0) {
                echo '<div style="height:' . esc_attr(round($paid_h)) . 'px;background:linear-gradient(to top,#16a34a,#4ade80);border-radius:0 0 4px 4px;min-height:3px;"></div>';
            }
            if ($cancelled > 0) {
                echo '<div style="height:' . esc_attr(round($cancelled_h)) . 'px;background:linear-gradient(to top,#dc2626,#f87171);min-height:3px;"></div>';
            }
            if ($pending > 0) {
                echo '<div style="height:' . esc_attr(round($pending_h)) . 'px;background:linear-gradient(to top,var(--lap-primary),#818cf8);border-radius:4px 4px 0 0;min-height:3px;"></div>';
            }
            if ($total === 0) {
                echo '<div style="height:4px;background:var(--lap-gray-200);border-radius:4px;"></div>';
            }
            echo '</div>';
            echo '<div class="lap-chart-label">' . esc_html($bar['label']) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Legenda
        echo '<div style="display:flex;gap:16px;justify-content:center;margin-top:12px;font-size:11px;color:var(--lap-gray-500);">';
        echo '<span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#4ade80;margin-right:4px;"></span>Betaald</span>';
        echo '<span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#f87171;margin-right:4px;"></span>Geannuleerd</span>';
        echo '<span><span style="display:inline-block;width:8px;height:8px;border-radius:2px;background:#818cf8;margin-right:4px;"></span>Open</span>';
        echo '</div>';
    }

    /**
     * Genereer activiteit bars data (stacked: paid/cancelled/pending)
     */
    private static function get_activity_bars($period)
    {
        $bars_data = array();

        if ($period === 'week') {
            for ($i = 6; $i >= 0; $i--) {
                $date = wp_date('Y-m-d', strtotime("-{$i} days"));
                $date_end = wp_date('Y-m-d', strtotime("-" . ($i - 1) . " days"));
                $day_name = wp_date('D', strtotime($date));

                $paid = count(wc_get_orders(array(
                    'status' => array('processing', 'completed'),
                    'date_created' => $date . '...' . $date_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));
                $cancelled = count(wc_get_orders(array(
                    'status' => 'cancelled',
                    'date_created' => $date . '...' . $date_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));
                $pending = count(wc_get_orders(array(
                    'status' => array('ter-goedkeuring', 'betaling-verzocht'),
                    'date_created' => $date . '...' . $date_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));

                $bars_data[] = array(
                    'label' => $day_name,
                    'paid' => $paid,
                    'cancelled' => $cancelled,
                    'pending' => $pending,
                );
            }
        } elseif ($period === 'year') {
            $current_month = intval(wp_date('n'));
            for ($m = 1; $m <= $current_month; $m++) {
                $month_start = wp_date('Y') . '-' . str_pad($m, 2, '0', STR_PAD_LEFT) . '-01';
                $month_end = wp_date('Y-m-d', strtotime('+1 month', strtotime($month_start)));
                $label = wp_date('M', strtotime($month_start));

                $paid = count(wc_get_orders(array(
                    'status' => array('processing', 'completed'),
                    'date_created' => $month_start . '...' . $month_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));
                $cancelled = count(wc_get_orders(array(
                    'status' => 'cancelled',
                    'date_created' => $month_start . '...' . $month_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));
                $pending = count(wc_get_orders(array(
                    'status' => array('ter-goedkeuring', 'betaling-verzocht'),
                    'date_created' => $month_start . '...' . $month_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));

                $bars_data[] = array(
                    'label' => $label,
                    'paid' => $paid,
                    'cancelled' => $cancelled,
                    'pending' => $pending,
                );
            }
        } else {
            for ($i = 3; $i >= 0; $i--) {
                $week_start = wp_date('Y-m-d', strtotime("-" . ($i * 7) . " days"));
                $week_end = wp_date('Y-m-d', strtotime("-" . (($i - 1) * 7) . " days"));
                $label = wp_date('d/m', strtotime($week_start));

                $paid = count(wc_get_orders(array(
                    'status' => array('processing', 'completed'),
                    'date_created' => $week_start . '...' . $week_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));
                $cancelled = count(wc_get_orders(array(
                    'status' => 'cancelled',
                    'date_created' => $week_start . '...' . $week_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));
                $pending = count(wc_get_orders(array(
                    'status' => array('ter-goedkeuring', 'betaling-verzocht'),
                    'date_created' => $week_start . '...' . $week_end,
                    'limit' => -1,
                    'return' => 'ids',
                )));

                $bars_data[] = array(
                    'label' => $label,
                    'paid' => $paid,
                    'cancelled' => $cancelled,
                    'pending' => $pending,
                );
            }
        }

        return $bars_data;
    }

    /**
     * Genereer dummy activiteit bars data (stacked)
     */
    private static function get_dummy_activity_bars($period)
    {
        $bars_data = array();

        if ($period === 'week') {
            $day_names = array('Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo');
            for ($i = 6; $i >= 0; $i--) {
                $date = strtotime("-{$i} days");
                $day_of_week = intval(wp_date('N', $date)) - 1;
                $is_weekend = ($day_of_week >= 5);

                $bars_data[] = array(
                    'label' => $day_names[$day_of_week],
                    'paid' => $is_weekend ? rand(1, 3) : rand(3, 8),
                    'cancelled' => rand(0, 2),
                    'pending' => $is_weekend ? rand(0, 2) : rand(1, 4),
                );
            }
        } elseif ($period === 'year') {
            $months = array('Jan', 'Feb', 'Mrt', 'Apr', 'Mei', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dec');
            $current_month = intval(wp_date('n'));
            for ($m = 0; $m < $current_month; $m++) {
                $bars_data[] = array(
                    'label' => $months[$m],
                    'paid' => rand(12, 40) + ($m * rand(1, 3)),
                    'cancelled' => rand(2, 8),
                    'pending' => rand(3, 10),
                );
            }
        } else {
            for ($i = 3; $i >= 0; $i--) {
                $week_start = strtotime("-" . ($i * 7) . " days");
                $label = wp_date('d/m', $week_start);
                $bars_data[] = array(
                    'label' => $label,
                    'paid' => rand(6, 18),
                    'cancelled' => rand(1, 5),
                    'pending' => rand(2, 8),
                );
            }
        }

        return $bars_data;
    }

    // ═══════════════════════════════════════════
    // Status donut chart (pure CSS/SVG)
    // ═══════════════════════════════════════════

    private static function render_status_donut($stats)
    {
        $segments = array(
            array(
                'label' => 'Ter goedkeuring',
                'count' => $stats['awaiting_approval'],
                'color' => '#dc2626',
            ),
            array(
                'label' => 'Betaling verzocht',
                'count' => $stats['payment_requested'],
                'color' => '#d97706',
            ),
            array(
                'label' => 'Verzendklaar',
                'count' => $stats['ready_to_ship'],
                'color' => '#16a34a',
            ),
        );

        $total = 0;
        foreach ($segments as $s) {
            $total += $s['count'];
        }

        if ($total === 0) {
            echo '<div class="lap-empty"><p>Geen actieve orders</p></div>';
            return;
        }

        // SVG donut via stroke-dasharray/dashoffset
        $size = 120;
        $stroke = 20;
        $radius = ($size - $stroke) / 2;
        $circumference = 2 * M_PI * $radius;

        echo '<div class="lap-donut-wrap">';
        echo '<svg class="lap-donut-svg" width="' . esc_attr($size) . '" height="' . esc_attr($size) . '" viewBox="0 0 ' . esc_attr($size) . ' ' . esc_attr($size) . '">';

        // Background circle
        echo '<circle cx="' . esc_attr($size / 2) . '" cy="' . esc_attr($size / 2) . '" r="' . esc_attr($radius) . '" fill="none" stroke="#f3f4f6" stroke-width="' . esc_attr($stroke) . '"/>';

        $offset = 0;
        foreach ($segments as $seg) {
            if ($seg['count'] <= 0) continue;
            $pct = $seg['count'] / $total;
            $dash = $pct * $circumference;
            $gap = $circumference - $dash;
            $rotation = ($offset * 360) - 90; // -90 to start at top

            echo '<circle cx="' . esc_attr($size / 2) . '" cy="' . esc_attr($size / 2) . '" r="' . esc_attr($radius) . '" fill="none" stroke="' . esc_attr($seg['color']) . '" stroke-width="' . esc_attr($stroke) . '" stroke-dasharray="' . esc_attr(round($dash, 2)) . ' ' . esc_attr(round($gap, 2)) . '" stroke-dashoffset="' . esc_attr(round(-$offset * $circumference, 2)) . '" transform="rotate(-90 ' . esc_attr($size / 2) . ' ' . esc_attr($size / 2) . ')" style="transition:stroke-dasharray .4s ease;"/>';

            $offset += $pct;
        }

        // Center text
        echo '<text x="' . esc_attr($size / 2) . '" y="' . esc_attr($size / 2 - 4) . '" text-anchor="middle" font-size="22" font-weight="700" fill="#1f2937">' . esc_html($total) . '</text>';
        echo '<text x="' . esc_attr($size / 2) . '" y="' . esc_attr($size / 2 + 12) . '" text-anchor="middle" font-size="10" fill="#9ca3af">actief</text>';

        echo '</svg>';

        // Legend
        echo '<div class="lap-donut-legend">';
        foreach ($segments as $seg) {
            $pct = $total > 0 ? round(($seg['count'] / $total) * 100) : 0;
            echo '<div class="lap-donut-item">';
            echo '<span class="lap-donut-dot" style="background:' . esc_attr($seg['color']) . ';"></span>';
            echo '<span class="lap-donut-item-label">' . esc_html($seg['label']) . '</span>';
            echo '<span class="lap-donut-item-value">' . esc_html($seg['count']) . ' <span style="color:var(--lap-gray-400);font-weight:400;">(' . esc_html($pct) . '%)</span></span>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    // ═══════════════════════════════════════════
    // Settings status
    // ═══════════════════════════════════════════

    private static function render_settings_status()
    {
        $configs = array(
            array('label' => 'Testmodus', 'key' => 'lap_test_mode', 'invert' => true),
            array('label' => 'Herinneringen', 'key' => 'lap_reminder_enabled', 'invert' => false),
            array('label' => 'Auto-annuleren', 'key' => 'lap_auto_cancel_enabled', 'invert' => false),
            array('label' => 'Debug logging', 'key' => 'lap_debug_log', 'invert' => true),
        );

        foreach ($configs as $config) {
            $enabled = (bool) get_option($config['key'], 0);
            $dot_class = $enabled ? 'on' : 'off';
            $status_text = $enabled ? 'Aan' : 'Uit';

            echo '<div class="lap-config-item">';
            echo '<span>' . esc_html($config['label']) . '</span>';
            echo '<span class="lap-config-status"><span class="lap-config-dot ' . esc_attr($dot_class) . '"></span>' . esc_html($status_text) . '</span>';
            echo '</div>';
        }

        echo '<div style="margin-top:12px;">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=lap-settings')) . '" class="lap-view-all" style="text-align:left;">Instellingen wijzigen &rarr;</a>';
        echo '</div>';
    }

    // ═══════════════════════════════════════════
    // Extra stats
    // ═══════════════════════════════════════════

    private static function render_extra_stats($stats)
    {
        echo '<ul class="lap-stat-list">';

        $period_label = isset($stats['period_label']) ? $stats['period_label'] : 'maand';

        // Totaal afgehandeld
        if (isset($stats['total_handled'])) {
            echo '<li class="lap-stat-item">';
            echo '<span class="lap-stat-item-label">Afgehandeld (' . esc_html($period_label) . ')</span>';
            echo '<span class="lap-stat-item-value">';
            self::render_comparison($stats, 'total_handled', 'total_handled_prev', false);
            echo '<span class="lap-badge success">' . esc_html($stats['total_handled']) . '</span>';
            echo '</span></li>';
        }

        // Totaal geannuleerd
        if (isset($stats['total_cancelled'])) {
            echo '<li class="lap-stat-item">';
            echo '<span class="lap-stat-item-label">Geannuleerd (' . esc_html($period_label) . ')</span>';
            echo '<span class="lap-stat-item-value">';
            self::render_comparison($stats, 'total_cancelled', 'total_cancelled_prev', true);
            echo '<span class="lap-badge ' . ($stats['total_cancelled'] > 0 ? 'danger' : 'success') . '">' . esc_html($stats['total_cancelled']) . '</span>';
            echo '</span></li>';
        }

        // Goedkeuringsratio
        if (isset($stats['approval_ratio']) && $stats['approval_ratio'] >= 0) {
            echo '<li class="lap-stat-item">';
            echo '<span class="lap-stat-item-label">Goedkeuringsratio</span>';
            echo '<span class="lap-stat-item-value">';
            self::render_comparison($stats, 'approval_ratio', 'approval_ratio_prev', false);
            echo '<span class="lap-badge ' . ($stats['approval_ratio'] >= 80 ? 'success' : 'warning') . '">' . esc_html(round($stats['approval_ratio'])) . '%</span>';
            echo '</span></li>';
        }

        // Gem. orderwaarde
        if (isset($stats['avg_order_value']) && $stats['avg_order_value'] > 0) {
            echo '<li class="lap-stat-item">';
            echo '<span class="lap-stat-item-label">Gem. orderwaarde</span>';
            echo '<span class="lap-stat-item-value">';
            self::render_comparison($stats, 'avg_order_value', 'avg_order_value_prev', false);
            echo '<span class="lap-badge success">' . wp_kses_post(wc_price($stats['avg_order_value'])) . '</span>';
            echo '</span></li>';
        }

        // Verloren omzet
        if (isset($stats['lost_orders_value']) && $stats['lost_orders_value'] > 0) {
            echo '<li class="lap-stat-item">';
            echo '<span class="lap-stat-item-label">Verloren omzet</span>';
            echo '<span class="lap-stat-item-value">';
            self::render_comparison($stats, 'lost_orders_value', 'lost_orders_value_prev', true);
            echo '<span class="lap-badge danger">' . wp_kses_post(wc_price($stats['lost_orders_value'])) . '</span>';
            echo '</span></li>';
        }

        // Gem. betaaltijd
        if (isset($stats['avg_payment_time_hours']) && $stats['avg_payment_time_hours'] > 0) {
            $hours = $stats['avg_payment_time_hours'];
            $display = $hours < 48 ? round($hours, 1) . 'u' : round($hours / 24, 1) . 'd';
            echo '<li class="lap-stat-item">';
            echo '<span class="lap-stat-item-label">Gem. betaaltijd</span>';
            echo '<span class="lap-stat-item-value"><span class="lap-badge success">' . esc_html($display) . '</span></span>';
            echo '</li>';
        }

        // Snelste goedkeuring
        if (isset($stats['fastest_approval_hours']) && $stats['fastest_approval_hours'] > 0) {
            $hours = $stats['fastest_approval_hours'];
            $display = $hours < 2 ? round($hours * 60) . ' min' : round($hours, 1) . 'u';
            echo '<li class="lap-stat-item">';
            echo '<span class="lap-stat-item-label">Snelste goedkeuring</span>';
            echo '<span class="lap-stat-item-value"><span class="lap-badge success">' . esc_html($display) . '</span></span>';
            echo '</li>';
        }

        echo '</ul>';
    }

    /**
     * Render vergelijkingsindicator (pijl omhoog/omlaag)
     *
     * @param array $stats Stats array
     * @param string $current_key Key voor huidige waarde
     * @param string $prev_key Key voor vorige waarde
     * @param bool $inverse True als lager = beter (bijv. geannuleerd, verloren omzet)
     */
    private static function render_comparison($stats, $current_key, $prev_key, $inverse = false)
    {
        if (!isset($stats[$prev_key]) || $stats[$prev_key] <= 0) return;

        $current = $stats[$current_key];
        $previous = $stats[$prev_key];
        $diff_pct = (($current - $previous) / $previous) * 100;

        if (abs($diff_pct) < 1) return;

        $is_up = $diff_pct >= 0;
        $arrow = $is_up ? '&#8593;' : '&#8595;';

        if ($inverse) {
            $class = $is_up ? 'up-bad' : 'down-good';
        } else {
            $class = $is_up ? 'up' : 'down';
        }

        echo '<span class="lap-comp ' . esc_attr($class) . '">' . $arrow . ' ' . esc_html(abs(round($diff_pct, 1))) . '%</span> ';
    }

    // ═══════════════════════════════════════════
    // Statistics (data layer)
    // ═══════════════════════════════════════════

    private static function get_dummy_statistics($period = 'month')
    {
        $period_labels = array(
            'week' => 'week',
            'month' => 'maand',
            'year' => 'jaar'
        );
        $period_label = isset($period_labels[$period]) ? $period_labels[$period] : 'maand';

        $stats = array(
            'awaiting_approval' => rand(3, 12),
            'payment_requested' => rand(5, 18),
            'avg_payment_age' => rand(2, 8) + (rand(0, 9) / 10),
            'conversion_rate' => rand(65, 95),
            'paid_count' => rand(15, 35),
            'total_7d' => rand(20, 40),
            'avg_approval_hours' => rand(2, 48),
            'expected_revenue' => rand(800, 3500),
            'expected_revenue_formatted' => wc_price(rand(800, 3500)),
            'ready_to_ship' => rand(2, 15),
            'on_the_way' => rand(1, 8),
            'overdue_count' => rand(0, 5),
            'overdue_percentage' => rand(5, 25),
            'overdue_days' => get_option('lap_auto_cancel_days', 7),
            'period' => $period,
            'period_label' => $period_label,
            'period_days' => $period === 'week' ? 7 : ($period === 'year' ? 365 : 30),
            'total_handled' => rand(45, 85),
            'total_cancelled' => rand(2, 12),
            'approval_ratio' => rand(82, 98),
            'avg_order_value' => rand(120, 250),
            'lost_orders_value' => rand(150, 800),
            'avg_payment_time_hours' => rand(12, 72),
            'fastest_approval_hours' => rand(1, 5) / 10,
            'total_handled_prev' => rand(40, 75),
            'total_cancelled_prev' => rand(3, 15),
            'approval_ratio_prev' => rand(78, 95),
            'avg_order_value_prev' => rand(110, 240),
            'lost_orders_value_prev' => rand(200, 900),
            'revenue_comparison' => array(
                'current' => rand(5000, 12000),
                'previous' => rand(4500, 11000),
                'diff_percentage' => rand(-20, 35),
                'label' => $period === 'week' ? 'vs vorige week' : ($period === 'year' ? 'vs vorig jaar' : 'vs vorige maand'),
            ),
        );

        $stats['expected_revenue_formatted'] = wc_price($stats['expected_revenue']);

        return $stats;
    }

    private static function get_statistics($period = 'month')
    {
        // Check of dummy data mode actief is
        if (get_option('lap_dashboard_dummy_data')) {
            return self::get_dummy_statistics($period);
        }

        // Transient caching
        $cache_key = 'lap_stats_' . $period;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Bepaal datum ranges op basis van periode
        $now = time();
        switch ($period) {
            case 'week':
                $current_start = strtotime('this week monday', $now);
                $current_end = $now;
                $previous_start = strtotime('-1 week', $current_start);
                $previous_end = strtotime('-1 second', $current_start);
                $period_days = 7;
                $period_label = 'week';
                break;
            case 'year':
                $current_start = strtotime('first day of january this year', $now);
                $current_end = $now;
                $previous_start = strtotime('-1 year', $current_start);
                $previous_end = strtotime('-1 second', $current_start);
                $period_days = 365;
                $period_label = 'jaar';
                break;
            case 'month':
            default:
                $current_start = strtotime('-30 days', $now);
                $current_end = $now;
                $previous_start = strtotime('-60 days', $now);
                $previous_end = strtotime('-30 days', $now);
                $period_days = 30;
                $period_label = 'maand';
                break;
        }

        $stats = array(
            'awaiting_approval' => 0,
            'payment_requested' => 0,
            'avg_payment_age' => 0,
            'conversion_rate' => 0,
            'paid_count' => 0,
            'total_7d' => 0,
            'avg_approval_hours' => 0,
            'expected_revenue' => 0,
            'expected_revenue_formatted' => wc_price(0),
            'ready_to_ship' => 0,
            'on_the_way' => 0,
            'revenue_comparison' => array(),
            'overdue_count' => 0,
            'overdue_percentage' => 0,
            'overdue_days' => 7,
            'avg_payment_time_hours' => 0,
            'avg_order_value' => 0,
            'lost_orders_count' => 0,
            'lost_orders_value' => 0,
            'approval_ratio' => 0,
            'total_handled' => 0,
            'total_cancelled' => 0,
            'fastest_approval_hours' => 0,
            'period' => $period,
            'period_label' => $period_label,
            'period_days' => $period_days,
        );

        // Ter goedkeuring
        $awaiting = wc_get_orders(array(
            'status' => 'ter-goedkeuring',
            'limit' => -1,
            'return' => 'objects',
        ));
        $stats['awaiting_approval'] = count($awaiting);

        $expected_revenue = 0;
        foreach ($awaiting as $order) {
            $expected_revenue += $order->get_total();
        }

        // Betaling verzocht
        $payment_requested = wc_get_orders(array(
            'status' => 'betaling-verzocht',
            'limit' => -1,
            'return' => 'objects',
        ));
        $stats['payment_requested'] = count($payment_requested);

        foreach ($payment_requested as $order) {
            $expected_revenue += $order->get_total();
        }

        $stats['expected_revenue'] = $expected_revenue;
        $stats['expected_revenue_formatted'] = wc_price($expected_revenue);

        // Gereed voor verzending
        $ready_orders = wc_get_orders(array(
            'status' => 'processing',
            'limit' => -1,
            'return' => 'ids',
        ));
        $stats['ready_to_ship'] = count($ready_orders);

        // Onderweg
        $on_the_way = wc_get_orders(array(
            'status' => 'onderweg',
            'limit' => -1,
            'return' => 'ids',
        ));
        $stats['on_the_way'] = count($on_the_way);

        // Gemiddelde leeftijd betaling verzocht
        if (!empty($payment_requested)) {
            $total_age = 0;
            $now_ts = time();
            foreach ($payment_requested as $order) {
                $created = $order->get_date_created();
                if ($created) {
                    $total_age += ($now_ts - $created->getTimestamp()) / DAY_IN_SECONDS;
                }
            }
            $stats['avg_payment_age'] = $total_age / count($payment_requested);
        }

        // Conversie huidige periode
        $recent_orders = wc_get_orders(array(
            'limit' => -1,
            'date_created' => '>=' . $current_start,
            'return' => 'objects',
        ));

        $stats['total_7d'] = count($recent_orders);
        $paid = 0;
        foreach ($recent_orders as $order) {
            if ($order->is_paid() || in_array($order->get_status(), array('processing', 'completed'), true)) {
                $paid++;
            }
        }
        $stats['paid_count'] = $paid;
        $stats['conversion_rate'] = $stats['total_7d'] > 0 ? round(($paid / $stats['total_7d']) * 100) : 0;

        // Gemiddelde goedkeuringstijd
        $approved_orders = wc_get_orders(array(
            'limit' => -1,
            'date_created' => '>=' . $current_start,
            'status' => array('betaling-verzocht', 'processing', 'completed'),
            'return' => 'objects',
        ));

        if (!empty($approved_orders)) {
            $total_hours = 0;
            $count = 0;
            foreach ($approved_orders as $order) {
                $notes = wc_get_order_notes(array(
                    'order_id' => $order->get_id(),
                    'type' => 'internal',
                ));

                $approved_time = null;
                foreach ($notes as $note) {
                    if (strpos($note->content, 'betaling-verzocht') !== false || strpos($note->content, 'goedgekeurd') !== false) {
                        $approved_time = strtotime($note->date_created);
                        break;
                    }
                }

                if ($approved_time) {
                    $created = $order->get_date_created();
                    if ($created) {
                        $hours = ($approved_time - $created->getTimestamp()) / 3600;
                        if ($hours > 0 && $hours < 720) {
                            $total_hours += $hours;
                            $count++;
                        }
                    }
                }
            }

            if ($count > 0) {
                $stats['avg_approval_hours'] = round($total_hours / $count, 1);
            }
        }

        // Omzet vergelijking
        $current_period_orders = wc_get_orders(array(
            'status' => array('processing', 'completed'),
            'date_created' => '>=' . wp_date('Y-m-d', $current_start),
            'limit' => -1,
            'return' => 'objects',
            'meta_key' => '_lap_managed_order',
            'meta_compare' => 'EXISTS',
        ));
        $current_revenue = 0;
        foreach ($current_period_orders as $order) {
            $current_revenue += $order->get_total();
        }

        $prev_period_orders = wc_get_orders(array(
            'status' => array('processing', 'completed'),
            'date_created' => wp_date('Y-m-d', $previous_start) . '...' . wp_date('Y-m-d', $previous_end),
            'limit' => -1,
            'return' => 'objects',
            'meta_key' => '_lap_managed_order',
            'meta_compare' => 'EXISTS',
        ));
        $prev_revenue = 0;
        foreach ($prev_period_orders as $order) {
            $prev_revenue += $order->get_total();
        }

        $diff_percentage = 0;
        if ($prev_revenue > 0) {
            $diff_percentage = (($current_revenue - $prev_revenue) / $prev_revenue) * 100;
        } elseif ($current_revenue > 0) {
            $diff_percentage = 100;
        }

        $comparison_labels = array(
            'week' => 'vs vorige week',
            'month' => 'vs vorige maand',
            'year' => 'vs vorig jaar',
        );

        $stats['revenue_comparison'] = array(
            'current' => $current_revenue,
            'previous' => $prev_revenue,
            'diff_percentage' => $diff_percentage,
            'label' => isset($comparison_labels[$period]) ? $comparison_labels[$period] : 'vs vorige periode',
        );

        // Verlopen orders
        $overdue_threshold_days = get_option('lap_auto_cancel_days', 7);
        $stats['overdue_days'] = $overdue_threshold_days;

        $overdue_date = strtotime("-{$overdue_threshold_days} days", time());

        $overdue_orders = array();
        foreach ($payment_requested as $order) {
            $created = $order->get_date_created();
            if ($created && $created->getTimestamp() < $overdue_date) {
                $overdue_orders[] = $order;
            }
        }

        $stats['overdue_count'] = count($overdue_orders);

        if ($stats['payment_requested'] > 0) {
            $stats['overdue_percentage'] = round(($stats['overdue_count'] / $stats['payment_requested']) * 100);
        }

        // EXTRA STATISTIEKEN

        // Huidige periode: alle managed orders
        $all_orders_current = wc_get_orders(array(
            'limit' => -1,
            'date_created' => '>=' . $current_start,
            'return' => 'objects',
            'meta_key' => '_lap_managed_order',
            'meta_compare' => 'EXISTS',
        ));

        // Vorige periode: alle managed orders
        $all_orders_previous = wc_get_orders(array(
            'limit' => -1,
            'date_created' => wp_date('Y-m-d', $previous_start) . '...' . wp_date('Y-m-d', $previous_end),
            'return' => 'objects',
            'meta_key' => '_lap_managed_order',
            'meta_compare' => 'EXISTS',
        ));

        // Gemiddelde betaaltijd
        $payment_times = array();
        foreach ($all_orders_current as $order) {
            if ($order->is_paid() || in_array($order->get_status(), array('processing', 'completed'), true)) {
                $notes = wc_get_order_notes(array(
                    'order_id' => $order->get_id(),
                    'type' => '',
                ));

                $payment_requested_time = null;
                foreach ($notes as $note) {
                    if (stripos($note->content, 'betaling-verzocht') !== false || stripos($note->content, 'payment requested') !== false) {
                        $payment_requested_time = strtotime($note->date_created);
                        break;
                    }
                }

                if ($payment_requested_time && $order->get_date_paid()) {
                    $paid_time = $order->get_date_paid()->getTimestamp();
                    $diff_hours = ($paid_time - $payment_requested_time) / 3600;
                    if ($diff_hours > 0 && $diff_hours < 720) {
                        $payment_times[] = $diff_hours;
                    }
                }
            }
        }

        if (!empty($payment_times)) {
            $stats['avg_payment_time_hours'] = round(array_sum($payment_times) / count($payment_times), 1);
        }

        // Gemiddelde orderwaarde
        $paid_orders_current = array_filter($all_orders_current, function ($order) {
            return $order->is_paid() || in_array($order->get_status(), array('processing', 'completed'), true);
        });

        if (!empty($paid_orders_current)) {
            $total_value = 0;
            foreach ($paid_orders_current as $order) {
                $total_value += $order->get_total();
            }
            $stats['avg_order_value'] = $total_value / count($paid_orders_current);
        }

        // Verloren orders (geannuleerd)
        $cancelled_orders_current = wc_get_orders(array(
            'status' => 'cancelled',
            'limit' => -1,
            'date_created' => '>=' . $current_start,
            'return' => 'objects',
            'meta_key' => '_lap_managed_order',
            'meta_compare' => 'EXISTS',
        ));

        $stats['lost_orders_count'] = count($cancelled_orders_current);
        $stats['total_cancelled'] = count($cancelled_orders_current);
        $lost_value = 0;
        foreach ($cancelled_orders_current as $order) {
            $lost_value += $order->get_total();
        }
        $stats['lost_orders_value'] = $lost_value;

        // Goedkeuringsratio
        $approved_count = count(array_filter($all_orders_current, function ($order) {
            return in_array($order->get_status(), array('betaling-verzocht', 'processing', 'completed'), true);
        }));

        $total_decisions = $approved_count + count($cancelled_orders_current);
        if ($total_decisions > 0) {
            $stats['approval_ratio'] = ($approved_count / $total_decisions) * 100;
        }

        // Totaal afgehandeld
        $stats['total_handled'] = count($paid_orders_current);

        // Snelste goedkeuring
        $approval_times = array();
        foreach ($all_orders_current as $order) {
            if (in_array($order->get_status(), array('betaling-verzocht', 'processing', 'completed'), true)) {
                $notes = wc_get_order_notes(array(
                    'order_id' => $order->get_id(),
                    'type' => 'internal',
                ));

                $approved_time = null;
                foreach ($notes as $note) {
                    if (strpos($note->content, 'betaling-verzocht') !== false || strpos($note->content, 'goedgekeurd') !== false) {
                        $approved_time = strtotime($note->date_created);
                        break;
                    }
                }

                if ($approved_time) {
                    $created = $order->get_date_created();
                    if ($created) {
                        $hours = ($approved_time - $created->getTimestamp()) / 3600;
                        if ($hours > 0 && $hours < 720) {
                            $approval_times[] = $hours;
                        }
                    }
                }
            }
        }

        if (!empty($approval_times)) {
            $stats['fastest_approval_hours'] = min($approval_times);
        }

        // VORIGE PERIODE VERGELIJKINGEN

        $cancelled_orders_previous = wc_get_orders(array(
            'status' => 'cancelled',
            'limit' => -1,
            'date_created' => wp_date('Y-m-d', $previous_start) . '...' . wp_date('Y-m-d', $previous_end),
            'return' => 'objects',
            'meta_key' => '_lap_managed_order',
            'meta_compare' => 'EXISTS',
        ));
        $stats['total_cancelled_prev'] = count($cancelled_orders_previous);

        $paid_orders_previous = array_filter($all_orders_previous, function ($order) {
            return $order->is_paid() || in_array($order->get_status(), array('processing', 'completed'), true);
        });
        $stats['total_handled_prev'] = count($paid_orders_previous);

        $approved_prev_count = count(array_filter($all_orders_previous, function ($order) {
            return in_array($order->get_status(), array('betaling-verzocht', 'processing', 'completed'), true);
        }));
        $total_decisions_prev = $approved_prev_count + count($cancelled_orders_previous);
        $stats['approval_ratio_prev'] = 0;
        if ($total_decisions_prev > 0) {
            $stats['approval_ratio_prev'] = ($approved_prev_count / $total_decisions_prev) * 100;
        }

        $stats['avg_order_value_prev'] = 0;
        if (!empty($paid_orders_previous)) {
            $total_value_prev = 0;
            foreach ($paid_orders_previous as $order) {
                $total_value_prev += $order->get_total();
            }
            $stats['avg_order_value_prev'] = $total_value_prev / count($paid_orders_previous);
        }

        $lost_value_prev = 0;
        foreach ($cancelled_orders_previous as $order) {
            $lost_value_prev += $order->get_total();
        }
        $stats['lost_orders_value_prev'] = $lost_value_prev;

        // Cache resultaat
        set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);

        return $stats;
    }
}

LAP_Dashboard::init();
