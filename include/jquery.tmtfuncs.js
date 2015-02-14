$(document).ready(function() {
 $('p.help').hide();
 $('img.tipper').css({'display':'inline','padding-left':'10px'}).click(function(){
  $(this).parent().next().next().slideToggle();
 });
 var elem = $('p.pageinput:first');
 var color = $(elem).css('color');
 var size = $(elem).css('font-size');
 $('.fakeicon').css({'color':color,'font-size':size});
});
