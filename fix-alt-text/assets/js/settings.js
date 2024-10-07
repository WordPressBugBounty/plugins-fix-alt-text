/**
 * Settings Scripts
 */
FixAltText = (typeof FixAltText === 'undefined') ? {} : FixAltText;

// Use jQuery shorthand
(function ($) {

    FixAltText.settings = {

        /**
         * Set listeners when the script loads
         *
         * @package FixAltText
         * @since 1.0.0
         */
        init: function () {

            let contentBody = $('body.tools-page-custom .content-body');

            contentBody.on('input', '.custom-block input[type=text]', FixAltText.settings.updateCustomBlock);
            contentBody.on('click', '#add-custom-block', FixAltText.settings.addCustomBlock);
            contentBody.on('click', '.custom-block .remove', FixAltText.settings.removeCustomBlock);

            contentBody.on('click', '.access-tool-roles, .access-settings-roles', FixAltText.settings.correctAccess);
        },

        /**
         * Updates the value of the input the user provides to be the value of the checkbox
         */
        updateCustomBlock: function () {

            let textField = $(this);
            let value = textField.val();
            let checkbox = textField.closest('.custom-block').find('input[type=checkbox]');

            // Update the checkbox value
            checkbox.val(value.trim());
        },

        /**
         * Add another custom block field
         *
         * @todo: the domain string 'fix-alt-text' use for 18n is hardcoded. It needs to be imported via localization later.
         *
         * @param e Click event
         */
        addCustomBlock: function (e) {
            e.preventDefault();
            let html = '<div class="block-row custom-block"><input name="blocks[]" value="" type="checkbox" CHECKED> <input type="text" value="" /> <button class="button remove"><span>remove</span></button></div>';
            $(html).insertBefore($(this));
        },

        /**
         * Removes the custom block fields
         */
        removeCustomBlock: function () {
            $(this).closest('.custom-block').remove();
        },

        /**
         * Force Adding Settings Access to have Tool Access and also removing Tool Access removes Settings Access
         */
        correctAccess: function () {

            let checkbox = $(this);
            let tr = checkbox.closest('tr');

            if (checkbox.hasClass('access-tool-roles')) {
                // Clicked on Tool Access
                if (!checkbox.is(':checked')) {
                    // Uncheck settings as well
                    tr.find('.access-settings-roles').prop('checked', false);
                }
            } else {
                // Clicked on View/Edit Settings

                if (checkbox.is(':checked')) {
                    // check tool access as well
                    tr.find('.access-tool-roles').prop('checked', true);
                }
            }

        },
    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {
        FixAltText.settings.init();
    });

})(jQuery);