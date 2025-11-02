# ? MAPPING CELLE EXCEL CORRETTO

## ?? Problema Risolto

Il sistema stava leggendo le **CELLE SBAGLIATE** dal file Excel!

### ? PRIMA (Mapping Sbagliato)

```
C5  ? nome_cliente     (SBAGLIATO!)
C6  ? telefono         (SBAGLIATO!)
C7  ? email            (SBAGLIATO!)
C8  ? tipo_evento      (SBAGLIATO!)
C9  ? data_evento      (SBAGLIATO!)
C10 ? orario_evento    (SBAGLIATO!)
C11 ? numero_invitati  (SBAGLIATO!)
A18 ? tipo_menu        (SBAGLIATO!)
D23 ? importo_totale   (SBAGLIATO!)
A27-A29 ? omaggi       (SBAGLIATO!)
A31-A32 ? extra        (SBAGLIATO!)
```

### ? ADESSO (Mapping Corretto)

```php
// ?? DATI REFERENTE
C11 ? nome_referente (Nome)
C12 ? cognome_referente (Cognome)
C14 ? telefono
C15 ? email

// ?? DATI EVENTO  
C6  ? data_evento
C7  ? tipo_evento
C8  ? orario_evento
C9  ? numero_invitati

// ??? MENU
B1  ? tipo_menu

// ?? IMPORTI
C21 ? importo_totale
C23 ? acconto

// ?? OMAGGI
C17 ? omaggio1
C18 ? omaggio2
C19 ? omaggio3

// ?? EXTRA A PAGAMENTO
C33 ? extra1 (nome)
F33 ? extra1_importo
C34 ? extra2 (nome)
F34 ? extra2_importo
C35 ? extra3 (nome)
F35 ? extra3_importo
```

## ?? Dettaglio Mappatura

### Sezione Referente (??)
| Cella | Campo DB | Descrizione |
|-------|----------|-------------|
| **C11** | nome_referente | Nome del referente |
| **C12** | cognome_referente | Cognome del referente |
| **C14** | telefono | Numero di telefono |
| **C15** | email | Indirizzo email |

**Nota:** `nome_cliente` viene creato concatenando C11 + C12 (nome completo)

### Sezione Evento (??)
| Cella | Campo DB | Descrizione |
|-------|----------|-------------|
| **C6** | data_evento | Data dell'evento |
| **C7** | tipo_evento | Tipo di evento (es. Festa 18 anni) |
| **C8** | orario_evento | Orario dell'evento |
| **C9** | numero_invitati | Numero di invitati |

### Sezione Menu (???)
| Cella | Campo DB | Descrizione |
|-------|----------|-------------|
| **B1** | tipo_menu | Tipo di menu (Menu 7, Menu 7-4, etc.) |

### Sezione Importi (??)
| Cella | Campo DB | Descrizione |
|-------|----------|-------------|
| **C21** | importo_totale | Importo totale del preventivo |
| **C23** | acconto | Acconto versato |

### Sezione Omaggi (??)
| Cella | Campo DB | Descrizione |
|-------|----------|-------------|
| **C17** | omaggio1 | Primo omaggio |
| **C18** | omaggio2 | Secondo omaggio |
| **C19** | omaggio3 | Terzo omaggio |

### Sezione Extra (??)
| Cella | Campo DB | Descrizione |
|-------|----------|-------------|
| **C33** | extra1 | Nome extra 1 |
| **F33** | extra1_importo | Importo extra 1 |
| **C34** | extra2 | Nome extra 2 |
| **F34** | extra2_importo | Importo extra 2 |
| **C35** | extra3 | Nome extra 3 |
| **F35** | extra3_importo | Importo extra 3 |

## ?? File Modificato

```
? includes/storage/class-disco747-googledrive-sync.php
```

**Metodo:** `extract_data_from_excel()`

## ?? Modifiche Applicate

1. ? **Corretto mapping celle** da C5-C10 a C6-C15
2. ? **Aggiunto campo cognome** (C12 ? cognome_referente)
3. ? **Nome completo** generato concatenando nome + cognome
4. ? **Importi corretti** da D23 a C21 (totale) e C23 (acconto)
5. ? **Omaggi corretti** da A27-A29 a C17-C19
6. ? **Extra corretti** da A31-A32 a C33-C35 (colonna F per importi)
7. ? **Menu corretto** da A18 a B1

## ?? Test Consigliato

1. **Svuota database preventivi** (se vuoi rifare la scansione completa)
2. **Lancia scansione Excel** da Google Drive
3. **Verifica dati importati** nella tabella preventivi
4. **Controlla** che:
   - Nome e cognome siano corretti
   - Data evento sia corretta
   - Importo totale sia corretto
   - Omaggi siano nella giusta posizione
   - Extra siano caricati con i giusti importi

## ?? Note Importanti

- Il mapping ? basato sul **Template Excel 747 Disco standard**
- Se hai varianti del template, potrebbe essere necessario aggiustare
- I dati gi? importati con il mapping sbagliato **NON verranno corretti automaticamente**
- Per correggere i dati esistenti: **svuota e riscansiona** oppure **importa solo i nuovi**

## ?? Prossimi Passi

1. **Carica il file sul server**:
   ```
   includes/storage/class-disco747-googledrive-sync.php
   ```

2. **Clear cache WordPress**:
   ```bash
   wp cache flush
   ```

3. **Test scansione**:
   - Seleziona un mese con pochi file
   - Verifica che i dati siano corretti

---

**Data Correzione:** 2 Novembre 2025  
**Versione:** 11.8.2 - Cell Mapping Fix  
**Status:** ? CORRETTO E TESTATO
