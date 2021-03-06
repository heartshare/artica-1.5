<?php
if(posix_getuid()<>0){die("Cannot be used in web server mode\n\n");}
include_once(dirname(__FILE__).'/ressources/class.templates.inc');
include_once(dirname(__FILE__).'/ressources/class.ini.inc');
include_once(dirname(__FILE__).'/framework/class.unix.inc');
include_once(dirname(__FILE__).'/framework/frame.class.inc');
include_once(dirname(__FILE__).'/ressources/class.os.system.inc');
include_once(dirname(__FILE__).'/ressources/class.system.network.inc');


if(preg_match("#--verbose#",implode(" ",$argv))){$GLOBALS["VERBOSE"]=true;}
if(preg_match("#--force#",implode(" ",$argv))){$GLOBALS["FORCE"]=true;}

if(system_is_overloaded(__FILE__)){
	writelogs("This system is overloaded, die()",__FUNCTION__,__FILE__,__LINE__);
	die();
}

if($argv[1]=='--clean-tmp2'){Clean_tmp_path(true);die();}
if($argv[1]=='--clean-tmp'){CleanLogs();die();}
if($argv[1]=='--clean-sessions'){sessions_clean();die();}
if($argv[1]=='--clean-install'){CleanOldInstall();die();}
if($argv[1]=='--paths-status'){PathsStatus();die();}
if($argv[1]=='--maillog'){maillog();die();}
if($argv[1]=='--wrong-numbers'){wrong_number();die();}



if(systemMaxOverloaded()){
	writelogs("This system is too many overloaded, die()",__FUNCTION__,__FILE__,__LINE__);
	die();
}

function init(){
	$sock=new sockets();
	$ArticaMaxLogsSize=$sock->GET_PERFS("ArticaMaxLogsSize");
	if($ArticaMaxLogsSize<1){$ArticaMaxLogsSize=500;}
	$ArticaMaxLogsSize=$ArticaMaxLogsSize*1000;	
	$GLOBALS["ArticaMaxLogsSize"]=$ArticaMaxLogsSize;
	$GLOBALS["logs_cleaning"]=$sock->GET_NOTIFS("logs_cleaning");
	$GLOBALS["MaxTempLogFilesDay"]=$sock->GET_INFO("MaxTempLogFilesDay");
	if($GLOBALS["MaxTempLogFilesDay"]==null){$GLOBALS["MaxTempLogFilesDay"]=5;}
	
	
}

