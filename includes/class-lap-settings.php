<?php
if ( ! defined('ABSPATH') ) exit;

class LAP_Settings {

    public static function init(){
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_filter('plugin_action_links_' . plugin_basename(LAP_PLUGIN_FILE), [__CLASS__, 'settings_link']);
    }

    // add_menu is nu verwijderd, dashboard class doet dit

    public static function register_settings(){
        register_setting('lap_settings_group', 'lap_test_mode', ['type'=>'boolean', 'default'=>0, 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_test_admin_only', ['type'=>'boolean', 'default'=>1, 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_dashboard_dummy_data', ['type'=>'boolean', 'default'=>0, 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_debug_log', ['type'=>'boolean', 'default'=>0, 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_force_approval_checkout', ['type'=>'boolean', 'default'=>1, 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_force_paid_bump', ['type'=>'boolean', 'default'=>1, 'capability'=>'manage_woocommerce']);

        // Reminders
        register_setting('lap_settings_group', 'lap_reminder_enabled', ['type'=>'boolean', 'default'=>0, 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_reminder_days', ['type'=>'integer', 'default'=>4, 'capability'=>'manage_woocommerce']);

        // Auto-cancel
        register_setting('lap_settings_group', 'lap_auto_cancel_enabled', ['type'=>'boolean', 'default'=>0, 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_auto_cancel_days', ['type'=>'integer', 'default'=>14, 'capability'=>'manage_woocommerce']);

        // Bankgegevens
        register_setting('lap_settings_group', 'lap_iban', ['type'=>'string', 'default'=>'NL23RABO0102107661', 'sanitize_callback'=>'sanitize_text_field', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_account_name', ['type'=>'string', 'default'=>'Lommers Lederwaren', 'sanitize_callback'=>'sanitize_text_field', 'capability'=>'manage_woocommerce']);

        // Email templates
        register_setting('lap_settings_group', 'lap_email_request_payment_subject', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_request_payment_heading', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_request_payment_content', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        
        register_setting('lap_settings_group', 'lap_email_reminder_subject', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_reminder_heading', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_reminder_content', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        
        register_setting('lap_settings_group', 'lap_email_cancelled_subject', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_cancelled_heading', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_cancelled_content', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);

        // Bevestiging ter goedkeuring
        register_setting('lap_settings_group', 'lap_email_confirmation_subject', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_confirmation_heading', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_confirmation_content', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);

        // Verzonden e-mail
        register_setting('lap_settings_group', 'lap_email_shipped_subject', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_shipped_heading', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);
        register_setting('lap_settings_group', 'lap_email_shipped_content', ['type'=>'string', 'default'=>'', 'capability'=>'manage_woocommerce']);

        add_settings_section('lap_main', 'Algemene instellingen', function(){
            echo '<p>Beheer de handmatige goedkeuringsflow, testmodus en logging.</p>';
        }, 'lap-settings');

        add_settings_section('lap_reminders', 'Herinneringen & Automatisering', function(){
            echo '<p>Configureer automatische herinneringen en annuleringen.</p>';
        }, 'lap-settings');

        add_settings_section('lap_email_templates', 'E-mail templates', function(){
            echo '<p>Pas de teksten van automatische e-mails aan.</p>';
            
            // Placeholder tabel
            echo '<div class="lap-info-box">';
            echo '<h4 style="margin-top:0;">📝 Beschikbare placeholders</h4>';
            echo '<table class="widefat lap-placeholder-table">';
            echo '<thead><tr><th>Placeholder</th><th>Beschrijving</th><th>Voorbeeld</th></tr></thead>';
            echo '<tbody>';
            echo '<tr><td><code>{order_number}</code></td><td>Bestelnummer</td><td>1234</td></tr>';
            echo '<tr><td><code>{customer_name}</code></td><td>Voornaam van de klant</td><td>Jan</td></tr>';
            echo '<tr><td><code>{payment_url}</code></td><td>Betaallink als button (paarse knop)</td><td><span style="background:#7f54b3;color:#fff;padding:4px 12px;border-radius:3px;font-size:12px;">Nu betalen</span></td></tr>';
            echo '<tr><td><code>{order_total}</code></td><td>Totaalbedrag (geformatteerd)</td><td>€ 149,95</td></tr>';
            echo '<tr><td><code>{iban}</code></td><td>Bank IBAN nummer</td><td>' . esc_html(get_option('lap_iban', 'NL23RABO0102107661')) . '</td></tr>';
            echo '<tr><td><code>{account_name}</code></td><td>Naam rekeninghouder</td><td>' . esc_html(get_option('lap_account_name', 'Lommers Lederwaren')) . '</td></tr>';
            echo '<tr><td><code>{tracking_code}</code></td><td>Track & trace code</td><td>3SABC12345678</td></tr>';
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }, 'lap-settings');

        add_settings_field('lap_force_approval_checkout', 'Checkout beperken tot goedkeuringsmethode', function(){
            $val = get_option('lap_force_approval_checkout', 1) ? 1 : 0;
            echo '<label><input type="checkbox" name="lap_force_approval_checkout" value="1" '.checked(1,$val,false).'> ';
            echo 'Toon op checkout alléén "Betaalverzoek (na goedkeuring)". (Zet uit om ook BACS/Mollie op checkout toe te staan.)';
            echo '</label>';
        }, 'lap-settings', 'lap_main');

        add_settings_field('lap_test_mode', 'Testmodus inschakelen', function(){
            $val = get_option('lap_test_mode') ? 1 : 0;
            echo '<label><input type="checkbox" name="lap_test_mode" value="1" '.checked(1,$val,false).'> ';
            echo 'Activeert testfunctionaliteit (simuleerbetaling-gateway op /order-pay, admin tools).';
            echo '</label>';
        }, 'lap-settings', 'lap_main');

        add_settings_field('lap_test_admin_only', 'Testfuncties alleen voor admins', function(){
            $val = get_option('lap_test_admin_only', 1) ? 1 : 0;
            echo '<label><input type="checkbox" name="lap_test_admin_only" value="1" '.checked(1,$val,false).'> ';
            echo 'Verberg testgateway en tools voor niet-admin gebruikers.';
            echo '</label>';
        }, 'lap-settings', 'lap_main');

        // Dashboard dummy data - ALLEEN TONEN ALS TESTMODUS AAN STAAT
        if (get_option('lap_test_mode')) {
            add_settings_field('lap_dashboard_dummy_data', '🎲 Dashboard dummy data', function(){
                $val = get_option('lap_dashboard_dummy_data', 0) ? 1 : 0;
                echo '<label><input type="checkbox" name="lap_dashboard_dummy_data" value="1" '.checked(1,$val,false).'> ';
                echo 'Toon dummy data op dashboard (voor testen zonder echte orders).';
                echo '</label>';
                echo '<p class="description">⚠️ Alleen voor testen! Schakelt echte orderdata uit en toont realistische testdata inclusief grafieken en vergelijkingen.</p>';
            }, 'lap-settings', 'lap_main');
        }

        add_settings_field('lap_force_paid_bump', 'Na betaling status forceren', function(){
            $val = get_option('lap_force_paid_bump', 1) ? 1 : 0;
            echo '<label><input type="checkbox" name="lap_force_paid_bump" value="1" '.checked(1,$val,false).'> ';
            echo 'Zet order na voltooide betaling automatisch door naar In behandeling/Voltooid als de status nog onbetaald is.';
            echo '</label>';
        }, 'lap-settings', 'lap_main');

        add_settings_field('lap_debug_log', 'Debug logging inschakelen', function(){
            $val = get_option('lap_debug_log') ? 1 : 0;
            echo '<label><input type="checkbox" name="lap_debug_log" value="1" '.checked(1,$val,false).'> ';
            echo 'Schrijf gebeurtenissen naar WooCommerce logs (Status → Logs, bron: lommers-approval).';
            echo '</label>';
        }, 'lap-settings', 'lap_main');

        add_settings_field('lap_iban', 'IBAN rekeningnummer', function(){
            $val = get_option('lap_iban', 'NL23RABO0102107661');
            echo '<input type="text" name="lap_iban" value="'.esc_attr($val).'" class="regular-text">';
            echo '<p class="description">Het IBAN nummer dat in betaalmails wordt getoond. Beschikbaar als <code>{iban}</code> placeholder.</p>';
        }, 'lap-settings', 'lap_main');

        add_settings_field('lap_account_name', 'Naam rekeninghouder', function(){
            $val = get_option('lap_account_name', 'Lommers Lederwaren');
            echo '<input type="text" name="lap_account_name" value="'.esc_attr($val).'" class="regular-text">';
            echo '<p class="description">De naam van de rekeninghouder in betaalmails. Beschikbaar als <code>{account_name}</code> placeholder.</p>';
        }, 'lap-settings', 'lap_main');

        // Reminders UI
        add_settings_field('lap_reminder_enabled', 'Herinneringsmail inschakelen', function(){
            $val = get_option('lap_reminder_enabled', 0) ? 1 : 0;
            echo '<label><input type="checkbox" name="lap_reminder_enabled" value="1" '.checked(1,$val,false).'> ';
            echo 'Verstuur automatisch een herinnering als er na X werkdagen geen betaling is.';
            echo '</label>';
        }, 'lap-settings', 'lap_reminders');

        add_settings_field('lap_reminder_days', 'Aantal werkdagen tot herinnering', function(){
            $val = intval( get_option('lap_reminder_days', 4) );
            echo '<input type="number" min="1" step="1" name="lap_reminder_days" value="'.esc_attr($val).'" style="width:80px"> ';
            echo '<span class="description">Stuur herinnering na zoveel werkdagen zonder betaling (weekenden tellen niet).</span>';
        }, 'lap-settings', 'lap_reminders');

        // Auto-cancel
        add_settings_field('lap_auto_cancel_enabled', 'Automatisch annuleren inschakelen', function(){
            $val = get_option('lap_auto_cancel_enabled', 0) ? 1 : 0;
            echo '<label><input type="checkbox" name="lap_auto_cancel_enabled" value="1" '.checked(1,$val,false).'> ';
            echo 'Annuleer automatisch orders die te lang onbetaald blijven.';
            echo '</label>';
        }, 'lap-settings', 'lap_reminders');

        add_settings_field('lap_auto_cancel_days', 'Dagen tot automatisch annuleren', function(){
            $val = intval( get_option('lap_auto_cancel_days', 14) );
            echo '<input type="number" min="1" step="1" name="lap_auto_cancel_days" value="'.esc_attr($val).'" style="width:80px"> ';
            echo '<span class="description">Annuleer orders automatisch na zoveel dagen zonder betaling.</span>';
        }, 'lap-settings', 'lap_reminders');

        // Email template: Betaling verzocht
        add_settings_field('lap_email_request_payment', '📧 Betaling verzocht - E-mail', function(){
            $subject = get_option('lap_email_request_payment_subject', '');
            $heading = get_option('lap_email_request_payment_heading', '');
            $content = get_option('lap_email_request_payment_content', '');
            
            $default_subject = 'Bestelling #{order_number} – betaalinstructies';
            $default_heading = 'Betaal je bestelling';
            $default_content = "Bedankt voor je bestelling. De tas is op voorraad en voor jou gereserveerd.\n\nWanneer je {order_total} overmaakt op {iban} t.n.v. {account_name}, verzenden wij de tas nadat het bedrag op onze rekening is bijgeschreven.\n\nVermeld bij de overschrijving: Bestelling {order_number}\n\n---\n\nJe kunt ook direct online betalen via iDeal of een van de andere betaalmogelijkheden:\n{payment_url}\n\nNa betaling ontvang je automatisch een bevestiging.";
            
            if (empty($subject)) $subject = $default_subject;
            if (empty($heading)) $heading = $default_heading;
            if (empty($content)) $content = $default_content;
            
            echo '<div class="lap-email-field">';
            echo '<p><label><strong>Onderwerp:</strong><br>';
            echo '<input type="text" name="lap_email_request_payment_subject" value="'.esc_attr($subject).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Koptekst:</strong><br>';
            echo '<input type="text" name="lap_email_request_payment_heading" value="'.esc_attr($heading).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Inhoud:</strong><br>';
            echo '<textarea name="lap_email_request_payment_content" rows="10" class="large-text code">'.esc_textarea($content).'</textarea></label></p>';
            echo '</div>';
        }, 'lap-settings', 'lap_email_templates');

        // Email template: Herinnering
        add_settings_field('lap_email_reminder', '⏰ Betalingsherinnering - E-mail', function(){
            $subject = get_option('lap_email_reminder_subject', '');
            $heading = get_option('lap_email_reminder_heading', '');
            $content = get_option('lap_email_reminder_content', '');
            
            $default_subject = 'Herinnering – bestelling #{order_number}';
            $default_heading = 'Herinnering: betaal je bestelling';
            $default_content = "Je artikel staat nog steeds voor je gereserveerd!\n\nWe hebben je betaling nog niet ontvangen. Wil je je bestelling afronden? Dat kan direct via het betaalverzoek hieronder.\n\nLet op: als de betaling niet op tijd binnen is, komt het artikel weer beschikbaar voor andere klanten.\n\n{payment_url}";
            
            if (empty($subject)) $subject = $default_subject;
            if (empty($heading)) $heading = $default_heading;
            if (empty($content)) $content = $default_content;
            
            echo '<div class="lap-email-field">';
            echo '<p><label><strong>Onderwerp:</strong><br>';
            echo '<input type="text" name="lap_email_reminder_subject" value="'.esc_attr($subject).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Koptekst:</strong><br>';
            echo '<input type="text" name="lap_email_reminder_heading" value="'.esc_attr($heading).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Inhoud:</strong><br>';
            echo '<textarea name="lap_email_reminder_content" rows="10" class="large-text code">'.esc_textarea($content).'</textarea></label></p>';
            echo '</div>';
        }, 'lap-settings', 'lap_email_templates');

        // Email template: Geannuleerd
        add_settings_field('lap_email_cancelled', '❌ Order geannuleerd - E-mail', function(){
            $subject = get_option('lap_email_cancelled_subject', '');
            $heading = get_option('lap_email_cancelled_heading', '');
            $content = get_option('lap_email_cancelled_content', '');
            
            $default_subject = 'Bestelling #{order_number} geannuleerd';
            $default_heading = 'Je bestelling is geannuleerd';
            $default_content = "Beste {customer_name},\n\nJe bestelling #{order_number} is geannuleerd.\n\nAls je vragen hebt over deze annulering, neem dan gerust contact met ons op.";
            
            if (empty($subject)) $subject = $default_subject;
            if (empty($heading)) $heading = $default_heading;
            if (empty($content)) $content = $default_content;
            
            echo '<div class="lap-email-field">';
            echo '<p><label><strong>Onderwerp:</strong><br>';
            echo '<input type="text" name="lap_email_cancelled_subject" value="'.esc_attr($subject).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Koptekst:</strong><br>';
            echo '<input type="text" name="lap_email_cancelled_heading" value="'.esc_attr($heading).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Inhoud:</strong><br>';
            echo '<textarea name="lap_email_cancelled_content" rows="10" class="large-text code">'.esc_textarea($content).'</textarea></label></p>';
            echo '</div>';
        }, 'lap-settings', 'lap_email_templates');

        // Email template: Bevestiging ter goedkeuring
        add_settings_field('lap_email_confirmation', 'Bevestiging ter goedkeuring - E-mail', function(){
            $subject = get_option('lap_email_confirmation_subject', '');
            $heading = get_option('lap_email_confirmation_heading', '');
            $content = get_option('lap_email_confirmation_content', '');
            
            $default_subject = 'Bedankt voor je bestelling – wij gaan controleren';
            $default_heading = 'Bedankt voor je bestelling';
            $default_content = "Beste {customer_name},\n\nBedankt voor je bestelling #{order_number}. We gaan nu controleren of alle artikelen op voorraad zijn.\n\nJe ontvangt zo snel mogelijk bericht van ons.";
            
            if (empty($subject)) $subject = $default_subject;
            if (empty($heading)) $heading = $default_heading;
            if (empty($content)) $content = $default_content;
            
            echo '<div class="lap-email-field">';
            echo '<p><label><strong>Onderwerp:</strong><br>';
            echo '<input type="text" name="lap_email_confirmation_subject" value="'.esc_attr($subject).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Koptekst:</strong><br>';
            echo '<input type="text" name="lap_email_confirmation_heading" value="'.esc_attr($heading).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Inhoud:</strong><br>';
            echo '<textarea name="lap_email_confirmation_content" rows="10" class="large-text code">'.esc_textarea($content).'</textarea></label></p>';
            echo '<p class="description">Deze e-mail wordt verstuurd wanneer een order de status "Ter goedkeuring" krijgt. Bevat geen betaallink.</p>';
            echo '</div>';
        }, 'lap-settings', 'lap_email_templates');

        // Email template: Onderweg / Verzonden
        add_settings_field('lap_email_shipped', '📦 Bestelling onderweg - E-mail', function(){
            $subject = get_option('lap_email_shipped_subject', '');
            $heading = get_option('lap_email_shipped_heading', '');
            $content = get_option('lap_email_shipped_content', '');
            
            $default_subject = 'Bestelling #{order_number} is onderweg';
            $default_heading = 'Je bestelling is onderweg';
            $default_content = "Goed nieuws! Je bestelling #{order_number} is onderweg.\n\nJe kunt je pakket volgen met track & trace code: {tracking_code}\n\nBedankt voor je bestelling en veel plezier ervan!";
            
            if (empty($subject)) $subject = $default_subject;
            if (empty($heading)) $heading = $default_heading;
            if (empty($content)) $content = $default_content;
            
            echo '<div class="lap-email-field">';
            echo '<p><label><strong>Onderwerp:</strong><br>';
            echo '<input type="text" name="lap_email_shipped_subject" value="'.esc_attr($subject).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Koptekst:</strong><br>';
            echo '<input type="text" name="lap_email_shipped_heading" value="'.esc_attr($heading).'" class="large-text"></label></p>';
            
            echo '<p><label><strong>Inhoud:</strong><br>';
            echo '<textarea name="lap_email_shipped_content" rows="10" class="large-text code">'.esc_textarea($content).'</textarea></label></p>';
            echo '<p class="description">Deze e-mail wordt verstuurd wanneer een order de status "Onderweg" krijgt. Placeholder <code>{tracking_code}</code> wordt vervangen door de track & trace code.</p>';
            echo '</div>';

            // Test versturen knop
            if (current_user_can('manage_woocommerce')) {
                $test_url = add_query_arg(
                    array(
                        'lap_test_shipped' => 1,
                        'lap_test_email' => get_option('admin_email'),
                        'lap_test_tracking' => urlencode('https://postnl.nl/tracktrace/?barcode=3SABC12345678'),
                        '_wpnonce' => wp_create_nonce('lap_test_shipped'),
                    ),
                    home_url('/')
                );
                $test_url_code = add_query_arg(
                    array(
                        'lap_test_shipped' => 1,
                        'lap_test_email' => get_option('admin_email'),
                        'lap_test_tracking' => '3SABC12345678',
                        '_wpnonce' => wp_create_nonce('lap_test_shipped'),
                    ),
                    home_url('/')
                );
                echo '<div style="margin-top:12px;padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;">';
                echo '<strong style="color:#16a34a;">&#128232; Test de "Onderweg" e-mail</strong>';
                echo '<p style="margin:6px 0 4px;font-size:13px;">Kies een testvariant:</p>';
                echo '<p style="margin:4px 0;"><a class="button" target="_blank" href="' . esc_url($test_url) . '" style="background:#16a34a;border-color:#16a34a;color:#fff;">&#128279; Test met track & trace URL</a>';
                echo ' <a class="button" target="_blank" href="' . esc_url($test_url_code) . '">&#128196; Test met alleen code</a></p>';
                echo '<p style="margin:6px 0 0;font-size:12px;color:#666;">Beide worden naar <code>' . esc_html(get_option('admin_email')) . '</code> gestuurd.</p>';
                echo '</div>';
            }
        }, 'lap-settings', 'lap_email_templates');
    }

    public static function render_page(){
        echo '<div class="wrap lap-settings-page">';
        echo '<h1>⚙️ Goedkeuren & Betalen - Instellingen</h1>';
        
        self::render_styles();
        
        echo '<form method="post" action="options.php">';
        settings_fields('lap_settings_group');
        do_settings_sections('lap-settings');
        submit_button('Instellingen opslaan', 'primary large');
        echo '</form>';

        // Admin testtools
        if ( get_option('lap_test_mode') && current_user_can('manage_woocommerce') ) {
            echo '<div class="lap-admin-section">';
            echo '<h2>🧪 Admin testtools</h2>';
            echo '<p>Je kunt in een order de actie "TEST: Markeer als betaald (simulatie)" gebruiken.</p>';
            echo '<p>Of simuleer een webhook via URL: <code>?lap_sim_webhook={ORDER_ID}&amp;_wpnonce={NONCE}</code></p>';
            echo '<p><em>Genereer de nonce met: <code>wp_create_nonce(\'lap_sim_webhook_{ORDER_ID}\')</code></em></p>';
            echo '<p><em>Let op: alleen beschikbaar voor beheerders en uitsluitend in testmodus.</em></p>';
            echo '</div>';
        }

        // Handmatig reminders uitvoeren (alleen voor beheerders)
        if ( current_user_can('manage_woocommerce') ) {
            $run_url = add_query_arg(
                array(
                    'lap_run_reminders' => 1,
                    '_wpnonce' => wp_create_nonce('lap_run_reminders'),
                ),
                home_url('/')
            );

            echo '<div class="lap-admin-section">';
            echo '<h2>⏰ Herinneringen handmatig uitvoeren</h2>';
            echo '<p>Alleen voor beheerders: voer de herinneringscontrole nu direct uit (om te testen).</p>';
            echo '<p><a class="button button-primary" target="_blank" href="'.esc_url($run_url).'">Nu uitvoeren</a></p>';
            echo '<p><em>Alleen orders met het meta veld "_lap_managed_order" (orders via de goedkeuringsflow) komen in aanmerking voor reminders.</em></p>';
            echo '</div>';
        }

        // Handmatig auto-cancel uitvoeren (alleen voor beheerders)
        if ( current_user_can('manage_woocommerce') ) {
            $cancel_url = add_query_arg(
                array(
                    'lap_run_auto_cancel' => 1,
                    '_wpnonce' => wp_create_nonce('lap_run_auto_cancel'),
                ),
                home_url('/')
            );

            echo '<div class="lap-admin-section">';
            echo '<h2>🗑️ Automatisch annuleren handmatig uitvoeren</h2>';
            echo '<p>Alleen voor beheerders: voer de auto-cancel controle nu direct uit (om te testen).</p>';
            echo '<p><a class="button button-secondary" target="_blank" href="'.esc_url($cancel_url).'">Nu uitvoeren</a></p>';
            echo '<p><em>Dit annuleert alle orders die langer dan X dagen onbetaald zijn (zoals ingesteld hierboven).</em></p>';
            echo '</div>';
        }

        // Eenmalige excuusmail bulk-verzending (maart 2026)
        if ( current_user_can('manage_woocommerce') ) {
            $apology_url = add_query_arg(
                array(
                    'lap_send_apology' => 1,
                    '_wpnonce'         => wp_create_nonce('lap_send_apology'),
                ),
                home_url('/')
            );
            $already_sent_count = count((array) get_option('lap_apology_sent_emails', array()));
            $csv_exists         = file_exists(LAP_PLUGIN_DIR . 'email.csv');

            $test_base_url = add_query_arg(
                array(
                    'lap_test_apology' => 1,
                    '_wpnonce'         => wp_create_nonce('lap_test_apology'),
                ),
                home_url('/')
            );

            echo '<div class="lap-admin-section" style="border-left: 4px solid #d63638;">';
            echo '<h2>📧 Excuusmail verzenden (maart 2026)</h2>';

            echo '<p><strong>Testmail sturen</strong><br>';
            echo 'Stuur de excuusmail naar één adres om de opmaak te controleren:</p>';
            echo '<p>';
            echo '<input type="email" id="lap_test_apology_email" placeholder="e-mailadres" style="width:280px;margin-right:8px;" value="" />';
            echo '<a class="button button-secondary" id="lap_test_apology_btn" href="#">Testmail versturen</a>';
            echo '</p>';
            echo '<script>
document.getElementById("lap_test_apology_btn").addEventListener("click", function(e) {
    e.preventDefault();
    var email = document.getElementById("lap_test_apology_email").value;
    if (!email) { alert("Vul een e-mailadres in."); return; }
    var url = ' . json_encode(esc_url_raw($test_base_url)) . ' + "&lap_test_email=" + encodeURIComponent(email);
    window.open(url, "_blank");
});
</script>';

            echo '<hr style="margin:16px 0;">';
            echo '<p><strong>Bulk versturen naar alle adressen in email.csv</strong><br>';
            echo 'Al verstuurd: <strong>' . esc_html($already_sent_count) . '</strong> adressen. ';
            if (!$csv_exists) {
                echo '<br><strong style="color:#d63638;">⚠ email.csv niet gevonden in de plugin map.</strong>';
            }
            echo '</p>';
            echo '<p><a class="button" style="border-color:#d63638;color:#d63638;" target="_blank" href="'.esc_url($apology_url).'">Excuusmails versturen</a></p>';
            echo '<p><em>Veilig om meerdere keren te drukken — adressen die al een mail hebben ontvangen worden overgeslagen.</em></p>';
            echo '</div>';
        }

        // Eenmalige cleanup: verwijder _lap_managed_order van orders die niet via de goedkeuringsflow zijn verlopen
        if ( current_user_can('manage_woocommerce') ) {
            $cleanup_url = add_query_arg(
                array(
                    'lap_cleanup_managed_meta' => 1,
                    '_wpnonce' => wp_create_nonce('lap_cleanup_managed_meta'),
                ),
                home_url('/')
            );

            echo '<div class="lap-admin-section" style="border-left: 4px solid #d63638;">';
            echo '<h2>🧹 Cleanup: corrigeer vervuilde orders</h2>';
            echo '<p><strong>Eenmalig uitvoeren</strong> na de bugfix van maart 2026. Verwijdert het meta veld <code>_lap_managed_order</code> van orders die per abuis gemarkeerd waren (betaalde orders die niet via de goedkeuringsflow liepen).</p>';
            echo '<p><a class="button" style="border-color:#d63638;color:#d63638;" target="_blank" href="'.esc_url($cleanup_url).'">Cleanup uitvoeren</a></p>';
            echo '<p><em>Veilig om meerdere keren uit te voeren. Na uitvoering kun je deze sectie negeren.</em></p>';
            echo '</div>';
        }

        echo '</div>';
    }
    
    private static function render_styles() {
        echo '<style>
            .lap-settings-page h2 { 
                border-bottom: 2px solid #2271b1; 
                padding-bottom: 10px; 
                margin-top: 30px;
                color: #1d2327;
            }
            .lap-info-box {
                background: linear-gradient(135deg, #f0f8ff 0%, #e8f4f8 100%);
                border-left: 4px solid #2271b1;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .lap-info-box h4 {
                color: #2271b1;
                margin-top: 0;
            }
            .lap-placeholder-table {
                background: white !important;
                border-radius: 4px;
                overflow: hidden;
            }
            .lap-placeholder-table code {
                background: #f0f0f1;
                padding: 3px 6px;
                border-radius: 3px;
                font-size: 13px;
                color: #d63638;
            }
            .lap-email-field {
                background: #fafafa;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #dcdcde;
                margin-bottom: 15px;
            }
            .lap-email-field strong {
                color: #1d2327;
                font-size: 14px;
            }
            .lap-email-field input[type="text"],
            .lap-email-field textarea {
                margin-top: 5px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            }
            .lap-email-field textarea {
                font-family: Consolas, Monaco, monospace;
                font-size: 13px;
                line-height: 1.6;
            }
            .lap-admin-section {
                background: white;
                border: 1px solid #c3c4c7;
                border-left: 4px solid #2271b1;
                padding: 20px;
                margin: 20px 0;
                border-radius: 4px;
            }
            .lap-admin-section h2 {
                margin-top: 0;
                border-bottom: none;
                padding-bottom: 0;
            }
            .lap-admin-section code {
                background: #f0f0f1;
                padding: 3px 6px;
                border-radius: 3px;
                font-size: 12px;
            }
            .lap-settings-page .form-table th {
                width: 280px;
                font-weight: 600;
                color: #1d2327;
            }
            .lap-settings-page .submit {
                padding: 0;
                margin-top: 20px;
            }
            .lap-settings-page .button-primary.large {
                font-size: 14px;
                height: 40px;
                line-height: 40px;
                padding: 0 24px;
            }
        </style>';
    }

    public static function settings_link($links){
        $url = admin_url('admin.php?page=lap-settings');
        $links[] = '<a href="'.esc_url($url).'">Instellingen</a>';
        
        // Voeg ook dashboard link toe
        $dashboard_url = admin_url('admin.php?page=lap-dashboard');
        $links[] = '<a href="'.esc_url($dashboard_url).'">Dashboard</a>';
        
        return $links;
    }
}
LAP_Settings::init();
