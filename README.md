# backup_mysql
## Objectives
This repository hosts script to backup and restore MySQL/MariaDB databases.

## Introduction
At the origin, this script developped to backup and restore a Centreon databases. We need to backup/restore each tables separatly.
An option allows to add a clause where to a specific table. This option added to limit backup size if table contains for example history datas.

## files
|----|----|
| Filename | Description |
| backup_mysql_tbl.php | General script to backup/restore |
| backup_mysql_tbl.ini | Setting file for script |
| backup_mysql_tbl.sh | General laucher script to backup few databases |
| backup_mysql_tbl.log | Log file example |
| etc/cron.d/backup_mysql_sch | Schedule configuration file |

## Requirement
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
The file backup_mysql_tbl.ini contains the following informations :
- Databases credentials.
- Backup directory.
- Rotation number.
- Enable/disable compression.
- NSCA configuration file.

_Note: NSCA is Nagios Service Check Acceptor. This feature is use to send result of backup to a NAGIOS/CENTREON-ENGINE server. This option is not mandatory but the package must be installed to execute the script `backup_mysql_tbl.php`._

## Usage
