<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Community Co-op

    This file is part of Fannie.

    Fannie is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    Fannie is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include('../config.php');
include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');

class AdvancedItemSearch extends FannieRESTfulPage
{
    protected $header = 'Advanced Search';
    protected $title = 'Advanced Search';

    function preprocess()
    {
        $this->__routes[] = 'get<search>';
        return parent::preprocess();
    }

    function get_search_handler()
    {
        global $FANNIE_OP_DB;

        /**
          Step 1:
          Get a preliminary item set by querying products table
        */

        $from = 'products AS p';
        $where = '1=1';
        $args = array();

        $upc = FormLib::get('upc');
        if ($upc !== '') {
            if (strstr($upc, '*')) {
                $upc = str_replace('*', '%', $upc);
                $where .= ' AND p.upc LIKE ? ';
                $args[] = $upc;
            } else {
                $upc = str_pad($upc, 13, '0', STR_PAD_LEFT);
                $where .= ' AND p.upc = ? ';
                $args[] = $upc;
            }
        }

        $desc = FormLib::get('description');
        if ($desc !== '') {
            $where .= ' AND p.description LIKE ? ';
            $args[] = '%' . $desc . '%';
        }

        $brand = FormLib::get('brand');
        if ($brand !== '') {
            if (is_numeric($brand)) {
                $where .= ' AND p.upc LIKE ? ';
                $args[] = '%' . $brand . '%';
            } else {
                $where .= ' AND (x.manufacturer LIKE ? OR v.brand LIKE ?) ';
                $args[] = '%' . $brand . '%';
                $args[] = '%' . $brand . '%';
                if (!strstr($from, 'prodExtra')) {
                    $from .= ' LEFT JOIN prodExtra AS x ON p.upc=x.upc ';
                }
                if (!strstr($from, 'vendorItems')) {
                    $from .= ' LEFT JOIN vendorItems AS v ON p.upc=v.upc ';
                }
            }
        }

        $superID = FormLib::get('superID');
        if ($superID !== '') {
            if (!strstr($from, 'superdepts')) {
                $from .= ' LEFT JOIN superdepts AS s ON p.department = s.dept_ID ';
            }
            $where .= ' AND s.superID = ? ';
            $args[] = $superID;
        }

        $dept1 = FormLib::get('deptStart');
        $dept2 = FormLib::get('deptEnd');
        if ($dept1 !== '' || $dept2 !== '') {
            // work with just one department field set
            if ($dept1 === '') {
                $dept1 = $dept2;
            } else if ($dept2 === '') {
                $dept2 = $dept1;
            }
            // swap order if needed
            if ($dept2 > $dept1) {
                $tmp = $dept1;
                $dept1 = $dept2;
                $dept2 = $tmp;
            }
            $where .= ' AND p.department BETWEEN ? AND ? ';
            $args[] = $dept1;
            $args[] = $dept2;
        }

        $modDate = FormLib::get('modDate');
        if ($modDate !== '') {
            switch(FormLib::get('modOp')) {
            case 'Before':
                $where .= ' AND p.modified < ? ';
                $args[] = $modDate . ' 00:00:00';
                break;
            case 'After':
                $where .= ' AND p.modified > ? ';
                $args[] = $modDate . ' 23:59:59';
                break;
            case 'On':
            default:
                $where .= ' AND p.modified BETWEEN ? AND ? ';
                $args[] = $modDate . ' 00:00:00';
                $args[] = $modDate . ' 23:59:59';
                break;
            }
        }

        $vendorID = FormLib::get('vendor');
        if ($vendorID !== '') {
            $where .= ' AND v.vendorID=? ';
            $args[] = $vendorID;
            if (!strstr($from, 'vendorItems')) {
                $from .= ' LEFT JOIN vendorItems AS v ON p.upc=v.upc ';
            }
        }

        $tax = FormLib::get('tax');
        if ($tax !== '') {
            $where .= ' AND p.tax=? ';
            $args[] = $tax;
        }

        $local = FormLib::get('local');
        if ($local !== '') {
            $where .= ' AND p.local=? ';
            $args[] = $local;
        }

        $fs = FormLib::get('fs');
        if ($fs !== '') {
            $where .= ' AND p.foodstamp=? ';
            $args[] = $fs;
        }

        $discount = FormLib::get('discountable');
        if ($discount !== '') {
            $where .= ' AND p.discount=? ';
            $args[] = $discount;
        }

        $dbc = FannieDB::get($FANNIE_OP_DB);
        $query = 'SELECT p.upc, p.description FROM ' . $from . ' WHERE ' . $where;
        $prep = $dbc->prepare($query);
        $result = $dbc->execute($prep, $args);

        $items = array();
        while($row = $dbc->fetch_row($result)) {
            $items[$row['upc']] = $row['description'];
        }

        /**
          Step two:
          Filter results based on sale-related
          parameters
        */
        $sale = FormLib::get('onsale');
        if ($sale !== '') {

            $where = '1=1';
            $args = array();

            $saletype = FormLib::get('saletype');
            if ($saletype !== '') {
                $where .= ' AND b.batchType = ? ';
                $args[] = $saletype;
            }

            $all = FormLib::get('sale_all', 0);
            $prev = FormLib::get('sale_past', 0);
            $now = FormLib::get('sale_current', 0);
            $next = FormLib::get('sale_upcoming', 0);
            // all=1 or all three times = 1 means no date filter
            if ($all == 0 && ($prev == 0 || $now == 0 || $next == 0)) {
                // all permutations where one of the times is zero
                if ($prev == 1 && $now == 1) {
                    $where .= ' AND b.endDate <= ' . $dbc->curdate();
                } else if ($prev == 1 && $next == 1) {
                    $where .= ' AND (b.endDate < ' . $dbc->curdate() . ' OR b.startDate > ' . $dbc->curdate() . ') ';
                } else if ($prev == 1) {
                    $where .= ' AND b.endDate < ' . $dbc->curdate();
                } else if ($now == 1 && $next == 1) {
                    $where .= ' AND b.endDate >= ' . $dbc->curdate();
                } else if ($now == 1) {
                    $where .= ' AND b.endDate <= ' . $dbc->curdate();
                } else if ($next == 1) {
                    $where .= ' AND b.startDate > ' .$dbc->curdate();
                }
            }

            $query = 'SELECT l.upc FROM batchList AS l INNER JOIN batches AS b
                        ON b.batchID=l.batchID WHERE ' . $where . ' 
                        GROUP BY l.upc';
            $prep = $dbc->prepare($query);
            $result = $dbc->execute($prep, $args);
            $saleUPCs = array();
            while($row = $dbc->fetch_row($result)) {
                $saleUPCs[] = $row['upc'];
            }

            if ($sale == 0) {
                // only items that are not selected sales
                foreach($saleUPCs as $s_upc) {
                    if (isset($items[$s_upc])) {
                        unset($items[$s_upc]);
                    }
                }
            } else {
                // noly items that are in selected sales
                // collect items in both sets
                $valid = array();
                foreach($saleUPCs as $s_upc) {
                    if (isset($items[$s_upc])) {
                        $valid[$s_upc] = $items[$s_upc];
                    }
                }
                $items = $valid;
            }
        }

        echo $this->streamOutput($items);

        return false;
    }

