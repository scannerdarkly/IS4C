<?php
include(dirname(__FILE__) . '/../../config.php');
if (!class_exists('SQLManager')) {
    include(__DIR__ . '/../../classlib2.0/FannieAPI.php');
}
if (basename(__FILE__) != basename($_SERVER['PHP_SELF'])) {
    return;
}

$dbc = new SQLManager($FANNIE_SERVER,$FANNIE_SERVER_DBMS,$FANNIE_OP_DB,
        $FANNIE_SERVER_USER,$FANNIE_SERVER_PW);

$p1 = $dbc->prepare("SELECT photo FROM productUser where upc=?");
$p2 = $dbc->prepare("SELECT upc FROM products WHERE upc=?");
$upP = $dbc->prepare("UPDATE productUser SET photo=? WHERE upc=?");
$dh = opendir('new');
while( ($file = readdir($dh)) !== False){
    $exts = explode(".",$file);
    
    $e = strtolower(array_pop($exts));
    if ($e != "png" && $e != "gif" && $e != "jpg" && $e != "jpeg")
        continue;

    $u = array_pop($exts);
    if (!is_numeric($u)) continue;

    $upc = str_pad($u,13,'0',STR_PAD_LEFT);

    $r1 = $dbc->execute($p1,array($upc));
    if ($dbc->num_rows($r1) > 0){
        $row = $dbc->fetchRow($r1);
        if (false && $row['photo'] && file_exists('done/' . $row['photo'])) {
            echo "UPC $upc already has image\n";
        } else {
            echo "UPC $upc found in productUser\n";
            $upR = $dbc->execute($upP,array($file,$upc));
            rename('new/'.$file,'done/'.$file);
        }
    } else {
        $r2 = $dbc->execute($p2,array($upc));
        if ($dbc->num_rows($r2) > 0){
            echo "UPC $upc found in products\n";    
        } else {
            echo "UPC $upc not found\n";
        }
    }
}

