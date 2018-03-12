#!/usr/bin/php -q
<?php
//###################################################
//# Written by : Philippe Candido <philippe.candido@cpf-informatique.Fr>
//# Purpose    : Save MySQL schema and tables in separate files
//# Date       : Feb 14, 2014
//# Version    : 1.1
//###################################################
//#
//# Requirement:
//#   - Setting backup_mysql_tbl.ini in current folder
//#
//###################################################
//# Version : 1.2
//# Modify : Aug 25 2015
//#     - Add output message when backup is successfully
//#     - Calculate size and backup duration.
//#
//###################################################
//# Version : 1.3
//# Modify : Aug 26 2015
//#     - Create restore processus function
//#     - Create list_archive function
//#
//###################################################
//# Version : 1.4
//# Modify : Sep 01 2015
//#     - Correct NSCA message output.
//#     - Add more options in helpline.
//#
//###################################################
//# Version : 1.5
//# Modify : Sep 01 2015
//#     - Display size archive when use argument -L
//#
//###################################################
//# Version : 1.6
//# Modify : Dec 30 2016
//#     - Add option --lock-tables=0 in mysqldump options. This options allow to locks table in MySQL Replication architecture.
//#
//###################################################
//# Version : 1.7
//# Modify : Feb 26 2018
//#     - Add option to save all databases.
//#
//###################################################
//###################################################
//# Version : 1.7.1
//# Modify : Mar 12 2018
//#     - Enable MySQL connection message when use -d argument.
//#
//###################################################

//******************************************************************************
//                              START MAIN PROGRAM
//******************************************************************************

$version="1.7";
// Create new Colors class
$colors = new Colors();

// Date start
$time_start=microtime(true);

//Get current directory
$current_fld=dirname(__FILE__);

//Get current script name
$fullscript_name = $_SERVER["SCRIPT_NAME"];
$break = Explode('/', $fullscript_name);
$script_name = $break[count($break) - 1];

//Get script name without extension
$break = Explode('.', $script_name);
$short_script_name = $break[0];

//Script setting file
//$script_ini_file=$current_fld . "/" . $short_script_name . ".ini";

//Save or restore Processus
$sav_rest_ps = "save";

//Default nsca status
$bck_status = 0;

//Init log file
$logfile=$current_fld . '/' . $short_script_name . '.log';

//Get the command line argument
$shortopts  = "";
$shortopts  .= "b:";    //MySQL Schema Name.
$shortopts  .= "i:";    //Configuratin file. (MANDATORY)
$shortopts  .= "q";     //Query Options.
$shortopts  .= "t::";   //Table name when -q option is used.
$shortopts  .= "w::";   //Clause where when -q option is used.
$shortopts  .= "d";     //Debug mode.
$shortopts  .= "s";     //SQL Debug mode.
$shortopts  .= "h";     //Helpline.
$shortopts  .= "A";     //Save all databases
$shortopts  .= "R";     //Restore mode
$shortopts  .= "H::";    //MySQL Hostname or Address IP
$shortopts  .= "U::";    //MySQL User
$shortopts  .= "P::";    //MYSQL Password
$shortopts  .= "D::";    //Archive Timestamp
$shortopts  .= "L::";    //List archive
$options = getopt($shortopts);

//Set variables
if (isset($options['h'])) {usage();}
if (isset($options['d'])) {$debug = 1;}
if (isset($options['s'])) {$dbg_sql = 1;}
if (isset($options['i'])) {
        $script_ini_file = $options['i'];
        require_once($script_ini_file);
} else {
        echo $colors->getColoredString("CRITICAL: No setting file found. Script aborted. (see helpline $script_name -h)","red")."\n";
        usage();
        exit(2);
}

if (isset($options['b'])) {
        $mysql_sch = $options['b'];
} else {
    // Check if option alldatabases is set
    if (isset($options['A'])) {
        $sav_rest_ps = 'saveAllDB';
        $mysql_sch = 'mysql';
    } else {
        echo $colors->getColoredString("CRITICAL: No MySQL Schema parameter. Script aborted. (see helpline $script_name -h)","red")."\n";
        exit(2);
    }
}

//Get clause WHERE option if exist.
if (isset($options['q'])) {
        if (isset($options['t'])) {
                $opt_table = (string)$options['t'];
        } else {
                echo $colors->getColoredString("CRITICAL: no option -t found. Option mandatory when use option -q. Script aborted. (see helpline $script_name -h)","red")."\n";
                exit(2);
        }
        if (isset($options['w'])) {
                $opt_where = (string)$options['w'];
        } else {
        echo $colors->getColoredString("CRITICAL: no option -w found. Option mandatory when use option -q. Script aborted. (see helpline $script_name -h)","red")."\n";
                exit(2);
        }
} else {
        //Set default value for caluse WEHRE
        $opt_where = 0;
}

