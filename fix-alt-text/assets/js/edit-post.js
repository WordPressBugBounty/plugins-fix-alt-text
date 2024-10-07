/**
 * Functionality related to the edit post admin screen
 */
FixAltText = (typeof FixAltText === 'undefined') ? {} : FixAltText;

const { __ } = wp.i18n;

// Use jQuery shorthand
(function ($) {

    FixAltText.editPost = {

        /**
         * @package FixAltText
         * @since 1.0.0
         */
        init: function () {

            let body = $('body');

            if (FixAltTextSettings.others.includes('Media Library' )) {
                // Monitor all clicks on the media modal
                body.on('click', '.media-modal .attachment', FixAltText.editPost.mediaModal);
                body.on('mouseover', '.media-toolbar-primary .button-primary', FixAltText.editPost.mediaModal);

                // Update alt text field highlight
                body.on('input', '#attachment-details-alt-text', FixAltText.editPost.changeAltText);
            }

            // Force Alt Text on WP Block Image
            body.on('mouseover', '.edit-post-header__settings .is-primary, .edit-post-header__settings .editor-post-save-draft', FixAltText.editPost.wpBlockImage);

            // console.log('init edit-post.js');
        },

        changeAltText: function () {

            let mediaModal = $(this).closest('.media-modal');
            FixAltText.editPost.highlightAltTextField( mediaModal );

        },

        /**
         * @package FixAltText
         * @since 1.0.0
         */
        mediaModal: function () {

            let clicked = $(this);

            if (clicked.hasClass('media-modal-close')) {
                // The exit button was clicked
                return;
            }

            let mediaModal = clicked.closest('.media-modal');
            let selected = mediaModal.find('.attachment.selected');

            if (selected.length > 0) {
                FixAltText.editPost.highlightAltTextField( mediaModal );
            }
        },

        /**
         * @package FixAltText
         * @since 1.0.0
         */
        highlightAltTextField: function ( mediaModal ) {

            let altText = mediaModal.find('#attachment-details-alt-text');
            if ( altText.length > 0 ) {
                let submitButton = mediaModal.find('.media-toolbar-primary .button-primary');
                let value = altText.val().trim();

                if ('' === value) {
                    alert( __('Please fill out Alt Text before continuing.') );
                    submitButton.prop("disabled", true);
                    altText.css('border', '2px solid red');
                    altText.focus();
                } else {
                    submitButton.prop("disabled", false);
                    altText.css('border', '');
                }
            }
        },

        /**
         * @package FixAltText
         * @since 1.0.0
         *
         * @todo consider renaming this function so that is makes more sense, since it's no longer for the Image block only.
         */
        wpBlockImage: function () {

            //console.log('wpBlockImage edit-post.js');

            let body = $('body');
            let settingsButton = body.find('.edit-post-header__settings [aria-label="'+__('Settings')+'"]');
            let blocksString = '';

            // Create the selectors for all blocks that are enabled
            for (let i = 0; i < FixAltTextSettings.blocks.length; i++) {
                if (blocksString === '') {
                    blocksString = '.interface-interface-skeleton__content [data-type="' + FixAltTextSettings.blocks[i] + '"]';
                } else {
                    blocksString += ',.interface-interface-skeleton__content [data-type="' + FixAltTextSettings.blocks[i] + '"]';
                }
            }
            let blocks = body.find(blocksString);
            let breakLoop = false;
            let imageErrors = body.find('.border-error');

            // Clear images with errors
            imageErrors.css('border', 'none');
            imageErrors.removeClass('border-error');

            // Loop through each block and check the alt text
            blocks.each(function () {
                let thisBlock = $(this);
                let images = thisBlock.find('img');

                images.each(function () {
                    let altText = $(this).attr('alt');

                    /**
                     * @todo check if the alt text matches the filename and is settings option set
                     */
                    if ('' === altText.trim() || altText.includes( __('This image has an empty alt attribute') )) {

                        // Expand the sidebar so that we can show settings for the block
                        if (!settingsButton.hasClass('is-pressed')) {
                            settingsButton.trigger('click');
                        }

                        // Focus on the block
                        thisBlock.focus();

                        // Highlight the image
                        $(this).css('border', '2px solid red');
                        $(this).addClass('border-error');

                        if (thisBlock.data('type') === 'core/image' || thisBlock.data('type') === 'core/gallery') {

                            if (thisBlock.data('type') === 'core/image') {
                                alert( __('Please fill out Alt Text for the Image Block before saving. Image ALT text highlighted in red.') );
                            } else if (thisBlock.data('type') === 'core/gallery') {
                                alert( __("One of your Gallery Block images are missing it's alt text. The image is highlighted red. To fix this, you will need to click the pencil icon and the image will be removed. Then click on the media library button and go find the image again and select it and then fill out the ALT text for that image within the media library.") );
                            }

                            let thisInput = $('.block-editor-block-inspector .components-textarea-control__input');
                            thisInput.css('border', '2px solid red');

                            // Scroll to input
                            $('.interface-interface-skeleton__sidebar').animate({
                                scrollTop: thisInput.offset().top - 230
                            }, 300);

                            thisInput.on('input', function () {
                                let input = $(this);
                                let value = input.val();

                                if ('' === value.trim()) {
                                    // Add red border to input to bring attention to it
                                    input.css('border', '2px solid red');
                                } else {
                                    // Remove red border
                                    input.css('border', '');
                                }
                            });

                        } else if (thisBlock.data('type') === 'core/media-text') {
                            alert( __('The image in the Media Text block is missing alt text.') );
                        }

                        // We are returning false to break the image loop
                        breakLoop = true;
                        return false;
                    }
                });

                // We are returning false to break the block loop
                if (breakLoop) {
                    return false;
                }

            });

        }
    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {
        // Set Listeners
        FixAltText.editPost.init();
    });

})(jQuery);