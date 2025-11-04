# 🚀 Sistema Automazione Funnel - 747 Disco CRM

## 📋 DESCRIZIONE

Sistema di marketing automation per:
1. **Funnel Pre-Conferma**: Convertire preventivi in conferme con acconto
2. **Funnel Pre-Evento**: Vendere upsell e extra a pochi giorni dall'evento

---

## 🎯 COME FUNZIONA

### **FUNNEL PRE-CONFERMA** (Automatico)

**Quando si attiva:**
- Appena crei un nuovo preventivo NON confermato (acconto = 0)

**Cosa fa:**
- Invia email al cliente nei giorni che hai configurato (es: +0, +2, +4 giorni)
- Ti invia email con link WhatsApp per inviare manualmente il messaggio
- Si ferma automaticamente se il cliente conferma con acconto

**Esempio sequenza:**
```
Giorno 0: Email "Il tuo preventivo è pronto"
Giorno 2: Email "Hai visto il preventivo?"
Giorno 4: Email "Ultima chance per confermare!"
```

---

### **FUNNEL PRE-EVENTO** (Automatico)

**Quando si attiva:**
- Eventi confermati con data tra 7-14 giorni

**Cosa fa:**
- Invia email promozionali con offerte extra
- Ti avvisa via email per contatto WhatsApp upselling

**Esempio sequenza:**
```
-10 giorni: Email "Aggiungi il pacchetto VIP con sconto!"
-7 giorni: Email "Ultima possibilità per gli extra"
```

---

## 🛠️ CONFIGURAZIONE

### **1. Accedi alla pagina**
Menu WordPress: **PreventiviParty → 🚀 Automazione Funnel**

### **2. Scegli il TAB**
- **Funnel Pre-Conferma**: Per convertire preventivi
- **Funnel Pre-Evento**: Per vendere extra
- **Tracking Attivi**: Vedi i funnel in corso
- **Impostazioni**: Controlla stato cron e variabili

### **3. Configura gli Step**

Clicca **"➕ Aggiungi Step"**

**Campi da compilare:**
- **Numero Step**: 1, 2, 3... (ordine di invio)
- **Giorni Offset**: 
  - `0` = Subito
  - `+2` = Dopo 2 giorni
  - `-10` = 10 giorni prima dell'evento
- **Nome Step**: Es. "Follow-up iniziale"
- **Email Attiva**: ✅ Spunta per inviare email
- **Oggetto Email**: "Il tuo preventivo è pronto!"
- **Corpo Email**: Scrivi il testo (puoi usare variabili)
- **WhatsApp Attivo**: ✅ Spunta per ricevere notifica WhatsApp
- **Testo WhatsApp**: Messaggio da inviare manualmente
- **Step Attivo**: ✅ Spunta per attivare lo step

---

## 📝 VARIABILI DISPONIBILI

Usa queste variabili nei testi (email e WhatsApp):

| Variabile | Cosa mostra |
|-----------|-------------|
| `{{nome_referente}}` | Nome del cliente |
| `{{cognome_referente}}` | Cognome |
| `{{tipo_evento}}` | Es: "18° Compleanno" |
| `{{data_evento}}` | Es: "25/12/2025" |
| `{{numero_invitati}}` | Es: "50" |
| `{{tipo_menu}}` | Es: "Menu 7" |
| `{{importo_totale}}` | Es: "3.500,00" |
| `{{acconto}}` | Es: "1.000,00" |
| `{{telefono_sede}}` | Telefono 747 Disco |
| `{{email_sede}}` | info@747disco.it |

**Esempio uso:**
```
Ciao {{nome_referente}},

Il tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!

Importo: €{{importo_totale}}
Acconto per conferma: €{{acconto}}

Per confermare contattaci!
```

---

## 📱 COME FUNZIONA WHATSAPP

**Niente API a pagamento!** Sistema 100% gratuito:

1. **Email automatica al cliente** ✉️
   - Il sistema invia email al cliente

2. **Email notifica a TE** 📧
   - Ricevi email a: **info@747disco.it**
   - Contiene link **"📱 INVIA WHATSAPP ORA"**

3. **Tu clicchi il link** 👆
   - Si apre WhatsApp (da smartphone o desktop)
   - Messaggio già precompilato pronto

4. **Premi Invio** ✅
   - Invii manualmente dopo aver verificato

**Vantaggi:**
- Zero costi
- Controllo totale sui messaggi
- Puoi personalizzare al volo
- Nessun servizio esterno

