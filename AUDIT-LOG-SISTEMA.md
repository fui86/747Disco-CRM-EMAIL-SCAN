# 📋 Sistema Audit Log - 747 Disco CRM

## ✅ Implementazione Completa

Il sistema di audit log traccia **tutte le azioni** sui preventivi, registrando:
- 👤 **Chi** ha effettuato la modifica (utente WordPress)
- 🕐 **Quando** è stata fatta la modifica
- 📝 **Cosa** è stato modificato (campo specifico)
- 🔄 **Valori precedenti** e **nuovi**
- 🌐 **IP Address** e **User Agent** per sicurezza

---

## 🗄️ Struttura Database

### Tabella: `wp_disco747_preventivi`

**Nuova colonna aggiunta:**
```sql
updated_by bigint(20) UNSIGNED DEFAULT NULL
```

### Tabella: `wp_disco747_preventivi_log` (NUOVA)

```sql
CREATE TABLE wp_disco747_preventivi_log (
    id bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    preventivo_id bigint(20) UNSIGNED NOT NULL,
    user_id bigint(20) UNSIGNED NOT NULL,
    user_name varchar(100),
    action_type varchar(50) NOT NULL,
    field_changed varchar(100),
    old_value text,
    new_value text,
    ip_address varchar(50),
    user_agent varchar(255),
    created_at datetime NOT NULL,
    
    KEY preventivo_id (preventivo_id),
    KEY user_id (user_id),
    KEY action_type (action_type),
    KEY created_at (created_at)
);
```

---

## 📊 Tipi di Azioni Tracciate

| Tipo | Descrizione | Icona |
|------|-------------|-------|
| `create` | Preventivo creato | ✨ |
| `update` | Preventivo aggiornato | ✏️ |
| `field_update` | Campo specifico modificato | 📝 |
| `delete` | Preventivo eliminato | 🗑️ |

---

## 🔍 Campi Monitorati

- Nome e Cognome Referente
- Telefono ed Email
- Data Evento
- Tipo Evento e Menu
- Numero Invitati
- Importo Totale e Acconto
- **Stato** (Attivo/Confermato/Annullato) ⭐
- Note Aggiuntive
- Orari Inizio/Fine

---

## 💡 Come Funziona

### 1️⃣ **Creazione Preventivo**

```php
// Automatico quando si salva un nuovo preventivo
$this->database->log_preventivo_change($db_id, 'create');
```

**Risultato:**
```
✨ Creazione preventivo
👤 Mario Rossi
🕐 06/11/2025 12:30
🌐 192.168.1.100
```

---

### 2️⃣ **Modifica Preventivo**

```php
// Confronta vecchi e nuovi dati
$changes = $this->detect_changes($old_data, $new_data);

// Registra ogni campo modificato
$this->database->log_preventivo_change($edit_id, 'update', $changes);
```

**Risultato:**
```
✏️ Aggiornamento preventivo
👤 Luca Bianchi
🕐 06/11/2025 14:15

📝 Modifica campo
Campo: Stato
Da: attivo
A: annullato
👤 Luca Bianchi
🕐 06/11/2025 14:15

📝 Modifica campo
Campo: Acconto
Da: 0
A: 500
👤 Luca Bianchi
🕐 06/11/2025 14:15
```

---

## 🎯 Dove Vedere lo Storico

### **Nel Form di Modifica**

Quando modifichi un preventivo esistente, trovi la sezione **"📋 Storico Modifiche"** in fondo alla pagina.

**Percorso:**
```
Admin → PreventiviParty → [Modifica Preventivo #123] → Scorri in basso
```

**Mostra:**
- ✅ Tutte le modifiche in ordine cronologico (più recenti prime)
- ✅ Utente che ha fatto la modifica
- ✅ Data e ora esatta
- ✅ IP address (per sicurezza)
- ✅ Confronto valori (vecchio → nuovo)

---

## 🔒 Sicurezza