    private function streamOutput($data) {
        $ret = '<table cellspacing="0" cellpadding="4" border="1">';
        foreach($data as $upc => $desc) {
            $ret .= sprintf('<tr><td><a href="ItemEditorPage.php?searchupc=%s" target="_advs%s">%s</a></td>
                            <td>%s</td></tr>', 
                            $upc, $upc, $upc, $desc);
        }
        $ret .= '</table>';

        return $ret;
    }

    function javascript_content()
    {
        ob_start();
        ?>
function getResults() {
    var dstr = $('#searchform').serialize();
    console.log(dstr);
    $.ajax({
        url: 'AdvancedItemSearch.php',
        type: 'get',
        data: 'search=1&' + dstr,
        success: function(data) {
            $('#resultArea').html(data);
        }
    });
}
        <?php
        return ob_get_clean();
    }

    function get_view()
    {
        global $FANNIE_OP_DB, $FANNIE_URL;
        $dbc = FannieDB::get($FANNIE_OP_DB);
        $this->add_script($FANNIE_URL.'src/CalendarControl.js');

        $ret = '<form method="post" id="searchform" onsubmit="getResults(); return false;">';
        $ret .= '<table>';    

        $ret .= '<tr>';

        $ret .= '<th>UPC</th><td><input type="text" size="12" name="upc" /></td>';

        $ret .= '<th>Super Dept</th><td><select name="superID"><option value="">Select...</option>';
        $supers = $dbc->query('SELECT superID, super_name FROM superDeptNames order by superID');
        while($row = $dbc->fetch_row($supers)) {
            $ret .= sprintf('<option value="%d">%s</option>', $row['superID'], $row['super_name']);
        }
        $ret .= '</select></td>';

        $ret .= '<th>Modified</th>';
        $ret .= '<td><select name="modOp"><option>On</option><option>Before</option><option>After</option></select>';
        $ret .= '<td><input type="text" name="modDate" onfocus="showCalendarControl(this);" size="10" /></td>';

        $ret .= '</tr><tr>';

        $ret .= '<th>Brand</th><td><input type="text" size="12" name="brand" /></td>';

        $ret .= '<th>Dept Start</th><td><select name="deptStart"><option value="">Select...</option>';
        $supers = $dbc->query('SELECT dept_no, dept_name FROM departments order by dept_no');
        while($row = $dbc->fetch_row($supers)) {
            $ret .= sprintf('<option value="%d">%d %s</option>', $row['dept_no'], $row['dept_no'], $row['dept_name']);
        }
        $ret .= '</select></td>';

        $ret .= '<th>Movement</th>';
        $ret .= '<td><select name="soldOp"><option>On</option><option>Before</option><option>After</option></select>';
        $ret .= '<td><input type="text" name="soldDate" onfocus="showCalendarControl(this);" size="10" /></td>';

        $ret .= '</tr><tr>';

        $ret .= '<th>Description</th><td><input type="text" size="12" name="description" /></td>';

        $ret .= '<th>Dept End</th><td><select name="deptEnd"><option value="">Select...</option>';
        $supers = $dbc->query('SELECT dept_no, dept_name FROM departments');
        while($row = $dbc->fetch_row($supers)) {
            $ret .= sprintf('<option value="%d">%d %s</option>', $row['dept_no'], $row['dept_no'], $row['dept_name']);
        }
        $ret .= '</select></td>';
        
        $ret .= '<th>Vendor</th><td colspan="2"><select name="vendor"><option value="">Any</option>';
        $vendors = $dbc->query('SELECT vendorID, vendorName FROM vendors');
        while($row = $dbc->fetch_row($vendors)) {
            $ret .= sprintf('<option value="%d">%s</option>', $row['vendorID'], $row['vendorName']);
        }
        $ret .= '</select></td>';

        $ret .= '</tr><tr>';

        $ret .= '<th>Tax</th><td><select name="tax"><option value="">Any</option><option value="0">NoTax</option>';
        $taxes = $dbc->query('SELECT id, description FROM taxrates');
        while($row = $dbc->fetch_row($taxes)) {
            $ret .= sprintf('<option value="%d">%s</option>', $row['id'], $row['description']);
        }
        $ret .= '</select></td>';

        $ret .= '<th>Local</th><td colspan="4"><select name="local"><option value="">Any</option><option value="0">No</option>';
        $origins = $dbc->query('SELECT originID, shortName FROM originName WHERE local=1');
        while($row = $dbc->fetch_row($origins)) {
            $ret .= sprintf('<option value="%d">%s</option>', $row['originID'], $row['shortName']);
        }
        $ret .= '</select>';

        $ret .= '&nbsp;&nbsp;&nbsp;';
        $ret .= '<b>FS</b>: <select name="fs"><option value="">Any</option><option value="1">Yes</option><option value="0">No</option></select>';
        $ret .= '&nbsp;&nbsp;&nbsp;';
        $ret .= '<b>Discountable</b>: <select name="discountable"><option value="">Any</option><option value="1">Yes</option><option value="0">No</option></select>';

        $ret .= '</td>'; // can fit another item here

        $ret .= '</tr><tr>';

        $ret .= '<th>On Sale</th><td><select name="onsale"><option value="">Any</option>';
        $ret .= '<option value="1">Yes</option><option value="0">No</option>';
        $ret .= '</td>';

        $ret .= '<th>Sale Type</th><td><select name="saletype"><option value="">Any</option>';
        $vendors = $dbc->query('SELECT batchTypeID, typeDesc FROM batchType WHERE discType <> 0');
        while($row = $dbc->fetch_row($vendors)) {
            $ret .= sprintf('<option value="%d">%s</option>', $row['batchTypeID'], $row['typeDesc']);
        }
        $ret .= '</select></td>';

        $ret .= '<td colspan="3">';
        $ret .= '<input type="checkbox" name="sale_all" id="sale_all" value="1" /> <label for="sale_all">All Sales</label> ';
        $ret .= '<input type="checkbox" name="sale_past" id="sale_past" value="1" /> <label for="sale_past">Past</label> ';
        $ret .= '<input type="checkbox" name="sale_current" id="sale_current" value="1" /> <label for="sale_current">Current</label> ';
        $ret .= '<input type="checkbox" name="sale_upcoming" id="sale_upcoming" value="1" /> <label for="sale_upcoming">Upcoming</label> ';
        
        $ret .= '</tr>';

        $ret .= '</table>';
        $ret .= '<input type="submit" value="Find Items" />';
        $ret .= '</form>';

        $ret .= '<hr />';

        $ret .= '<div id="resultArea"></div>';

        return $ret;
    }

}

FannieDispatch::go();