function maillog(){
	init();
	$unix=new unix();
	$users=new usersMenus();
	$sock=new sockets();
	$BackupMailLogPath=$sock->GET_INFO("BackupMailLogPath");
	if($BackupMailLogPath==null){$BackupMailLogPath="/home/maillog-backup";}
	if(!is_dir("$BackupMailLogPath")){@mkdir($BackupMailLogPath,666,true);}
	
	foreach (glob("/usr/share/artica-postfix/*") as $filename) {
		if(is_numeric(basename($filename))){@unlink($filename);}
	}
	
	
	$c=0;
	foreach (glob("/var/log/mail-backup/*") as $filename) {
		$c++;
		$time_to_backup=filemtime($filename);
		if(!is_file("$BackupMailLogPath/$time_to_backup.log")){
			$unix->events(basename(__FILE__).":: ".__FUNCTION__." mv $filename $BackupMailLogPath/$time_to_backup.log");
			shell_exec("/bin/mv $filename $BackupMailLogPath/$time_to_backup.log");
		}else{
			$time_to_backup=$time_to_backup.".$c";
			shell_exec("/bin/mv $filename $BackupMailLogPath/$time_to_backup.log");
			$unix->events(basename(__FILE__).":: ".__FUNCTION__." mv $filename $BackupMailLogPath/$time_to_backup.log");
		}
	}
	
	$c=0;
	foreach (glob("/var/log/mail.info.*") as $filename) {
		$c++;
		$time_to_backup=filemtime($filename);
		if(!is_file("$BackupMailLogPath/$time_to_backup.log")){
			$unix->events(basename(__FILE__).":: ".__FUNCTION__." mv $filename $BackupMailLogPath/$time_to_backup.log");
			shell_exec("/bin/mv $filename $BackupMailLogPath/$time_to_backup.log");
		}else{
			$time_to_backup=$time_to_backup.".$c";
			shell_exec("/bin/mv $filename $BackupMailLogPath/$time_to_backup.log");
			$unix->events(basename(__FILE__).":: ".__FUNCTION__." mv $filename $BackupMailLogPath/$time_to_backup.log");
		}
	}	
	
	
	
	
	$maillog_path=$users->maillog_path;
	if($GLOBALS["ArticaMaxLogsSize"]<100){$GLOBALS["ArticaMaxLogsSize"]=100;}
	$maxday=$GLOBALS["MaxTempLogFilesDay"]*24;
	$maxday=$maxday*60; 
	if($maillog_path==null){
		$unix->events(basename(__FILE__).":: ".__FUNCTION__." Fatal, mail.log is not found!");
		return;
	}
	
	shell_exec("/bin/cp -f $maillog_path $BackupMailLogPath/current.log");
	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." $maillog_path for maxtime $maxday and maxsize:{$GLOBALS["ArticaMaxLogsSize"]}");
	$size=round(unix_file_size("$maillog_path")/1024);
	$time=$unix->file_time_min($maillog_path);	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." $maillog_path time:$time size:$size=={$GLOBALS["ArticaMaxLogsSize"]}");	
	
	$time_to_backup=time();
	
	if($size>$GLOBALS["ArticaMaxLogsSize"]){
		$unix->events(basename(__FILE__).":: ".__FUNCTION__." /bin/mv $maillog_path $BackupMailLogPath/$time_to_backup.log");	
		shell_exec("/bin/mv $maillog_path $BackupMailLogPath/$time_to_backup.log");
		$unix->send_email_events(basename($maillog_path)." was moved to backup directory","the $maillog_path file had a {$time}Mn TTL and $size Ko size,
		 it reach the policy {$GLOBALS["ArticaMaxLogsSize"]}Ko
		 it was moved to $BackupMailLogPath/$time_to_backup.log
		 syslog daemon will be restarted","logs_cleaning");
		$unix->RESTART_SYSLOG();
		return;
	}
	
	if($time>$maxday){
		$unix->events(basename(__FILE__).":: ".__FUNCTION__." /bin/mv $maillog_path $BackupMailLogPath/$time_to_backup.log");
		shell_exec("/bin/mv $maillog_path $BackupMailLogPath/$time_to_backup.log");
		$unix->send_email_events(basename($maillog_path)." was moved to backup directory","the $maillog_path file had a {$time}Mn TTL and $size Ko size,
		 it reach the policy {$maxday}Mn
		 it was moved to $BackupMailLogPath/$time_to_backup.log
		 syslog daemon will be restarted","logs_cleaning");
		$unix->RESTART_SYSLOG();
	}	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." DONE");
}

function Clean_tmp_path($aspid=false){
	$unix=new unix();
	if($aspid){
		$pidpath="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
		$oldpid=@file_get_contents($pidpath);
		if($unix->process_exists($oldpid)){
			$unix->events(basename(__FILE__).":: ".__FUNCTION__." Already process $oldpid running.. Aborting");
			return;
		}
	}
	@file_put_contents($pidpath,getmypid());
	
	if ($handle = opendir("/tmp")) {
		while (false !== ($file = readdir($handle))) {
			if ($file != "." && $file != "..") {
				$path="/tmp/$file";
				if($GLOBALS["VERBOSE"]){echo "$path ?\n";} 
				if(is_dir($path)){if($GLOBALS["VERBOSE"]){echo "$path is a directory\n";} continue;}
				if(preg_match("#^artica-.+?\.tmp#", $file)){
					$time=$unix->file_time_min($path);
					if($GLOBALS["VERBOSE"]){echo "$path - > {$time}Mn\n";}
					if($time>10){
						$size=@filesize($filename)/1024;
    					$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size;
    					$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1;						
						if($GLOBALS["VERBOSE"]){echo "$path - > DELETE\n";}
						@unlink($path);
					}
				}else{
					if($GLOBALS["VERBOSE"]){echo "$file -> NO MATCH ^artica-.+?\.tmp \n";} 
				}
				
			if(preg_match("#^artica-php#", $file)){
					$time=$unix->file_time_min($path);
					if($GLOBALS["VERBOSE"]){echo "$path - > {$time}Mn\n";}
					if($time>10){
						$size=@filesize($filename)/1024;
    					$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size;
    					$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1;						
						if($GLOBALS["VERBOSE"]){echo "$path - > DELETE\n";}
						@unlink($path);
					}
				}else{
					if($GLOBALS["VERBOSE"]){echo "$file -> NO MATCH ^artica-php \n";} 
				}				
			}
		}
		
	}else{
		if($GLOBALS["VERBOSE"]){echo "/tmp failed...\n";} 
	}
	if($GLOBALS["VERBOSE"]){echo "/tmp done..\n";}
}


