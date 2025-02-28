#!/bin/bash

SECONDS=0
filename=$(ls -rt $(dirname "$0")/dumps/*.sql | tail -n 1)
echo "Youngest SQL dump is $filename - importing to MariaDB ..."
mariadb -u dbis -ppassword dbis < "$filename"
echo "Import script finished in $SECONDS s."
