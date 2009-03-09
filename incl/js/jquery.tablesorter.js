/**
 * Know issues:
 * 1) Does not work with colspans
 * 2) Issue when th's withing table body.  Unpredictable behavior.
 */
 (function($) {

   var comparators = {
     STRING: function(a,b) {
       return $(a).text() == $(b).text() ? 0 :
                 $(a).text() > $(b).text() ? 1 : -1;
     },
     NUMERIC: function(a,b) {
       return parseFloat($(a).text()) == parseFloat($(b).text()) ? 0 :
                 parseFloat($(a).text()) > parseFloat($(b).text()) ? 1 : -1;
     },
     STRING_INSENSITIVE: function(a, b) {
       return $(a).text().toLowerCase() == $(b).text().toLowerCase() ? 0 :
                 $(a).text().toLowerCase() > $(b).text().toLowerCase() ? 1 : -1;
     }
   }

   $.fn.tsort = function(column,direction,compare) {
     var d = direction == -1 ? -1 : !!direction ? 1 : -1;

     var comp = $.isFunction(compare) ? compare :
                   comparators[compare] ? comparators[compare] : comparators.STRING_INSENSITIVE;

     this.each(function() {
       var $table = $(this);
       var arrayRows = $(this).find('tbody tr').get();
       arrayRows.sort(function(rowA,rowB) {
         return d*comp($(rowA).children('td:eq('+column+')').get(0),$(rowB).children('td:eq('+column+')').get(0));
       });
       $(arrayRows).each(function() {
         $table.find('tbody:first').append(this);
       });
     });
   }
 })(jQuery);

