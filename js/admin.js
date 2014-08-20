jQuery(document).ready(function($) {
	// languages form
	// fills the fields based on the language dropdown list choice
	$('#lang_list').change(function() {
		value = $(this).val().split('-');
		selected = $("select option:selected").text().split(' - ');
		$('#lang_slug').val(value[0]);
		$('#lang_locale').val(value[1]);
		$('input[name="rtl"]').val([value[2]]);
		$('#lang_name').val(selected[0]);
	});

	// settings page
	// manages visibility of fields
	$("input[name='force_lang']").change(function() {
		function eml_toggle(a, test) {
			test ? a.show() : a.hide();
		}

		var value = $(this).val();
		eml_toggle($('#eml-domains-table'), 3 == value);
		eml_toggle($("#eml-hide-default"), 3 > value);
		eml_toggle($("#eml-detect-browser"), 3 > value);
		eml_toggle($("#eml-rewrite"), 2 > value);

		eml_toggle($("#eml-url-complements"), 3 > value || $("input[name='redirect_lang']").size());
	});
});
