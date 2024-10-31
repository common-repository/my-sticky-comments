jQuery(document).ready(function($){
	/* Move the checkbox in the Status container */
	$('#sc_container').appendTo('#comment-status-radio');
	
	$('input[name="sticky2_type"]').change(function(){

		if ($(this).val() == 'text') {
			$('#sticky2_image').parent().parent().css('display', 'none');
			$('input[name="sticky2_text"]').parent().parent().css('display', 'table-row');
		} else {
			$('input[name="=sticky1_text"]').parent().parent().css('display', 'none');
			$('#sticky1_image').parent().parent().css('display', 'table-row');		
		}
	});
	
});