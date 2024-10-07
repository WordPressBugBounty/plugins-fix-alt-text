/**
 * Functionality related to the edit attachment admin screen
 */
FixAltText = (typeof FixAltText === 'undefined') ? {} : FixAltText;

// Use jQuery shorthand
(function ($) {

FixAltText.editAttachment = {

    init: function () {

        if (FixAltTextSettings.others.includes('Media Library')) {
            FixAltText.editAttachment.addAsterix();

            $('#publishing-action .button-primary').on('mouseover', FixAltText.editAttachment.checkField);
        }

    },

    /**
     * Adds the red asterix (required) to the Alt Text field label
     */
    addAsterix: function () {

        $('p.attachment-alt-text label').append(' <span class="required">*</span>');

    },

    /**
     * Checks to see if the user has Alt Text value
     */
    checkField: function () {

        // get value of alt text field
        let altText = $('p.attachment-alt-text input[type="text"]').val();

        if ('' == altText.trim()) {
            alert('Required: The Alternative Text field is empty.');
        }

    }

}

/**
 * Wait until document loads before adding listeners / calling functions
 */
$(document).ready(function () {
    // Set Listeners
    FixAltText.editAttachment.init();
});

})(jQuery);