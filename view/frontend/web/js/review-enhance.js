/**
 * ETechFlow_AdvancedProductReviews — review form enhancer
 *
 * Ensures the core review form can carry file uploads (multipart) and
 * shows a live preview of selected photos/videos.
 *
 * @author ETechFlow <etechflow0@gmail.com>
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $form = $(element);

        // The core review form is not multipart by default — enable file uploads.
        $form.attr('enctype', 'multipart/form-data');

        var $file = $form.find(config.fileSelector);
        var $preview = $form.find(config.previewSelector);

        $file.on('change', function () {
            $preview.empty();
            Array.prototype.forEach.call(this.files || [], function (file) {
                var url = window.URL.createObjectURL(file);
                var $node;

                if (file.type.indexOf('video') === 0) {
                    $node = $('<video>', {
                        src: url,
                        controls: true,
                        'class': 'etf-preview-item etf-preview-video'
                    });
                } else {
                    $node = $('<img>', {
                        src: url,
                        'class': 'etf-preview-item etf-preview-image'
                    });
                }
                $preview.append($node);
            });
        });
    };
});