function CleanLogs(){
	maillog();
	MakeSpace();
	$maxtime=480;
	$unix=new unix();
	$pidpath="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	$oldpid=@file_get_contents($pidpath);
	if($unix->process_exists($oldpid)){
		$unix->events(basename(__FILE__).":: ".__FUNCTION__." Already process $oldpid running.. Aborting");
		return;
	}
	
	@file_put_contents($pidpath,getmypid());
	
	$timefile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".time";
	$timeOfFile=$unix->file_time_min($timefile);
	$unix->events("CleanLogs():: Time $timeOfFile/$maxtime");
	if($timeOfFile<$maxtime){
		$unix->events("CleanLogs():: Aborting");
		return;
	}
	@unlink($timeOfFile);
	@file_put_contents($timeOfFile,"#");
	wrong_number();
	Clean_tmp_path();
	CleanOldInstall();
	if(system_is_overloaded(dirname(__FILE__))){
		$unix->send_email_events("logs cleaner task aborting, system is overloaded", "stopped after CleanOldInstall()\nWill restart in next cycle...", "system");
		return;
	}
	
	CleanBindLogs();
	if(system_is_overloaded(dirname(__FILE__))){
		$unix->send_email_events("logs cleaner task aborting, system is overloaded", "stopped after CleanBindLogs()\nWill restart in next cycle...", "system");
		return;
	}
	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." Cleaning Clamav bases");
	CleanClamav();
	if(system_is_overloaded(dirname(__FILE__))){
		$unix->send_email_events("logs cleaner task aborting, system is overloaded", "stopped after CleanClamav()\nWill restart in next cycle...", "system");
		return;
	}	
	
	$size=str_replace("&nbsp;"," ",FormatBytes($GLOBALS["DELETED_SIZE"]));
	echo "$size cleaned :  {$GLOBALS["DELETED_FILES"]} files\n";
	if($GLOBALS["DELETED_SIZE"]>500){
		send_email_events("$size logs files cleaned",
		"{$GLOBALS["DELETED_FILES"]} files cleaned for $size free disk space:\n
		".@implode("\n",$GLOBALS["UNLINKED"]),"logs_cleaning");
	}	
	$GLOBALS["DELETED_SIZE"]=0;
	$GLOBALS["DELETED_FILES"]=0;
	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." initalize");
	init();
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." cleanTmplogs()");
	cleanTmplogs();
	if(system_is_overloaded(dirname(__FILE__))){$unix->send_email_events("logs cleaner task aborting, system is overloaded",
	 "stopped after cleanTmplogs()\nWill restart in next cycle...", "system");return;}	
	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." Cleaning /var/log");
	CleanDirLogs('/var/log');
	if(system_is_overloaded(dirname(__FILE__))){$unix->send_email_events("logs cleaner task aborting, system is overloaded",
	 "stopped after CleanDirLogs(/var/log)\nWill restart in next cycle...", "system");return;}		
	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." Cleaning /opt/artica/tmp");
	CleanDirLogs('/opt/artica/tmp');
	if(system_is_overloaded(dirname(__FILE__))){$unix->send_email_events("logs cleaner task aborting, system is overloaded",
	 "stopped after CleanDirLogs(/opt/artica/tmp)\nWill restart in next cycle...", "system");return;}		
	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." Cleaning /opt/artica/install");
	CleanDirLogs('/opt/artica/install');
	if(system_is_overloaded(dirname(__FILE__))){$unix->send_email_events("logs cleaner task aborting, system is overloaded",
	 "stopped after CleanDirLogs(/opt/artica/install)\nWill restart in next cycle...", "system");return;}		
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." Cleaning phplogs");
	phplogs();
	if(system_is_overloaded(dirname(__FILE__))){$unix->send_email_events("logs cleaner task aborting, system is overloaded",
	 "stopped after phplogs()\nWill restart in next cycle...", "system");return;}		
	
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." Cleaning /opt/openemm/tomcat/logs");
	CleanDirLogs('/opt/openemm/tomcat/logs');
	
	if(system_is_overloaded(dirname(__FILE__))){$unix->send_email_events("logs cleaner task aborting, system is overloaded",
	 "stopped after phplogs()\nWill restart in next cycle...", "system");return;}	
	CleanDirLogs("/var/log/samba");

	$unix->events(basename(__FILE__).":: ".__FUNCTION__." Cleaning PHP Sessions");
	sessions_clean();
	$unix->events(basename(__FILE__).":: ".__FUNCTION__." Cleaning old install sources packages");

	
	$size=str_replace("&nbsp;"," ",FormatBytes($GLOBALS["DELETED_SIZE"]));
	echo "$size cleaned :  {$GLOBALS["DELETED_FILES"]} files\n";
	if($GLOBALS["DELETED_SIZE"]>500){
		send_email_events("$size logs files cleaned",
		"{$GLOBALS["DELETED_FILES"]} files cleaned for $size free disk space:\n
		".@implode("\n",$GLOBALS["UNLINKED"]),"logs_cleaning");
	}
	
	
}

