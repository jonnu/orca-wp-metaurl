'use strict';

if (typeof ORCA === 'undefined') {
    var ORCA = {};
}

ORCA.MetaUrl = function ($) {

    return {
        styleDefault: function (element) {

            if (element.val() !== '' || !element.data('defaultValue')) {
                element.removeClass('orca-default');
                return;
            }

            element.val(element.data('defaultValue')).addClass('orca-default');
        },
        styleClick: function (event) {

            if (event.type == 'focusin' && $(event.target).val() === $(event.target).data('defaultValue')) {
                $(event.target).removeClass('orca-default').val('');
                return;
            }

            if (event.type == 'focusout' && $(event.target).val() == '' && $(event.target).data('defaultValue')) {
                $(event.target).addClass('orca-default').val($(event.target).data('defaultValue'));
                return;
            }
        }
    };

}(jQuery);

jQuery(document).ready(function($) {

    $('.orca-row input').each(function (_, element) {
        ORCA.MetaUrl.styleDefault($(element));
    });

    $('.orca-row').on('focus blur', 'input', {}, ORCA.MetaUrl.styleClick);

    $('form').on('submit', function () {
        $(this).find('.orca-row input').each(function (_, element) {
            if ($(element).val() == $(element).data('defaultValue')) {
                $(element).val('');
            }
        });
    });
});
