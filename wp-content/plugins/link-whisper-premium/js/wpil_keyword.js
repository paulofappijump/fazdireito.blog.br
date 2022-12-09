"use strict";

(function ($) {
    $(document).on('click', '#wpil_keywords_table .delete', wpil_keyword_delete);
    $(document).on('click', '#wpil_keywords_settings i', wpil_keyword_settings_show);
    $(document).on('click', '.link-whisper_page_link_whisper_keywords .column-keyword .dashicons', wpil_keyword_local_settings_show);
    $(document).on('click', '#wpil_keywords_settings input[type="submit"]', wpil_keyword_clear_fields);
    $(document).on('click', '#add_keyword_form a.single-autolink-create', wpil_keyword_add);
    $(document).on('click', '.wpil_keyword_local_settings_save', wpil_keyword_local_settings_save);
    $(document).on('click', '#wpil_keywords_reset_button', wpil_keyword_reset);
    $(document).on('click', '.wpil-insert-selected-keywords', wpil_insert_selected_keywords);
    $(document).on('click', '.wpil-bulk-keywords-import', bulkImportAutolinks);
    $(document).on('click', '#wpil-bulk-keywords-create', bulkCreateAutolinks);
    $(document).on('click', '#wpil-bulk-keywords-global-set', bulkSetAutolinkSettings);
    $(document).on('click', '.wpil-bulk-autolink-setting-icon', toggleBulkAutolinkSettings);
    $(document).on('click', '.wpil-autolink-import-method', toggleBulkAutolinkCreateMethod);
    $(document).on('click', '.wpil-open-bulk-autolink-create-form', showBulkAutolinkInterface);
    $(document).on('click', '#wpil-bulk-keywords-close, .wpil-autolink-bulk-create-background', hideBulkAutolinkInterface);
    $(document).on('change', '#wpil-autolink-csv-import-file', toggleFileImportButton);
    $(document).on('change keyup', '#wpil-autolink-keyword-field, #wpil-autolink-url-field', toggleFieldImportButton);

    var autolinkBulkData = [],
        stepped = 0,
        rowCount = 0,
        errorCount = 0,
        firstError = undefined;

    function bulkImportAutolinks(){
        var method = $('input[name="wpil-autolink-import-method"]:checked').val();
        autolinkBulkData = [];

        // clear any existing rows and separators
        $('.wpil-bulk-autolink-rows .wpil-bulk-autolink-row').empty();

        if(method === 'csv'){
            getCSVImportData();
        }else if(method === 'field'){
            autolinkBulkData = getFieldImportData();
            assembleKeywordRows();
        }
    }

    function getCSVImportData(){
        if($('.wpil-autolink-csv-import .wpil-bulk-keywords-import').hasClass('disabled')){
            return;
        }

		var config = buildConfig();

        console.log('testing!');
        $('#wpil-autolink-csv-import-file').parse({
            config: config,
            error: function(err, file)
            {
                console.log("ERROR:", err, file);
                firstError = firstError || err;
                errorCount++;
            },
            complete: function()
            {
                assembleKeywordRows();
            }
        });
    }

    function getFieldImportData(){
        if($('.wpil-autolink-field-import-container .wpil-bulk-keywords-import').hasClass('disabled')){
            return;
        }

        var keywords = $('#wpil-autolink-keyword-field').val();
        var urls = $('#wpil-autolink-url-field').val();
        var data = [];

        if(!keywords || keywords.trim() === ''){
            wpil_swal({"title": "No Keywords", "text": "Please enter one URL on each line for each Autolink URL", "icon": "error"});
            return;
        }else if(!urls || urls.trim() === ''){
            wpil_swal({"title": "No URLs", "text": "Please enter one URL on each line for each Autolink Keyword", "icon": "error"});
            return;
        }

        keywords = keywords.split("\n");
        urls = urls.split("\n");

        for(var i in keywords){
            if(undefined === urls[i] || keywords[i].length < 1 || urls[i].length < 1){
                continue;
            }

            data.push([keywords[i], urls[i]]);
        }

        return data;
    }

    /**
     * Assembles the rows of keywords to import
     **/
    function assembleKeywordRows(){
        if(autolinkBulkData.length < 1){
            wpil_swal({"title": "Data Error", "text": "The entered data couldn't be processed, please check the data source and try again.", "icon": "error"});
            return;
        }


        for(var i in autolinkBulkData){
            var template = $('.wpil-autolink-bulk-keyword-container .wpil-row-template').clone().removeClass('wpil-row-template');
            var dat = autolinkBulkData[i];
            var evenOdd = (i % 2 === 0) ? 'even': 'odd';
            template.find('input[name="keyword"]').val(dat[0]);
            template.find('input[name="link"]').val(dat[1]);
            $('.wpil-bulk-autolink-rows').append(template);
            $('.wpil-bulk-autolink-rows').append('<div class="wpil-bulk-autolink-row-separator ' + evenOdd + '"></div>');
            console.log(dat);
        }

        // hide the data import interface components
        $('.wpil-autolink-bulk-create-container').addClass('bulk-create-temp-hidden');
        // show the autolink create interface components
        $('.wpil-autolink-bulk-keyword-heading-container, .wpil-autolink-bulk-keyword-container, #wpil-bulk-keywords-create, .wpil-autolink-bulk-keyword-global-setting-container').addClass('bulk-create-temp-display');
    }


    var start, end;
    function printStats(msg)
    {
        if (msg)
            console.log(msg);
        console.log("       Time:", (end-start || "(Unknown; your browser does not support the Performance API)"), "ms");
        console.log("  Row count:", rowCount);
        if (stepped)
            console.log("    Stepped:", stepped);
        console.log("     Errors:", errorCount);
        if (errorCount)
            console.log("First error:", firstError);
    }
    
    
    
    function buildConfig()
    {
        // consult: papaparse.com/docs#config
        return {
            header: $('#header').prop('checked'),
            dynamicTyping: $('#dynamicTyping').prop('checked'),
            skipEmptyLines: $('#skipEmptyLines').prop('checked'),
            preview: parseInt($('#preview').val() || 0),
            step: $('#stream').prop('checked') ? stepFn : undefined,
            skipEmptyLines: true,
            encoding: $('#encoding').val(),
            worker: $('#worker').prop('checked'),
            comments: $('#comments').val(),
            complete: completeFn,
            error: errorFn,
            download: false
        };
    }
    
    function stepFn(results, parser)
    {
        stepped++;
        if (results)
        {
            if (results.data)
                rowCount += results.data.length;
            if (results.errors)
            {
                errorCount += results.errors.length;
                firstError = firstError || results.errors[0];
            }
        }
    }
    
    function completeFn(results)
    {
        end = now();

        console.log(results);

        if (results && results.errors)
        {
            if (results.errors)
            {
                errorCount = results.errors.length;
                firstError = results.errors[0];
            }
            if (results.data && results.data.length > 0)
                rowCount = results.data.length;
        }

        if(results && results.data && results.data.length){
            for(var i in results.data){
                var dat = results.data[i];
                if( dat.length < 1 ||
                    undefined === dat[0] ||
                    undefined === dat[1] ||
                    dat[0].length < 1 ||
                    dat[1].length < 1 ||
                    typeof dat[0] !== 'string' ||
                    typeof dat[1] !== 'string' ||
                    dat[0].toLowerCase() === 'keyword' && dat[1].toLowerCase() === 'link')
                {
                    continue;
                }
                autolinkBulkData.push(results.data[i]);
            }
        }

        printStats("Parse complete");
        console.log("    Results:", results);
    }

    function errorFn(err, file)
    {
        end = now();
        console.log("ERROR:", err, file);
        enableButton();
    }
    
    function enableButton()
    {
        $('#submit').prop('disabled', false);
    }
    
    function now()
    {
        return typeof window.performance !== 'undefined'
                ? window.performance.now()
                : 0;
    }

    function bulkCreateAutolinks(){
        var keywordData = [];
        var checked = $('input[name="wpil-create-autolink"]:checked');

        // notify the user if no links are checked
        if(checked.length < 1){
            wpil_swal({"title": "No Rules Selected", "text": "Please select some autolinking rules to create", "icon": "info"});
            return;
        }

        checked.each(function(index, element){
            var parent = $(element).parent().parent();

            if(parent.hasClass('wpil-row-template')){
                return;
            }

            var setPriority = parent.find('.wpil-bulk-autolinks-set-priority-checkbox').prop('checked') ? 1 : 0;
            var restrictedToDate = parent.find('.wpil-bulk-autolinks-date-checkbox').prop('checked') ? 1 : 0;
            var restrictedToCat = parent.find('.wpil-bulk-autolinks-restrict-to-cats').prop('checked') ? 1 : 0;

            var params = {
                keyword: parent.find('input[name="keyword"]').val(),
                link: parent.find('input[name="link"]').val(),
                wpil_keywords_add_same_link: parent.find('.wpil-bulk-autolinks-add-same-link').is(':checked') ? 1 : 0,
                wpil_keywords_link_once: parent.find('.wpil-bulk-autolinks-link-once').is(':checked') ? 1 : 0,
                wpil_keywords_select_links: parent.find('.wpil-bulk-autolinks-select-links').is(':checked') ? 1 : 0,
                wpil_keywords_set_priority: setPriority,
                wpil_keywords_restrict_date: restrictedToDate,
                wpil_keywords_case_sensitive: parent.find('.wpil-bulk-autolinks-case-sensitive').is(':checked') ? 1 : 0,
                wpil_keywords_force_insert: parent.find('.wpil-bulk-autolinks-force-insert').is(':checked') ? 1 : 0,
                wpil_keywords_restrict_to_cats: restrictedToCat,
            };

            if(setPriority){
                var priority = parent.find('.wpil-bulk-autolinks-priority-setting').val();
                if(!priority){
                    priority = null;
                }
                params['wpil_keywords_priority_setting'] = priority;
            }

            if(restrictedToDate){
                var date = parent.find('.wpil-bulk-autolinks-restricted-date').val();
                if(!date){
                    date = null;
                }
                params['wpil_keywords_restricted_date'] = date;
            }

            if(restrictedToCat){
                var selectedCats = [];
                parent.find('.wpil-bulk-autolinks-restrict-keywords-input:checked').each(function(index, element){
                    selectedCats.push($(element).data('term-id'));
                });

                params['restricted_cats'] = selectedCats;
            }

            keywordData.push(params);
        });

        if(keywordData.length < 1){
            return;
        }

        var data = {
            action: 'wpil_bulk_keyword_add',
            nonce: wpil_keyword_nonce,
            keyword_data: keywordData
        }

        // hide the autolink create panels
        $('.bulk-create-temp-display').removeClass('bulk-create-temp-display');
        // unhide the progress loader
        $('.wpil-autolink-bulk-create-wrapper .progress-panel-container').addClass('bulk-create-temp-display');

        ajaxBulkCreateAutolinks(data);
    }

    function ajaxBulkCreateAutolinks(data){
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: data,
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                wpil_swal({"title": "Error", "content": wrapper, "icon": "error"});
            },
            success: function(response){
                console.log(response);
                if (response.error) {
                    wpil_swal(response.error.title, response.error.text, 'error');
                    return;
                }

                $('.wpil-autolink-bulk-create-wrapper').find('.progress_count').text(response.displayMessage);
                if(response.finish){
                    setTimeout(function(){
                        location.reload();
                    }, 300);
                }else{
                    // if we have data and the bulk create panel is open
                    if(response.keyword_id && response.loop !== undefined && $('.wpil-autolink-bulk-create-wrapper').is(':visible')){
                        data = {
                            'action': 'wpil_bulk_keyword_process',
                            'keyword_ids': response.keyword_id,
                            'keyword_total': response.keyword_total,
                            'nonce': wpil_keyword_nonce,
                            'total': response.total,
                            'loop': response.loop
                        };

                        ajaxBulkCreateAutolinks(data);
                    }
                }
            }
        });
    }

    /**
     * Sets the setting values for all the imported bulk autolinks.
     **/
    function bulkSetAutolinkSettings(){

        var globalSettings = $('.wpil-bulk-autolink-global-settings');

        var importedAutolinks = $('.wpil-bulk-autolink-row').not('.wpil-row-template');

console.log(globalSettings);
console.log(importedAutolinks);

        // exit if there's no imported autolinks or settings
        if(importedAutolinks.length < 1 || globalSettings.length < 1){
            return;
        }

        // compile the settings so they're easier to read
        var settings = {
            'add_same': globalSettings.find('.wpil-bulk-autolinks-add-same-link').prop('checked'),
            'link_once': globalSettings.find('.wpil-bulk-autolinks-link-once').prop('checked'),
            'force_insert': globalSettings.find('.wpil-bulk-autolinks-force-insert').prop('checked'),
            'select_links': globalSettings.find('.wpil-bulk-autolinks-select-links').prop('checked'),
            'set_priority': globalSettings.find('.wpil-bulk-autolinks-set-priority-checkbox').prop('checked'),
            'priority': globalSettings.find('.wpil-bulk-autolinks-priority-setting').val(),
            'after_date': globalSettings.find('.wpil-bulk-autolinks-date-checkbox').prop('checked'),
            'after_dated': globalSettings.find('.wpil-bulk-autolinks-restricted-date').val(),
            'case_sensitive': globalSettings.find('.wpil-bulk-autolinks-case-sensitive').prop('checked'),
            'restrict_cats': globalSettings.find('.wpil-bulk-autolinks-restrict-to-cats').prop('checked'),
            'restricted_cats': []
        };

        //jQuery('.wpil-bulk-autolink-global-settings').find('.wpil-bulk-autolinks-date-checkbox').is('checked')

        if(settings.restrict_cats){
            globalSettings.find('.wpil-bulk-autolinks-restrict-keywords-input:checked').each(function(index, element){
                settings.restricted_cats.push($(element).data('term-id'));
            });
        }
        console.log(settings);

        // update all the setting values for the imported settings
        importedAutolinks.find('.wpil-bulk-autolinks-add-same-link[type="checkbox"]').prop('checked', settings.add_same);
        importedAutolinks.find('.wpil-bulk-autolinks-link-once[type="checkbox"]').prop('checked', settings.link_once);
        importedAutolinks.find('.wpil-bulk-autolinks-force-insert[type="checkbox"]').prop('checked', settings.force_insert);
        importedAutolinks.find('.wpil-bulk-autolinks-select-links[type="checkbox"]').prop('checked', settings.select_links);
        importedAutolinks.find('.wpil-bulk-autolinks-set-priority-checkbox[type="checkbox"]').prop('checked', settings.set_priority);
        importedAutolinks.find('.wpil-bulk-autolinks-priority-setting[type="number"]').val(settings.priority);
        importedAutolinks.find('.wpil-bulk-autolinks-date-checkbox[type="checkbox"]').prop('checked', settings.after_date);
        importedAutolinks.find('.wpil-bulk-autolinks-restricted-date[type="date"]').val(settings.after_dated);
        importedAutolinks.find('.wpil-bulk-autolinks-case-sensitive[type="checkbox"]').prop('checked', settings.case_sensitive);
        importedAutolinks.find('.wpil-bulk-autolinks-restrict-to-cats[type="checkbox"]').prop('checked', settings.restrict_cats);

        importedAutolinks.find('.wpil-bulk-autolinks-restrict-keywords-input').each(function(index, element){
            var id = $(element).data('term-id');
            if(settings.restricted_cats.indexOf(id) !== -1){
                $(element).prop('checked', true);
            }else{
                $(element).prop('checked', false);
            }
        });

        // toggle the hide/showing of compound settings
        var priorityDisplay = (settings.set_priority) ? 'block': 'none';
        var dateDisplay = (settings.after_date) ? 'block': 'none';

        importedAutolinks.find('.wpil-bulk-autolinks-priority-setting[type="number"]').css('display', priorityDisplay);
        importedAutolinks.find('.wpil_keywords_restricted_date-container').css('display', dateDisplay);
    }

    function toggleBulkAutolinkSettings(){
        if($(this).hasClass('wpil-global-setting-icon')){
            var settings = $(this).parent().parent().find('.wpil-bulk-autolink-settings');
        }else{
            var settings = $(this).parent().find('.wpil-bulk-autolink-settings');
        }

        if(!settings.hasClass('open')){
            settings.addClass('open');
        }else{
            settings.removeClass('open');
        }
    }

    function toggleBulkAutolinkCreateMethod(){
        var method = $(this).val();

        if(method === 'field'){
            var hide = $('.wpil-autolink-csv-import-container');
            $('.wpil-autolink-field-import-container').removeClass('hidden');

            if(!hide.hasClass('hidden')){
                hide.addClass('hidden');
            }
        }else{
            var hide = $('.wpil-autolink-field-import-container');
            $('.wpil-autolink-csv-import-container').removeClass('hidden');

            if(!hide.hasClass('hidden')){
                hide.addClass('hidden');
            }
        }
    }

    function showBulkAutolinkInterface(e){
        e.preventDefault();
        // unset any CSV files
        $('#wpil-autolink-csv-import-file').val(null);
        // clear the textareas
        $('.wpil-autolink-field-import textarea').val(null);
        // show the background
        $('.wpil-autolink-bulk-create-background').removeClass('hidden');
        // show the interface
        $('.wpil-autolink-bulk-create-wrapper').removeClass('hidden');
    }

    function hideBulkAutolinkInterface(){
        // unset any CSV files
        $('#wpil-autolink-csv-import-file').val(null);
        // clear the textareas
        $('.wpil-autolink-field-import textarea').val(null);
        // hide the background
        $('.wpil-autolink-bulk-create-background').addClass('hidden');
        // hide the interface
        $('.wpil-autolink-bulk-create-wrapper').addClass('hidden');
        // now that we're out of view, remove the temp display statuses
        $('.bulk-create-temp-hidden').removeClass('bulk-create-temp-hidden');
        $('.bulk-create-temp-display').removeClass('bulk-create-temp-display');
        // clear any created rows
        $('.wpil-bulk-autolink-rows').empty();
        // disable the import buttons
        $('.wpil-bulk-keywords-import').removeClass('disabled').addClass('disabled');
    }

    /**
     * Enables the file import button if there's a file to import.
     * Disables the button if there's no file
     **/
    function toggleFileImportButton(){
        var button = $('.wpil-autolink-csv-import .wpil-bulk-keywords-import');

        if(!$(this).val()){
            button.removeClass('disabled').addClass('disabled');
        }else{
            button.removeClass('disabled');
        }
    }

    /**
     * Enables and disables the field import button depending on if there's field data
     **/
    function toggleFieldImportButton(){
        var keywords = $('#wpil-autolink-keyword-field').val();
        var urls = $('#wpil-autolink-url-field').val();
        var button = $('.wpil-autolink-field-import-container .wpil-bulk-keywords-import');

        if(keywords.length > 0 && urls.length > 0){
            button.removeClass('disabled');
        }else{
            button.removeClass('disabled').addClass('disabled');
        }
    }




    if (is_wpil_keyword_reset) {
        wpil_keyword_reset_process(2, 1);
    }

    function wpil_keyword_delete() {
        if (confirm("Are you sure you want to delete this keyword?")) {
            var el = $(this);
            var id = el.data('id');

            $.post(ajaxurl, {
                action: 'wpil_keyword_delete',
                id: id
            }, function(){
                el.closest('tr').fadeOut(300);
            });
        }
    }

    function wpil_keyword_settings_show() {
        $('#wpil_keywords_settings .block').toggle();
    }

    function wpil_keyword_local_settings_show() {
        $(this).closest('td').find('.block').toggle();
    }

    $(document).on('change', '.wpil_keywords_set_priority_checkbox, .wpil-bulk-autolinks-set-priority-checkbox', wpilShowSetPriorityInput);
    function wpilShowSetPriorityInput(){
        var button = $(this);
        button.parent().find('.wpil_keywords_priority_setting_container').toggle();
    }

    $(document).on('change', '.wpil_keywords_restrict_date_checkbox, .wpil-bulk-autolinks-date-checkbox', wpilShowRestrictDateInput);
    function wpilShowRestrictDateInput(){
        var button = $(this);
        button.parent().find('.wpil_keywords_restricted_date-container').toggle();
    }

    $(document).on('click', '.wpil-keywords-restrict-cats-show', wpilShowRestrictCategoryList);
    function wpilShowRestrictCategoryList(){
        console.log(this);
        var button = $(this);
        button.parents('.block').find('.wpil-keywords-restrict-cats').toggle();
        button.toggleClass('open');
    }

    function wpil_keyword_clear_fields() {
        $('input[name="keyword"]').val('');
        $('input[name="link"]').val('');
    }

    function wpil_keyword_add() {
        var form = $('#add_keyword_form');
        var keyword = form.find('input[name="keyword"]').val();
        var link = form.find('input[name="link"]').val();

        if(keyword.length === 0 || link.length === 0){
            wpil_swal({"title": "Auto-Link Field Empty", "text": "Please make sure there's a Keyword and a Link in the Auto-Link creation fields before attempting to creating an Auto-Link.", "icon": "info"});
            return;
        }

        var restrictedToDate = $('#wpil_keywords_restrict_date').prop('checked') ? 1 : 0;
        var restrictedToCat = $('#wpil_keywords_restrict_to_cats').prop('checked') ? 1 : 0;
        var setPriority = $('#wpil_keywords_set_priority').prop('checked') ? 1 : 0;

        form.find('input[type="text"]').hide();
        form.find('.progress_panel').show();
        var params = {
            keyword: keyword,
            link: link,
            wpil_keywords_add_same_link: $('#wpil_keywords_add_same_link').prop('checked') ? 1 : 0,
            wpil_keywords_link_once: $('#wpil_keywords_link_once').prop('checked') ? 1 : 0,
            wpil_keywords_select_links: $('#wpil_keywords_select_links').prop('checked') ? 1 : 0,
            wpil_keywords_set_priority: setPriority,
            wpil_keywords_restrict_date: restrictedToDate,
            wpil_keywords_case_sensitive: $('#wpil_keywords_case_sensitive').prop('checked') ? 1 : 0,
            wpil_keywords_force_insert: $('#wpil_keywords_force_insert').prop('checked') ? 1 : 0,
            wpil_keywords_restrict_to_cats: restrictedToCat,
        };

        if(setPriority){
            var priority = $('#wpil_keywords_priority_setting').val();
            if(!priority){
                priority = null;
            }
            params['wpil_keywords_priority_setting'] = priority; 
        }

        if(restrictedToDate){
            var date = $('#wpil_keywords_restricted_date').val();
            if(!date){
                date = null;
            }
            params['wpil_keywords_restricted_date'] = date; 
        }

        if(restrictedToCat){
            var selectedCats = [];
            $('#wpil_keywords_settings .wpil-restrict-keywords-input:checked').each(function(index, element){
                selectedCats.push($(element).data('term-id'));
            });

            params['restricted_cats'] = selectedCats; 
        }

        wpil_keyword_process(null, 0, form, params);
    }

    function wpil_keyword_local_settings_save() {
        var keyword_id = $(this).data('id');
        var form = $(this).closest('.local_settings');
        form.find('.block').hide();
        form.find('.progress_panel').show();
        var setPriority = form.find('input[type="checkbox"][name="wpil_keywords_set_priority"]').prop('checked') ? 1 : 0;
        var restrictedToDate = form.find('input[type="checkbox"][name="wpil_keywords_restrict_date"]').prop('checked') ? 1 : 0;
        var restrictedToCats = form.find('input[type="checkbox"][name="wpil_keywords_restrict_to_cats"]').prop('checked') ? 1 : 0;
        var params = {
            wpil_keywords_add_same_link: form.find('input[type="checkbox"][name="wpil_keywords_add_same_link"]').prop('checked') ? 1 : 0,
            wpil_keywords_link_once: form.find('input[type="checkbox"][name="wpil_keywords_link_once"]').prop('checked') ? 1 : 0,
            wpil_keywords_select_links: form.find('input[type="checkbox"][name="wpil_keywords_select_links"]').prop('checked') ? 1 : 0,
            wpil_keywords_restrict_date: restrictedToDate,
            wpil_keywords_case_sensitive: form.find('input[type="checkbox"][name="wpil_keywords_case_sensitive"]').prop('checked') ? 1 : 0,
            wpil_keywords_force_insert: form.find('input[type="checkbox"][name="wpil_keywords_force_insert"]').prop('checked') ? 1 : 0,
            wpil_keywords_restrict_to_cats: restrictedToCats,
            wpil_keywords_set_priority: setPriority
        };

        if(setPriority){
            var priority = form.find('input[name="wpil_keywords_priority_setting"]').val();
            if(!priority){
                priority = 0;
            }
            params['wpil_keywords_priority_setting'] = parseInt(priority); 
        }

        if(restrictedToDate){
            var date = form.find('input[name="wpil_keywords_restricted_date"]').val();
            if(!date){
                date = null;
            }
            params['wpil_keywords_restricted_date'] = date; 
        }

        if(restrictedToCats){
            var selectedCats = [];
            form.find('input.wpil-restrict-keywords-input[type="checkbox"]:checked').each(function(index, element){
                selectedCats.push($(element).data('term-id'));
            });

            params['restricted_cats'] = selectedCats; 
        }

        wpil_keyword_process(keyword_id, 0, form, params);
    }

    function wpil_keyword_process(keyword_id, total, form, params = {}) {
        var data = {
            action: 'wpil_keyword_add',
            nonce: wpil_keyword_nonce,
            keyword_id: keyword_id,
            total: total
        }

        for (var key in params) {
            data[key] = params[key];
        }

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: data,
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                wpil_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(wpil_keyword_process(keyword_id, keyword, link));
            },
            success: function(response){
                if (response.error) {
                    wpil_swal(response.error.title, response.error.text, 'error');
                    return;
                }

                form.find('.progress_count').text(parseInt(response.progress) + '%');
                if (response.finish) {
                    location.reload();
                } else {
                    if (response.keyword_id && response.total) {
                        wpil_keyword_process(response.keyword_id, response.total, form);
                    }
                }
            }
        });
    }

    function wpil_keyword_reset() {
        $('#wpil_keywords_table .table').hide();
        $('#wpil_keywords_table .progress').show();
        wpil_keyword_reset_process(1, 1);
    }

    function wpil_keyword_reset_process(count, total) {
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'wpil_keyword_reset',
                nonce: wpil_keyword_nonce,
                count: count,
                total: total,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                wpil_swal({"title": "Error", "content": wrapper, "icon": "error"}).then(wpil_keyword_reset_process(1, 1));
            },
            success: function(response){
                if (response.error) {
                    wpil_swal(response.error.title, response.error.text, 'error');
                    return;
                }

                var progress = Math.floor((response.ready / response.total) * 100);
                $('#wpil_keywords_table .progress .progress_count').text(progress + '%' + ' ' + response.ready + '/' + response.total);
                if (response.finish) {
                    location.reload();
                } else {
                    wpil_keyword_reset_process(response.count, response.total)
                }
            }
        });
    }

    function wpil_insert_selected_keywords(e){
        e.preventDefault();

        var parentCell = $(this).closest('.wpil-dropdown-column');
        var checkedLinks = $(this).closest('td.column-select_links').find('[name=wpil_keyword_select_link]:checked');
        var linkIds = [];

        $(checkedLinks).each(function(index, element){
            var id = $(element).data('select-keyword-id');
            if(id){
                linkIds.push(id);
            }
        });

        if(linkIds.length < 1){
            return;
        }

        // hide the dropdown and show the loading bar
        parentCell.find('.wpil-collapsible-wrapper').css({'display': 'none'});
        parentCell.find('.progress_panel.loader').css({'display': 'block'});

        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                action: 'wpil_insert_selected_keyword_links',
                link_ids: linkIds,
                nonce: wpil_keyword_nonce,
            },
            error: function (jqXHR, textStatus, errorThrown) {
                var wrapper = document.createElement('div');
                $(wrapper).append('<strong>' + textStatus + '</strong><br>');
                $(wrapper).append(jqXHR.responseText);
                wpil_swal({"title": "Error", "content": wrapper, "icon": "error"});
                // hide the loading bar and show the dropdown
                parentCell.find('.progress_panel.loader').css({'display': 'none'});
                parentCell.find('.wpil-collapsible-wrapper').css({'display': 'block'});
            },
            success: function(response){
                if (response.error) {
                    wpil_swal(response.error.title, response.error.text, 'error');

                    // hide the loading bar and show the dropdown
                    parentCell.find('.progress_panel.loader').css({'display': 'none'});
                    parentCell.find('.wpil-collapsible-wrapper').css({'display': 'block'});
                    return;
                }

                if (response.success) {
                    wpil_swal({"title": response.success.title, "text": response.success.text, "icon": "success"}).then(function(){
                        location.reload();
                    });
                } else {
                    location.reload();
                }
            }
        });
    }

    $('.wpil-select-all-possible-keywords').on('change', function(e){
        var id = $(this).data('keyword-id');
        if($(this).is(':checked')){
            $('.column-select_links .wpil-content .keyword-' + id + ' li input[name="wpil_keyword_select_link"]').prop('checked', true);
        }else{
            $('.column-select_links .wpil-content .keyword-' + id + ' li input[name="wpil_keyword_select_link"]').prop('checked', false);
        }
    });
})(jQuery);