<?php
/**
 * Script per pulire TUTTE le cache - 747 Disco CRM
 * Eseguire questo file via browser: https://747disco.it/wp-content/plugins/747disco-crm/clear-cache.php
 */

// Sicurezza base
$secret = $_GET['secret'] ?? '';
if ($secret !== 'disco747clear') {
    die('Accesso negato. Usa: ?secret=disco747clear');
}

echo "<h1>?? Pulizia Cache 747 Disco CRM</h1>";
echo "<pre>";

// 1. Reset OPcache (PHP)
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "? OPcache PHP resettata\n";
} else {
    echo "??  OPcache non disponibile\n";
}

// 2. Carica WordPress
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../../../wp-load.php');

// 3. Flush cache WordPress
wp_cache_flush();
echo "? Cache WordPress pulita\n";

// 4. Delete transients
global $wpdb;
$deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
echo "? Eliminati {$deleted} transients\n";

// 5. Flush rewrite rules
flush_rewrite_rules();
echo "? Rewrite rules aggiornate\n";

// 6. Clear object cache (se Redis/Memcached)
if (function_exists('wp_cache_flush_runtime')) {
    wp_cache_flush_runtime();
    echo "? Object cache runtime pulita\n";
}

// 7. Forza ricaricamento plugin
delete_option('active_plugins');
$active_plugins = get_option('active_plugins', array());
update_option('active_plugins', $active_plugins);
echo "? Lista plugin ricaricata\n";

echo "\n?? CACHE COMPLETAMENTE PULITA!\n";
echo "\n?? PROSSIMI PASSI:\n";
echo "   1. Ricarica la pagina admin (Ctrl+F5)\n";
echo "   2. Se il problema persiste, riavvia PHP-FPM sul server\n";
echo "   3. Elimina questo file per sicurezza: rm " . __FILE__ . "\n";
echo "</pre>";

echo "<h2>?? Debug Info</h2>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "Plugin attivo: " . (is_plugin_active('747disco-crm/747disco-crm.php') ? '? SI' : '? NO') . "\n";
echo "Encoding: " . mb_internal_encoding() . "\n";
echo "</pre>";
?>
