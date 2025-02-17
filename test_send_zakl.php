<?php
include_once('upload_zakl_cred.php');

$file_zakl_test = file_get_contents('zakl_test_in.pdf');
$zakl_body = $file_zakl_test;
$zakl_body = file_get_contents('zakl_test_base64.txt');

function clean64Encode($input_str) {
    $clean64Str = '';
    // $input_file = fopen($input_file_name, "r");
    // while(!feof($input_file))
    $str_offset = 0;
    $get_str_length = 57 * 143;

    while(($chunk_string = substr($input_str, $str_offset, $get_str_length)) !== '')
    {
        // $plain = fread($input_file, 57 * 143);
        $encoded = base64_encode($chunk_string);
        $encoded = chunk_split($encoded, 76, "\r\n");
        $clean64Str .= $encoded;
        $str_offset += $get_str_length;
    }
    // fclose($input_file);
    return $clean64Str;
}

$post_str = 'KARTA=1001081597&DAT='.rawurlencode('2023-05-30 07:32:25').'&NAME=2717.'.rawurlencode('МРТ колінного суглоба.pdf').'&DOC='.$zakl_body;

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
        // 'Content-Length: ' . strlen($post_str)
    ),
));
$response = curl_exec($curl);

curl_close($curl);
echo $response.PHP_EOL;

// file_put_contents('zakl_php_base64.txt', clean64Encode($zakl_body));
// file_put_contents('zakl_test_out.pdf', base64_decode(file_get_contents('zakl_php_base64.txt')));
