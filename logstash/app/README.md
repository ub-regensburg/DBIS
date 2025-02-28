Run `kill_indexer.sh` to stop the cronjob.    

To prevent future running jobs comment the line in the crontab and restart `sudo service cron reload`.

`sudo grep CRON /var/log/syslog`
