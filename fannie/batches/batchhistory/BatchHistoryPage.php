<?php
/*******************************************************************************

    Copyright 2009,2010 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('FannieAPI')) {
    include_once($FANNIE_ROOT.'classlib2.0/FannieAPI.php');
}
if (!function_exists('checkLogin')) {
    include_once($FANNIE_ROOT . 'auth/login.php');
}

class BatchHistoryPage extends FannieRESTfulPage
{
    protected $title = 'Batch History';
    protected $header = 'Product Batch History';

    public $description = '[Batch History Page] is the primary tool for viewing
        historical activity of batches.';

    function preprocess()
    {
        global $FANNIE_OP_DB;
        $this->con = FannieDB::get($FANNIE_OP_DB);
        $this->__routes[] = 'get<upc>';
        $this->__routes[] = 'get<bid>';

        return parent::preprocess();
    }

    function get_view()
    {
        return <<< HTML
<form method="get" class="form-inline">
    <input type="text" class="form-control" name="upc"/>
    <button type="submit" class="btn btn-default">View Product History</button>
</form><br />
HTML;
    }

    public function get_bid_view()
    {
        $bid = FormLib::get('bid');
        $ret = $this->getBatchHistory($bid);
        return $ret;
    }

    public function get_upc_view()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $ret = '';
        $bu = new BatchUpdateModel($dbc);
        $upc = FormLib::get('upc');
        $bu->upc($upc);
        $upcCols = array('batchID','updateType','upc','modified','user','specialPrice');
        $ret .= '<table class="table table-bordered table-condensed small" id="iTable"><thead>';
        foreach ($upcCols as $column) {
            $ret .= '<th>' . ucwords($column) . '</th>';
        }
        $ret .= '</thead><tbody>';
        foreach ($bu->find() as $obj) {
            $ret .= '<tr class="info">';
            if ($obj->upc()) {
                foreach ($upcCols as $upcCol) {
                    if ($upcCol == 'batchID'){
                        $ret .= '<td><a style="cursor: pointer;"
                            onClick="get_bid('.$obj->$upcCol().'); return false;">'
                            . $obj->$upcCol() . '</a></td>';
                    } else {
                        $ret .= '<td>' . $obj->$upcCol() . '</td>';
                    }
                }
            }
            $ret .= '</tr>';
        }
        $ret .= '</tbody></table>';
        $ret .= '
            <form method="get" id="bidForm">
                <input type="hidden" name="bid" id="bidIn" value="" />
            </form>
        ';

        return $ret;

    }
    
    /**
        @getProdBatchHist
        Return product batch history w/o loading Fannie ui
    */
    public function getProdBatchHist($upc)
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $ret = '';
        $bu = new BatchUpdateModel($dbc);
        $bu->upc($upc);
        $upcCols = array('batchID','updateType','upc','modified','user','specialPrice');
        $ret .= '<table class="table table-bordered table-condensed small" id="iTable"><thead>';
        foreach ($upcCols as $column) {
            $ret .= '<th>' . ucwords($column) . '</th>';
        }
        $ret .= '</thead><tbody>';
        foreach ($bu->find() as $obj) {
            $ret .= '<tr class="info">';
            if ($obj->upc()) {
                foreach ($upcCols as $upcCol) {
                    if ($upcCol == 'batchID'){
                        $ret .= '<td><a style="cursor: pointer;"
                            onClick="get_bid('.$obj->$upcCol().'); return false;">'
                            . $obj->$upcCol() . '</a></td>';
                    } else {
                        $ret .= '<td>' . $obj->$upcCol() . '</td>';
                    }
                }
            }
            $ret .= '</tr>';
        }
        $ret .= '</tbody></table>';
        $ret .= '
            <form method="get" id="bidForm">
                <input type="hidden" name="bid" id="bidIn" value="" />
            </form>
        ';

        return $ret;
    }

    /**
        @getBatchHistory
        Return batch history w/o loading Fannie ui
    */
    public function getBatchHistory($bid)
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $ret = '';
		$bm = new BatchesModel($dbc);
        $ret .= '<div class="" align="center"><h4 style="color: grey">Batch #<strong>'.$bid.'</strong></h4></div>';
        $bu = new BatchUpdateModel($dbc);
        $bu->batchID($bid);
        $bt = new BatchTypeModel($dbc);
        $columns = array('updateType','batchName','batchType','owner','startDate',
            'endDate','user','modified');
        $ret .= '<table class="table table-bordered table-condensed small" id="bTable"><thead>';
        foreach ($columns as $column) {
            $ret .= '<th>' . ucwords($column) . '</th>';
        }
        $ret .= '</thead><tbody>';
        $s = 1;
        foreach ($bu->find() as $obj) {
            if ($obj->upc() == NULL) {
                $ret .= '<tr class="warning">';
                foreach ($columns as $column) {
                    if ($s === 0 && $column != 'modified' && $column != 'updateType') {
                        if ($obj->$column() != ${'last_'.$column}) {
                            $fweight = 'font-weight: bold; color: #6b0000;';
                        } else {
                            $fweight = '';
                        }
                    }
                    ${'last_'.$column} = $obj->$column();
                    if ($column == 'startDate' || $column == 'endDate'){
                        $ret .= '<td style="'.$fweight.'">' . $obj->$column() . '</td>';
                    } else if ($column == 'modified') {
                        $ret .= '<td style="'.$fweight.'">' . $obj->$column() . '</td>';
                    } else if ($column == 'batchType') {
                        $bt->reset();
                        $bt->batchTypeID($obj->$column());
                        $bt->load();
                        $ret .= '<td style="'.$fweight.'">' . $bt->typeDesc() . '</td>';
                    } else {
                        $ret .= '<td style="'.$fweight.'">' . $obj->$column() . '</td>';
                    }
                }
                $s = 0;
            }
            $ret .= '</tr>';
        }
        $ret .= '</tbody></table>';

        $ret .= '<div class="" align="center"><h4 style="color: grey">Product Info</h4></div>';
        $upcCols = array('updateType','upc','modified','user','specialPrice');
        $ret .= '<table class="table table-bordered table-condensed small" id="iTable"><thead>';
        foreach ($upcCols as $column) {
            $ret .= '<th>' . ucwords($column) . '</th>';
        }
        $ret .= '</thead><tbody>';
        foreach ($bu->find() as $obj) {
            $ret .= '<tr class="info">';
            if (!$obj->upc() == NULL) {
                foreach ($upcCols as $upcCol) {
                    $ret .= '<td>' . $obj->$upcCol() . '</td>';
                }
            }
            $ret .= '</tr>';
        }

        $ret .= '</tbody></table>';

        return $ret;
    }

    public function javascriptContent()
    {
        ob_start();?>
function get_bid(bid)
{
    $("#bidIn").val(bid);
    $("#bidForm").submit();
}
        <?php
        return ob_get_clean();
    }

    public function helpContent()
    {
        return '';
    }

}

FannieDispatch::conditionalExec();

