-- ===================================================================
-- SCRIPT SQL - FIX TEMPLATE EMAIL FUNNEL 747 DISCO
-- ===================================================================
-- Questo script:
-- 1. Cancella i template corrotti esistenti
-- 2. Inserisce nuovi template Gmail-safe (senza tag <style>)
-- 3. Resetta i tracking attivi per ripartire puliti
-- ===================================================================

-- STEP 1: Backup (opzionale ma consigliato)
-- CREATE TABLE wp_disco747_funnel_sequences_backup AS SELECT * FROM wp_disco747_funnel_sequences;

-- STEP 2: Cancella template corrotti
DELETE FROM wp_disco747_funnel_sequences WHERE funnel_type = 'pre_conferma';

-- STEP 3: Inserisci nuovi template Gmail-safe

-- ===================================================================
-- TEMPLATE 1: "Serve una mano?" (Day +1)
-- ===================================================================
INSERT INTO wp_disco747_funnel_sequences 
(funnel_type, step_number, step_name, days_offset, send_time, email_enabled, email_subject, email_body, whatsapp_enabled, whatsapp_text, active)
VALUES (
    'pre_conferma',
    1,
    'Serve una mano?',
    1,
    '14:00:00',
    1,
    'Tutto chiaro? | 747 Disco',
    '<!doctype html>
<html>
<body style="margin:0;padding:0;background:#1a1a1a">
  <div style="display:none;font-size:1px;color:#1a1a1a;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">Omaggi bloccati per te (48 ore): Foto Pro, Crepes Nutella, Sicurezza, SIAE ~€200. Conferma in 1 minuto.</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a">
    <tr><td align="center" style="padding:0 12px">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin:0 auto;background:#1a1a1a;color:#ffffff">
        <tr><td style="padding:30px">
          
          <table role="presentation" width="100%"><tr><td align="center" style="padding-bottom:22px">
            <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="180" alt="747 Disco" style="width:180px;max-width:100%;height:auto">
          </td></tr></table>
          
          <table role="presentation" width="100%" style="background:linear-gradient(135deg,#c28a4d 0%,#a67c44 100%);border-radius:16px">
            <tr><td align="center" style="padding:24px 20px;border-radius:16px">
              <h1 style="margin:0;font-size:28px;line-height:1.25;font-weight:900;color:#ffffff">Serve una mano sul preventivo? 👋</h1>
              <p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#ffffff">Riguarda il tuo <strong>{{tipo_evento}}</strong> del <strong>{{data_evento}}</strong>.</p>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%"><tr><td style="padding:20px 0 6px 0">
            <p style="font-size:17px;color:#eaeaea;line-height:1.7;margin:0 0 8px">Ciao <strong>{{nome}}</strong>, è passato un giorno dal preventivo. Se hai dubbi su menu, orari o costi, rispondi pure: ti guidiamo in 2 minuti.</p>
            <p style="font-size:15px;color:#bfbfbf;line-height:1.6;margin:0">Intanto abbiamo <strong>bloccato per te</strong> gli omaggi qui sotto per altre <strong>48 ore</strong>.</p>
          </td></tr></table>
          
          <table role="presentation" width="100%" style="margin-top:14px;background:#fff4e3;border:2px solid #c28a4d;border-radius:16px;color:#2b1e1a">
            <tr><td style="padding:18px">
              <p style="margin:0 0 10px;font-weight:900;font-size:18px;text-align:center;color:#c28a4d">🎁 OMAGGI BLOCCATI PER TE — 48 ORE</p>
              <ul style="margin:0;padding-left:20px;line-height:1.9;font-size:14px">
                <li>📸 <strong>Servizio Fotografico Pro</strong> (~€250)</li>
                <li>🥞 <strong>Crepes alla Nutella</strong> (~€200)</li>
                <li>🛡️ <strong>Accoglienza & Sicurezza</strong> (~€180)</li>
                <li>🎼 <strong>SIAE Inclusa</strong> (~€200)</li>
              </ul>
              <p style="margin:10px 0 0;color:#7a5a00;font-size:13px;text-align:center">Valore totale: <strong>~€830</strong></p>
              <div style="text-align:center;margin-top:16px">
                <a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Confermo%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎁" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Conferma e mantieni gli omaggi</a>
              </div>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;background:#1a1a1a;border:3px solid #c28a4d;border-radius:16px">
            <tr><td align="center" style="padding:22px 18px">
              <p style="color:#d4a574;margin:0 0 12px;font-size:16px">Hai una domanda o vuoi bloccare tutto in 1 minuto?</p>
              <a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Ho%20ricevuto%20il%20preventivo%20per%20il%20{{data_evento}}%20👍" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Scrivici su WhatsApp</a>
              <p style="color:#8f8f8f;font-size:12px;margin:10px 0 0">Oppure rispondi a: <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a></p>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" style="margin-top:26px;border-top:1px solid #333">
            <tr><td align="center" style="padding:24px">
              <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="120" alt="747 Disco" style="width:120px;height:auto;opacity:.9;margin:0 0 12px">
              <p style="margin:0;color:#c28a4d;font-weight:700">747 DISCO</p>
              <p style="margin:6px 0;color:#d4a574;font-size:14px">La tua festa inizia qui</p>
              <p style="margin:15px 0 0;color:#999;font-size:12px;line-height:1.6">📧 <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a><br>📞 <a href="tel:+393471811119" style="color:#d4a574;text-decoration:none">+39 347 181 1119</a><br>📍 V.le J.F. Kennedy, 131 – Ciampino (RM)</p>
              <p style="margin-top:14px;font-size:11px;color:#666">Hai ricevuto questa email perché hai richiesto un preventivo (ID: {{preventivo_id}}).</p>
            </td></tr>
          </table>
          
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>',
    0,
    NULL,
    1
);

