# FIX: Rinomina File Excel su Google Drive quando cambia lo Stato

## Problema
Quando cambio lo stato del preventivo da "attivo" ad "annullato" nel form-preventivo, il sistema non rinomina il file Excel su Google Drive (aggiungendo "NO " davanti al nome).

## Soluzione Implementata

Ho creato il file helper `class-disco747-forms-rename-helper.php` che contiene la logica per rinominare i file su Google Drive.

## Modifiche da Applicare

### 1. Aggiungi il metodo `rename_file()` alla classe GoogleDrive ✅
**File**: `/workspace/includes/storage/class-disco747-googledrive.php`  
**Stato**: ✅ COMPLETATO

Il metodo è stato aggiunto alla riga 639.

### 2. Modifica la classe Disco747_Forms

**File**: `/workspace/includes/handlers/class-disco747-forms.php`

#### 2.1 Includi il file helper (dopo la riga 22)
```php
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Include helper per rinomina file
require_once __DIR__ . '/class-disco747-forms-rename-helper.php';
```

#### 2.2 Usa il trait nella classe (dopo la riga 24)
```php
class Disco747_Forms {
    
    // Trait per gestione rinomina file
    use Disco747_Forms_Rename_Helper;
    
    private $database;
    private $excel;
    ...
```

#### 2.3 Modifica la query nel metodo `update_preventivo()` (intorno alla riga 233-235)

**TROVA:**
```php
$old_preventivo = $wpdb->get_row($wpdb->prepare(
    "SELECT stato, acconto FROM {$this->table_name} WHERE id = %d",
    $edit_id
));
```

**SOSTITUISCI CON:**
```php
$old_preventivo = $wpdb->get_row($wpdb->prepare(
    "SELECT stato, acconto, googledrive_file_id, data_evento, tipo_evento, tipo_menu FROM {$this->table_name} WHERE id = %d",
    $edit_id
));
```

#### 2.4 Aggiungi la chiamata al metodo di rinomina (dopo la riga 309)

**TROVA:**
```php
// Rileggi preventivo aggiornato dal database per avere tutti i dati
$preventivo = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$this->table_name} WHERE id = %d",
    $edit_id
), ARRAY_A);
```

**AGGIUNGI DOPO:**
```php
// GESTIONE RINOMINA FILE SU GOOGLE DRIVE
$rename_success = $this->handle_google_drive_rename($edit_id, $old_preventivo, $data);

if (!$rename_success) {
    $this->log('[Forms] Rinomina fallita, provo con rigenera + upload...', 'WARNING');
    // Fallback: rigenera e carica nuovo file
    $this->regenerate_and_upload_excel($edit_id, $preventivo);
}
```

#### 2.5 Salva il googledrive_file_id quando viene creato un nuovo preventivo (intorno alla riga 1016)

**TROVA:**
```php
'googledrive_url' => $data['googledrive_url'] ?? '',
'excel_url' => $data['googledrive_url'] ?? '',
```

**SOSTITUISCI CON:**
```php
'googledrive_url' => $data['googledrive_url'] ?? '',
'googledrive_file_id' => $data['googledrive_file_id'] ?? '',
'excel_url' => $data['googledrive_url'] ?? '',
```

#### 2.6 Salva il file_id dopo l'upload nella creazione nuovo preventivo (intorno alla riga 152-156)

**TROVA:**
```php
if ($excel_url) {
    $cloud_url = $excel_url;
    $data['googledrive_url'] = $excel_url;
```

**SOSTITUISCI CON:**
```php
if ($excel_url) {
    // Se è un array con file_id, salvalo
    if (is_array($excel_url) && isset($excel_url['file_id'])) {
        $cloud_url = $excel_url['url'];
        $data['googledrive_url'] = $excel_url['url'];
        $data['googledrive_file_id'] = $excel_url['file_id'];
    } else {
        // Formato vecchio (solo URL)
        $cloud_url = $excel_url;
        $data['googledrive_url'] = $excel_url;
    }
```

## Come Funziona

1. Quando aggiorni un preventivo, il sistema legge il vecchio stato e il `googledrive_file_id` dal database
2. Calcola il vecchio e il nuovo nome del file in base allo stato
3. Se il nome è cambiato:
   - Usa l'API di Google Drive per rinominare il file (PATCH request)
   - Il file mantiene lo stesso ID ma cambia nome
4. Se la rinomina fallisce (es. file_id mancante per preventivi vecchi):
   - Fa fallback a rigenera + upload come prima

## Vantaggi

- ✅ Non crea file duplicati su Google Drive
- ✅ Più veloce (rinomina vs rigenera+upload)
- ✅ Mantiene la cronologia del file su Google Drive
- ✅ Funziona anche con preventivi vecchi (fallback)

## Test

1. Crea un nuovo preventivo con stato "attivo"
2. Verifica che venga creato il file Excel su Google Drive
3. Modifica lo stato a "annullato"
4. Verifica che il file su Google Drive sia stato rinominato con "NO " davanti
5. Non dovrebbero esserci file duplicati

## File Modificati/Creati

- ✅ `/workspace/includes/storage/class-disco747-googledrive.php` - Aggiunto metodo `rename_file()`
- ⏳ `/workspace/includes/handlers/class-disco747-forms.php` - Da modificare manualmente
- ✅ `/workspace/includes/handlers/class-disco747-forms-rename-helper.php` - File helper creato

## Note

- Il backup del file originale è stato creato: `class-disco747-forms.php.backup-YYYYMMDD-HHMMSS`
- Per ripristinare: `cp class-disco747-forms.php.backup-* class-disco747-forms.php`
