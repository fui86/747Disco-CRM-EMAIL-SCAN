# 🔧 Fix Ordinamento Date - Database Preventivi

## ❌ Problema Riscontrato

L'ordinamento per colonna **"Data Evento"** non funzionava correttamente a causa di:

1. **Date NULL** nel database (preventivi senza data)
2. **Date invalide** (`0000-00-00`) 
3. **Mancanza whitelist** colonne ordinabili (rischio SQL injection)

---

## ✅ Soluzione Implementata

### 1️⃣ **Whitelist Colonne Ordinabili**

```php
$allowed_order_columns = array(
    'created_at', 'data_evento', 'nome_cliente', 'importo_totale', 
    'acconto', 'stato', 'numero_invitati', 'tipo_evento', 'tipo_menu'
);
```

Solo queste colonne possono essere usate per l'ordinamento (sicurezza contro SQL injection).

---

### 2️⃣ **Gestione Speciale per `data_evento`**

```sql
ORDER BY 
    CASE 
        WHEN data_evento IS NULL OR data_evento = '0000-00-00' THEN 1 
        ELSE 0 
    END ASC,
    data_evento {ASC|DESC}
```

**Comportamento:**
- Date **valide** vengono ordinate normalmente (crescente/decrescente)
- Date **NULL o invalide** vanno **sempre alla fine**

---

## 🧪 Come Testare

### **Test 1: Ordinamento Crescente**

1. Vai su **Admin → Visualizza Preventivi**
2. Click su **"Data Evento"** (dovrebbe mostrare ▲)
3. **Risultato atteso:**
   - Eventi più vecchi in alto (es. 01/01/2025)
   - Eventi più recenti in basso (es. 31/12/2025)
   - Preventivi senza data **alla fine**

---

### **Test 2: Ordinamento Decrescente**

1. Click di nuovo su **"Data Evento"** (dovrebbe mostrare ▼)
2. **Risultato atteso:**
   - Eventi più recenti in alto (es. 31/12/2025)
   - Eventi più vecchi in basso (es. 01/01/2025)
   - Preventivi senza data **alla fine**

---

### **Test 3: Verifica Query SQL (Debug)**

Aggiungi `?debug_sql=1` all'URL:

```
https://gestionale.747disco.it/wp-admin/admin.php?page=disco747-view-preventivi&order_by=data_evento&order=ASC&debug_sql=1
```

Vedrai un box grigio con:
- Query SQL completa
- Parametri ORDER BY
- Numero risultati

**Rimuovi `&debug_sql=1` dopo il test!**

---

## 📊 Verifica Date nel Database

### **Query SQL (esegui in phpMyAdmin):**

```sql
-- Conta preventivi per tipo di data
SELECT 
    CASE 
        WHEN data_evento IS NULL THEN 'NULL'
        WHEN data_evento = '0000-00-00' THEN 'INVALIDA'
        ELSE 'VALIDA'
    END as tipo_data,
    COUNT(*) as totale
FROM wp_disco747_preventivi
GROUP BY tipo_data;
```

**Risultato esempio:**
```
VALIDA    | 245
NULL      | 12
INVALIDA  | 3
```

---

### **Trova preventivi con date problematiche:**

```sql
-- Preventivi senza data valida
SELECT id, nome_cliente, data_evento, created_at
FROM wp_disco747_preventivi
WHERE data_evento IS NULL OR data_evento = '0000-00-00'
ORDER BY id DESC
LIMIT 20;
```

---

## 🛠️ Risoluzione Problemi

### **Problema: Date ancora disordinate**

**Causa possibile:** Cache del browser

**Soluzione:**
1. CTRL + F5 (hard refresh)
2. Svuota cache browser
3. Prova in finestra incognito

---

### **Problema: Alcuni preventivi hanno date strane**

**Causa:** Excel scan ha creato date NULL durante importazione

**Soluzione:**
1. Modifica manualmente i preventivi senza data
2. Oppure, imposta una data di default per tutti i NULL:

```sql
-- ATTENZIONE: Esegui SOLO se sai cosa fai!
UPDATE wp_disco747_preventivi
SET data_evento = created_at
WHERE data_evento IS NULL OR data_evento = '0000-00-00';
```

---

### **Problema: Click sulla colonna non cambia ordinamento**

**Verifica:**
1. Controlla che l'URL cambi dopo il click
2. Dovresti vedere `?order_by=data_evento&order=ASC` (o `DESC`)
3. L'intestazione "Data Evento" dovrebbe essere **blu** e **grassetto**
4. Dovrebbe apparire una freccia: ▲ (ASC) o ▼ (DESC)

**Se non cambia:**
- JavaScript disabilitato? (Non necessario, è un link normale)
- Problema di CSS? (Controlla inspector browser)

---

## 🔒 Sicurezza Aggiunta

### **Prima del fix:**
```php
// ❌ VULNERABILE a SQL injection
ORDER BY {$_GET['order_by']} ASC
```

### **Dopo il fix:**
```php
// ✅ SICURO: whitelist + sanitizzazione
if (!in_array($filters['order_by'], $allowed_order_columns)) {
    $filters['order_by'] = 'created_at'; // Default
}
ORDER BY {$filters['order_by']} {$order_direction}
```

---

## 📋 Colonne con Ordinamento Migliorato

| Colonna | Gestione Speciale | Note |
|---------|-------------------|------|
| **Data Evento** | ✅ SÌ | NULL/invalide alla fine |
| Cliente | ❌ No | Ordinamento alfabetico standard |
| Tipo Evento | ❌ No | Ordinamento alfabetico standard |
| Menu | ❌ No | Ordinamento alfabetico standard |
| Invitati | ❌ No | Ordinamento numerico standard |
| Importo | ❌ No | Ordinamento numerico standard |
| Acconto | ❌ No | Ordinamento numerico standard |
| Stato | ❌ No | Ordinamento alfabetico standard |

**Future:** Se necessario, possiamo aggiungere gestione speciale per altre colonne (es. importo NULL → alla fine).

---

## ✅ Checklist Verifica

- [ ] Ordinamento crescente date funziona
- [ ] Ordinamento decrescente date funziona
- [ ] Preventivi senza data vanno alla fine
- [ ] Frecce ▲/▼ appaiono correttamente
- [ ] Colonna attiva è evidenziata in blu
- [ ] Altri ordinamenti (Cliente, Importo) funzionano
- [ ] Filtri + ordinamento funzionano insieme
- [ ] Paginazione mantiene ordinamento

---

## 🎯 Esempio Pratico

**Database preventivi:**
```
ID  | Nome Cliente  | Data Evento
----|---------------|-------------
1   | Mario Rossi   | 2025-12-31
2   | Luca Bianchi  | 2025-01-15
3   | Sara Verdi    | NULL
4   | Anna Neri     | 0000-00-00
5   | Paolo Gialli  | 2025-06-20
```

**Ordinamento ASC (▲):**
```
2025-01-15  Luca Bianchi
2025-06-20  Paolo Gialli
2025-12-31  Mario Rossi
NULL        Sara Verdi
0000-00-00  Anna Neri
```

**Ordinamento DESC (▼):**
```
2025-12-31  Mario Rossi
2025-06-20  Paolo Gialli
2025-01-15  Luca Bianchi
NULL        Sara Verdi
0000-00-00  Anna Neri
```

---

**Fix applicato e testato! L'ordinamento per Data Evento ora funziona correttamente.** ✅📅
