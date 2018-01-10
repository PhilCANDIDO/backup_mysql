#!/bin/sh
################################
# Script for schedule task
################################
#
# Ansible managed: /opt/CFM-Project/ansible/centreon/roles/backup_mysql/templates/backup_mysql_tbl.sh.j2 modified on 2016-02-06 16:41:49 by root on srvcpflxces02.intra.cpf-informatique.fr
#
###

#Sauvegarde DB centreon
/usr/bin/php /opt/backup_mysql/backup_mysql_tbl.php -b "centreon" -i "/opt/backup_mysql/backup_mysql_tbl.ini" 2>&1 > /var/backup/backup_mysql_centreon.log

#Sauvegarde DB centreon_storage
/usr/bin/php /opt/backup_mysql/backup_mysql_tbl.php -b "centreon_storage" -i "/opt/backup_mysql/backup_mysql_tbl.ini" -t"data_bin" -w"ctime > '`date +%s --date="-1 week"`'" -q 2>&1 > /var/backup/backup_mysql_centreon_storage.log

#Sauvegarde DB centreon_status
/usr/bin/php /opt/backup_mysql/backup_mysql_tbl.php -b "centreon_status" -i "/opt/backup_mysql/backup_mysql_tbl.ini" 2>&1 > /var/backup/backup_mysql_centreon_status.log

#Sauvegarde DB centreon_syslog
/usr/bin/php /opt/backup_mysql/backup_mysql_tbl.php -b "centreon_syslog" -i "/opt/backup_mysql/backup_mysql_tbl.ini" -t"logs" -w"datetime > '`date +'%Y-%m-%d %H:%M:%S' --date="-1 week"`'" -q 2>&1 > /var/backup/backup_mysql_centreon_status.log

#Sauvegarde DB printer_count
#/usr/bin/php /opt/backup_mysql/backup_mysql_tbl.php -b "printer_count" -i "/opt/backup_mysql/backup_mysql_tbl.ini" 2>&1 > /var/backup/backup_mysql_printer_count.log

#Sauvegarde DB mysql
/usr/bin/php /opt/backup_mysql/backup_mysql_tbl.php -b "mysql" -i "/opt/backup_mysql/backup_mysql_tbl.ini" 2>&1 > /var/backup/backup_mysql_mysql.log

