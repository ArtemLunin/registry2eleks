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

## Upload conclusions
`php upload_zakl.php`
