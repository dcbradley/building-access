/*
  Allow users to sort tables by clicking on column headers.
  Requires jquery.

  To make all th columns in a table sortable, add the class 'clicksort' to the table.
  To prevent a column from being sortable add the class 'no-clicksort' to that column header or use td instead of th.
  Alternatively, do not define 'clicksort' in the table.  Define it in each sortable column.

  To define a secondary sort column, add the class 'clicksort-secondary' to that column header.
  To make a column sort in reverse order by default, add the class 'clicksort-reverse' to the column header.
  To make the table sort itself upon page load, add the class 'clicksort-init' to the table.

  To programmatically sort a table, call one of the functions:
  clicksort.sortTable()
  clicksort.resortTable()
  clicksort.sortTableDir()
*/

// put everything in the namespace 'clicksort'
var clicksort = (function() {

var my = {};

function resortTable(table) {
  // use the current columns and directions
  sortTableDir(table,0,0,0,0);
}
function sortTable(table,column,column2) {
  // reverse direction of the primary sort column
  // use default direction for secondary sort column
  sortTableDir(table,column,-2,column2,0);
}
function lastLine(str) {
  str = str.trim();
  var pos = str.lastIndexOf("\n");
  if( pos == -1 ) return str;
  return str.substring(pos+1).trim();
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
function getColIndex(e) {
  table = $(e).closest('table');
  var tr = table.find('thead tr');
  var rowspans = {};
  for(var i=0; i<tr.length; i++) {
    var cur_col = 0;
    if( cur_col in rowspans ) {
      cur_col += rowspans[cur_col];
    }
    var cols = $(tr[i]).find('th,td');
    for(var j=0; j<cols.length; j++) {
      if( cols[j] == e ) return cur_col+1;
      if( cols[j].rowSpan>1 ) {
        rowspans[cur_col] = cols[j].colSpan;
      }
      cur_col += cols[j].colSpan;
      if( cur_col in rowspans ) {
        cur_col += rowspans[cur_col];
      }
    }
  }
  return 0;
}
function getColAtIndex(table,index) {
  var tr = $(table).find('thead tr');
  var rowspans = {};
  var best_so_far = null;
  for(var i=0; i<tr.length; i++) {
    var cur_col = 0;
    if( cur_col in rowspans ) {
      cur_col += rowspans[cur_col];
    }
    var cols = $(tr[i]).find('th,td');
    for(var j=0; j<cols.length; j++) {
      if( index == cur_col+1 ) {
        // If there are multiple rows in the header, there may be multiple
	// columns matching the same column index.  Return the first
	// one that contains the clicksort class.  Failing that,
	// return the first one.
        if( cols[j].classList.contains('clicksort') ) {
          return cols[j];
	}
	if( !best_so_far ) {
	  best_so_far = cols[j];
	}
      }
      if( cols[j].rowSpan>1 ) {
        rowspans[cur_col] = cols[j].colSpan;
      }
      cur_col += cols[j].colSpan;
      if( cur_col in rowspans ) {
        cur_col += rowspans[cur_col];
      }
    }
  }
  return best_so_far;
}
function getPrimaryClickSortColumn(table,default_column1) {
  if( default_column1 ) return default_column1;

  var cols = $(table).find('thead tr .clicksort');
  var first_col = 0;
  var secondary_col = 0;
  for(var i=0; i<cols.length; i++) {
    var e = cols[i];
    var cur_col = getColIndex(e);

    if( e.classList.contains('clicksort-cur-primary') ) {
      return cur_col;
    }
    if( e.classList.contains('clicksort-secondary') ) {
      secondary_col = cur_col;
    }
    if( first_col == 0 && e.classList.contains('clicksort') ) {
      first_col = cur_col;
    }
  }
  if( secondary_col ) return secondary_col;
  return first_col;
}
function getSecondaryClickSortColumn(table,default_column2,primary_col) {
  if( default_column2 ) return default_column2;

  var cols = $(table).find('thead tr .clicksort');
  var first_col = 0;
  var second_col = 0;
  for(var i=0; i<cols.length; i++) {
    var e = cols[i];
    var cur_col = getColIndex(e);

    if( e.classList.contains('clicksort-secondary') ) {
      return cur_col;
    }

    if( first_col == 0 ) first_col = cur_col;
    else if( second_col == 0 ) second_col = cur_col;
  }
  if( first_col && first_col != primary_col ) return first_col;
  if( second_col ) return second_col;
  return first_col;
}
function setClickSortColumnClass(table,column,class_name) {
  var e = getColAtIndex(table,column);
  if( e ) {
    e.classList.add(class_name);
  }
}
function clearClickSortColumnClass(table,class_name) {
  var cols = $(table).find('thead tr .' + class_name);
  for(var i=0; i<cols.length; i++) {
    var e = cols[i];
    e.classList.remove(class_name);
  }
}
function getClickSortColumnDir(table,column,dir) {
  var cur_dir = 0;
  var e = getColAtIndex(table,column);
  if( e ) {
    if( e.classList.contains("clicksort-az") ) {
      cur_dir = 1;
    }
    else if( e.classList.contains("clicksort-za") ) {
      cur_dir = -1;
    }
  }
  if( dir == 0 ) {
    dir = cur_dir;
  }
  else if( dir == -2 ) {
    dir = -1*cur_dir;
  }
  if( dir == 0 ) {
    if( e && e.classList.contains("clicksort-reverse") ) {
      dir = -1;
    } else {
      dir = 1;
    }
  }
  return dir;
}
function getClickSortSecondaryColumnDir(table,column,dir) {
  var e = getColAtIndex(table,column);
  if( dir == 0 ) {
    if( e && e.classList.contains("clicksort-reverse") ) {
      dir = -1;
    } else {
      dir = 1;
    }
  }
  return dir;
}
function setClickSortColumnDir(table,column,dir) {
  var cur_dir = 0;
  var e = getColAtIndex(table,column);
  if( e ) {
    if( dir >= 0 ) {
      e.classList.add("clicksort-az");
      e.classList.remove("clicksort-za");
    } else {
      e.classList.remove("clicksort-az");
      e.classList.add("clicksort-za");
    }
  }
}
function getTdSortText(parent,column) {
  if( parent && parent.cells.length > column ) {
    return getCellSortText(parent.cells[column]);
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
  // column: index (starting with 1) of primary column or 0 to use current/default
  // dir: -2 = flip direction, -1 = reverse, 0 = default, 1 = forward
  // column2: secondary column or 0 to use default
  // dir2: direction of secondary, coded the same way as dir

  table = $(table).closest('table')[0];
  if( !('clicksort_initialized' in table.dataset) ) {
    initClickSort(table,'.clicksort');
  }

  column = getPrimaryClickSortColumn(table,column);
  column2 = getSecondaryClickSortColumn(table,column2,column);

  dir = getClickSortColumnDir(table,column,dir);
  if( column2 == column ) {
    dir2 = dir;
  } else {
    dir2 = getClickSortSecondaryColumnDir(table,column2,dir2);
  }

  //console.log("clicksort(" + column + "," + dir + "," + column2 + "," + dir2 + ")");

  clearClickSortColumnClass(table,'clicksort-cur-primary');
  setClickSortColumnClass(table,column,'clicksort-cur-primary');
  clearClickSortColumnClass(table,'clicksort-az');
  clearClickSortColumnClass(table,'clicksort-za');
  setClickSortColumnDir(table,column,dir);

  var thead = $(table).find('thead');
  thead.find('th.clicksort').addClass('sorting');

  // Do the remainder of the sorting in a function that gets called
  // shortly after sortTable() returns.  This improves the chance
  // (but does not apparently guarantee) that the mouse cursor will
  // change to a progress pointer as defined in the css for 'sorting'
  setTimeout(function() {

  var tbody = $(table).find('tbody');
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
  thead.find('.clicksort').each(function(index) {
    if( !$(this).find('.sorticon').length ) {
      $(this).html( $(this).html() + "<span class='sorticon'>" + sorticon + "</span>");
    }
  });
  // Hide all sort icons
  thead.find('.sorticon').css("visibility","hidden");
  // Fixup the direction of the active sorticon and make it visible
  $(getColAtIndex(table,column)).find('.sorticon').html(sorticon).css("visibility","visible");

  thead.find('th.clicksort').removeClass('sorting');

  },1); // end of setTimeout()
}

function initClickSortElement(e) {
  if( 'clicksort_initialized' in e.dataset ) return;
  if( e.tagName == "TABLE" ) {
    initClickSort(e,"thead th:not(.no-clicksort)");
  } else if( e.tagName == "TH" ) {
    if( e.classList.contains("no-clicksort") ) return;
    if( !e.classList.contains("clicksort") ) {
      e.classList.add("clicksort");
    }
    e.addEventListener("click",function(evt) {
      sortTable(e,getColIndex(e),0);
    });
    $(e).closest('table')[0].dataset.clicksort_initialized = 1;
    e.dataset.clicksort_initialized = 1;
  }
}

function initClickSort(top,selector) {
  var nodes = top.querySelectorAll(selector);
  if( top != document && top.matches(selector) ) {
    initClickSortElement(top);
  }
  for( var i=0; i<nodes.length; i++ ) {
    initClickSortElement(nodes[i]);
  }
}
function initTableSort(top,selector) {
  var nodes = top.querySelectorAll(selector);
  for( var i=0; i<nodes.length; i++ ) {
    if( nodes[i].tagName == "TABLE" ) {
      resortTable(nodes[i]);
    }
  }
}
function init() {
  document.addEventListener("DOMContentLoaded", function(){
    initClickSort(document,'.clicksort');
    initTableSort(document,'.clicksort-init');
  });
}

// public functions
my.init = init;
my.resortTable = resortTable;
my.sortTable = sortTable;
my.sortTableDir = sortTableDir;

return my;
}());

clicksort.init();
