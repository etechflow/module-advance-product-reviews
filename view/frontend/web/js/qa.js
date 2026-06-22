/**
 * ETechFlow_AdvancedProductReviews — product Q&A interactions
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
define(['jquery', 'mage/translate'], function ($, $t) {
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
                showLoader: false
            }).done(function (res) {
                $msg.text(res.message || '');
                $msg.toggleClass('etf-success', !!res.success)
                    .toggleClass('etf-error', !res.success);

                if (res.success) {
                    // Show the new question inline (when approved + returned) so
                    // the user doesn't have to reload the page.
                    if (res.approved && res.question) {
                        var $list = $root.find('.etf-qa-list');
                        if (!$list.length) {
                            $root.find('.etf-qa-empty').remove();
                            $list = $('<ol class="etf-qa-list"></ol>');
                            $form.before($list);
                        }
                        var q = res.question;
                        var $li = $('<li class="etf-qa-item"></li>');
                        var $q = $('<div class="etf-question"></div>');
                        $('<span class="etf-q-mark"></span>').text('Q').appendTo($q);
                        $('<span class="etf-q-text"></span>').text(q.question || '').appendTo($q);
                        $li.append($q);
                        $('<div class="etf-q-meta"></div>')
                            .text($t('Asked by %1').replace('%1', q.author_name || ''))
                            .appendTo($li);
                        $list.append($li);
                    }
                    $form.find('textarea[name=question]').val('');
                }
            }).fail(function () {
                $msg.text($t('Could not submit your question. Please try again.'))
                    .addClass('etf-error');
            });
        });
    };
});
