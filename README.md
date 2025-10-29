# WP Feedback Plugin (AJAX)

Plugin WordPress minimal per raccogliere feedback via form AJAX.  
Sviluppato da Giacomo Lanzi — Plan B Project.

## Caratteristiche
- Shortcode `[feedback_form]` per mostrare il form pubblico.
- Shortcode `[feedback_list]` per amministratori (lista, paginazione AJAX, dettaglio).
- Tabella custom creata all'attivazione.
- Validazione server-side, Nonce, capability checks.
- Nessuna dipendenza esterna (usa jQuery bundled in WP).
- Stile desktop only

## Installazione locale
1. Copia la cartella `wp-feedback-plugin` in `wp-content/plugins/`.
2. Attiva il plugin da **Plugin → Installed Plugins**.
3. Crea le pagine:
   - `Feedback` con lo shortcode `[feedback_form]`
   - `Feedback Entries` con lo shortcode `[feedback_list]` (visibile solo agli admin)

## Contributi
Se vuoi aggiungere miglioramenti (es. blocco Gutenberg, vanilla JS, tests), apri una PR.

## Licenza
GPL-2.0 — vedi `LICENSE`.