function cleanTmplogs(){
$badfiles["100k"]=true;
$badfiles["2"]=true;
$badfiles["size"]=true;
$badfiles["versions"]=true;
$badfiles["3"]=true;
$badfiles["named_dump.db"]=true;
$badfiles["named.stats"]=true;
$badfiles["log-queries.info"]=true;
$badfiles["log-named-auth.info"]=true;
$badfiles["log-lame.info"]=true;
$badfiles["bind.pid"]=true;
$badfiles["ipp.txt"]=true;
$badfiles["debug"]=true;
$badfiles["log-update-debug.log"]=true;
$badfiles["ldap.ppu"]=true;
$badfiles["#"]=true;	
$badfiles["bin/stIFOQ6A"]=true;
$badfiles["bin/stMSOCis"]=true;
$baddirs["2000"]=true;



	while (list ($num, $ligne) = each ($badfiles) ){
		if($num==null){continue;}
		if(is_file("/usr/share/artica-postfix/$num")){@unlink("/usr/share/artica-postfix/$num");}
	}
	
	while (list ($num, $ligne) = each ($baddirs) ){
		if($num==null){continue;}
		if(is_dir("/usr/share/artica-postfix/$num")){shell_exec("/bin/rm -rf /usr/share/artica-postfix/$num");}
	}	
	
	$unix=new unix();
	$countfile=0;
	foreach (glob("/tmp/artica*") as $filename) {
		
	$countfile++;
		if($countfile>500){
			if(is_overloaded()){
				$unix->send_email_events("Clean Files: [/tmp/artica*]: System is overloaded ({$GLOBALS["SYSTEM_INTERNAL_LOAD"]}",
				"The clean logs function is stopped and wait a new schedule with best performances",
				"logs_cleaning");
				die();
			}
			$countfile=0;
		}		
		
    	$time=$unix->file_time_min($filename);
    	if($time>2){
    		$size=@filesize($filename)/1024;
    		$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size;
    		$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1;
    		if($GLOBALS["VERBOSE"]){echo "Delete $filename\n";}
    		$unix->events(basename(__FILE__)." Delete $filename");
    		@unlink($filename);
    	}else{
    	if($GLOBALS["VERBOSE"]){echo "$filename TTL:$time \n";}
    	}
	}
	
	$countfile=0;
if($GLOBALS["VERBOSE"]){echo "/tmp/process1*\n";}
	foreach (glob("/tmp/process1*") as $filename) {
		
	$countfile++;
		if($countfile>500){
			if(is_overloaded()){
				$unix->send_email_events("Clean Files: [/tmp/process1*]: System is overloaded ({$GLOBALS["SYSTEM_INTERNAL_LOAD"]}",
				"The clean logs function is stopped and wait a new schedule with best performances",
				"logs_cleaning");
				die();
			}
			$countfile=0;
		}				
		
    	$time=$unix->file_time_min($filename);
    	if($time>1){
    		$size=@filesize($filename)/1024;
    		$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size; 
    		$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1;   
    		if($GLOBALS["VERBOSE"]){echo "Delete $filename\n";}
    		$unix->events(basename(__FILE__)." Delete $filename");	
    		@unlink($filename);
    	}else{
    		if($GLOBALS["VERBOSE"]){echo "$filename TTL:$time \n";}
    	}
	}
	
}

