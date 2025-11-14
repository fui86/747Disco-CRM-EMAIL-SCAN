# 📊 Tracking & Analytics Guide - Email Campaign

## 🎯 OBIETTIVO

Tracciare con precisione le performance della campagna email per ottimizzare ROI e prendere decisioni data-driven.

---

## 🔗 PARAMETRI UTM

### Cos'è UTM?

UTM (Urchin Tracking Module) sono parametri aggiunti agli URL per tracciare sorgente, campagna e comportamento degli utenti in Google Analytics.

### Struttura Base UTM

```
https://tuosito.it/pagina?utm_source=XXX&utm_medium=XXX&utm_campaign=XXX&utm_content=XXX&utm_term=XXX
```

---

## 🛠️ UTM PER QUESTA CAMPAGNA

### Link WhatsApp Principale (CTA Button)

```
https://wa.me/393471811119?text=Ciao!%20Vorrei%20bloccare%20i%20drink%20per%20il%20mio%20evento%20del%20{{data_evento}}&utm_source=email&utm_medium=email_marketing&utm_campaign=optional_drink_pre_evento&utm_content=cta_button_whatsapp&utm_term=pacchetto_combo
```

**Breakdown:**
- `utm_source=email` → Sorgente: email
- `utm_medium=email_marketing` → Mezzo: email marketing
- `utm_campaign=optional_drink_pre_evento` → Campagna specifica
- `utm_content=cta_button_whatsapp` → Contenuto: quale CTA
- `utm_term=pacchetto_combo` → Termine: quale offerta

---

### Link Offerta 1 (Drink Singoli)

Se hai una landing page dedicata invece di WhatsApp:

```
https://747disco.it/optional-drink?utm_source=email&utm_medium=email_marketing&utm_campaign=optional_drink_pre_evento&utm_content=card_drink_singoli&utm_term=drink_4euro
```

---

### Link Offerta 2 (Pacchetto Combo)

```
https://747disco.it/optional-pacchetto?utm_source=email&utm_medium=email_marketing&utm_campaign=optional_drink_pre_evento&utm_content=card_pacchetto_combo&utm_term=pacchetto_300euro
```

---

### Link Logo / Header

```
https://747disco.it?utm_source=email&utm_medium=email_marketing&utm_campaign=optional_drink_pre_evento&utm_content=header_logo
```

---

## 📱 UTM PER A/B TEST

### Subject Line A

Aggiungi `&utm_term=subject_a` a tutti i link:

```
...&utm_term=subject_a_risparmio
```

### Subject Line B

Aggiungi `&utm_term=subject_b` a tutti i link:

```
...&utm_term=subject_b_urgency
```

Questo ti permette di vedere quale subject genera più conversioni, non solo open rate.

---

## 🔧 TOOL PER GENERARE UTM

### Google Campaign URL Builder (Gratuito)

https://ga-dev-tools.google/campaign-url-builder/

**Come usarlo:**
1. Inserisci URL base
2. Compila i campi UTM
3. Copia URL generato
4. Usa nell'email

### Alternative:

- **UTM.io** - https://utm.io (gratuito, con tracking history)
- **Bitly** - Accorcia URL e traccia click
- **Google Sheets Template** - Crea spreadsheet con formule

---

## 📊 CONFIGURAZIONE GOOGLE ANALYTICS

### GA4 (Google Analytics 4)

#### Setup:

