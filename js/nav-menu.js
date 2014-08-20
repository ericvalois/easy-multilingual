jQuery(document).ready(function($) {
	$('#update-nav-menu').bind('click', function(e) {
		if ( e.target && e.target.className && -1 != e.target.className.indexOf('item-edit')) {
			$("input[value='#eml_switcher'][type=text]").parent().parent().parent().each( function(){
				var item = $(this).attr('id').substring(19);
				// remove default fields we don't need
				$(this).children('.description-thin,.field-link-target,.field-description,.field-url').remove();
				h = $('<input>').attr({
						type: 'hidden',
						id: 'edit-menu-item-title-'+item,
						name: 'menu-item-title['+item+']',
						value: eml_data.strings[4]
				});
				$(this).append(h);
				h = $('<input>').attr({
						type: 'hidden',
						id: 'edit-menu-item-url-'+item,
						name: 'menu-item-url['+item+']',
						value: '#eml_switcher'
				});
				$(this).append(h);
				// a hidden field which exits only if our jQuery code has been executed
				h = $('<input>').attr({
						type: 'hidden',
						id: 'edit-menu-item-eml-detect-'+item,
						name: 'menu-item-eml-detect['+item+']',
						value: 1
				});
				$(this).append(h);

				ids = Array('hide_current','force_home','show_flags','show_names'); // reverse order

				// add the fields
				for(var i = 0; i < ids.length; i++) {
					p = $('<p>').attr('class', 'description');
					$(this).prepend(p);
					label = $('<label>').attr('for', 'menu-item-'+ids[i]+'-'+item).text(' '+eml_data.strings[i]);
					p.append(label);
					cb = $('<input>').attr({
						type: 'checkbox',
						id: 'edit-menu-item-'+ids[i]+'-'+item,
						name: 'menu-item-'+ids[i]+'['+item+']',
						value: 1
					});
					if ((typeof(eml_data.val[item]) != 'undefined' && eml_data.val[item][ids[i]] == 1) || (typeof(eml_data.val[item]) == 'undefined' && ids[i] == 'show_names')) // show_names as default value
						cb.prop('checked', true);
					label.prepend(cb);
				}
			});

			// disallow unchecking both show names and show flags
			$('.menu-item-data-object-id').each(function() {
				var id = $(this).val();
				$('#edit-menu-item-show_flags-'+id).change(function() {
					if ('checked' != $(this).attr('checked'))
						$('#edit-menu-item-show_names-'+id).prop('checked', true);
				});
			});

		}
	});

});