//Retrieve Arguments to restore_process function
if (isset($options['R'])) {
        $sav_rest_ps = "restore";
        //Get MySQL restore server Hostname or IP address
        if (isset($options['H'])) {
                $R_mysql_host = $options['H'];
        } else {
                echo $colors->getColoredString("CRITICAL: Argument -H is mandatory when use option -R. Script aborted. (see helpline $script_name -h)","red")."\n";
                exit(2);
        }
        //Get MySQL restore username
        if (isset($options['U'])) {
                $R_mysql_user = $options['U'];
        } else {
                echo $colors->getColoredString("CRITICAL: Argument -U is mandatory when use option -R. Script aborted. (see helpline $script_name -h)","red")."\n";
                exit(2);
        }
        //Get MySQL restore password
        if (isset($options['P'])) {
                $R_mysql_pwd = $options['P'];
        } else {
                echo $colors->getColoredString("CRITICAL: Argument -P is mandatory when use option -R. Script aborted. (see helpline $script_name -h)","red")."\n";
                exit(2);
        }
        //Get MySQL restore port
        if (isset($mysql_port)) {
                $R_mysql_pwd = $mysql_port;
        } else {
                $R_mysql_pwd = 3306;
                echo $colors->getColoredString("WARNING: Variable MySQL Port not found in ini file. Default value 3306 used.","red")."\n";
        }
        //Get archive timestamp
        if (isset($options['D'])) {
                $R_timestamp = $options['D'];
        } else {
                echo $colors->getColoredString("CRITICAL: Argument -D is mandatory when use option -R. Script aborted. (see helpline $script_name -h)","red")."\n";
                exit(2);
        }
}

//Retrieve Arguments to list_archive function
if (isset($options['L'])) { $sav_rest_ps = "list";}

//Retrieve binary
$MYSQLDUMP=exec("$WHICH mysqldump");
$DF=exec("$WHICH df");
$TAIL=exec("$WHICH tail");
$AWK=exec("$WHICH awk");
$BZIP2=exec("$WHICH bzip2");
$RM=exec("$WHICH rm");
$DU=exec("$WHICH du");
$BUNZIP2=exec("$WHICH bunzip2");
$MYSQL=exec("$WHICH mysql");

//Retrieve arguments from ini file
if (isset($debug)) {echo $colors->getColoredString("MySQL Host = $mysql_host","green")."\n";}
if (isset($debug)) {echo $colors->getColoredString("MySQL User = $mysql_user","green")."\n";}
if (isset($debug)) {echo $colors->getColoredString("MySQL Pwd = $mysql_pwd","green")."\n";}
if (isset($debug)) {echo $colors->getColoredString("Destination folder = $dest_fld","green")."\n";}
if (isset($debug)) {echo $colors->getColoredString("Retention = $retention","green")."\n";}
if (isset($debug)) {echo $colors->getColoredString("Compression = $compress","green")."\n";}
if (isset($debug)) {echo $colors->getColoredString("CENTREON Status = $cent_sts","green")."\n";}
if (isset($debug)) {echo $colors->getColoredString("MySQL Schema = $mysql_sch","green")."\n";}
if (isset($debug) AND isset($options['q'])) {echo $colors->getColoredString("Option Table = $opt_table","green")."\n";}
if (isset($debug) AND isset($options['q'])) {echo $colors->getColoredString("Clause WHERE = \"$opt_where\"","green")."\n";}
if (isset($debug) AND isset($options['R'])) {echo $colors->getColoredString("Restore Mode  : MySQL Host = $R_mysql_host","cyan")."\n";}
if (isset($debug) AND isset($options['R'])) {echo $colors->getColoredString("Restore Mode  : MySQL User = $R_mysql_user","cyan")."\n";}
if (isset($debug) AND isset($options['R'])) {echo $colors->getColoredString("Restore Mode  : MySQL Pwd = $R_mysql_pwd","cyan")."\n";}
if (isset($debug) AND isset($options['R'])) {echo $colors->getColoredString("Restore Mode  : Archive Timestamp = $R_timestamp","cyan")."\n";}