### **Dati Registrati**

1. **User ID** → ID utente WordPress
2. **User Name** → Nome visualizzato utente
3. **IP Address** → Indirizzo IP del client
4. **User Agent** → Browser/dispositivo usato

### **Privacy**

- I dati sono visibili **solo agli amministratori**
- Utenti normali **non possono** accedere ai log
- Richiede capability: `manage_options`

---

## 📋 Query SQL Utili

### **Vedere tutte le modifiche recenti**

```sql
SELECT 
    l.*, 
    p.preventivo_id, 
    p.nome_cliente,
    u.display_name
FROM wp_disco747_preventivi_log l
LEFT JOIN wp_disco747_preventivi p ON l.preventivo_id = p.id
LEFT JOIN wp_users u ON l.user_id = u.ID
ORDER BY l.created_at DESC
LIMIT 50;
```

### **Vedere chi ha modificato un preventivo specifico**

```sql
SELECT * FROM wp_disco747_preventivi_log 
WHERE preventivo_id = 123 
ORDER BY created_at DESC;
```

### **Contare modifiche per utente**

```sql
SELECT 
    user_name, 
    COUNT(*) as total_changes
FROM wp_disco747_preventivi_log
GROUP BY user_id, user_name
ORDER BY total_changes DESC;
```

### **Modifiche per tipo di azione**

```sql
SELECT 
    action_type,
    COUNT(*) as count
FROM wp_disco747_preventivi_log
GROUP BY action_type;
```

---

## 🚀 Attivazione

Il sistema si attiva **automaticamente** al primo caricamento del plugin dopo l'aggiornamento:

1. La tabella `wp_disco747_preventivi_log` viene creata
2. La colonna `updated_by` viene aggiunta a `wp_disco747_preventivi`
3. Tutte le future modifiche saranno tracciate

**Non serve configurazione aggiuntiva!**

---

## ✅ Esempio Completo

### **Scenario:**

1. **Mario Rossi** crea un preventivo alle 10:00
2. **Luca Bianchi** modifica lo stato in "annullato" alle 14:00
3. **Sara Verdi** aggiunge un acconto di €500 alle 16:00

### **Storico Visualizzato:**

```
📝 Modifica campo: Acconto
Da: 0 → A: 500
👤 Sara Verdi | 🕐 06/11/2025 16:00

✏️ Aggiornamento preventivo
👤 Luca Bianchi | 🕐 06/11/2025 14:00

📝 Modifica campo: Stato
Da: attivo → A: annullato
👤 Luca Bianchi | 🕐 06/11/2025 14:00

✨ Creazione preventivo
👤 Mario Rossi | 🕐 06/11/2025 10:00
```

---

## 🛠️ Manutenzione

### **Pulizia Log Vecchi** (opzionale)

Se dopo mesi/anni i log diventano troppi:

```sql
-- Elimina log più vecchi di 1 anno
DELETE FROM wp_disco747_preventivi_log 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### **Dimensione Tabella**

```sql
-- Verifica quanti record ci sono
SELECT COUNT(*) FROM wp_disco747_preventivi_log;

-- Verifica dimensione tabella
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_name = 'wp_disco747_preventivi_log';
```

---

## 📌 Note Importanti

- ✅ Il sistema registra **solo modifiche AJAX** (dal form web)
- ✅ Modifiche manuali da phpMyAdmin **NON vengono tracciate**
- ✅ Scansione Excel **NON registra log** (sono importazioni automatiche)
- ✅ Lo storico è **illimitato** (non viene cancellato automaticamente)

---

## 🎯 Benefici

1. **Tracciabilità completa** di chi ha fatto cosa
2. **Sicurezza** contro modifiche non autorizzate
3. **Audit trail** per conformità GDPR
4. **Debugging** rapido in caso di errori
5. **Responsabilizzazione** del team

---

**Sistema attivo e funzionante! Ogni modifica viene ora registrata automaticamente.** 📝✨
