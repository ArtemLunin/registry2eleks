<?php
$logFileName = './uploaded_zakl.log';

$runningStatusFile = './reg_upload_in_process.txt';

if (file_exists($runningStatusFile)) exit;

include_once('includes/incl.php');
include_once('plugins/tovar/t_conclusion_print.php');
include_once('upload_zakl_cred.php');
// include_once("ext_cls/mpdf/mpdf.php");

$conn = ConnectToDatabese();
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

// AND z_zakl.OKONCH_DT BETWEEN '2023-01-01 00:00:00' AND '2023-12-31 23:59:59'
// pers.REGNUM=1001081597
$lastID = 685708;

$logHandle = fopen($logFileName, "a+");

$runningStatusHandle = fopen($runningStatusFile, "w");
fflush($runningStatusHandle);
fclose($runningStatusHandle);

$file_zakl_test = file_get_contents('zakl_test_in.pdf');

$parSQL = "SELECT z_zakl.ID, pers.REGNUM, z_zakl.CONCL_2_PDF AS concl_br, z_zakl.OKONCH_DT, tovar.ARTIKUL, tovar.NAZ
FROM personareg AS pers, zakazsp_zakl AS z_zakl, tovar
WHERE z_zakl.ID>=$lastID
AND z_zakl.OKONCH=1 AND z_zakl.PERSONA=pers.PERSONA AND z_zakl.TOVAR=tovar.ID
ORDER BY z_zakl.ID
LIMIT 1";

$zakl_arr = $conn->Execute($parSQL) or die  ("sql error: $parSQL\n<br>");

while(!$zakl_arr->EOF) {
    $log_str = "";
    $naz_zakl = mb_convert_encoding($zakl_arr->fields['NAZ'], 'UTF-8');
    $zakl_body = gzuncompress(base64_decode($zakl_arr->fields['concl_br']));
    $zakl_body = $file_zakl_test;

    $log_str = date('Y-m-d H:i:s').",ID:".$zakl_arr->fields['ID'].",KARTA:".$zakl_arr->fields['REGNUM'].",DATE_ZAKL:". $zakl_arr->fields['OKONCH_DT'].",ART:".$zakl_arr->fields['ARTIKUL'].",NAZ:".$naz_zakl." -> uploaded\n";

    $post_str = 'KARTA='.$zakl_arr->fields['REGNUM'].'&DAT='.rawurlencode($zakl_arr->fields['OKONCH_DT']).'&NAME='.$zakl_arr->fields['ARTIKUL'].'.'.rawurlencode($zakl_arr->fields['NAZ']).'.pdf'.'&DOC='.base64_encode($zakl_body);

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
        // CURLOPT_POSTFIELDS => 'KARTA='.$zakl_arr->fields['REGNUM'].'&DAT='.rawurlencode($zakl_arr->fields['OKONCH_DT']).'&NAME='.'999.Тестовое заключение.pdf'.'&DOC='.base64_encode(gzuncompress(base64_decode($zakl_arr->fields['concl_br']))),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . AuthorizationDigest,
            // 'Content-Length: ' . 200000
            // 'Cookie: PHPSESSID=m2o9nr8hq4lhp7798i5i9oqpb2'
        ),
    ));
    $response = curl_exec($curl);

    curl_close($curl);
    echo $response.PHP_EOL;

    file_put_contents('test_zakl.pdf', $zakl_body);
    sleep(5);
    fwrite($logHandle, $log_str);
    fflush($logHandle);
    $zakl_arr->MoveNext();
}

fclose($logHandle);
// fclose($iniHandle);
@unlink($runningStatusFile);