1. **Eventi da tracciare:**
   - `email_click` (qualsiasi click da email)
   - `whatsapp_click` (click su CTA WhatsApp)
   - `offer_view` (scroll fino all'offerta)
   - `purchase` (conversione finale)

2. **Parametri personalizzati:**
   ```javascript
   {
     event_category: 'email_campaign',
     event_label: 'optional_drink',
     value: 300 // Valore medio ordine
   }
   ```

3. **Conversioni:**
   - Definisci "whatsapp_click" come conversione
   - Definisci "purchase" come conversione
   - Assegna valore monetario

---

### Universal Analytics (se ancora attivo)

#### Custom Dimensions:

1. **Email Campaign Name** (Dimensione 1)
   - Valore: "optional_drink_pre_evento"

2. **Email Variant** (Dimensione 2)
   - Valore: "subject_a" o "subject_b"

3. **Event Date** (Dimensione 3)
   - Valore: {{data_evento}}

4. **Customer Segment** (Dimensione 4)
   - Valore: "18_compleanno" / "aziendale" / etc.

---

## 📈 METRICHE DA MONITORARE

### Nel tuo Email Service Provider (ESP)

| Metrica | Target | Come si calcola |
|---------|--------|-----------------|
| **Delivered** | >98% | Email recapitate / Email inviate |
| **Open Rate** | >28% | Aperture uniche / Email recapitate |
| **Click Rate** | >4% | Click unici / Email recapitate |
| **CTOR** | >15% | Click unici / Aperture uniche |
| **Bounce Rate** | <2% | Bounce / Email inviate |
| **Unsubscribe** | <0.5% | Unsub / Email recapitate |

---

### In Google Analytics

| Metrica | Target | Dove trovarla |
|---------|--------|---------------|
| **Sessions** | - | Acquisition > Campaigns |
| **Conversions** | >8% | Conversions > Multi-channel Funnels |
| **Revenue** | >€1500 | Ecommerce > Overview |
| **Avg Session Duration** | >2min | Behavior > Site Content |
| **Bounce Rate** | <40% | Behavior > Landing Pages |

---

### Dashboard Custom (da creare)

**Report da costruire in GA4:**

```
Nome Report: Email Campaign Performance - Optional Drink

Dimensioni:
- utm_campaign
- utm_content  
- utm_term
- Event date

Metriche:
- Sessions
- Conversions
- Revenue
- Conversion rate
- Average order value

Filtri:
- utm_campaign = "optional_drink_pre_evento"
```

---

## 📧 TRACKING A LIVELLO EMAIL

### Pixel di Tracking (Open tracking)

Tutte le piattaforme ESP lo fanno automaticamente, ma se implementi manualmente:

```html
<img src="https://tuoserver.it/track/open?email={{email}}&campaign=optional_drink&timestamp={{timestamp}}" width="1" height="1" style="display:none">
```

### Click Tracking

Le piattaforme ESP wrappano automaticamente i tuoi link con redirect tracking:

```
Link originale:
https://wa.me/393471811119

Link trackato da ESP:
https://tuoesp.com/track/click/abc123xyz
↓ (redirect)
https://wa.me/393471811119?utm_source=...
```

---

## 🎯 EVENTI PERSONALIZZATI DA TRACCIARE

### Event: Email Sent

```javascript
gtag('event', 'email_sent', {
  'campaign_name': 'optional_drink_pre_evento',
  'recipient_email': '{{email}}',
  'event_date': '{{data_evento}}'
});
```

### Event: Email Opened

```javascript
gtag('event', 'email_open', {
  'campaign_name': 'optional_drink_pre_evento',
  'subject_variant': 'subject_a'
});
```

### Event: CTA Clicked

```javascript
gtag('event', 'cta_click', {
  'campaign_name': 'optional_drink_pre_evento',
  'cta_type': 'whatsapp_button',
  'offer_type': 'pacchetto_combo'
});
```

### Event: Conversion (WhatsApp message sent)

**Nota:** Difficile tracciare automaticamente, richiede integrazione WhatsApp Business API.

**Workaround:** Chiedi al cliente come ha saputo dell'offerta.

---

## 📊 EXCEL/SHEETS TRACKING

Se non hai GA, traccia manualmente con spreadsheet:

### Template Spreadsheet

| Data | Subject Variant | Email Inviate | Delivered | Opened | Clicked | WhatsApp Msg | Conversioni | Revenue |
|------|----------------|---------------|-----------|--------|---------|--------------|-------------|---------|
| 14/11 | Variant A | 100 | 98 | 30 | 5 | 3 | 2 | €600 |
| 14/11 | Variant B | 100 | 99 | 35 | 8 | 5 | 3 | €900 |

**KPI Calcolati:**
- Open Rate = Opened / Delivered
- CTR = Clicked / Delivered
- Conversion Rate = Conversioni / Delivered
- Revenue per Email = Revenue / Email Inviate

---

## 🔍 ANALISI AVANZATA

### Funnel Analysis

1. **Email Inviata** (100%)
   ↓
2. **Email Consegnata** (98%)
   ↓
3. **Email Aperta** (30%)
   ↓
4. **CTA Cliccato** (5%)
   ↓
5. **WhatsApp Aperto** (4%)
   ↓
6. **Messaggio Inviato** (3%)
   ↓
7. **Conversione** (2%)

**Dove si perde gente?**
- Tra apertura e click → Copy/CTA non convincente
- Tra click e messaggio → Frizione su WhatsApp
- Tra messaggio e conversione → Prezzo/obiezioni

---

### Segmentazione Analysis

Analizza performance per segmento:

| Segmento | Open Rate | Conv Rate | AOV | Revenue |
|----------|-----------|-----------|-----|---------|
| 18° Compleanno | 32% | 12% | €280 | €1,120 |
| Eventi Aziendali | 28% | 8% | €350 | €980 |
| Matrimoni | 35% | 15% | €400 | €2,100 |

**Insight:** Matrimoni convertono meglio → investi più lì.

---

### Day/Time Analysis

Testa giorno e ora invio:

| Giorno | Ora | Open Rate | Click Rate | Conv Rate |
|--------|-----|-----------|------------|-----------|
| Lun | 10:00 | 22% | 2.5% | 5% |
| Mar | 19:00 | 31% | 5.2% | 10% |
| Mer | 14:00 | 25% | 3.8% | 7% |
| Gio | 20:00 | 33% | 6.1% | 12% |

**Insight:** Giovedì sera performa meglio → invia in quel slot.

---

## 🎨 HEATMAP ANALYSIS

### Tool consigliati:

**1. Litmus Email Analytics** ($99/mese)
- Heatmap click
- Client/device breakdown
- Location tracking

**2. MailChimp Analytics** (incluso)
- Click map su email
- Device breakdown
- Link performance

### Cosa cercare:

🔴 **Zone calde** (molti click):
- CTA principale
- Immagini
- Prezzi

❄️ **Zone fredde** (pochi click):
- Footer
- FAQ (potrebbe essere ok, info non azione)

**Ottimizzazione:** Sposta elementi importanti in zone calde.

---

## 💰 ROI CALCULATION

### Formula ROI:

```
ROI = [(Revenue - Costo) / Costo] × 100

Dove:
Revenue = Conversioni × Average Order Value
Costo = Costo piattaforma email + Tempo setup
```

### Esempio:

```
Revenue: 10 conversioni × €280 AOV = €2,800
Costo: 
  - Piattaforma email: €30/mese
  - Tempo setup: 3h × €30/h = €90
  - TOTALE: €120

ROI = [(2,800 - 120) / 120] × 100 = 2,233%
```

### Break-Even Point:

```
Conversioni necessarie per break-even:
= Costo / Average Order Value
= €120 / €280
= 0.43 conversioni

→ Serve meno di 1 vendita per rientrare nei costi!
```

---

## 📱 TRACKING WHATSAPP (AVANZATO)

### WhatsApp Business API

Se usi WhatsApp Business API, puoi tracciare:
- Messaggi ricevuti da campagna
- Conversazioni aperte
- Risposte date
- Conversioni chiuse

### Workaround Manuale:

**Metodo 1: UTM nel messaggio**

```
https://wa.me/393471811119?text=Ciao!%20Codice%20EMAIL-OPT-{{id_cliente}}%20...
```

Quando ricevi messaggio con quel codice, sai che viene da email.

**Metodo 2: Landing page intermedia**

```
Email → Landing page tracciata → WhatsApp
```

Pro: Puoi tracciare tutto in GA
Contro: Un passaggio in più (frizione)

---

## 🧪 A/B TEST TRACKING

### Setup Test:

**Variant A (50% traffico):**
- Subject: {{nome}}, risparmi 25€...
- utm_term=variant_a

**Variant B (50% traffico):**
- Subject: {{nome}}, solo 7 giorni...
- utm_term=variant_b

### Metriche da confrontare:

| Metrica | Variant A | Variant B | Winner |
|---------|-----------|-----------|--------|
| Open Rate | 30% | 35% | B 🏆 |
| Click Rate | 5% | 4.5% | A |
| Conv Rate | 8% | 11% | B 🏆 |
| Revenue | €1,200 | €1,680 | B 🏆 |
| **RPE*** | €6.00 | €8.40 | B 🏆 |

*Revenue Per Email

**Decisione:** Scaliamo Variant B!

### Significatività Statistica:

Usa tool come:
- **Optimizely Sample Size Calculator**
- **VWO A/B Test Duration Calculator**

Sample minimo: 300 email per variante per risultati affidabili.

---

## 📋 REPORT SETTIMANALE TEMPLATE

```
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CAMPAIGN PERFORMANCE - SETTIMANA 14-20 NOV
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📧 EMAIL METRICS:
- Inviate: 250
- Delivered: 245 (98%)
- Opened: 77 (31.4%) ↑ vs target 28%
- Clicked: 12 (4.9%) ↑ vs target 4%
- Unsubscribed: 1 (0.4%) ✅

💰 CONVERSION METRICS:
- WhatsApp messages: 8
- Conversioni: 6 (2.4%)
- AOV: €283
- Revenue: €1,698

📊 A/B TEST:
Winner: Subject B (urgency)
- Open Rate: 35% vs 28%
- Revenue: €980 vs €718

🎯 TOP PERFORMERS:
1. Pacchetto Combo: 4 vendite (€1,200)
2. Drink Singoli: 2 vendite (€400)

⚠️ ISSUES:
- 3 email bounce (indirizzi vecchi)
- 1 complaint spam

✅ ACTION ITEMS:
- Scalare Subject B su resto database
- Rimuovere indirizzi bounce
- Follow-up con 65 aperture senza click

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## 🚀 QUICK START TRACKING

**Setup minimo in 10 minuti:**

1. ✅ Aggiungi parametri UTM base ai link principali
2. ✅ Verifica che Google Analytics sia installato
3. ✅ Crea evento conversione personalizzato in GA
4. ✅ Setup goal per "cta_whatsapp_click"
5. ✅ Crea spreadsheet backup per dati email
6. ✅ Imposta reminder settimanale per report

**Non serve di più per iniziare!**

Ottimizzazioni avanzate vengono dopo.

---

## 📚 RISORSE

### Tool Gratuiti:
- Google Analytics (GA4)
- Google Campaign URL Builder
- UTM.io
- Google Sheets

### Tool Premium:
- Litmus ($99/mese) - Email analytics avanzato
- Mixpanel ($89/mese) - Product analytics
- Segment ($120/mese) - Customer data platform

### Guide:
- Google Analytics Academy (gratis)
- Email Geeks Community
- Litmus Blog

---

**Pro Tip Finale:**

Non farti paralizzare dall'analysis paralysis. 

**Setup minimo funzionante:**
1. UTM sui link principali
2. Google Analytics attivo
3. Conversione tracciata

**Il resto viene col tempo. Lancia e ottimizza! 🚀**

---

**Creato da:** Email Marketing Analytics Specialist
**Data:** 14 Novembre 2025
**Versione:** 1.0