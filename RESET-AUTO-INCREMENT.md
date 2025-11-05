# 🔄 Come Resettare AUTO_INCREMENT Tabella Preventivi

**Problema:** Dopo aver cancellato i record, gli ID ripartono dall'ultimo numero invece di 1.

**Causa:** MySQL mantiene il contatore AUTO_INCREMENT anche dopo DELETE.

---

## ✅ Soluzione Automatica (CONSIGLIATA)

### Usa il Pulsante "Svuota e Rianalizza"

Dalla versione v3.1+, il pulsante **"Svuota e Rianalizza"** nella pagina "Scansione File Excel":

1. ✅ Cancella tutti i record
2. ✅ **Resetta AUTO_INCREMENT a 1** (NUOVO!)
3. ✅ Rilancia la scansione

**Questo è il metodo consigliato!**

---

## 🔧 Soluzione Manuale (Se hai già cancellato i record)

Se hai già cancellato i record manualmente e vuoi solo resettare l'AUTO_INCREMENT:

### Opzione 1: phpMyAdmin (Più Facile)

1. Accedi a **phpMyAdmin**
2. Seleziona il tuo database WordPress
3. Clicca su **"SQL"** nel menu in alto
4. Incolla questa query:

```sql
ALTER TABLE wp_disco747_preventivi AUTO_INCREMENT = 1;
```

5. Clicca **"Esegui"**
6. ✅ Fatto! Il prossimo record avrà ID = 1

---

### Opzione 2: Da Codice PHP (Temporaneo)

Se non hai accesso a phpMyAdmin, puoi eseguire la query da WordPress.

**ATTENZIONE:** Questo è un metodo temporaneo, rimuovi il codice subito dopo.

#### Step 1: Aggiungi il Codice

Aggiungi questo codice **in fondo** a `wp-config.php` (prima di `/* That's all, stop editing! */`):

```php
// ⚠️ TEMPORANEO: Reset AUTO_INCREMENT
add_action('init', function() {
    if (current_user_can('manage_options')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_preventivi';
        $wpdb->query("ALTER TABLE {$table_name} AUTO_INCREMENT = 1");
        echo '<div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#4caf50;color:white;padding:20px;border-radius:8px;z-index:9999;">✅ AUTO_INCREMENT resettato a 1!</div>';
        die(); // Ferma l'esecuzione per vedere il messaggio
    }
});
```

#### Step 2: Visita Qualsiasi Pagina del Sito

Accedi come amministratore e visita qualsiasi pagina del sito (es. homepage).

Vedrai il messaggio: **"✅ AUTO_INCREMENT resettato a 1!"**

#### Step 3: RIMUOVI IL CODICE IMMEDIATAMENTE

**IMPORTANTE:** Dopo aver visto il messaggio, **rimuovi subito il codice** da `wp-config.php`.

Se non lo rimuovi, la query verrà eseguita ad ogni caricamento di pagina (pessime performance).

---

### Opzione 3: Script SQL Standalone

Se hai accesso SSH al server, puoi eseguire questo script:

```bash
# Sostituisci con le tue credenziali
mysql -u USERNAME -p DATABASE_NAME << EOF
ALTER TABLE wp_disco747_preventivi AUTO_INCREMENT = 1;
SELECT AUTO_INCREMENT FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'wp_disco747_preventivi';
EOF
```

L'output dovrebbe mostrare:
```
AUTO_INCREMENT
1
```

---

## 🔍 Verifica Reset Riuscito

### Da phpMyAdmin

1. Seleziona la tabella `wp_disco747_preventivi`
2. Clicca su **"Operazioni"**
3. Guarda la sezione **"Opzioni tabella"**
4. Cerca **"AUTO_INCREMENT"**
5. Dovrebbe mostrare **"1"**

### Da SQL

```sql
SELECT AUTO_INCREMENT 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'wp_disco747_preventivi';
```

Se l'output è `1`, il reset è riuscito! ✅

---

## 📊 Esempio Prima/Dopo

### Prima del Reset
```
Ultimo record cancellato: ID 278
Nuovo record inserito: ID 279 ← Continua dalla sequenza
```

### Dopo il Reset
```
Ultimo record cancellato: ID 278
Reset AUTO_INCREMENT eseguito
Nuovo record inserito: ID 1 ← Riparte da 1! ✅
```

---

## 🚨 Troubleshooting

### "Access denied; you need the ALTER privilege"

**Problema:** L'utente del database non ha permessi ALTER.

**Soluzione:**
1. Contatta il provider hosting
2. Chiedi di aggiungere il permesso `ALTER` all'utente del database
3. Oppure chiedi di eseguire la query per te

### "Table 'database.wp_disco747_preventivi' doesn't exist"

**Problema:** Nome tabella o database errato.

**Soluzione:**
Verifica il nome esatto:
```sql
SHOW TABLES LIKE '%preventivi%';
```

Poi usa il nome corretto nella query ALTER TABLE.

### Reset non funziona (AUTO_INCREMENT resta alto)

**Causa:** Ci sono ancora record nella tabella.

**Soluzione:**
```sql
-- Verifica se ci sono record
SELECT COUNT(*) FROM wp_disco747_preventivi;

-- Se ci sono record, cancellali prima
DELETE FROM wp_disco747_preventivi;

-- Poi resetta
ALTER TABLE wp_disco747_preventivi AUTO_INCREMENT = 1;
```

---

## ✅ Riepilogo

| Metodo | Difficoltà | Quando Usarlo |
|--------|-----------|---------------|
| **Pulsante "Svuota e Rianalizza"** | 🟢 Facile | Sempre (automatico) ⭐ CONSIGLIATO |
| **phpMyAdmin SQL** | 🟢 Facile | Dopo cancellazione manuale |
| **Codice in wp-config.php** | 🟡 Medio | Senza phpMyAdmin |
| **SSH/MySQL CLI** | 🔴 Avanzato | Accesso server diretto |

---

## 📝 Note Aggiuntive

- ✅ Il reset AUTO_INCREMENT è **sicuro** e **non influisce sui dati**
- ✅ Se hai riferimenti FK (foreign keys) ad altri preventivi, **attenzione ai duplicati**
- ✅ Dopo il reset, i nuovi ID ripartiranno da 1
- ✅ Se vuoi ripartire da un numero specifico (es. 100):
  ```sql
  ALTER TABLE wp_disco747_preventivi AUTO_INCREMENT = 100;
  ```

---

**Autore:** Assistant (Background Agent)  
**Data:** 2025-11-05  
**Versione Plugin:** 11.8.0+
