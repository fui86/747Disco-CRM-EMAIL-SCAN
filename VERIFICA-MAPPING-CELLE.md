# ?? VERIFICA MAPPING CELLE EXCEL - PER L'UTENTE

## ? Problema Segnalato

Vengono letti male:
- ? Cellulare/Telefono
- ? Mail/Email
- ? Importo
- ? Acconto

## ? Mapping Attualmente Configurato

```php
// ?? Dati Referente
C11 ? nome_referente
C12 ? cognome_referente
C14 ? telefono  ? VERIFICA QUESTA CELLA
C15 ? email     ? VERIFICA QUESTA CELLA

// ?? Importi
C21 ? importo_totale  ? VERIFICA QUESTA CELLA
C23 ? acconto         ? VERIFICA QUESTA CELLA
```

## ?? Come Verificare

### **Metodo 1: Apri un file Excel di esempio**

1. Vai su Google Drive
2. Apri un file Excel dei preventivi (es: "CONF 11_12 Festa 18 anni.xlsx")
3. Verifica le celle:

**Telefono/Cellulare:**
- Vai alla cella **C14**
- Cosa c'? scritto? ____________
- ? il numero di telefono? **SI / NO**
- Se NO, in quale cella c'? il telefono? ____________

**Email/Mail:**
- Vai alla cella **C15**
- Cosa c'? scritto? ____________
- ? l'indirizzo email? **SI / NO**
- Se NO, in quale cella c'? l'email? ____________

**Importo Totale:**
- Vai alla cella **C21**
- Cosa c'? scritto? ____________ (es: ?1.590,00)
- ? l'importo totale del preventivo? **SI / NO**
- Se NO, in quale cella c'? l'importo totale? ____________

**Acconto:**
- Vai alla cella **C23**
- Cosa c'? scritto? ____________ (es: ?500,00)
- ? l'acconto versato? **SI / NO**
- Se NO, in quale cella c'? l'acconto? ____________

### **Metodo 2: Screenshot**

Puoi inviarmi uno **screenshot dell'Excel** con le celle visibili? Cos? vedo direttamente la struttura.

## ?? Template Possibili

### **Ipotesi 1: Template Standard**
```
C11 = Nome
C12 = Cognome
C14 = Telefono
C15 = Email
C21 = Importo
C23 = Acconto
```

### **Ipotesi 2: Template con Offset**
```
C11 = Nome
C12 = Cognome
C13 = Telefono  ? Forse qui?
C14 = Email     ? Forse qui?
C20 = Importo   ? Forse qui?
C22 = Acconto   ? Forse qui?
```

### **Ipotesi 3: Colonna Diversa**
```
B11 = Nome
B12 = Cognome
B14 = Telefono  ? Forse colonna B invece di C?
B15 = Email
B21 = Importo
B23 = Acconto
```

## ?? Correzione Rapida

Una volta verificate le celle corrette, dimmi esattamente:

```
TELEFONO ? nella cella: _____
EMAIL ? nella cella: _____
IMPORTO ? nella cella: _____
ACCONTO ? nella cella: _____
```

E corregger? immediatamente il codice! ??
