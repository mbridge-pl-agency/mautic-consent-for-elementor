(function ($) {
    $(function () {
        $('#wpme-test-connection').on('click', function (e) {
            e.preventDefault();
            const $btn = $(this);
            const $out = $('#wpme-test-result');
            $btn.prop('disabled', true);
            $out.text('Sprawdzam...');

            $.post(WPME_ADMIN.ajax_url, {
                action: 'wpme_test_connection',
                nonce: WPME_ADMIN.nonce,
            }).done(function (resp) {
                if (resp && resp.success) {
                    const $span = $('<span/>').css('color', 'green').text('✔ ' + resp.data.message);
                    $out.empty().append($span);
                } else {
                    const msg = resp && resp.data && resp.data.message ? resp.data.message : 'Nieznany błąd';
                    const $span = $('<span/>').css('color', 'red').text('✖ ' + msg);
                    $out.empty().append($span);
                }
            }).fail(function () {
                const $span = $('<span/>').css('color', 'red').text('✖ Błąd sieci');
                $out.empty().append($span);
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    });
})(jQuery);