//Check binary
if ($MYSQLDUMP!="") {
        if (isset($debug)) {echo $colors->getColoredString("Binary mysqldump = $MYSQLDUMP","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: binary mysqldump not found. Script aborted","red")."\n";
        exit(2);
}
if ($DF!="") {
        if (isset($debug)) {echo $colors->getColoredString("Binary df = $DF","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: binary df not found. Script aborted.","red")."\n";
        exit(2);
}
if ($TAIL!="") {
        if (isset($debug)) {echo $colors->getColoredString("Binary tail = $TAIL","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: binary tail not found. Script aborted.","red")."\n";
        exit(2);
}
if ($AWK!="") {
        if (isset($debug)) {echo $colors->getColoredString("Binary awk = $AWK","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: binary awk not found. Script aborted.","red")."\n";
        exit(2);
}
if ($BZIP2!="") {
        if (isset($debug)) {echo $colors->getColoredString("Binary bzip2 = $BZIP2","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: binary bzip2 not found. Script aborted.","red")."\n";
        exit(2);
}
if ($RM!="") {
        if (isset($debug)) {echo $colors->getColoredString("Binary rm = $RM","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: binary rm not found. Script aborted.","red")."\n";
        exit(2);
}
if ($BUNZIP2!="") {
        if (isset($debug)) {echo $colors->getColoredString("Binary bunzip2 = $BUNZIP2","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: binary bunzip2 not found. Script aborted.","red")."\n";
        exit(2);
}
if ($MYSQL!="") {
        if (isset($debug)) {echo $colors->getColoredString("Binary mysql = $MYSQL","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: binary mysql not found. Script aborted","red")."\n";
        exit(2);
}


//nsca OPTIONS
if ($cent_sts == 1) {
        $SEND_NSCA=exec("$WHICH send_nsca");
        if ($SEND_NSCA!="") {
                if (isset($debug)) {echo $colors->getColoredString("Binary send_nsca = $SEND_NSCA","green")."\n";}
        } else {
                echo $colors->getColoredString("CRITICAL: binary send_nsca not found. Script aborted.","red")."\n";
                exit(2);
        }
        if (!isset($centengine)) {
                $colors->getColoredString("CRITICAL: argument \$centengine not define in $script_ini_file. Script aborted.","red")."\n";
                exit(2);
        } else {
                if (isset($debug)) {echo $colors->getColoredString("CENTREON ENGINE : $centengine","green")."\n";}
        }
        if (!isset($cent_svc)) {
                $colors->getColoredString("CRITICAL: argument \$cent_svc not define in $script_ini_file. Script aborted.","red")."\n";
                exit(2);
        } else {
                if (isset($debug)) {echo $colors->getColoredString("CENTREON Service name : $cent_svc","green")."\n";}
        }
        if (!isset($cent_host)) {
                $colors->getColoredString("CRITICAL: argument \$cent_host not define in $script_ini_file. Script aborted.","red")."\n";
                exit(2);
        } else {
                if (isset($debug)) {echo $colors->getColoredString("CENTREON Service name : $cent_host","green")."\n";}
        }
        if (!isset($nsca_cfg)) {
                $colors->getColoredString("CRITICAL: argument \$cent_cfg not define in $script_ini_file. Script aborted.","red")."\n";
                exit(2);
        } else {
                if (isset($debug)) {echo $colors->getColoredString("nsca setting file : $nsca_cfg","green")."\n";}
        }
}

//Check if backup destination folder exist
if (is_dir($dest_fld)) {
        if (isset($debug)) {echo $colors->getColoredString("Destination folder $dest_fld found.","green")."\n";}
} else {
        echo $colors->getColoredString("CRITICAL: Destination folder $dest_fld not found. Script aborted.","red")."\n";
        exit(2);
}

//********************************************
//** Save Or Restore processus
//********************************************
switch ($sav_rest_ps) {
        case "saveAllDB":
                //call save_process function
                if (isset($debug)) {echo $colors->getColoredString("**Call list all databases function **","yellow")."\n";}
                list_alldb();
                break;
        case "save":
                //call save_process function
                if (isset($debug)) {echo $colors->getColoredString("**Call save_process function **","yellow")."\n";}
                save_process();
                //Send backup result to CENTREON
                if ($cent_sts == 1) {nsca();}
                break;
        case "restore":
                //call restore_process function
                if (isset($debug)) {echo $colors->getColoredString("**Call restore_process function **","yellow")."\n";}
                restore_process();
                break;
        case "list":
                //call list_archive function
                if (isset($debug)) {echo $colors->getColoredString("**Call list_archive function **","yellow")."\n";}
                if (isset($options['R'])){
                        //Display command to restore
                        list_archive($mysql_sch, "CMD", $R_mysql_host, $R_mysql_user, $R_mysql_pwd);
                } else {
                        //Display only archive
                        list_archive($mysql_sch);
                }
                break;
}
exit(0);

//******************************************************************************
//                                  END MAIN PROGRAM
//******************************************************************************


//******************************************************************************
//                                              FUNCTIONS
//*****************************************************************************

//********************************************
//               list_alldb
//********************************************
//
// Purpose :
//      - List all databases
//
//********************************************
//
// From :
//      - Main program
//
//********************************************
//
// Input :
//
//
//********************************************

function list_alldb() {
        // list variables
        global $debug, $colors, $dest_fld, $fld_dest_tbl, $time_start, $message, $logfile;
        global $mysql_host, $mysql_user, $mysql_pwd, $mysql_port, $mysql_sch, $tbl_name;

        // Connexion to MySQL

        if (isset($dbg_sql)) {$err_level = error_reporting(0);}
        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_pwd, "mysql", $mysql_port);
        if (isset($dbg_sql)) {$err_level = error_reporting(0);}

        //Check MySQL connection
        if (mysqli_connect_errno()) {
                $message = printf("Connection DB aborted : %s\n", mysqli_connect_error());
                $colors->getColoredString("$message","red")."\n";
                writelog($message, $logfile);
                exit(2);
        }

        //Retrieve Schema list
        $sql_listdb = "SHOW DATABASES;";
        $query_listdb = $mysqli->query($sql_listdb);

        mysqli_close($mysqli);

        if (isset($dbg_sql)) {echo $colors->getColoredString("SQL : $sql_listdb","purple")."\n";}

        // Parse schema list and save it
        while ($row = $query_listdb->fetch_row()) {
            $mysql_sch = (string)$row[0];

            //DEBUG
            if (isset($debug)) {echo $colors->getColoredString("MySQL Schema : $mysql_sch","green")."\n";}
            //call save_process function
            if (isset($debug)) {echo $colors->getColoredString("**Call save_process function **","yellow")."\n";}
            save_process();
            //Send backup result to CENTREON
            if ($cent_sts == 1) {nsca();}
        }

}

//********************************************
//               save_process
//********************************************
//
// Purpose :
//      - Save process
//
//********************************************
//
// From :
//      - Main program
//
//********************************************
//
// Input :
//
//
//********************************************

function save_process() {
        // list variables
        global $debug, $colors, $dest_fld, $fld_dest_tbl, $time_start, $message, $logfile;
        global $mysql_host, $mysql_user, $mysql_pwd,$mysql_port, $mysql_sch, $tbl_name;
        global $DF, $TAIL, $AWK, $DU;

        //Create backup folder tree
        $fld_dest_tbl = $dest_fld . "/" . $mysql_sch ."/". date('YmdHis');
        if (is_dir($fld_dest_tbl)) {
                if (isset($debug)) {echo $colors->getColoredString("      Destination folder $fld_dest_tbl found.","green")."\n";}
        } else {
                //Create subfolder
                mkdir($fld_dest_tbl, 0700, true);
        }


        // Connexion to MySQL

        if (isset($dbg_sql)) {$err_level = error_reporting(0);}
        $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_pwd, $mysql_sch, $mysql_port);
        if (isset($dbg_sql)) {$err_level = error_reporting(0);}

        //Check MySQL connection
        if (mysqli_connect_errno()) {
                $message = printf("Connection DB aborted : %s\n", mysqli_connect_error());
                $colors->getColoredString("$message","red")."\n";
                writelog($message, $logfile);
                exit(2);
        }

        //Retrieve Schema size
        $sql_db_size = "SELECT table_schema \"DB_Name\", SUM( data_length + index_length) / 1024 \"DB_Size\" FROM information_schema.TABLES WHERE table_schema LIKE \"$mysql_sch\" GROUP BY table_schema;";
        $query_db_size = $mysqli->query($sql_db_size);

        if (isset($dbg_sql)) {echo $colors->getColoredString("SQL : $sql_db_size","purple")."\n";}

        $row = $query_db_size->fetch_object();
        $db_name = $row->DB_Name;
        $db_size = $row->DB_Size;

        if (isset($debug)) {echo $colors->getColoredString("SIZING : database $db_name = $db_size kB.","green")."\n";}

        //Retrieve destination folder backup size
        $cmd = $DF.' -Pk '. $dest_fld . ' | '.$TAIL.' -1 | '.$AWK.' \'{print $4}\'';
        if (isset($debug)) {echo $colors->getColoredString("Command : $cmd","purple")."\n";}

        $io = popen ( $cmd, 'r' );
        $dest_fld_size = trim(fgets ( $io, 4096));
        pclose ( $io );

        if (isset($debug)) {echo $colors->getColoredString("SIZING : folder $dest_fld = $dest_fld_size kB.","green")."\n";}

        ///Retrieve destination folder backup size en percent
        $cmd = $DF.' -Pk '. $dest_fld . ' | '.$TAIL.' -1 | '.$AWK.' \'{print $5}\'';
        if (isset($debug)) {echo $colors->getColoredString("Command : $cmd","purple")."\n";}

        $io = popen ( $cmd, 'r' );
        $dest_fld_size_prct = trim(fgets ( $io, 4096));
        pclose ( $io );

        if (isset($debug)) {echo $colors->getColoredString("SIZING : folder $dest_fld = $dest_fld_size_prct.","green")."\n";}

        //Check if folder size has enough space
        if ($db_size > $dest_fld_size) {
                echo $colors->getColoredString("CRITICAL : Destination folder ($dest_fld) has not enough space ($dest_fld_size kB), to store database $db_name ($db_size kB). Script aborted.","red")."\n";
        } else {
                if (isset($debug)) {echo $colors->getColoredString("Destination folder has enough space disk to store database DUMP.","green")."\n";}
        }

        //List tables in database
        $sql_lst_tbl = "SHOW TABLES FROM $mysql_sch;";
        $query_lst_tbl = $mysqli->query($sql_lst_tbl);

        if (isset($dbg_sql)) {echo $colors->getColoredString("SQL : $sql_lst_tbl","purple")."\n";}
        while ($row = $query_lst_tbl->fetch_row()) {
                $tbl_name = (string)$row[0];

                //DEBUG
                if (isset($debug)) {echo $colors->getColoredString("Table : $tbl_name","green")."\n";}

                //Execute mysqldump by table
                table_save();
        }

        //Retention process
        retention();

        //Calcul duration
        $time_end = microtime(true);
        $duration = round($time_end - $time_start, 2);

        //Calculate archive size
        $cmd = $DU.' -sb '. $fld_dest_tbl . ' | '.$AWK.' \'{print $1}\'';
        if (isset($debug)) {echo $colors->getColoredString("Command : $cmd","purple")."\n";}
        $io = popen ( $cmd, 'r' );
        $fld_dest_tbl_size = trim(fgets ( $io, 4096));
        pclose ( $io );

        //End Message
        $message = "OK : Backup of DB $mysql_sch successfully. | $mysql_sch"."_bck_time=$duration"."s $mysql_sch"."_bck_size=$fld_dest_tbl_size"."b bck_fld_usage=$dest_fld_size_prct";
        echo $colors->getColoredString("$message","green")."\n";
}

//********************************************
//               table_save
//********************************************
//
// Purpose :
//      - Save table
//
//********************************************
//
// From :
//      - Function save_process
//
//********************************************
//
// Input :
//
//
//********************************************

function table_save() {
        global $mysql_sch, $debug, $MYSQLDUMP, $mysql_host, $mysql_user, $mysql_pwd;
        global $tbl_name, $fld_dest_tbl, $compress, $BZIP2, $opt_table, $opt_where;

        // Create new Colors class
        $colors = new Colors();

        //Check if clause WHERE is used
        if (($opt_where !== 0) && (strcasecmp($tbl_name, $opt_table) == 0)) {
                $where = " --where=\"$opt_where\"";
                if (isset($debug)) {echo $colors->getColoredString("    opt_table=$opt_table","green")."\n";}
                if (isset($debug)) {echo $colors->getColoredString("    opt_where=$opt_where","green")."\n";}
        } else {
                $where = " ";}

        $options="--add-drop-table --add-locks --create-options --disable-keys --extended-insert --lock-tables=0 --quick --set-charset";

        //mysqldump command
        if ($compress == 1) {
                $mysqldump_cmd = "$MYSQLDUMP -u\"$mysql_user\" -p\"$mysql_pwd\" $options --quote-names $mysql_sch $tbl_name $where | $BZIP2 -9 >  $fld_dest_tbl/$tbl_name.sql.bz2";
        } else {
                $mysqldump_cmd = "$MYSQLDUMP -u\"$mysql_user\" -p\"$mysql_pwd\" $options --quote-names $mysql_sch $tbl_name $where > $fld_dest_tbl/$tbl_name.sql";
        }
        if (isset($debug)) {echo $colors->getColoredString("    mysqldump command : $mysqldump_cmd","purple")."\n";}
        exec($mysqldump_cmd);
}

//********************************************
//               Retention
//********************************************
//
// Purpose :
//      - Purge backup destination folder
//
//********************************************
//
// From :
//      - Function save_process
//
//********************************************
//
// Input :
//
//
//********************************************

function retention() {
        global $mysql_sch, $debug, $dest_fld, $retention, $RM;

        // Create new Colors class
        $colors = new Colors();

        //List folder sorting by date
        $scan_fld_dest_tbl = scandir("$dest_fld/$mysql_sch");

        foreach ($scan_fld_dest_tbl as $directory) {
                if (($directory != '.') AND ($directory != '..') AND (is_dir("$dest_fld/$mysql_sch/$directory"))) {
                        if (count_fld("$dest_fld/$mysql_sch") >= $retention) {
                                if (isset($debug)) {echo $colors->getColoredString("      Directory (DELETE)-> $dest_fld/$mysql_sch/$directory","green")."\n";}
                        exec("$RM -rf $dest_fld/$mysql_sch/$directory");
                        continue;
                        }
                        if (isset($debug)) {echo $colors->getColoredString("      Directory : $dest_fld/$mysql_sch/$directory","green")."\n";}
                }
        }
}

//********************************************
//               count_fld
//********************************************
//
// Purpose :
//      - Count the number of folder in directory
//
//********************************************
//
// From :
//      - Function Retention
//
//********************************************
//
// Input : folder full path
//
//
//********************************************

function count_fld($dir_path) {
        global $debug;

        //Directory counter
        $cpt = 0;
        //List folder sorting by date
        $scan_dir_path = scandir($dir_path);

        foreach ($scan_dir_path as $dir) {
                if (($dir != '.') AND ($dir != '..') AND (is_dir("$dir_path/$dir"))) {
                        $cpt++;
                }
        }
        //Number of folder to delete
        return($cpt++);

}

//********************************************
//               nsca
//********************************************
//
// Purpose :
//      - Send Nagios message from nsca protocola
//
//********************************************
//
// From :
//      - Function save_process
//
//********************************************
//
// Input :
//
//
//********************************************

function nsca() {
        global $debug, $colors, $centengine, $cent_svc, $cent_host, $message, $SEND_NSCA, $nsca_cfg;
        global $bck_status, $dbg_sql;

        //Create nsca message
        $nsca_cmd = "echo -e \"$cent_host;$cent_svc;$bck_status;$message\" | $SEND_NSCA -H $centengine -c $nsca_cfg -d ';'";
        if (isset($dbg_sql)) {echo $colors->getColoredString("NSCA MSG : $nsca_cmd","purple")."\n";}

        //Execute nsca command
        $nsca_rtr = shell_exec("$nsca_cmd");

        //Search the string successfull
        if (strpos($nsca_rtr,'sent to host successfully') !== false) {
                if (isset($debug)) {echo $colors->getColoredString("Backup status sent to centreon.","green")."\n";};
				echo $nsca_rtr
        } else {
                echo $colors->getColoredString("CRITICAL : nsca message not send to CENTREON. Please check the following message.","red")."\n";
                echo "  "."$nsca_rtr";
        }
}

//********************************************
//               restore_process
//********************************************
//
// Purpose :
//      - restore process
//
//********************************************
//
// From :
//      - Main program
//
//********************************************
//
// Input :
//
//
//********************************************

function restore_process() {
        global $mysql_sch, $R_mysql_host, $R_mysql_user, $R_mysql_pwd, $R_mysql_port, $R_timestamp, $MYSQL, $BUNZIP2;
        global $dest_fld, $debug, $colors, $time_start;

        //Display message
        echo $colors->getColoredString("========================================","brown")."\n";
        echo $colors->getColoredString("|       MySQL Restoration MODE         |","brown")."\n";
        echo $colors->getColoredString("========================================","brown")."\n";
        echo "\n";
        echo $colors->getColoredString("Database $mysql_sch will be deleted.","brown")."\n";
        echo "\n";
        //Compt file to restore
        $cpt = 1;

        //Prepare mysql restore command
        if ($R_mysql_pwd == "") {
                $mysql_restore = "$MYSQL -h'$R_mysql_host' -u'$R_mysql_user' -P '$R_mysql_port' $mysql_sch";
                // Connexion to MySQL
                if (isset($dbg_sql)) {$err_level = error_reporting(0);}
                $mysqli = new mysqli($R_mysql_host, $R_mysql_user, '', $mysql_sch, $R_mysql_port);
                if (isset($dbg_sql)) {error_reporting($err_level);}
        } else {
                $mysql_restore = "$MYSQL -h'$R_mysql_host' -u'$R_mysql_user' -p'$R_mysql_pwd' -P'$R_mysql_port' $mysql_sch";
                // Connexion to MySQL
                if (isset($dbg_sql)) {$err_level = error_reporting(0);}
                $mysqli = new mysqli($R_mysql_host, $R_mysql_user, $R_mysql_pwd, $mysql_sch, $R_mysql_port);
                if (isset($dbg_sql)) {error_reporting($err_level);}
        }
        //Check MySQL connection
        if (mysqli_connect_errno()) {
                $message = printf("Connection DB aborted : %s\n", mysqli_connect_error());
                $colors->getColoredString("$message","red")."\n";
                exit(2);
        }

        if (isset($debug)) {echo $colors->getColoredString("Mysql restore command : $mysql_restore","brown")."\n";}

        //DELETE DB before restore
        if ($mysqli->query("DROP DATABASE IF EXISTS $mysql_sch;") === TRUE) {
                if (isset($debug)) {echo $colors->getColoredString("DROP DATABASE $mysql_sch realised.","brown")."\n";}
        } else {
                $message = printf("MySQL Query error : %s\n", $mysqli->error);
                $colors->getColoredString("$message","red")."\n";
                exit(2);
        }

        //Disable foreign keys
        if ($mysqli->query("SET FOREIGN_KEY_CHECKS=0;") === TRUE) {
                if (isset($debug)) {echo $colors->getColoredString("Foreign keys disabled.","brown")."\n";}
        } else {
                $message = printf("MySQL Query error : %s\n", $mysqli->error);
                $colors->getColoredString("$message","red")."\n";
                exit(2);
        }

        //Créate DB
        if ($mysqli->query("CREATE DATABASE $mysql_sch;") === TRUE) {
                if (isset($debug)) {echo $colors->getColoredString("Foreign keys disabled.","brown")."\n";}
        } else {
                $message = printf("MySQL Query error : %s\n", $mysqli->error);
                $colors->getColoredString("$message","red")."\n";
                exit(2);
        }

        //Get all SQL script in archive repository
        $archive_repo = "$dest_fld/$mysql_sch/$R_timestamp";
        if (!file_exists($archive_repo)) {
                echo $colors->getColoredString("CRITICAL : folder $archive_repo does not exist.","red")."\n";
                exit(2);
        }
        $array_sqlfile = array_diff(scandir($archive_repo), array('..', '.'));
        foreach ($array_sqlfile as &$sqlfile) {
                if (isset($debug)) {echo $colors->getColoredString("SQLFile : $sqlfile","brown")."\n";}
                //Check the SQLFile type (bz2 = compressed)
                $file_ext = substr($sqlfile, strrpos($sqlfile, '.') + 1, strlen($sqlfile) - strrpos($sqlfile, '.') - 1);
                if (isset($debug)) {echo $colors->getColoredString("SQLFile extension : $file_ext","brown")."\n";}
                //Check if file is compressed
                if ($file_ext == "bz2") {
                        $mysql_restore_cmd = "$BUNZIP2 --stdout $dest_fld/$mysql_sch/$R_timestamp/$sqlfile | $mysql_restore 2>&1";
                        if (isset($debug)) {echo $colors->getColoredString("MySQL restore command : $mysql_restore_cmd","brown")."\n";}
                        $mysql_restore_exec = shell_exec("$mysql_restore_cmd");
                        if (isset($debug)) {echo $colors->getColoredString("MySQL restore command return : $mysql_restore_exec","green")."\n";}
                }
                if ($file_ext == "sql") {
                        $mysql_restore_cmd = "$mysql_restore < $dest_fld/$mysql_sch/$R_timestamp/$sqlfile 2>&1";
                        if (isset($debug)) {echo $colors->getColoredString("MySQL restore command : $mysql_restore_cmd","brown")."\n";}
                        $mysql_restore_exec = shell_exec("$mysql_restore_cmd");
                        if (isset($debug)) {echo $colors->getColoredString("MySQL restore command return : $mysql_restore_exec","green")."\n";}
                }
                if ($mysql_restore_exec == "") {echo $colors->getColoredString("File $sqlfile restored successfully.","brown")."\n";}
                //Delete variables
                unset($mysql_restore_cmd, $mysql_restore_exec);
                //Counter files
                $cpt = $cpt + 1;
        }

         //Enable foreign keys
        if ($mysqli->query("SET FOREIGN_KEY_CHECKS=1;") === TRUE) {
                if (isset($debug)) {echo $colors->getColoredString("Foreign keys ENABLED.","brown")."\n";}
        } else {
                $message = printf("MySQL Query error : %s\n", $mysqli->error);
                $colors->getColoredString("$message","red")."\n";
                exit(2);
        }
        mysqli_close($mysqli);

        //Calcul duration
        $time_end = microtime(true);
        $duration = round($time_end - $time_start, 2);
        echo "\n";
        echo $colors->getColoredString("========================================","brown")."\n";
        echo $colors->getColoredString("|          END MySQL restore           |","brown")."\n";
        echo $colors->getColoredString("========================================","brown")."\n";
        echo $colors->getColoredString("Restore DB $mysql_sch terminated successfully.","brown")."\n";
        echo $colors->getColoredString("$cpt SQL Files restored in $duration"."s.","brown")."\n";
}

//********************************************
//               list_archive
//********************************************
//
// Purpose :
//      - list archive
//
//********************************************
//
// From :
//      - Main program
//
//********************************************
//
// Input :
//
//
//********************************************

function list_archive($mysql_sch, $cmd="LST", $R_mysql_host="", $R_mysql_user="", $R_mysql_pwd="") {
        global $dest_fld, $debug, $colors, $DU, $AWK;
        global $script_name, $current_fld, $script_ini_file ;

        echo $colors->getColoredString("List backup archive for $mysql_sch database :","cyan")."\n";

        //Archive repository
        $archive_repo = "$dest_fld/$mysql_sch";

        //Check if file exist
        if (!file_exists($archive_repo)) {
                echo $colors->getColoredString("CRITICAL : folder $archive_repo does not exist.","red")."\n";
                exit(2);
        }
        if (isset($debug)) {echo $colors->getColoredString("Archive repository : $archive_repo","cyan")."\n";}

        //Get subfolders archive
        $arr_timestamp = array_diff(scandir($archive_repo), array('..', '.'));
        foreach ($arr_timestamp as &$timestamp) {
                $bck_year = substr($timestamp,0 ,4);
                $bck_month = substr($timestamp,4 ,2);
                $bck_day = substr($timestamp,6 ,2);
                $bck_hour = substr($timestamp,8 ,2);
                $bck_mn = substr($timestamp,10 ,2);
                $bck_sec = substr($timestamp,12 ,2);

                //Calculate archive size
                $str_archive_repo_size ="";
                $cmd_sizing = $DU.' -sb '.$archive_repo.'/'.$timestamp.' | '.$AWK.' \'{print $1}\'';
                if (isset($debug)) {echo $colors->getColoredString("Command : $cmd_sizing","purple")."\n";}
                $io = popen ( $cmd_sizing, 'r' );
                $archive_repo_size = trim(fgets ( $io, 4096));
                pclose ( $io );
                if ($archive_repo_size > 1024) {
                        $archive_repo_size = round($archive_repo_size / 1024, 3);
                        $str_archive_repo_size = $archive_repo_size."Kb";
                }
                if ($archive_repo_size > 1024) {
                        $archive_repo_size = round($archive_repo_size / 1024,3);
                        $str_archive_repo_size = $archive_repo_size."Mb";
                }
                echo $colors->getColoredString("   Found archive backuped $bck_year-$bck_month-$bck_day $bck_hour:$bck_mn:$bck_sec (timestamp=$timestamp) (size=$str_archive_repo_size)","cyan")."\n";
                if ($cmd == "CMD") {
                        echo $colors->getColoredString("   Restore command : ","cyan");
                        echo $colors->getColoredString("php $current_fld/$script_name -b'$mysql_sch' -i'$script_ini_file' -R -H'$R_mysql_host' -U'$R_mysql_user' -P'$R_mysql_pwd' -D'$timestamp'","green")."\n\n";
                        //echo "php $current_fld/$script_name -b\"$mysql_sch\" -i\"$script_ini_file\" -R -H\"$R_mysql_host\" -U\"$R_mysql_user\" -P\"$R_mysql_pwd\" -D\"$timestamp\"\n";
                }
        unset($archive_repo_size);
        }

}


//********************************************
// Send email with attachement
//********************************************
function mail_attachment($attachement, $filename, $mailto, $from_name, $subject, $message, $logfile) {

        global $debug;
        $file = $attachement;
        $file_size = filesize($file);
        $handle = fopen($file, "r");
        $content = fread($handle, $file_size);
        fclose($handle);
        $content = chunk_split(base64_encode($content));
        $uid = md5(uniqid(time()));
        $name = basename($file);
        $header = "From: ".$from_name."\r\n";
        $header .= "MIME-Version: 1.0\r\n";
        $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";
        $header .= "This is a multi-part message in MIME format.\r\n";
        $header .= "--".$uid."\r\n";
        $header .= "Content-type:text/plain; charset=iso-8859-1\r\n";
        $header .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $header .= $message."\r\n\r\n";
        $header .= "--".$uid."\r\n";
        $header .= "Content-Type: application/octet-stream; name=\"".$filename."\"\r\n"; // use different content types here
        $header .= "Content-Transfer-Encoding: base64\r\n";
        $header .= "Content-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n";
        $header .= $content."\r\n\r\n";
        $header .= "--".$uid."--";
        if (mail($mailto, $subject, "", $header)) {
                writelog("Mail send ... OK", $logfile);
                //DEBUG
                if ($debug == 1) {printf("Mail send ... OK\n");}
        }
        else {
                writelog("Mail send ... ERROR!", $logfile);
                //DEBUG
                if ($debug == 1) {printf("Mail send ... ERROR!\n");}
        }
}

//********************************************
//                writelog
//********************************************
function writelog($logmessage,$logfile) {
        //Create timestamp with the date
        $timestamp = date('\[Ymd\-H\:i\:s\] ');
        $logmessage = $timestamp . $logmessage . "\n\r";

        //Create file
        if (file_exists($logfile)===false) {
                touch($logfile);
        }
        //Open the log file in append
        if (!$handle = fopen($logfile, 'a')) {
                echo "The log $filename cannot be open.\n\rScript aborted\n\r";
                exit (200);
        }

        //Write log message
        if (fwrite($handle, $logmessage) === FALSE) {
                echo "Cannot write in the log file $logfile .\n\rScript aborted\n\r";
                fclose($handle);
                exit (300);
        }
        fclose($handle);
}

//********************************************
//                usage
//********************************************
function usage() {
        global $script_name, $version, $current_fld;

$Helpline = <<<EOF
********************************
* $script_name
********************************

        usage: $script_name options

        Save/Restore MySQL database table by table (optionnal clause where)
        (Version $version.)

Preriquisite :
       Setting file.

Purpose :
        This programm backup/restore MySQL or MariDB database in archive repository, in separate table file.
        Archive repository, MySQL/MariaDB credentials, are configurable in external setting file.
        A setting file template is named backup_mysql_tbl.ini.

OPTIONS:
   -h      Show this message.
   -b      MySQL Schema Name. (Mandatory if option A is not specified)
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
$ php $script_name -A -i"$current_fld/backup_mysql_tbl.ini"

        - Backup database centreon
$ php $script_name -b"centreon" -i"$current_fld/backup_mysql_tbl.ini"

        - Backup database centreon_storage but only last month data in table data_bin
$ php $script_name -b"centreon_storage" -i $current_fld/backup_mysql_tbl.ini" -t"data_bin" -w"ctime > '`date +%s --date="-1 months"`'" -q
(important : do not use space between swith -t and -w and values)

        - Backup database centreon and display debug messages very verbose
$ php $script_name -b"centreon" -i "$current_fld/backup_mysql_tbl.ini" -s -d

Restore process examples :
        - List archive available for a database
$ php $script_name -b"centreon" -i"$current_fld/backup_mysql_tbl.ini" -L

        - List archive available for a database and display command line
$ php $script_name -b"centreon" -i"$current_fld/backup_mysql_tbl.ini" -R -H'localhost' -U'root' -P'password' -D -L

        - Restore command :
php $script_name -b"centreon" -i"$current_fld/backup_mysql_tbl.ini" -R -H"localhost" -U"root" -P"password" -D"20150901011501"

EOF;
echo "$Helpline\n";
exit(3);
}



//******************************************************************************
//                                              CLASSES
//******************************************************************************

//********************************************
//               Colors
//********************************************


class Colors {
 private $foreground_colors = array();
 private $background_colors = array();

 public function __construct() {
 // Set up shell colors
 $this->foreground_colors['black'] = '0;30';
 $this->foreground_colors['dark_gray'] = '1;30';
 $this->foreground_colors['blue'] = '0;34';
 $this->foreground_colors['light_blue'] = '1;34';
 $this->foreground_colors['green'] = '0;32';
 $this->foreground_colors['light_green'] = '1;32';
 $this->foreground_colors['cyan'] = '0;36';
 $this->foreground_colors['light_cyan'] = '1;36';
 $this->foreground_colors['red'] = '0;31';
 $this->foreground_colors['light_red'] = '1;31';
 $this->foreground_colors['purple'] = '0;35';
 $this->foreground_colors['light_purple'] = '1;35';
 $this->foreground_colors['brown'] = '0;33';
 $this->foreground_colors['yellow'] = '1;33';
 $this->foreground_colors['light_gray'] = '0;37';
 $this->foreground_colors['white'] = '1;37';

 $this->background_colors['black'] = '40';
 $this->background_colors['red'] = '41';
 $this->background_colors['green'] = '42';
 $this->background_colors['yellow'] = '43';
 $this->background_colors['blue'] = '44';
 $this->background_colors['magenta'] = '45';
 $this->background_colors['cyan'] = '46';
 $this->background_colors['light_gray'] = '47';
 }

 // Returns colored string
 public function getColoredString($string, $foreground_color = null, $background_color = null) {
 $colored_string = "";

 // Check if given foreground color found
 if (isset($this->foreground_colors[$foreground_color])) {
 $colored_string .= "\033[" . $this->foreground_colors[$foreground_color] . "m";
 }
 // Check if given background color found
 if (isset($this->background_colors[$background_color])) {
 $colored_string .= "\033[" . $this->background_colors[$background_color] . "m";
 }

 // Add string and end coloring
 $colored_string .=  $string . "\033[0m";

 return $colored_string;
 }

 // Returns all foreground color names
 public function getForegroundColors() {
 return array_keys($this->foreground_colors);
 }

 // Returns all background color names
 public function getBackgroundColors() {
 return array_keys($this->background_colors);
 }
 }
?>
