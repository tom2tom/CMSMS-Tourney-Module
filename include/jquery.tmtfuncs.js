$(function() {
 $('.table_drag').tableDnD({
	dragClass: 'row1hover',
	onDrop: function(table, droprows) {
		var odd = true;
		var oddclass = 'row1';
		var evenclass = 'row2';
		var droprow = $(droprows)[0];
		$(table).find('tbody tr').each(function() {
			var name = odd ? oddclass : evenclass;
			if (this === droprow) {
				name = name+'hover';
			}
			$(this).removeClass().addClass(name);
			odd = !odd;
		});
		if (typeof ajaxData !== 'undefined' && $.isFunction(ajaxData)) {		
			var ajaxdata = ajaxData(droprow,droprows.length);
			if (ajaxdata) {
				$.ajax({
				 url: 'moduleinterface.php',
				 type: 'POST',
				 data: ajaxdata,
				 dataType: 'text',
				 success: dropresponse
				});
			}
		}
	 }
  }).find('tbody tr').removeAttr('onmouseover').removeAttr('onmouseout').mouseover(function() {
		var now = $(this).attr('class');
		$(this).attr('class', now+'hover');
  }).mouseout(function() {
		var now = $(this).attr('class');
		var to = now.indexOf('hover');
		$(this).attr('class', now.substring(0,to));
  });

 $('.updown').hide();
 $('.dndhelp').css('display','block');

 var elem = $('p.pageinput:first');
 var color = $(elem).css('color');
 var size = $(elem).css('font-size');
 $('.fakeicon').css({'color':color,'font-size':size});
});
