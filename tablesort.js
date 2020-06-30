var last_sort_column = 0;
var last_sort_dir = 1;
function resortTable(table) {
  if( !last_sort_column ) {
    last_sort_column = table.find('thead .clicksort')[0].cellIndex+1;
    last_sort_dir = 1;
  }
  sortTableDir(table,last_sort_column,last_sort_dir,1,1);
}
function lastLine(str) {
  str = str.trim();
  var pos = str.lastIndexOf("\n");
  if( pos == -1 ) return str;
  return str.substring(pos+1).trim();
}
function sortTable(table,column,column2) {
  var dir = 1;
  if( last_sort_column == column ) {
    dir = -last_sort_dir;
  }
  sortTableDir(table,column,dir,column2,1);
}
function getTableParent(element) {
  return $(element).closest('table');
}
function getCellSortText(element) {
  var sort_text;
  var input = element.getElementsByTagName("input")[0];
  if( input ) {
    if( input.getAttribute("type") == "checkbox" ) {
      sort_text = input.checked ? "1" : "0";
    } else {
      sort_text = input.getAttribute("value");
      if( !sort_text ) sort_text = input.getAttribute("placeholder");
      if( !sort_text ) sort_text = element.textContent;
    }
  } else {
    sort_text = element.textContent;
  }
  return lastLine(sort_text);
}
function getTdSortText(parent,column) {
  var cur_col = 0;
  for(var i=0; i<parent.childNodes.length; i++) {
    var e = parent.childNodes[i];
    /* this skips over text nodes such as a stray "\n" between td elements */
    if( e.nodeName == "TD" ) {
      if( cur_col == column ) return getCellSortText(e);
      cur_col += 1;
    }
  }
  return "";
}
var cmp_table_cell_num_re = /^\s*-?[0-9]*[.]?[0-9]*\s*$/;
function cmpTableCells(a_field,b_field) {
  var a_num = parseFloat(a_field);
  if( !Number.isNaN(a_num) ) {
      var b_num = parseFloat(b_field);
      if( !Number.isNaN(b_num) ) {
          if( cmp_table_cell_num_re.test(a_field) && cmp_table_cell_num_re.test(b_field) ) {
            if( a_num > b_num ) return 1;
            if( a_num < b_num ) return -1;
            return 0;
          }
      }
  }
  // using 'en-US-u-kf-upper' so uppercase sorts before lowercase rather than the other way around
  return a_field.localeCompare(b_field,'en-US-u-kf-upper');
}
function sortTableDir(table,column,dir,column2,dir2) {
  table = getTableParent(table);

  last_sort_column = column;
  last_sort_dir = dir;

  var thead = table.find('thead');
  thead.find('th.clicksort').addClass('sorting');

  // Do the remainder of the sorting in a function that gets called
  // shortly after sortTable() returns.  This improves the chance
  // (but does not apparently guarantee) that the mouse cursor will
  // change to a progress pointer as defined in the css for 'sorting'
  setTimeout(function() {

  var tbody = table.find('tbody');
  tbody.find('tr').sort(function(a, b) {
    //var a_vis = $(a).is(':visible');
    //var b_vis = $(b).is(':visible');
    // _much_ faster than the above:
    var a_vis = window.getComputedStyle(a).display !== 'none';
    var b_vis = window.getComputedStyle(b).display !== 'none';

    if( a_vis && !b_vis ) return -1*dir;
    if( !a_vis && b_vis ) return 1*dir;

    var a_field = getTdSortText(a,column-1);
    var b_field = getTdSortText(b,column-1);

    var cmp = cmpTableCells(a_field,b_field);
    if( cmp ) return cmp*dir;

    a_field = getTdSortText(a,column2-1);
    b_field = getTdSortText(b,column2-1);
    cmp = cmpTableCells(a_field,b_field);
    return cmp*dir2;
  }).appendTo(tbody);

  var sorticon = dir == 1 ? "&blacktriangledown;" : "&blacktriangle;";

  // Add sorticon placeholders to all sortable columns.
  // We want them to take up space even if they are not visible,
  // to avoid ugly jumps in column sizes when changing which column
  // is sorted.
  thead.find('th.clicksort').each(function(index) {
    if( !$(this).find('.sorticon').length ) {
      $(this).html( $(this).html() + "<span class='sorticon'>" + sorticon + "</span>");
    }
  });
  // Hide all sort icons
  thead.find('.sorticon').css("visibility","hidden");
  // Fixup the direction of the active sorticon and make it visible
  thead.find('th:nth-child(' + column + ') .sorticon').html(sorticon).css("visibility","visible");

  thead.find('th.clicksort').removeClass('sorting');

  },1); // end of setTimeout()
}
