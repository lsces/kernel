// Shared component-title typeahead: debounce, overlapping-response race guard,
// and Up/Down/Enter/Escape keyboard nav, used by food's edit_movement.tpl/
// edit_assembly.tpl/add_assembly_item.tpl and stock's add_movement_component.tpl.
// Found hand-rolled near-identically across all four (the debounce/race-guard/
// keyboard-nav portion was byte-for-byte the same) - see liberty's
// LibertyContent::resolveContentIdByTitle() for this widget's PHP-side
// counterpart (the picked-id-or-title-fallback resolve, deduped the same way).
//
// Each page still owns its own per-row behaviour (e.g. food's qty_mode/SGL
// prefill) via the onSelect/onReset callbacks below - only the mechanics that
// were actually identical everywhere are shared here.
(function($) {
	window.BitComponentTypeahead = function(config) {
		var $input    = $(config.input);
		var $dd       = $(config.dropdown);
		var $id       = config.idField ? $(config.idField) : null;
		var minLength = config.minLength || 2;
		var debounce  = config.debounce  || 250;
		var timer;
		var seq = 0; // request-generation counter - see reqId below

		function buildLabel(row) {
			return row.supplier ? row.title + ' [' + row.supplier + ']' : row.title;
		}

		if (config.autofocus) { $input.trigger('focus'); }

		$input.on('input', function() {
			// Manual retyping invalidates whatever was previously selected -
			// otherwise a stale id could silently survive a hand-edit and point at
			// the wrong (same-titled, different-supplier) row.
			if ($id) { $id.val(''); }
			if (config.onReset) { config.onReset(); }
			var q = $(this).val();
			clearTimeout(timer);
			$dd.hide().empty();
			if (q.length < minLength) return;
			// clearTimeout above only cancels a fetch that hasn't fired yet - an
			// already-in-flight one from a previous keystroke can still land after
			// this one and, without the reqId check below, get appended on top
			// instead of replacing it (duplicate-looking rows from two overlapping
			// responses, not a duplicate in the data - real bug hit 2026-08-21,
			// reproducible only as a race, never via a direct query no matter the
			// data state).
			var reqId = ++seq;
			timer = setTimeout(function() {
				var params = $.extend({ q: q }, config.extraParams ? config.extraParams() : {});
				$.getJSON(config.lookupUrl, params, function(data) {
					if (reqId !== seq) return; // a newer request has since superseded this one
					$dd.empty();
					if (!data.length) return;
					$.each(data, function(i, row) {
						$dd.append($('<li>').append(
							$('<a>').attr('href', '#').data('row', row).text(buildLabel(row))
						));
					});
					$dd.show();
				});
			}, debounce);
		});

		$(document).on('mousedown', config.dropdown + ' a', function(e) {
			e.preventDefault();
			var row = $(this).data('row');
			$input.val(buildLabel(row));
			if ($id) { $id.val(row.content_id); }
			$dd.hide().empty();
			if (config.onSelect) { config.onSelect(row); }
		});

		$input.on('blur', function() { setTimeout(function() { $dd.hide(); }, 150); });

		$input.on('keydown', function(e) {
			if (!$dd.is(':visible')) return;
			var $links = $dd.find('a'), idx = $links.index($dd.find('li.active a'));
			if (e.key === 'ArrowDown') { e.preventDefault(); $links.parent().removeClass('active'); $links.eq(idx + 1 < $links.length ? idx + 1 : 0).parent().addClass('active'); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); $links.parent().removeClass('active'); $links.eq(idx > 0 ? idx - 1 : $links.length - 1).parent().addClass('active'); }
			else if (e.key === 'Enter') { var $a = $dd.find('li.active a'); if ($a.length) { e.preventDefault(); $a.trigger('mousedown'); } }
			else if (e.key === 'Escape') { $dd.hide(); }
		});
	};
}(jQuery));
