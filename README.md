# backup_mysql
## Objectives
This repository hosts script to backup and restore MySQL/MariaDB databases.

## Introduction
At the origin, this script developped to backup and restore a Centreon databases. We need to backup/restore each tables separatly.
An option allows to add a clause where to a specific table. This option added to limit backup size if table contains for example history datas.

## Files
| Filename | Description |
|----|----|
| backup_mysql_tbl.php | General script to backup/restore |
| backup_mysql_tbl.ini | Setting file for script |
| backup_mysql_tbl.sh | General laucher script to backup few databases |
| backup_mysql_tbl.log | Log file example |
| etc/cron.d/backup_mysql_sch | Schedule configuration file |

## Requirements
This script is validate on CentOS v6 and v7, and MySQL v5.x or MariaDB 10.x
The following packages are mandatory :
- php-pdo
- php-cli
- php-common
- php-mysql
- nsca-client

If you use MySQl Server :
- MySQL-client

If you use mariadb Server :
- MariaDB-client

## Installation
Clone git repository in your server. For example in /opt directory.
```
# cd /opt
# git clone https://github.com/PhilCANDIDO/backup_mysql.git
```

Update setting file `backup_mysql_tbl.ini`. And update the general launcher script `backup_mysql_tbl.sh`.

Copy the schedule configuration `backup_mysql_tbl.sh` in `/etc/cron.d/`.

## Setting file
The file _backup_mysql_tbl.ini_ contains the following informations :
- Databases credentials.
- Backup directory.
- Rotation number.
- Enable/disable compression.
- NSCA configuration file.

_Note: NSCA is Nagios Service Check Acceptor. This feature is use to send result of backup to a NAGIOS/CENTREON-ENGINE server. This option is not mandatory but the package must be installed to execute the script `backup_mysql_tbl.php`._

## Usage
```
# php backup_mysql_tbl.php -h
********************************
* backup_mysql_tbl.php
********************************

        usage: backup_mysql_tbl.php options

        Save/Restore MySQL database table by table (optionnal clause where)
        (Version 1.6.)

Preriquisite :
       Setting file.

Purpose :
        This programm backup/restore MySQL or MariDB database in archive repository, in separate table file.
        Archive repository, MySQL/MariaDB credentials, are configurable in external setting file.
        A setting file template is named backup_mysql_tbl.ini.

OPTIONS:
   -h      Show this message.
   -b      MySQL Schema Name. (Mandatory)
   -i      Setting file with MySQL credentials. (Mandatory)
   -A      Save all databases.
   -q      Query options for clause WHERE.
   -t      Table use in clause WHERE.
   -w      Clause WHERE options.
   -d      Display debug mode.
   -s      Display SQL Query and Command.
   -R      Restore Mode.
   -H      Restore Mode : Mysql hostname or IP address.
   -U      Restore Mode : Mysql username.
   -P      Restore Mode : Mysql password.
   -D      Restore Mode : Archive timestamp
   -L      List archive to get Timestamp.

Save process examples :
        - Backup all databases
$ php backup_mysql_tbl.php -A -i"/opt/backup_mysql/backup_mysql_tbl.ini"

        - Backup database centreon
$ php backup_mysql_tbl.php -b"centreon" -i"/opt/backup_mysql/backup_mysql_tbl.ini"

        - Backup database centreon_storage but only last month data in table data_bin
$ php backup_mysql_tbl.php -b"centreon_storage" -i /opt/backup_mysql/backup_mysql_tbl.ini" -t"data_bin" -w"ctime > '`date +%s --date="-1 months"`'" -q
(important : do not use space between swith -t and -w and values)

        - Backup database centreon and display debug messages very verbose
$ php backup_mysql_tbl.php -b"centreon" -i "/opt/backup_mysql/backup_mysql_tbl.ini" -s -d

Restore process examples :
        - List archive available for a database
$ php backup_mysql_tbl.php -b"centreon" -i"/opt/backup_mysql/backup_mysql_tbl.ini" -L

        - List archive available for a database and display command line
$ php backup_mysql_tbl.php -b"centreon" -i"/opt/backup_mysql/backup_mysql_tbl.ini" -R -H'localhost' -U'root' -P'password' -D -L

        - Restore command :
php backup_mysql_tbl.php -b"centreon" -i"/opt/backup_mysql/backup_mysql_tbl.ini" -R -H"localhost" -U"root" -P"password" -D"20150901011501"
```
