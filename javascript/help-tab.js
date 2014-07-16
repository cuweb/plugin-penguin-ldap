var j$ = jQuery.noConflict();

j$(document).ready(function () {
	j$("#test-button").click(function() {
	
		var data = {
			'action': 'my_action',
			'whatever': 1234
		};
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		j$.post(ajaxurl, data, function (res, err) {
			//if (err) alert (err);
			j$('#push-result').append (res).fadeIn();
		});
	});	
});;
