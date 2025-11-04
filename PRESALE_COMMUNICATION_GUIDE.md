# Guida alle Comunicazioni di Pre-Vendita - 747 Disco CRM

## 📧 Email di Pre-Vendita

### Template HTML Professionale
Il sistema include un template email HTML accattivante con i colori del locale:
- **Nero** (#000000)
- **Oro** (#D4AF37)
- **Grigio** (#808080)
- **Bianco** (#FFFFFF)

### Funzionalità
- Logo 747 Disco integrato
- Design responsive
- Box di urgenza per offerta limitata (€450 omaggi)
- Riepilogo evento elegante
- CTA chiara per WhatsApp
- Immagini dalla gallery del sito
- Social proof e testimonianze

### Utilizzo

```php
use Disco747_CRM\Communication\Disco747_Email;

$email_handler = new Disco747_Email();

// Invia email di pre-vendita
$success = $email_handler->send_presale_email($preventivo_data, $options);
```

### Placeholder Supportati
- `{{nome_referente}}` - Nome del referente
- `{{cognome_referente}}` - Cognome del referente
- `{{nome_cliente}}` - Nome completo cliente
- `{{tipo_evento}}` - Tipo di evento
- `{{data_evento}}` - Data evento (formato: 25 Dicembre 2025)
- `{{numero_invitati}}` - Numero invitati
- `{{tipo_menu}}` - Tipo menu scelto
- `{{importo_totale}}` - Importo totale (es: €3.500,00)
- `{{acconto}}` - Importo acconto
- `{{telefono_sede}}` - Telefono 747 Disco
- `{{email_sede}}` - Email info@747disco.it

---

## 💬 WhatsApp Funnel di Pre-Vendita

### Sistema Funnel a 4 Step
Il sistema genera automaticamente 4 messaggi WhatsApp sequenziali per massimizzare le conversioni:

1. **Step 1 - Benvenuto** (Invio immediato)
   - Saluto personale
   - Riferimento all'email inviata
   - Creazione anticipazione

2. **Step 2 - Urgenza** (Dopo 24 ore)
   - Ricordo dell'offerta limitata
   - Scadenza 7 giorni
   - €450 di omaggi gratis

3. **Step 3 - Social Proof** (Dopo 48 ore)
   - Testimonianze clienti
   - Esperienza e fiducia
   - Invito alla conferma

4. **Step 4 - Offerta Finale** (Dopo 72 ore)
   - Ultimo reminder
   - Riepilogo completo
   - CTA finale urgente

### Utilizzo

#### Genera Funnel Completo
```php
use Disco747_CRM\Communication\Disco747_WhatsApp;

$whatsapp_handler = new Disco747_WhatsApp();

// Genera tutti i messaggi del funnel
$funnel = $whatsapp_handler->generate_presale_funnel($preventivo_data, $options);

// Restituisce array con tutti gli step:
// [
//   'step1_welcome' => ['url' => '...', 'message' => '...', 'delay_hours' => 0],
//   'step2_urgency' => ['url' => '...', 'message' => '...', 'delay_hours' => 24],
//   'step3_social_proof' => ['url' => '...', 'message' => '...', 'delay_hours' => 48],
//   'step4_final_offer' => ['url' => '...', 'message' => '...', 'delay_hours' => 72]
// ]
```

#### Genera Singolo Messaggio
```php
// Genera solo il primo messaggio (immediato)
$whatsapp_url = $whatsapp_handler->generate_presale_whatsapp_message(
    $preventivo_data,
    'step1_welcome' // 'step1_welcome', 'step2_urgency', 'step3_social_proof', 'step4_final_offer'
);
```

---

## 🚀 Utilizzo Coordinato (Consigliato)

### Utilizzo tramite Messaging Manager
Il modo più semplice per inviare tutte le comunicazioni di pre-vendita:

```php
use Disco747_CRM\Communication\Disco747_Messaging;

$messaging = new Disco747_Messaging();

// Invia email + WhatsApp funnel completo
$results = $messaging->send_presale_communication($preventivo_data, array(
    'send_email' => true,           // Invia email di pre-vendita
    'send_whatsapp' => true,       // Genera link WhatsApp
    'whatsapp_step' => 'step1_welcome' // Step iniziale WhatsApp
));

// Risultati:
// [
//   'success' => true/false,
//   'email' => ['success' => true, 'type' => 'presale'],
//   'whatsapp' => [
//       'success' => true,
//       'url' => 'https://wa.me/...',
//       'funnel' => [...tutti gli step...],
//       'type' => 'presale_funnel'
//   ],
//   'errors' => []
// ]
```

---

## 🔌 Integrazione Automatica

### Hook Dopo Salvataggio Preventivo
Per inviare automaticamente le comunicazioni dopo la creazione di un preventivo, aggiungi questo codice nel file che gestisce il salvataggio:

```php
// Dopo il salvataggio del preventivo nel database
do_action('disco747_preventivo_saved', $preventivo_id, $preventivo_data);

// Nel file di inizializzazione del plugin o in un file dedicato:
add_action('disco747_preventivo_saved', function($preventivo_id, $preventivo_data) {
    if (empty($preventivo_data['mail']) || empty($preventivo_data['cellulare'])) {
        return; // Skip se mancano contatti
    }
    
    $messaging = new Disco747_Messaging();
    $messaging->send_presale_communication($preventivo_data, array(
        'send_email' => true,
        'send_whatsapp' => true
    ));
}, 10, 2);
```

---

## 📋 Struttura Dati Preventivo Richiesta

```php
$preventivo_data = array(
    'nome_referente' => 'Mario',
    'cognome_referente' => 'Rossi',
    'mail' => 'mario.rossi@example.com',
    'cellulare' => '3331234567',
    'data_evento' => '2025-12-25',
    'tipo_evento' => 'Festa di Compleanno',
    'numero_invitati' => 50,
    'tipo_menu' => 'Menu 74',
    'importo_preventivo' => 3500.00,
    'acconto' => 1000.00
);
```

---

## 🎨 Personalizzazione

### Modificare Template Email
Il template HTML si trova in:
`includes/communication/class-disco747-email.php` → metodo `generate_presale_email_content()`

### Modificare Template WhatsApp
I template WhatsApp si trovano in:
`includes/communication/class-disco747-whatsapp.php` → metodo `get_presale_funnel_templates()`

### Cambiare Delay tra Messaggi
Modifica il campo `delay_hours` nei template funnel in `get_presale_funnel_templates()`.

---

## ⚙️ Configurazione

### Telefono Sede
Il telefono viene recuperato automaticamente da:
1. Configurazione plugin (`disco747_company_phone`)
2. Configurazione di default (`06 123456789`)

### Email Sede
L'email viene recuperata automaticamente da:
1. Configurazione SMTP (`email_from_address`)
2. Configurazione di default (`info@747disco.it`)

---

## 📸 Immagini Gallery

Le email includono automaticamente immagini dalla gallery del sito:
- URL utilizzato: `https://747disco.it/wp-content/uploads/2025/11/Marco-74-1024x683.jpg`
- Puoi modificare l'URL nell'HTML del template se necessario

---

## ✅ Test

### Test Email
```php
$test_data = array(
    'nome_referente' => 'Test',
    'cognome_referente' => 'Cliente',
    'mail' => 'test@example.com',
    'data_evento' => '2025-12-25',
    'tipo_evento' => 'Festa Test',
    'numero_invitati' => 50,
    'tipo_menu' => 'Menu 74',
    'importo_preventivo' => 3500.00,
    'acconto' => 1000.00
);

$email_handler = new Disco747_Email();
$email_handler->send_presale_email($test_data);
```

### Test WhatsApp
```php
$whatsapp_handler = new Disco747_WhatsApp();
$url = $whatsapp_handler->generate_presale_whatsapp_message($test_data, 'step1_welcome');
echo "Apri questo link: " . $url;
```

---

## 🐛 Troubleshooting

### Email non inviate
- Verifica configurazione SMTP nelle impostazioni del plugin
- Controlla i log di WordPress (`wp-content/debug.log`)
- Verifica che l'email destinatario sia valida

### WhatsApp URL non generato
- Verifica che il campo `cellulare` sia presente nei dati
- Controlla formato numero telefono (deve essere numerico)

### Placeholder non sostituiti
- Verifica che tutti i dati siano presenti in `$preventivo_data`
- Controlla i nomi dei placeholder (case-sensitive)

---

## 📞 Supporto

Per domande o problemi, contatta il team di sviluppo o consulta la documentazione completa del plugin.