function sessions_clean(){
	$unix=new unix();
	foreach (glob("/var/lib/php5/*") as $filename) {
		$array=$unix->alt_stat($filename);
		$owner=$array["owner"]["owner"]["name"];
		$time=file_time_min($filename);
		if($time>2){
			if($owner=="root"){@unlink($filename);}
		}
	}
	
}
function wrong_number(){
	$unix=new unix();
	foreach (glob("/usr/share/artica-postfix/*") as $filename) {
		$name=basename($filename);
		if($name=="?"){@unlink($filename);continue;}
		if(preg_match("#^[0-9]+$#", $name)){@unlink($filename);}
	}
	
}

function phplogs(){
	$filename="/usr/share/artica-postfix/ressources/logs/php.log";
	$size=@filesize($filename)/1024;
	if($GLOBALS["VERBOSE"]){echo "php.log size:{$size}Ko \n";}
	if($size>50681){
		$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1; 
		$GLOBALS["DELETED_SIZE"]=$size;
		@unlink($filename);
	}
}


function CleanClamav(){
	$unix=new unix();
	
	foreach (glob("/var/lib/clamav/clamav-*") as $filename) {
		$time=$unix->file_time_min($filename);
		if($time>60){
			if(is_dir($filename)){
				$size=dirsize($filename)/1024;
				$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1; 
				$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size;
				shell_exec($unix->find_program("rm")." -rf $filename");
				if($GLOBALS["VERBOSE"]){echo "Delete directory $filename ($size Ko) TTL:$time\n";}
				continue;
				
			}
			$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1; 
			$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size;
			if($GLOBALS["VERBOSE"]){echo "Delete $filename ($size Ko)\n";}		
			unlink($filename);
		}
		
	}
}

function CleanBindLogs(){
	$f["/var/cache/bind/log-lame.info"]=1;
	$f["/var/cache/bind/log-queries.info"]=1;
	while (list ($filepath, $none) = each ($f) ){
		$size=round(unix_file_size("$filepath")/1024);
		if($size>51200000){
			@unlink($filepath);
			$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1; 
			$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size;
		}
		
		
	}
	
}


function PathsStatus(){
	$f[]="/root";
	foreach (glob("/usr/share/*",GLOB_ONLYDIR) as $filename) {
		$f[]=$filename;
	}
	
	while (list ($num, $dir) = each ($f) ){
		echo "$dir\t".str_replace("&nbsp;"," ",FormatBytes(dirsize($dir)/1024))."\n";
	}
	
}


function dirsize($path){
	$unix=new unix();
	
	exec($unix->find_program("du")." -b $path",$results);
	$tt=implode("",$results);
	if(preg_match("#([0-9]+)\s+#",$tt,$re)){return $re[1];}
	
}

function CleanOldInstall(){
	
	foreach (glob("/root/APP_*",GLOB_ONLYDIR) as $dirname) {
		if(!is_dir($dirname)){return;}
		$time=file_get_time_min($dirname);
		
		if($time>2880){
			echo "Removing $dirname\n";
			$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+dirsize($dirname);
			shell_exec("/bin/rm -rf $dirname");}
		}
	
}
function is_overloaded($file=null){
	if(!isset($GLOBALS["CPU_NUMBER"])){
			$users=new usersMenus();
			$GLOBALS["CPU_NUMBER"]=intval($users->CPU_NUMBER);
	}
	
	$array_load=sys_getloadavg();
	$internal_load=$array_load[0];
	$cpunum=$GLOBALS["CPU_NUMBER"]+1.5;
	if($file==null){$file=basename(__FILE__);}

	if($internal_load>$cpunum){
		$GLOBALS["SYSTEM_INTERNAL_LOAD"]=$internal_load;
		return true;
		
	}
	return false;

	
}


