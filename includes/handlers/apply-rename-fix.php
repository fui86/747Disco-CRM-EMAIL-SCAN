<?php
/**
 * Script per applicare il fix della rinomina file al file class-disco747-forms.php
 * Eseguire solo una volta per applicare le modifiche
 */

// Leggi il file originale
$file_path = __DIR__ . '/class-disco747-forms.php';
$content = file_get_contents($file_path);

if ($content === false) {
    die("Errore: impossibile leggere il file\n");
}

// Backup del file originale
$backup_path = $file_path . '.backup-' . date('Y-m-d-His');
file_put_contents($backup_path, $content);
echo "Backup creato: " . basename($backup_path) . "\n";

// Modifica 1: Includi il trait dopo la dichiarazione della classe
$search1 = 'class Disco747_Forms {';
$replace1 = 'class Disco747_Forms {
    
    // Trait per gestione rinomina file
    use Disco747_Forms_Rename_Helper;';

if (strpos($content, 'use Disco747_Forms_Rename_Helper') === false) {
    $content = str_replace($search1, $replace1, $content);
    echo "Modifica 1: Trait aggiunto\n";
} else {
    echo "Modifica 1: Trait già presente\n";
}

// Modifica 2: Includi il file del trait all'inizio del file (dopo namespace)
$search2 = "namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}";

$replace2 = "namespace Disco747_CRM\Handlers;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Include helper per rinomina file
require_once __DIR__ . '/class-disco747-forms-rename-helper.php';";

if (strpos($content, 'class-disco747-forms-rename-helper.php') === false) {
    $content = str_replace($search2, $replace2, $content);
    echo "Modifica 2: Require del trait aggiunto\n";
} else {
    echo "Modifica 2: Require già presente\n";
}

// Modifica 3: Modifica il metodo update_preventivo per leggere il file_id
$search3 = "SELECT stato, acconto FROM {";
$replace3 = "SELECT stato, acconto, googledrive_file_id, data_evento, tipo_evento, tipo_menu FROM {";

$content = str_replace($search3, $replace3, $content);
echo "Modifica 3: Query aggiornata per includere googledrive_file_id\n";

// Modifica 4: Aggiungi la gestione della rinomina dopo il ricaricamento del preventivo
$search4 = "// Rileggi preventivo aggiornato dal database per avere tutti i dati";
$replace4 = "// Rileggi preventivo aggiornato dal database per avere tutti i dati
        
        // GESTIONE RINOMINA FILE SU GOOGLE DRIVE
        $rename_success = \$this->handle_google_drive_rename(\$edit_id, \$old_preventivo, \$data);
        
        if (!\$rename_success) {
            \$this->log('[Forms] Rinomina fallita, provo con rigenera + upload...', 'WARNING');
        }";

if (strpos($content, 'handle_google_drive_rename') === false) {
    $content = str_replace($search4, $replace4, $content);
    echo "Modifica 4: Chiamata a handle_google_drive_rename aggiunta\n";
} else {
    echo "Modifica 4: Chiamata già presente\n";
}

// Modifica 5: Salva il googledrive_file_id quando viene creato un nuovo preventivo
$search5 = "'googledrive_url' => \$data['googledrive_url'] ?? '',
            'excel_url' => \$data['googledrive_url'] ?? '',";

$replace5 = "'googledrive_url' => \$data['googledrive_url'] ?? '',
            'googledrive_file_id' => \$data['googledrive_file_id'] ?? '',
            'excel_url' => \$data['googledrive_url'] ?? '',";

if (strpos($content, "'googledrive_file_id' => ") === false || 
    substr_count($content, "'googledrive_file_id' => ") < 2) {
    $content = str_replace($search5, $replace5, $content);
    echo "Modifica 5: googledrive_file_id aggiunto al salvataggio\n";
} else {
    echo "Modifica 5: googledrive_file_id già presente\n";
}

// Modifica 6: Salva il file_id dopo l'upload nella creazione nuovo preventivo
$search6 = "if (\$excel_url) {
                        \$cloud_url = \$excel_url;
                        \$data['googledrive_url'] = \$excel_url;";

$replace6 = "if (\$excel_url) {
                        // Se è un array con file_id, salvalo
                        if (is_array(\$excel_url) && isset(\$excel_url['file_id'])) {
                            \$cloud_url = \$excel_url['url'];
                            \$data['googledrive_url'] = \$excel_url['url'];
                            \$data['googledrive_file_id'] = \$excel_url['file_id'];
                        } else {
                            // Formato vecchio (solo URL)
                            \$cloud_url = \$excel_url;
                            \$data['googledrive_url'] = \$excel_url;
                        }";

if (strpos($content, "if (is_array(\$excel_url) && isset(\$excel_url['file_id']))") === false) {
    $content = str_replace($search6, $replace6, $content);
    echo "Modifica 6: Salvataggio file_id dopo upload aggiunto\n";
} else {
    echo "Modifica 6: Salvataggio file_id già presente\n";
}

// Salva il file modificato
$result = file_put_contents($file_path, $content);

if ($result === false) {
    die("Errore: impossibile scrivere il file\n");
}

echo "\nModifiche applicate con successo!\n";
echo "File modificato: " . basename($file_path) . "\n";
echo "Backup salvato: " . basename($backup_path) . "\n";
echo "\nPer ripristinare il backup:\n";
echo "mv " . basename($backup_path) . " " . basename($file_path) . "\n";
