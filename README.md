# registry2eleks
Fix conclusions in Registry and upload conclusions to Eleks

## Create pending list
### By selected date (year-month)
`php create_pending_zakl_list.php -d yyyy-dd`
### By ID between Start and End (from 1 to 100)
`php create_pending_zakl_list.php -s 1 -e 100`

## Update conclusions
### log file store to $logFileName
`php registry_update_zakl2.php -f file_pending_IDs.txt`

## Stored login and password for Basic Auth
### upload_zakl_cred.php

## Upload conclusions. Remove reg_upload_in_process.txt file if need to stop
`php upload_zakl.php`
### Upload conclusions from start ro end ID
`php upload_zakl.php -s 1001 -e 1002`
### Upload 1 conclusion, only -s arg
`php upload_zakl.php -s 1001`
### Upload conclusion use by html full
`php upload_zakl.php -s 1001 -h`
