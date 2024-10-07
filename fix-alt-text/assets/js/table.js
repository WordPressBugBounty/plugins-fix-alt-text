/**
 * Settings Scripts
 */
FixAltText = (typeof FixAltText === 'undefined') ? {} : FixAltText;

// Use jQuery shorthand
(function ($) {

    FixAltText.table = {

        /**
         * Set listeners when the script loads
         *
         * @package FixAltText
         * @since 1.0.0
         */
        init: function () {

            let contentBody = $('.content-body');

            contentBody.on('click', 'td .image-preview-thumbnail-wrap, td .image-preview-modal .dashicons', FixAltText.table.toggleLargerPreview);
            contentBody.on('click', 'td.column-image_alt_text .row-actions a, td.column-image_alt_text input[type=submit]', FixAltText.table.editInlineAltText);
            contentBody.find('td.column-image_alt_text form').submit(FixAltText.table.editInlineAltText);

            contentBody.on('change', '.select-wrapper select', FixAltText.table.applyFilters);
            $('.privacy-settings-tabs-wrapper').on('mouseout', 'a', FixAltText.table.applyFilters);

            // Initially run
            FixAltText.table.highlightFilters();
        },

        /**
         * Highlights current filters that are active if filters are applied
         */
        highlightFilters: function () {
            let resetLink = $('.reset-filters');

            if (resetLink.length) {
                let allFilters = $('.select-wrapper');

                allFilters.each(function () {
                    let wrapper = $(this);
                    let select = wrapper.find('select');

                    if (select.val() == '') {
                        wrapper.removeClass('active');
                    } else {
                        wrapper.addClass('active');
                    }
                });

                let searchBox = $('.search-box input[type=search]');

                if (searchBox.val() != '') {
                    searchBox.addClass('active');
                }
            }

        },

        /**
         * Submit form on filter select change
         */
        applyFilters: function () {
            let form = $(this).closest('form');

            // Ensure we start on page 1
            form.find('input[name="paged"]').val('1');

            form.submit();
        },

        /**
         * Process ajax requests
         *
         * @param object data
         * @param object content
         * @param string action
         * @param string type
         */
        ajax: function (data, content, action, type = 'get', callbackFunction = '') {

            $.ajax({
                type: type,
                dataType: "html",
                url: FixAltTextAjax.ajaxURL,
                data: {
                    action: action,
                    data: data,
                },
                success: function (json) {
                    let response = JSON.parse(json);

                    content.html(response.html);

                    // Run Callback Function
                    if (typeof callbackFunction == "function") {
                        callbackFunction('success');
                    }
                },
                fail: function () {
                    content.html('Unknown Error');

                    // Run Callback Function
                    if (typeof callbackFunction == "function") {
                        callbackFunction('fail');
                    }
                },
                error: function (request) {
                    content.html(request.responseText);

                    // Run Callback Function
                    if (typeof callbackFunction == "function") {
                        callbackFunction('error');
                    }
                }
            });

        },

        /**
         * Edit Inline Alt Text Column. Grabs the input fields with the values filled in.
         */
        editInlineAltText: function (e) {

            e.preventDefault();

            let click = $(this);
            let row = click.closest('tr');
            let columnId = row.find('.column-id');
            let ajaxData = columnId.find('.ajax-data');

            let data = {};
            data['nonce'] = ajaxData.data('nonce-inline-edit');
            data['id'] = ajaxData.data('id');
            data['from_post_id'] = ajaxData.data('from-post-id');
            data['from_post_type'] = ajaxData.data('from-post-type');
            data['from_where'] = ajaxData.data('from-where');
            data['from_where_key'] = ajaxData.data('from-where-key');
            data['image_index'] = ajaxData.data('image-index');
            data['image_url'] = ajaxData.data('image-url');
            data['s'] = row.closest('.content-body').find('input[type="search"]').val();

            let columnImageAltText = row.find('.column-image_alt_text');
            let content = columnImageAltText.find('.td-content');

            // @todo display loading spinner

            if ($(e.target).is('a, a *')) {
                // Clicked row action link;

                let tr = click.closest('tr');

                if ('#edit' == click.attr('href')) {
                    FixAltText.table.ajax(data, content, 'fixalttext_edit_inline_alt_text', 'get', function (responseType) {

                        if ('success' == responseType) {
                            // Autofocus on textarea
                            tr.find('textarea[name="replace"]').focus();
                        }
                    });

                } else {
                    // Assume cancel

                    FixAltText.table.ajax(data, content, 'fixalttext_cancel_inline_alt_text');
                }
            } else {
                // Update inline alt text

                // Grab the new value from input box
                data['replace'] = click.closest('li').find('textarea[name="replace"]').val();

                // Ajax: send to PHP
                FixAltText.table.ajax(data, content, 'fixalttext_update_inline_alt_text', 'post', function (responseType) {

                    if ('success' == responseType) {

                        // Grab the update id content and ajax data
                        let newIdContent = columnImageAltText.find('.update-id-column').html();

                        // Update the row's id
                        columnId.find('.id-content').html(newIdContent);

                        // Get new issue data
                        let newIssueContent = columnId.find('.ajax-data').data('image-issue');

                        // Update Issue
                        row.find('.issue-content').html(newIssueContent);

                        if (newIssueContent == '') {

                            // Clear Issue Visual Indicator
                            row.removeClass('issue');

                            // Indicate visually that it has updated
                            row.addClass('updated');

                            setTimeout(function () {
                                // Remove visual indication
                                row.removeClass('updated');
                            }, 500);
                        } else {
                            // Indicate visually that it has updated
                            row.addClass('issue');
                        }

                        // Remove the temporary data
                        row.find('.update-id-column').remove();

                    } else {
                        // Indicate visually that it has failed
                        row.addClass('failed');
                    }

                });

            }

        },

        /**
         * Displays the larger original image in a popup modal
         */
        toggleLargerPreview: function () {

            let td = $(this).closest('td');
            let table = td.closest('table');

            if (td.hasClass('on')) {
                // Hide

                $(this).closest('td').find('.image-preview-modal').remove();
                td.removeClass('on');
            } else {
                // Show

                // Hide all previews in case others are visible
                table.find('td').find('.image-preview-modal').remove();
                table.find('td').removeClass('on');

                let largeImageSrc = td.find('.image-preview-thumbnail').data('large-src');

                if (largeImageSrc) {
                    td.append('<div class="image-preview-modal"><img src="' + largeImageSrc + '" alt="Displaying Preview Image Modal"><span class="dashicons dashicons-dismiss"></span></div>');
                    td.addClass('on');
                }
            }

        },

    }

    /**
     * Wait until document loads before adding listeners / calling functions
     */
    $(document).ready(function () {
        // Set Listeners
        FixAltText.table.init();
    });

})(jQuery);