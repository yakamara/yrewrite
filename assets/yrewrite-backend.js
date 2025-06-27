$(document).on('rex:ready', function() {
    $(document).on('click', '.yrewrite-copy-htaccess', function(e) {
        const msg = $(this).data('yrewrite-confirm-msg');
        if (!confirm(msg)) {
            e.preventDefault(); // blockiert z.B. Link-Klicks oder Form-Submits
            e.stopImmediatePropagation(); // verhindert, dass andere click-Handler noch feuern
            return false;
        }
        // Wenn OK gedrückt wurde, läuft es weiter
    });
});
