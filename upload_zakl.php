<?php
$logFileName = './uploaded_zakl.log';
$failedLogFileName = './failed_upl_zakl.log';
$idProcessFile = './id_uploaded.ini';

$runningStatusFile = './reg_upload_in_process.txt';

if (file_exists($runningStatusFile)) exit;

include_once('includes/incl.php');
include_once('plugins/tovar/t_conclusion_print.php');
include_once('upload_zakl_cred.php');

$conn = ConnectToDatabese();
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

// AND z_zakl.OKONCH_DT BETWEEN '2023-01-01 00:00:00' AND '2023-12-31 23:59:59'
// pers.REGNUM=1001081597
$lastID = 0;
$uploadConcl = 20;
$delay_next = 4;
$hour_stop = 23;
$startID = $stopID = 0;
$maxIDFinished = 0;

$max_length_filename = 142;

$currentDateObj = date_create();

date_time_set($currentDateObj, $hour_stop, 0);
$stopDateTime = date_timestamp_get($currentDateObj);

$ini_arr = parse_ini_file($idProcessFile);

$options = getopt("s:u:e:");
if (array_key_exists('s', $options) && $options['s']) {
    $startID = filter_var($options['s'], FILTER_VALIDATE_INT, [
        "options" => [
            "min_range" => 0, 
            "max_range" => 1000000, 
            'default' => $startID
        ]]);
}
if (array_key_exists('u', $options) && $options['u']) {
    $uploadConcl = filter_var($options['u'], FILTER_VALIDATE_INT, [
        "options" => [
            "min_range" => 1, 
            "max_range" => 10000, 
            'default' => $uploadConcl
        ]]);
}
if (array_key_exists('e', $options) && $options['e']) {
    $stopID = filter_var($options['e'], FILTER_VALIDATE_INT, [
        "options" => [
            "min_range" => 1, 
            "max_range" => 1000000, 
            'default' => 0
        ]]);
}

if ($ini_arr && intval($ini_arr['lastID']) && intval($ini_arr['lastID']) > 0) {
    $lastID = intval($ini_arr['lastID']);
}

$logHandle = fopen($logFileName, "a+");
$failedLogHandle = fopen($failedLogFileName, "a+");
$iniHandle = fopen($idProcessFile, "w");

$runningStatusHandle = fopen($runningStatusFile, "w");
fflush($runningStatusHandle);
fclose($runningStatusHandle);


$SQLMAXID= "SELECT MAX(ID) AS MAXID FROM ZAKAZSP_ZAKL WHERE OKONCH=1";
$id_arr = $conn->Execute($SQLMAXID) or die  ("sql error: $SQLMAXID\n<br>");
while(!$id_arr->EOF) {
    $maxIDFinished = intval($id_arr->fields['MAXID']);
    $id_arr->MoveNext();
}
$cause_stop = $maxIDFinished;
$log_str = date('Y-m-d H:i:s').",Detected MAXID: $cause_stop" . PHP_EOL;
fwrite($logHandle, $log_str);
fflush($logHandle);

if ($startID !== 0) {
    $cause_stop = $lastID = $startID;
    $log_str = date('Y-m-d H:i:s').",Start ID from parameters: $cause_stop" . PHP_EOL;
    fwrite($logHandle, $log_str);
    fflush($logHandle);
}

$replaced_chars = ["\\","/","+",",","."];

// while($lastID < $stopID) {
while($lastID <= $maxIDFinished) {
    $cause_stop = 'hmm...';
    $current_ts = time();

    if ((($stopDateTime - time() <= 0) && ($cause_stop = 'time')) || 
    ((!file_exists($runningStatusFile)) && ($cause_stop = 'external interrupt'))) {       
        $log_str = date('Y-m-d H:i:s').",Stopped by $cause_stop" . PHP_EOL;
        fwrite($logHandle, $log_str);
        fflush($logHandle);
        break;
    }
    if ($lastID < 240000 && ($cause_stop = 'too small ID')) {
        $log_str = date('Y-m-d H:i:s').",Stopped by $cause_stop" . PHP_EOL;
        fwrite($logHandle, $log_str);
        fflush($logHandle);
        break;
    }
    if ($startID !== 0 && $lastID > $stopID  && ($cause_stop = 'end diap')) {
        $log_str = date('Y-m-d H:i:s').",Stopped by $cause_stop" . PHP_EOL;
        fwrite($logHandle, $log_str);
        fflush($logHandle);
        break;
    }

    $parSQL = "SELECT z_zakl.ID, pers.REGNUM, z_zakl.CONCL_2_PDF AS concl_br, z_zakl.OKONCH_DT, tovar.ARTIKUL, tovar.NAZ FROM personareg AS pers, zakazsp_zakl AS z_zakl, tovar WHERE z_zakl.ID>=$lastID AND z_zakl.OKONCH=1 AND z_zakl.PERSONA=pers.PERSONA AND z_zakl.TOVAR=tovar.ID ORDER BY z_zakl.ID LIMIT 1";

    $zakl_arr = $conn->Execute($parSQL) or die  ("sql error: $parSQL\n<br>");

    while(!$zakl_arr->EOF) {
        $naz_zakl = mb_convert_encoding($zakl_arr->fields['NAZ'], 'UTF-8');
        $zakl_body = gzuncompress(base64_decode($zakl_arr->fields['concl_br']));

        $log_str = date('Y-m-d H:i:s').",ID:".$zakl_arr->fields['ID'].",KARTA:".$zakl_arr->fields['REGNUM'].",DATE_ZAKL:". $zakl_arr->fields['OKONCH_DT'].",ART:".$zakl_arr->fields['ARTIKUL'].",NAZ:".$naz_zakl." -> uploaded" . PHP_EOL;

        $post_str = 'KARTA='.$zakl_arr->fields['REGNUM'].'&DAT='.rawurlencode($zakl_arr->fields['OKONCH_DT']).'&NAME='.$zakl_arr->fields['ARTIKUL'].'.'.rawurlencode(substr(str_replace($replaced_chars, "_", $zakl_arr->fields['NAZ']), 0, $max_length_filename)).'.pdf'.'&DOC='.rawurlencode(base64_encode($zakl_body));

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://online.diaservis.ua/wcl_diaservice/upload2eleks.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $post_str,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . AuthorizationDigest,
                'Content-Length: ' . strlen($post_str)
            ),
        ));
        $response = curl_exec($curl);
        $mess = curl_error($curl);
        curl_close($curl);
        // echo iconv('cp1251','utf-8',$response).PHP_EOL;

        if($mess) {
            fwrite($failedLogHandle, "error:" . $mess . $log_str);
            fflush($failedLogHandle);
        } else {
            fwrite($logHandle, $log_str);
            fflush($logHandle);
        }

        $lastID = intval($zakl_arr->fields['ID']);
        sleep($delay_next);
        $lastID = $lastID + 1;
        rewind($iniHandle);
        fwrite($iniHandle, "lastID=" . $lastID);
        fflush($iniHandle);
        $zakl_arr->MoveNext();
    }
}
fclose($logHandle);
fclose($iniHandle);
fclose($failedLogHandle);
@unlink($runningStatusFile);