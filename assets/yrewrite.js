$(document).on('click', '.yrewrite-copy-htaccess', function (event) {
    var message = $(this).data('confirm');

    if (message && !window.confirm(message)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
});
