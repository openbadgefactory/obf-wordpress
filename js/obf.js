jQuery(document).ready(function($) {
        // Hide badge fields
        function obf_set_badge_field_visibility($visible) {
            jQuery('#post-body-content').toggle($visible);
            jQuery('#normal-sortables').toggle($visible);
        }
        // Check if badge editing is disabled and we want to hide badge fields
        function obf_badge_field_visibility_check() {
            if ($('#_badgeos_obf_editing_disabled').val() == 1) {
                obf_set_badge_field_visibility(false);
            } else {
                obf_set_badge_field_visibility(true);
            }
        }
        obf_badge_field_visibility_check();
        $('#_badgeos_obf_editing_disabled').change( function(){
            obf_badge_field_visibility_check();
        }).change();

	// Show badge sharing options only if "send to open badge factory" is enabled
	$('#_badgeos_send_to_obf').change( function(){
		if ( '0' == $(this).val() )
			$('#obf-badge-settings').hide();
		else
			$('#obf-badge-settings').show();
	}).change();

	// Retrieve credly category results via AJAX
	$('#obf_category_search_submit').click( function( event ) {

		// Stop the default submission from happening
		event.preventDefault();

		// Grab our form values
		var search_terms = $('#obf_category_search').val();
                
                var old_inputs = $('#obf-badge-settings fieldset').find('input'), old_categories = [];
                for (var i=0; i<old_inputs.length; i++) {
                    old_categories.push($(old_inputs[i]).val());
                }
		$.ajax({
			type : "post",
			dataType : "json",
			url : ajaxurl,
			data : { "action": "search_obf_categories", "search_terms": search_terms },
			success : function(response) {
                                var new_inputs = $(response);
                                for (var i=0; i<new_inputs.length; i++) {
                                    var el = new_inputs[i], name = $(el).attr('for');
                                    if (name && name.length > 0 && $.inArray(name, old_categories) == -1) {
                                        $('#obf-badge-settings fieldset').append(el);
                                        $('#obf-badge-settings fieldset').append('<br/>');
                                    }
                                }
				 //$('#obf-badge-settings fieldset').append(response);
				 $('#obf_search_results').show();
			}
		});

	});

});