-- ===================================================================
-- TEMPLATE 2: "Ultimi posti" (Day +2)
-- ===================================================================
INSERT INTO wp_disco747_funnel_sequences 
(funnel_type, step_number, step_name, days_offset, send_time, email_enabled, email_subject, email_body, whatsapp_enabled, whatsapp_text, active)
VALUES (
    'pre_conferma',
    2,
    'Ultimi posti',
    2,
    '10:00:00',
    1,
    'Ultimi posti per la tua data | 747 Disco',
    '<!doctype html>
<html>
<body style="margin:0;padding:0;background:#1a1a1a">
  <div style="display:none;font-size:1px;color:#1a1a1a;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">La tua data {{data_evento}} è ancora disponibile ma le richieste aumentano. Blocca ora i tuoi omaggi!</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a">
    <tr><td align="center" style="padding:0 12px">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin:0 auto;background:#1a1a1a;color:#ffffff">
        <tr><td style="padding:30px">
          
          <table role="presentation" width="100%"><tr><td align="center" style="padding-bottom:22px">
            <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="180" alt="747 Disco" style="width:180px;max-width:100%;height:auto">
          </td></tr></table>
          
          <table role="presentation" width="100%" style="background:linear-gradient(135deg,#c28a4d 0%,#a67c44 100%);border-radius:16px">
            <tr><td align="center" style="padding:24px 20px;border-radius:16px">
              <h1 style="margin:0;font-size:28px;line-height:1.25;font-weight:900;color:#ffffff">La tua data è ancora libera... per poco ⏰</h1>
              <p style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#ffffff">Evento del <strong>{{data_evento}}</strong> — <strong>{{tipo_evento}}</strong></p>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%"><tr><td style="padding:22px 0 10px 0">
            <p style="font-size:17px;color:#eaeaea;line-height:1.7;margin:0 0 12px">Ciao <strong>{{nome}}</strong>, sappiamo che stai valutando anche altre location — è normale! Ma prima di decidere, vogliamo dirti una cosa chiara e semplice:</p>
            <p style="font-size:17px;color:#FFD700;line-height:1.7;margin:0 0 16px;text-align:center;font-weight:700">A questo prezzo, con questi servizi inclusi, <u>difficilmente troverai un pacchetto simile</u>.</p>
            <p style="font-size:15px;color:#bfbfbf;line-height:1.7;margin:0">Da noi non paghi solo la sala: hai <strong>staff, musica, sicurezza, catering e omaggi premium</strong> già inclusi. Nessuna sorpresa, nessun costo nascosto: solo una festa perfetta, chiavi in mano.</p>
          </td></tr></table>
          
          <table role="presentation" width="100%" style="margin-top:20px;background:#121212;border:2px solid #c28a4d;border-radius:14px">
            <tr><td style="padding:20px 18px">
              <h3 style="margin:0 0 10px;font-size:18px;color:#c28a4d">⭐ Recensioni reali, emozioni vere</h3>
              <p style="margin:0 0 8px;font-size:14px;line-height:1.7;color:#eaeaea">Centinaia di famiglie e ragazzi ci hanno scelto e ci hanno lasciato <strong>solo recensioni 5 stelle</strong> su Google.</p>
              <p style="margin:0;font-size:14px;color:#d4a574;line-height:1.7">"Tutto perfetto, organizzazione impeccabile."<br>"Location pazzesca, staff gentilissimo, serata indimenticabile."</p>
              <p style="margin:10px 0 0;color:#bfbfbf;font-size:13px">👉 <a href="https://www.google.com/search?q=747Disco+Recensioni" style="color:#FFD700;text-decoration:none">Leggi le recensioni</a></p>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" style="margin-top:25px;background:#fff4e3;border:2px solid #c28a4d;border-radius:14px;color:#2b1e1a">
            <tr><td style="padding:18px">
              <p style="margin:0 0 10px;font-weight:900;font-size:18px;text-align:center;color:#c28a4d">🎁 OMAGGI ANCORA BLOCCATI PER TE</p>
              <ul style="margin:0;padding-left:20px;line-height:1.9;font-size:14px">
                <li>📸 <strong>Servizio Fotografico Professionale</strong> (~€250)</li>
                <li>🍫 <strong>Crepes alla Nutella per tutti</strong> (~€200)</li>
                <li>🛡️ <strong>Accoglienza & Sicurezza dedicate</strong> (~€180)</li>
                <li>🎼 <strong>SIAE Inclusa</strong> (~€200)</li>
              </ul>
              <p style="margin:10px 0 0;color:#7a5a00;font-size:13px;text-align:center">Valore: <strong>oltre €800</strong> — inclusi nel tuo preventivo.</p>
              <div style="text-align:center;margin-top:16px">
                <a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Confermo%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎁" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Confermo e blocco i miei omaggi</a>
              </div>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;background:#1a1a1a;border:3px solid #c28a4d;border-radius:16px">
            <tr><td align="center" style="padding:22px 18px">
              <p style="color:#d4a574;margin:0 0 12px;font-size:16px">Scrivici ora e blocca il tuo evento con tutti i vantaggi inclusi 👇</p>
              <a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Voglio%20confermare%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎉" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Conferma ora su WhatsApp</a>
              <p style="color:#8f8f8f;font-size:12px;margin:10px 0 0">Oppure rispondi a: <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a></p>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" style="margin-top:24px;border-top:1px solid #333">
            <tr><td align="center" style="padding:24px">
              <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="120" alt="747 Disco" style="width:120px;height:auto;opacity:.9;margin:0 0 12px">
              <p style="margin:0;color:#c28a4d;font-weight:700">747 DISCO</p>
              <p style="margin:6px 0;color:#d4a574;font-size:14px">La tua festa inizia qui</p>
              <p style="margin:15px 0 0;color:#999;font-size:12px;line-height:1.6">📧 <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a><br>📞 <a href="tel:+393471811119" style="color:#d4a574;text-decoration:none">+39 347 181 1119</a><br>📍 V.le J.F. Kennedy, 131 – Ciampino (RM)</p>
              <p style="margin-top:14px;font-size:11px;color:#666">Hai ricevuto questa email perché hai richiesto un preventivo (ID: {{preventivo_id}}).</p>
            </td></tr>
          </table>
          
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>',
    0,
    NULL,
    1
);

