/**
 * ETechFlow_AdvancedProductReviews — review list interactions
 *
 * Handles "was this helpful?" voting, comment submission, client-side
 * filtering (recommended / verified / with media) and sorting.
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var voteUrl = $root.data('vote-url');
        var commentUrl = $root.data('comment-url');
        var translateUrl = $root.data('translate-url');

        /**
         * Escape a plain string for safe insertion as HTML text.
         * @param {string} str
         * @returns {string}
         */
        function escapeHtml(str) {
            return $('<div>').text(str == null ? '' : String(str)).html();
        }

        /**
         * Render a translated value into its field element, preserving lines.
         * Pros/cons are line-separated blobs rendered as <li> items.
         * @param {jQuery} $el
         * @param {string} field
         * @param {string} value
         */
        function applyField($el, field, value) {
            if (value == null || value === '') {
                return;
            }
            if (field === 'pros' || field === 'cons') {
                var lines = String(value).split(/\r\n|\r|\n/).filter(function (l) {
                    return $.trim(l) !== '';
                });
                $el.html(lines.map(function (l) {
                    return '<li>' + escapeHtml(l) + '</li>';
                }).join(''));
            } else {
                $el.html(escapeHtml(value).replace(/\n/g, '<br>'));
            }
        }

        // ---------------- Helpful voting ----------------
        $root.on('click', '[data-role=etf-helpful] button', function () {
            var $btn = $(this);
            var $item = $btn.closest('.etf-review-item');
            var reviewId = $item.data('review-id');
            var helpful = $btn.data('helpful');

            if ($item.data('voted')) {
                return;
            }
            $.ajax({
                url: voteUrl,
                type: 'POST',
                dataType: 'json',
                data: {review_id: reviewId, helpful: helpful},
                showLoader: false
            }).done(function (res) {
                if (res.success) {
                    $item.data('voted', true);
                    $item.find('[data-role=helpful-count]').text(res.helpful_count);
                    $item.find('[data-role=nothelpful-count]').text(res.not_helpful_count);
                    $item.attr('data-helpful', res.helpful_count);
                } else if (res.message) {
                    alert(res.message);
                }
            });
        });

        // ---------------- Comment submission ----------------
        $root.on('submit', '[data-role=etf-comment-form]', function (e) {
            e.preventDefault();
            var $form = $(this);
            $.ajax({
                url: commentUrl,
                type: 'POST',
                dataType: 'json',
                data: $form.serialize(),
                showLoader: true
            }).done(function (res) {
                var $msg = $('<div class="etf-comment-message"></div>').text(res.message || '');
                $form.after($msg);
                if (res.success) {
                    $form[0].reset();
                }
            });
        });

        // ---------------- Translation (Claude) ----------------
        $root.on('click', '[data-role=etf-translate] .etf-translate-btn', function () {
            var $btn = $(this);
            var $item = $btn.closest('.etf-review-item');
            var $status = $item.find('[data-role=etf-translate-status]');
            var reviewId = $item.data('review-id');

            // Toggle back to the original text.
            if ($btn.data('state') === 'translated') {
                var originals = $item.data('etf-originals') || {};
                $item.find('[data-role=etf-translatable]').each(function () {
                    var field = $(this).data('field');
                    if (originals[field] !== undefined) {
                        $(this).html(originals[field]);
                    }
                });
                $btn.data('state', 'original').text($btn.data('label-translate'));
                $status.text('');
                return;
            }

            // Re-apply a translation we already fetched.
            var cached = $item.data('etf-translation');
            if (cached) {
                showTranslation($item, $btn, $status, cached);
                return;
            }

            $btn.prop('disabled', true);
            $status.text($t('Translating…'));
            $.ajax({
                url: translateUrl,
                type: 'POST',
                dataType: 'json',
                data: {review_id: reviewId},
                showLoader: false
            }).done(function (res) {
                if (res && res.success) {
                    $item.data('etf-translation', res);
                    showTranslation($item, $btn, $status, res);
                } else {
                    $status.text((res && res.message) || $t('Translation failed.'));
                }
            }).fail(function () {
                $status.text($t('Translation failed.'));
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        /**
         * Stash the original markup (once) and swap in the translated fields.
         */
        function showTranslation($item, $btn, $status, res) {
            if (!$item.data('etf-originals')) {
                var originals = {};
                $item.find('[data-role=etf-translatable]').each(function () {
                    originals[$(this).data('field')] = $(this).html();
                });
                $item.data('etf-originals', originals);
            }
            $item.find('[data-role=etf-translatable]').each(function () {
                var field = $(this).data('field');
                if (res[field]) {
                    applyField($(this), field, res[field]);
                }
            });
            $btn.data('state', 'translated').text($btn.data('label-original'));
            $status.text('');
        }

        // ---------------- Video lightbox ----------------
        $root.on('click', '[data-role=etf-video-play]', function () {
            var src = $(this).data('video-src');
            if (!src) { return; }
            openVideoLightbox(src);
        });

        /**
         * Open a modal overlay that plays the given video; only at this point
         * is the (potentially large) video file actually requested.
         * @param {string} src
         */
        function openVideoLightbox(src) {
            var $overlay = $(
                '<div class="etf-video-lightbox" data-role="etf-video-lightbox">' +
                    '<div class="etf-video-lightbox-inner">' +
                        '<button type="button" class="etf-video-close" aria-label="' + $t('Close') + '">&times;</button>' +
                        '<video class="etf-video-player" controls autoplay playsinline></video>' +
                    '</div>' +
                '</div>'
            );
            $overlay.find('.etf-video-player').attr('src', src);
            $('body').append($overlay);

            function close() {
                var video = $overlay.find('.etf-video-player')[0];
                if (video) { video.pause(); }
                $overlay.remove();
                $(document).off('keyup.etfVideo');
            }

            $overlay.on('click', function (e) {
                if (e.target === $overlay[0] || $(e.target).hasClass('etf-video-close')) {
                    close();
                }
            });
            $(document).on('keyup.etfVideo', function (e) {
                if (e.keyCode === 27) { close(); }
            });
        }

        // ---------------- Filtering ----------------
        $root.on('click', '[data-role=etf-filters] .etf-filter', function () {
            var $btn = $(this);
            var filter = $btn.data('filter');
            $btn.addClass('active').siblings('.etf-filter').removeClass('active');

            $root.find('.etf-review-item').each(function () {
                var $item = $(this);
                var show = filter === 'all' ||
                    (filter === 'recommended' && $item.data('recommended') === 1) ||
                    (filter === 'verified' && $item.data('verified') === 1) ||
                    (filter === 'media' && $item.data('media') === 1);
                $item.toggle(!!show);
            });
        });

        // ---------------- Sorting ----------------
        $root.on('change', '[data-role=etf-sort]', function () {
            var key = $(this).val();
            var $list = $root.find('.etf-items');
            var $items = $list.children('.etf-review-item').get();

            $items.sort(function (a, b) {
                if (key === 'helpful') {
                    return ($(b).data('helpful') || 0) - ($(a).data('helpful') || 0);
                }
                if (key === 'rating') {
                    return ($(b).data('rating') || 0) - ($(a).data('rating') || 0);
                }
                return 0; // 'date' = keep server order (already newest first)
            });
            $.each($items, function (i, li) {
                $list.append(li);
            });
        });
    };
});
