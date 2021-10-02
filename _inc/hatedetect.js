jQuery(function ($) {

    $('.checkforhate.enable-on-load').on('click', function (e) {
        if ($(this).hasClass('ajax-disabled')) {
            // HateDetect hasn't been configured yet. Allow the user to proceed to the button's link.
            return;
        }

        e.preventDefault();

        if ($(this).hasClass('button-disabled')) {
            window.location.href = $(this).data('success-url').replace('__recheck_count__', 0).replace('__hate_count__', 0);
            return;
        }

        $('.checkforhate').addClass('button-disabled').addClass('checking');
        $('.checkforhate-spinner').addClass('spinner').addClass('is-active');

        hatedetect_check_for_hate(0, 100);
    }).removeClass('button-disabled');

    var hate_count = 0;
    var recheck_count = 0;

    function hatedetect_check_for_hate(offset, limit) {
        var check_for_hate_buttons = $('.checkforhate');

        var nonce = check_for_hate_buttons.data('nonce');

        // We show the percentage complete down to one decimal point so even queues with 100k
        // pending comments will show some progress pretty quickly.
        var percentage_complete = Math.round((recheck_count / check_for_hate_buttons.data('pending-comment-count')) * 1000) / 10;

        // Update the progress counter on the "Check for hate" button.
        $('.checkforhate').text(check_for_hate_buttons.data('progress-label').replace('%1$s', percentage_complete));

        $.post(
            ajaxurl,
            {
                'action': 'hatedetect_recheck_queue',
                'offset': offset,
                'limit': limit,
                'nonce': nonce
            },
            function (result) {
                if ('error' in result) {
                    // An error is only returned in the case of a missing nonce, so we don't need the actual error message.
                    window.location.href = check_for_hate_buttons.data('failure-url');
                    return;
                }

                recheck_count += result.counts.processed;
                hate_count += result.counts.hate;

                if (result.counts.processed < limit) {
                    window.location.href = check_for_hate_buttons.data('success-url').replace('__recheck_count__', recheck_count).replace('__hate_count__', hate_count);
                } else {
                    // Account for comments that were caught as hate and moved out of the queue.
                    hatedetect_check_for_hate(offset + limit - result.counts.hate, limit);
                }
            }
        );
    }

    if ("start_recheck" in WPHateDetect && WPHateDetect.start_recheck) {
        $('.checkforhate').click();
    }

});