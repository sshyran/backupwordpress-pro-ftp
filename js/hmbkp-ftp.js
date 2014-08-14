(function ($) {
	"use strict";
	$(function () {

		$(document).on('change', 'select[id*=connection_type]', function(e){
			switch (this.value){
				case 'ftp':
					$('input[id*=port]').val('21');
					break;
				case 'sftp':
					$('input[id*=port]').val('22');
					break;
				case 'ssh2':
					$('input[id*=port]').val('900');
					break;
			}
		});

	});
}(jQuery));