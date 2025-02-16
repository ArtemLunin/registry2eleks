<?php
$logFileName = './pdf_store.log';
// id_process.ini
// lastID = 0
$idProcessFile = './id_process.ini';
$runningStatusFile = './reg_update_in_process.txt';

if (file_exists($runningStatusFile)) exit;

include_once('includes/incl.php');
include_once('plugins/tovar/t_conclusion_print.php');
//запись html в pdf
include_once("ext_cls/mpdf/mpdf.php");
// ConnectToDatabese - описана в incl.php
$conn = ConnectToDatabese();
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

$lastID = 0;
$okonch = 1;
$hour_speed = 19;
$hour_stop = 23;
$delay_next = 4;
$delay_on_speed_mode = 3;

$currentDateObj = date_create();

date_time_set($currentDateObj, $hour_stop, 0);
$stopDateTime = date_timestamp_get($currentDateObj);

$ini_arr = parse_ini_file($idProcessFile);

if ($ini_arr && intval($ini_arr['lastID']) && intval($ini_arr['lastID']) > 0) {
    $lastID = intval($ini_arr['lastID']);
}

$logHandle = fopen($logFileName, "a+");
$iniHandle = fopen($idProcessFile, "w");

$runningStatusHandle = fopen($runningStatusFile, "w");
fflush($runningStatusHandle);
fclose($runningStatusHandle);

while(true) {
    $cause_stop = 'hmm...';
    $current_ts = time();
    $current_hour = intval(date('H'));
    if ((($stopDateTime - time() <= 0) && ($cause_stop = 'time')) || 
        ((!file_exists($runningStatusFile)) && ($cause_stop = 'external interrupt'))) {
            
        $log_str = date('Y-m-d H:i:s').",Stopped by $cause_stop\n";
        fwrite($logHandle, $log_str);
        fflush($logHandle);
        break;
    }
    if ($current_hour >= $hour_speed && $delay_next != $delay_on_speed_mode) {
        $delay_next = $delay_on_speed_mode;
        $log_str = date('Y-m-d H:i:s').",Switch to fast mode\n";
        fwrite($logHandle, $log_str);
        fflush($logHandle);
    }
    $parSQL = "SELECT ID, OKONCH, OKONCH_DT, CONCL_1_HTM, CONCL_2_HTM FROM ZAKAZSP_ZAKL WHERE ID>=$lastID AND OKONCH=$okonch LIMIT 1";

    $zakl_arr = $conn->Execute($parSQL) or die  ("sql error: $parSQL\n<br>");

    while(!$zakl_arr->EOF) {
        $log_str = "";
        
        if ($zakl_arr->fields['CONCL_1_HTM'] != null && 
        trim($zakl_arr->fields['CONCL_1_HTM']) != '') {

            $log_str = date('Y-m-d H:i:s').",ID:".$zakl_arr->fields['ID'].",DATE_ZAKL:". $zakl_arr->fields['OKONCH_DT']." -> saved\n";
            $o = NewObject($conn,'TZakazsp_zakl',$zakl_arr->fields['ID']);
            $ds = sys_get_temp_dir();
            $fn = $ds.'/conclusion.pdf';

            $mpdf = new mPDF();
            if(file_exists($fn)) unlink($fn);
            $mpdf->WriteHTML(base64_decode($zakl_arr->fields['CONCL_1_HTM']));
            $mpdf->Output($fn);
            $ss = file_get_contents($fn);
            $o->sf('CONCL_1_PDF',base64_encode(gzcompress($ss,9)));

            $mpdf = new mPDF();
            if(file_exists($fn)) unlink($fn);
            $mpdf->WriteHTML(base64_decode($zakl_arr->fields['CONCL_2_HTM']));
            $mpdf->Output($fn);
            $ss = file_get_contents($fn);
            $o->sf('CONCL_2_PDF',base64_encode(gzcompress($ss,9)));
            $s = $o->BaseUpdate();

            fwrite($logHandle, $log_str);
            fflush($logHandle);

            $lastID = intval($zakl_arr->fields['ID']);
            
            sleep($delay_next);
        }
        $lastID = $lastID + 1;
        rewind($iniHandle);
        fwrite($iniHandle, "lastID=" . $lastID);
        fflush($iniHandle);

        $zakl_arr->MoveNext();
    }
}

fclose($logHandle);
fclose($iniHandle);
@unlink($runningStatusFile);
