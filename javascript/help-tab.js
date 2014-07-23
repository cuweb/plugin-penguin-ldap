var j$ = jQuery.noConflict();

var $pushResult = j$('#push-result');

j$(document).ready(function () {
	j$("#test-button").click(function() {
		$pushResult.fadeOut();
	
		var data = {
			'action': 'my_action'
		};
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		j$.post(ajaxurl, data, function (res, err) {
			$pushResult.empty().append(res).fadeIn();
		});
	});	
});;
