/**
 * ETechFlow_AdvancedProductReviews — product Q&A interactions
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var submitUrl = $root.data('submit-url');

        $root.on('submit', '[data-role=etf-qa-form]', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $msg = $form.find('[data-role=etf-qa-message]');

            $.ajax({
                url: submitUrl,
                type: 'POST',
                dataType: 'json',
                data: $form.serialize(),
                showLoader: true
            }).done(function (res) {
                $msg.text(res.message || '');
                $msg.toggleClass('etf-success', !!res.success)
                    .toggleClass('etf-error', !res.success);
                if (res.success) {
                    $form.find('textarea[name=question]').val('');
                }
            }).fail(function () {
                $msg.text('Could not submit your question. Please try again.')
                    .addClass('etf-error');
            });
        });
    };
});