-- ===================================================================
-- TEMPLATE 3: "Ultime 24 ore" (Day +3)
-- ===================================================================
INSERT INTO wp_disco747_funnel_sequences 
(funnel_type, step_number, step_name, days_offset, send_time, email_enabled, email_subject, email_body, whatsapp_enabled, whatsapp_text, active)
VALUES (
    'pre_conferma',
    3,
    'Ultime 24 ore',
    3,
    '09:00:00',
    1,
    'Ultime 24 Ore | 747 Disco',
    '<!doctype html>
<html>
<body style="margin:0;padding:0;background:#1a1a1a">
  <div style="display:none;font-size:1px;color:#1a1a1a;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">Ultime 24 ore per bloccare la tua data {{data_evento}} e mantenere i 4 omaggi esclusivi.</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#1a1a1a">
    <tr><td align="center" style="padding:0 12px">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin:0 auto;background:#1a1a1a;color:#ffffff">
        <tr><td style="padding:30px">
          
          <table role="presentation" width="100%"><tr><td align="center" style="padding-bottom:22px">
            <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="180" alt="747 Disco" style="width:180px;max-width:100%;height:auto">
          </td></tr></table>
          
          <table role="presentation" width="100%" style="background:#ff3b30;border:2px solid #5a0000;border-radius:14px">
            <tr><td align="center" style="padding:16px 14px">
              <span style="background:#1a1a1a;color:#ffd6d6;border:1px solid #4d0000;padding:6px 12px;border-radius:999px;font-weight:800;font-size:12px;display:inline-block">⏰ CONTA ALLA ROVESCIA</span>
              <h2 style="margin:10px 0 6px 0;color:#ffffff;font-size:22px;line-height:1.25;font-weight:900">ULTIME <span style="color:#ffd700">24 ORE</span> PER BLOCCARE LA TUA DATA</h2>
              <p style="margin:0;color:#ffd6d6;font-size:13px;line-height:1.5">Offerta all-inclusive + 4 omaggi ancora attivi fino a stasera</p>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" style="margin-top:20px;background:#121212;border:1px solid #ff5c5c;border-radius:12px">
            <tr><td style="padding:18px">
              <p style="margin:0 0 10px;color:#eaeaea;font-size:16px;line-height:1.7">Ciao <strong>{{nome}}</strong>, immaginiamo che tu stia valutando anche altre soluzioni — ma ti avvisiamo con la massima trasparenza:</p>
              <p style="margin:12px 0;color:#FFD700;font-weight:800;text-align:center;font-size:17px">⏳ Stiamo ricevendo <u>più richieste per la stessa data</u> e, alla prima conferma, il sistema chiude la disponibilità.</p>
              <p style="margin:10px 0 0;color:#eaeaea;font-size:15px;line-height:1.8">La tua data <strong>{{data_evento}}</strong> è ancora libera, ma <strong>gli omaggi scadranno tra poche ore</strong>.</p>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" style="margin-top:22px;background:#fff4e3;border:2px solid #c28a4d;border-radius:16px;color:#2b1e1a">
            <tr><td style="padding:18px">
              <p style="margin:0 0 10px;font-weight:900;font-size:18px;text-align:center;color:#c28a4d">🎁 ULTIME ORE PER I 4 OMAGGI ESCLUSIVI</p>
              <ul style="margin:0;padding-left:20px;line-height:1.9;font-size:14px">
                <li>📸 <strong>Servizio Fotografico Professionale</strong> (~€250)</li>
                <li>🍫 <strong>Crepes alla Nutella per tutti</strong> (~€200)</li>
                <li>🛡️ <strong>Accoglienza & Sicurezza dedicate</strong> (~€180)</li>
                <li>🎼 <strong>SIAE Inclusa</strong> (~€200)</li>
              </ul>
              <p style="margin:10px 0 0;color:#7a5a00;font-size:13px;text-align:center">Dopo la scadenza, gli omaggi si <strong>azzerrano automaticamente</strong>.</p>
              <div style="text-align:center;margin-top:16px">
                <a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Confermo%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎁" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Confermo ora e blocco la mia offerta</a>
              </div>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:22px;background:#1a1a1a;border:3px solid #c28a4d;border-radius:16px">
            <tr><td align="center" style="padding:22px 18px">
              <p style="color:#FFD700;margin:0 0 12px;font-size:17px;font-weight:700">⏰ Ultima chiamata: tra poche ore l\'offerta verrà chiusa.</p>
              <a href="https://wa.me/393471811119?text=Ciao%20sono%20{{nome}}%20{{cognome}}.%20Voglio%20confermare%20il%20{{tipo_evento}}%20del%20{{data_evento}}%20🎉" style="background:#25d366;color:#ffffff;text-decoration:none;padding:14px 26px;border-radius:40px;font-weight:800;border:3px solid #1fa855;display:inline-block">💬 Conferma ora su WhatsApp</a>
              <p style="color:#8f8f8f;font-size:12px;margin:10px 0 0">Oppure rispondi a: <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a></p>
            </td></tr>
          </table>
          
          <table role="presentation" width="100%" style="margin-top:24px;border-top:1px solid #333">
            <tr><td align="center" style="padding:24px">
              <img src="https://747disco.it/wp-content/uploads/2025/06/images.png" width="120" alt="747 Disco" style="width:120px;height:auto;opacity:.9;margin:0 0 12px">
              <p style="margin:0;color:#c28a4d;font-weight:700">747 DISCO</p>
              <p style="margin:6px 0;color:#d4a574;font-size:14px">La tua festa inizia qui</p>
              <p style="margin:15px 0 0;color:#999;font-size:12px;line-height:1.6">📧 <a href="mailto:eventi@747disco.it" style="color:#d4a574;text-decoration:none">eventi@747disco.it</a><br>📞 <a href="tel:+393471811119" style="color:#d4a574;text-decoration:none">+39 347 181 1119</a><br>📍 V.le J.F. Kennedy, 131 – Ciampino (RM)</p>
              <p style="margin-top:14px;font-size:11px;color:#666">Hai ricevuto questa email perché hai richiesto un preventivo (ID: {{preventivo_id}}).</p>
            </td></tr>
          </table>
          
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>',
    0,
    NULL,
    1
);

-- ===================================================================
-- VERIFICA: Controlla che i template siano stati inseriti
-- ===================================================================
SELECT id, step_number, step_name, days_offset, active, 
       LEFT(email_subject, 50) AS subject_preview,
       LENGTH(email_body) AS body_length
FROM wp_disco747_funnel_sequences 
WHERE funnel_type = 'pre_conferma'
ORDER BY step_number;

-- ===================================================================
-- OPZIONALE: Resetta tracking attivi per ripartire puliti
-- ===================================================================
-- ATTENZIONE: Questo cancella tutti i tracking attivi!
-- Decommenta solo se vuoi ripartire da zero
-- UPDATE wp_disco747_funnel_tracking SET status = 'stopped' WHERE status = 'active';
