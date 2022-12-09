"use strict";

(function ($) {
    $(document).on('change', '#wpil_links_table_filter select', wpil_report_filter);
    $(document).on('click', '#wpil_links_table_filter .button-primary', wpil_report_filter_submit);

    function wpil_report_filter() {
        var block = $('#wpil_links_table_filter');

        var post_type = block.find('select[name="post_type"]').val();

        $('.wpil_filter_post_type:not(.' + post_type + ')').css({'display': 'none'});
        $('.wpil_filter_post_type.' + post_type).css({'display': 'block'});

        var filterType = block.find('select[name="filter_type"]').val();

        switch (filterType) {
            case '2':
                block.find('.filter-by-type, .filter-by-count').css({'display': 'inline-block'});
            break;
            case '1':
                block.find('.filter-by-type').css({'display': 'none'});
                block.find('.filter-by-count').css({'display': 'inline-block'});
            break;
            case '0':
                block.find('.filter-by-type').css({'display': 'inline-block'});
                block.find('.filter-by-count').css({'display': 'none'});
            default:
            break;
        }

        if($(this).attr('name') === 'post_type'){
            block.find('select[name="category"]').val(0);
        }
    }
    wpil_report_filter();

    function wpil_report_filter_submit() {
        var block = $(this).closest('div');
        var filterType = block.find('select[name="filter_type"]').val();
        var linkType = block.find('select[name="link_type"]').val();
        var linkCountMin = block.find('input[name="link_min_count"]').val();
        var linkCountMax = block.find('input[name="link_max_count"]').val();
        var post_type = block.find('select[name="post_type"]').val();
        var category = block.find('select[name="category"]').val();
        var filterNonce = block.find('.post-filter-nonce').val();
        var loc = block.find('select[name="location"]').val();
        var orphaned = (-1 !== window.location.href.indexOf('orphaned')) ? '&orphaned=1': '';
        var url = admin_url + 'admin.php?page=link_whisper&type=links' + orphaned + '&filter_type=' + filterType;

        switch (filterType) {
            case '2':
                url += '&post_type=' + post_type + '&category=' + category + '&link_type=' + linkType + '&link_min_count=' + linkCountMin;
                if(linkCountMax !== ''){
                    url += '&link_max_count=' + linkCountMax;
                }
            break;
            case '1':
                url += '&link_type=' + linkType + '&link_min_count=' + linkCountMin;
                if(linkCountMax !== ''){
                    url += '&link_max_count=' + linkCountMax;
                }
            break; 
            case '0':
            default:
                url += '&post_type=' + post_type + '&category=' + category;
            break;
        }

        if(typeof loc !== 'undefined' && (filterType === '2' || filterType === '0')){
            url += '&location=' + loc;
        }

        // save the updated filter settings
        updateFilterSettings(post_type, category, filterNonce, url);
    }

    function updateFilterSettings(postType = '', category = '', filterNonce, url){
        var data = {
            action: 'wpil_save_user_filter_settings',
            post_type: postType,
            category: category,
            setting_type: 'report',
            nonce: filterNonce
        };

        $.ajax({
            url: ajaxurl,
            dataType: 'json',
            data: data,
            method: 'post',
            error: function (jqXHR, textStatus, errorThrown) {
                wpil_swal('Error', errorThrown, 'error');
            },
            success: function (response) {
                location.href = url;
            }
        });
    }

    setInterval(function(){
        $.post(ajaxurl, {
            action: 'wpil_report_reload',
        }, function(response){
            if (response == 'yes') {
                location.reload();
            }
        });
    }, 5000);
})(jQuery);