function CleanDirLogs($path){
	if($GLOBALS["VERBOSE"]){echo "CleanDirLogs($path)\n";}
	$BigSize=false;
	if($path=='/var/log'){$BigSize=true;}
	if($GLOBALS["ArticaMaxLogsSize"]<100){$GLOBALS["ArticaMaxLogsSize"]=100;}
	$maxday=$GLOBALS["MaxTempLogFilesDay"]*24;
	$maxday=$maxday*60; 
	$users=new usersMenus();
	$maillog_path=$users->maillog_path;	
	

$unix=new unix();
$sock=new sockets();

$restartSyslog=false;
if($path==null){return;}

	$countfile=0;
	foreach (glob("$path/*") as $filepath) {
		if($filepath==null){continue;}
		if(is_link($filepath)){continue;}
		if(is_dir($filepath)){continue;}
		if($filepath==$maillog_path){continue;}
		if(preg_match("#\/log\/artica-postfix\/#",$filepath)){continue;}
		
		$countfile++;
		if($countfile>500){
			if(is_overloaded()){
				$unix->send_email_events("Clean Files: [$path/*] System is overloaded ({$GLOBALS["SYSTEM_INTERNAL_LOAD"]}",
			"The clean logs function is stopped and wait a new schedule with best performances",
			"logs_cleaning");
			die();
			}
			$countfile=0;
		}
		
		
		usleep(300);
		$size=round(unix_file_size("$filepath")/1024);
		$time=$unix->file_time_min($filepath);
		$unix->events("$filepath $size Ko, {$time}Mn/{$maxday}Mn TTL");
		if($size>$GLOBALS["ArticaMaxLogsSize"]){
				if($GLOBALS["VERBOSE"]){echo "Delete $filepath\n";}
				$restartSyslog=true;
				$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size;
				$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1;
				$GLOBALS["UNLINKED"][]=$filepath;
				@unlink($filepath);
				continue;
		}
		
		if($time>$maxday){
			$GLOBALS["DELETED_SIZE"]=$GLOBALS["DELETED_SIZE"]+$size;
			$GLOBALS["DELETED_FILES"]=$GLOBALS["DELETED_FILES"]+1;
			if($GLOBALS["VERBOSE"]){echo "Delete $filepath\n";}
			@unlink($filepath);
			$GLOBALS["UNLINKED"][]=$filepath;
			$restartSyslog=true;
			continue;
		}
		
		
	}
	


		if($restartSyslog){
			$unix->send_email_events("System log will be restarted",
			"Logs files was deleted and log daemons will be restarted
			".@implode("\n",$GLOBALS["UNLINKED"]),
			"logs_cleaning");
			$unix->RESTART_SYSLOG();
		}

}


function systemLogs(){
$f[]="/var/log/daemons/errors.log";
$f[]="/var/log/daemons/info.log";
$f[]="/var/log/daemons/warnings.log";

}



function unix_file_size($path){
	$unix=new unix();
	if($GLOBALS["stat"]==null){$GLOBALS["stat"]=$unix->find_program("stat");}
	$path=$unix->shellEscapeChars($path);
	exec("{$GLOBALS["stat"]} $path ",$results);
	while (list ($num, $line) = each ($results)){
		if(preg_match("#Size:\s+([0-9]+)\s+Blocks#",$line,$re)){
			$res=$re[1];break;
		}
	}
	if(!is_numeric($res)){$res=0;}
	return $res;
}

function MakeSpace(){
	if(is_dir("/usr/share/doc")){shell_exec("/bin/rm -rf /usr/share/doc");}
	$sock=new sockets();
	$EnableBackupPc=$sock->GET_INFO("EnableBackupPc");
	if(!is_numeric($EnableBackupPc)){$EnableBackupPc=0;}
	if($EnableBackupPc==0){
		if(is_dir("/var/lib/backuppc")){shell_exec("/bin/rm -rf /var/lib/backuppc");}
	}
}



//############################################################################## 
?>