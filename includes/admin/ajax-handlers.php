<?php
/**
 * AJAX Handlers per Funnel Email - 747 Disco CRM
 * Gestisce anteprima e test invio email
 * 
 * AGGIUNGI QUESTO CODICE AL FILE: includes/admin/ajax-handlers.php
 * OPPURE includilo separatamente
 * 
 * @package    Disco747_CRM
 * @subpackage Funnel
 * @version    1.0.0
 */

// Aggiungi questi handler AJAX

/**
 * AJAX: Anteprima email funnel
 */
add_action('wp_ajax_disco747_preview_funnel_email', 'disco747_ajax_preview_funnel_email');
function disco747_ajax_preview_funnel_email() {
    check_ajax_referer('disco747_funnel_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
    }
    
    $funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
    $preview = $funnel_manager->preview_email($sequence_id);
    
    wp_send_json_success($preview);
}

/**
 * AJAX: Test invio email funnel
 */
add_action('wp_ajax_disco747_test_funnel_email', 'disco747_ajax_test_funnel_email');
function disco747_ajax_test_funnel_email() {
    check_ajax_referer('disco747_funnel_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    $test_email = sanitize_email($_POST['test_email'] ?? '');
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
    }
    
    if (!$test_email || !is_email($test_email)) {
        wp_send_json_error('Email non valida');
    }
    
    $funnel_manager = new \Disco747_CRM\Funnel\Disco747_Funnel_Manager();
    $result = $funnel_manager->test_send_email($sequence_id, $test_email);
    
    if ($result['success']) {
        wp_send_json_success($result['message']);
    } else {
        wp_send_json_error($result['message']);
    }
}

/**
 * AJAX: Carica dati sequenza per editing
 */
add_action('wp_ajax_disco747_get_funnel_sequence', 'disco747_ajax_get_funnel_sequence');
function disco747_ajax_get_funnel_sequence() {
    check_ajax_referer('disco747_funnel_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
    
    global $wpdb;
    $sequence_id = intval($_POST['sequence_id'] ?? 0);
    
    if (!$sequence_id) {
        wp_send_json_error('ID sequenza mancante');
    }
    
    $sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';
    $sequence = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$sequences_table} WHERE id = %d",
        $sequence_id
    ));
    
    if (!$sequence) {
        wp_send_json_error('Sequenza non trovata');
    }
    
    wp_send_json_success($sequence);
}