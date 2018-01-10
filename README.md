# backup_mysql
## Objectives
This repository hosts script to backup and restore MySQL/MariaDB databases.

## Introduction
At the origin, this script developped to backup and restore a Centreon databases. We need to backup/restore each tables separatly.
An option allows to add a clause where to a specific table. This option added to limit backup size if table contains for example history datas.

## Requirement
This script is validate on CentOS v6 and v7, and MySQL v5.x or MariaDB 10.x
The following packages are mandatory :
- php-pdo
- php-cli
- php-common
- php-mysql

If you use MySQl Server :
- MySQL-client

If you use mariadb Server :
- MariaDB-client

## Settings files
