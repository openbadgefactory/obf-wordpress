jQuery(document).ready(function($) {

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
                

		$.ajax({
			type : "post",
			dataType : "json",
			url : ajaxurl,
			data : { "action": "search_obf_categories", "search_terms": search_terms },
			success : function(response) {
				 $('#obf-badge-settings fieldset').append(response);
				 $('#obf_search_results').show();
			}
		});

	});

});