---

## ⏰ TIMING & AUTOMATISMI

### **Cron Job (Automatico)**
Il sistema controlla **ogni ora** se ci sono email da inviare.

### **Check Pre-Evento (Automatico)**
Controlla **ogni giorno alle 09:00** se ci sono eventi da 7-14 giorni.

### **Quando si ferma il funnel?**
- ✅ Preventivo confermato (acconto pagato) → STOP Pre-Conferma
- ✅ Tutti gli step completati → STOP Automatico
- ⏸️ Messo in pausa manualmente dalla pagina "Tracking Attivi"

---

## 🔧 OPERAZIONI

### **Aggiungere uno Step**
1. Vai al tab corretto (Pre-Conferma o Pre-Evento)
2. Clicca **"➕ Aggiungi Step"**
3. Compila il form
4. Salva

### **Modificare uno Step**
1. Clicca **"✏️ Modifica"** sullo step
2. Modifica i campi
3. Salva

### **Disattivare uno Step (senza eliminarlo)**
1. Modifica lo step
2. Togli ✅ da "Step Attivo"
3. Salva

### **Eliminare uno Step**
1. Clicca **"🗑️"** sullo step
2. Conferma eliminazione

### **Vedere Funnel Attivi**
1. Vai al tab **"Tracking Attivi"**
2. Vedi lista preventivi in funnel
3. Puoi mettere in **⏸️ Pausa** se necessario

---

## 🧪 TEST MANUALE

### **Testare il Cron**
1. Vai a **Impostazioni** tab
2. Clicca **"🧪 Test Cron Manuale"**
3. Controlla i log di WordPress

### **Verificare Stato Cron**
Nella pagina vedrai:
- ✅ **ATTIVO** (tutto ok)
- ❌ **NON ATTIVO** (contatta sviluppatore)

---

## 📊 STATISTICHE

Nella dashboard vedrai:
- **Funnel Attivi**: Quanti preventivi sono in funnel ora
- **Completati Oggi**: Quanti funnel sono terminati oggi
- **Prossimo Check**: Quando sarà il prossimo invio automatico

---

## ❓ FAQ

### **"Come faccio a cambiare l'email notifiche WhatsApp?"**
Attualmente è fissata a `info@747disco.it`. 

### **"Posso inviare WhatsApp automatici?"**
No, per evitare costi API. Il sistema ti avvisa via email e tu invii manualmente con un click.

### **"Quanti step posso creare?"**
Illimitati! Puoi avere 3, 5, 10 step... come preferisci.

### **"Cosa succede se elimino un preventivo?"**
Il funnel associato rimane nel database ma non invierà più nulla.

### **"Posso avere timing negativi?"**
Sì! Usa `-10`, `-7` per il funnel Pre-Evento (giorni prima dell'evento).

### **"Il cliente riceve troppe email?"**
Puoi disattivare singoli step o mettere in pausa il funnel dalla pagina Tracking.

---

## 🆘 SUPPORTO

Se qualcosa non funziona:

1. **Controlla i Log WordPress**
   - Cerca `[747Disco-Funnel]` nei log
   
2. **Verifica Cron Attivo**
   - Vai in Impostazioni tab
   - Controlla che sia ✅ ATTIVO

3. **Testa Manualmente**
   - Usa il pulsante "🧪 Test Cron Manuale"

4. **Controlla Database**
   - Tabelle: `wp_disco747_funnel_sequences` e `wp_disco747_funnel_tracking`

---

## 📦 FILE SISTEMA

```
includes/funnel/
├── class-disco747-funnel-database.php     (Gestione DB)
├── class-disco747-funnel-manager.php      (Logica core)
├── class-disco747-funnel-scheduler.php    (Cron jobs)
└── README-FUNNEL.md                        (Questa guida)

includes/admin/views/
└── funnel-automation-page.php              (Interfaccia admin)
```

---

## ✅ CHECKLIST PRIMA PARTENZA

- [ ] Plugin attivato/riattivato (per creare tabelle)
- [ ] Controllato tab "Impostazioni" → Cron ✅ ATTIVO
- [ ] Configurato almeno 1 step nel "Funnel Pre-Conferma"
- [ ] Testato creando un preventivo nuovo
- [ ] Controllato email di test arrivata a info@747disco.it

---

🎉 **Sistema Pronto!** Ora il funnel lavora per te 24/7!
