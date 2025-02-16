<?php
$logFileName = './pdf_store.log';

$zakl_checks_list_dir = 'temp_update_checks';
$istart_id = $iend_id = false;

$options = getopt("d:s:e:");
if (array_key_exists('d', $options)) {
    $date_templ = '/ID:(\d+),.*'.$options['d'].'/';
    $file_suff = $options['d'];
} elseif (array_key_exists('s', $options) && array_key_exists('e', $options) && $options['s'] && $options['e']) {
    $istart_id = intval($options['s']);
    $iend_id = intval($options['e']);

    $date_templ = '/ID:(\d+)[,.*|\s]'.'/';
    $file_suff = $options['s'].'-'.$options['e'];
}

$file_success = $zakl_checks_list_dir.'/'.$file_suff.'_success.txt';
$file_pending = $zakl_checks_list_dir.'/'.$file_suff.'_pending.txt';

$id_zakl = [];
$logHandle = fopen($logFileName, "r");
$log_success = fopen($file_success, "w");
while(($log_str = fgets($logHandle)) !== false) {
    $matches = [];
    if (preg_match($date_templ, $log_str, $matches) === 1) {
        $match_id = intval($matches[1]);
        if (in_array($match_id, $id_zakl) == false) {
            if ($istart_id !== false && $iend_id !== false && $match_id >= $istart_id && $match_id <= $iend_id || $istart_id === false && $iend_id === false) {
                $id_zakl[] = $match_id;
                fwrite($log_success, $matches[1].PHP_EOL);
            }
        }
    }
}
fclose($logHandle);

$start_id = $id_zakl[0];
$end_id = max($id_zakl);

$id_pending = fopen($file_pending, "w");

for ($id = $start_id; $id < $end_id; $id++) { 
    if (in_array($id, $id_zakl)) {
        continue;
    }
    fwrite($id_pending, $id.PHP_EOL);
}

fclose($id_pending);

