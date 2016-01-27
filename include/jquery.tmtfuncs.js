$(document).ready(function() {
 $('p.help').hide();
 $('img.tipper').css('display','inline');
 var elem = $('p.pageinput:first');
 var color = $(elem).css('color');
 var size = $(elem).css('font-size');
 $('.fakeicon').css({'color':color,'font-size':size});
 $('p img.tipper').click(function(){
  $(this).parent().nextAll('p.help:first').slideToggle();
 });
 $('th img.tipper').click(function(){
  $(this).closest('table').nextAll('p.help:first').slideToggle();
 });
});
