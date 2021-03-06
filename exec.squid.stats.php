<?php
$GLOBALS["BYPASS"]=true;
include_once(dirname(__FILE__).'/ressources/class.templates.inc');
include_once(dirname(__FILE__).'/ressources/class.ccurl.inc');
include_once(dirname(__FILE__).'/ressources/class.ini.inc');
include_once(dirname(__FILE__).'/ressources/class.mysql.inc');
include_once(dirname(__FILE__).'/framework/class.unix.inc');
include_once(dirname(__FILE__).'/ressources/class.squid.inc');
include_once(dirname(__FILE__).'/ressources/class.artica.graphs.inc');
include_once(dirname(__FILE__).'/ressources/class.os.system.inc');
include_once(dirname(__FILE__)."/framework/frame.class.inc");

$GLOBALS["OLD"]=false;
$GLOBALS["FORCE"]=false;
$GLOBALS["Q"]=new mysql_squid_builder();
if(is_array($argv)){
	if(preg_match("#--verbose#",implode(" ",$argv))){$GLOBALS["VERBOSE"]=true;}
	if(preg_match("#--old#",implode(" ",$argv))){$GLOBALS["OLD"]=true;}
	if(preg_match("#--force#",implode(" ",$argv))){$GLOBALS["FORCE"]=true;}
}
if($GLOBALS["VERBOSE"]){ini_set('display_errors', 1);	ini_set('html_errors',0);ini_set('display_errors', 1);ini_set('error_reporting', E_ALL);}
$unix=new unix();
$GLOBALS["CLASS_UNIX"]=$unix;
events("Executed " .@implode(" ",$argv));
$sock=new sockets();
if(!is_dir("/var/log/artica-postfix/artica-squid-events")){@mkdir("/var/log/artica-postfix/artica-squid-events",644,true);}
$squidEnableRemoteStatistics=$sock->GET_INFO("squidEnableRemoteStatistics");
if(!is_numeric($squidEnableRemoteStatistics)){$squidEnableRemoteStatistics=0;}
if($squidEnableRemoteStatistics==1){events("this server is not in charge of statistics...");die();}
if(!ifMustBeExecuted()){
	if($GLOBALS["VERBOSE"]){echo "this server is not in charge of statistics (categories repositories or Statistics Appliance) ...\n";}
	events("this server is not in charge of statistics (categories repositories or Statistics Appliance) ...");die();
}

if($GLOBALS["VERBOSE"]){echo "LAUNCH: '{$argv[1]}'\n";}


if($argv[1]=='--scan-hours'){scan_hours();die();}
if($argv[1]=='--scan-months'){scan_months();die();}
if($argv[1]=='--tables-days'){table_days();die();}
if($argv[1]=='--block-days'){block_days();die();}
if($argv[1]=='--hours'){clients_hours();die();}
if($argv[1]=='--flow-month'){flow_month();die();}
if($argv[1]=='--members'){members_hours();die();}
if($argv[1]=='--members-month'){members_month();die();}
if($argv[1]=='--parse-cacheperfs'){squid_cache_perfs();die();}
if($argv[1]=='--show-tables'){show_tables();die();}
if($argv[1]=='--tables'){$q=new mysql();$q->CheckTablesSquid();die();}
if($argv[1]=='--members-month-kill'){members_month_delete();exit;}
if($argv[1]=='--fix-tables'){$GLOBALS["Q"]->FixTables();exit;}
if($argv[1]=='--visited-sites'){visited_sites();exit;}
if($argv[1]=='--sync-categories'){sync_categories();exit;}
if($argv[1]=='--re-categorize'){re_categorize();exit;}
if($argv[1]=='--re-re-categorize'){__re_categorize_subtables();exit;}
if($argv[1]=='--export-last-websites'){export_last_websites();exit;}

//visited_sites
if($GLOBALS["VERBOSE"]){echo "UNABLE TO UNDERSTAND: '{$argv[1]}'\n";}

$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".pid";
$oldpid=@file_get_contents($pidfile);
if($oldpid<100){$oldpid=null;}
$unix=new unix();
if($unix->process_exists($oldpid)){if($GLOBALS["VERBOSE"]){echo "Already executed pid $oldpid\n";}die();}
$mypid=getmypid();
@file_put_contents($pidfile,$mypid);

function sync_categories(){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	$oldpid=@file_get_contents($pidfile);
	if($oldpid<100){$oldpid=null;}
	$unix=new unix();
	if($unix->process_exists($oldpid,basename(__FILE__))){if($GLOBALS["VERBOSE"]){echo "Already executed pid $oldpid\n";}die();}
	$mypid=getmypid();
	@file_put_contents($pidfile,$mypid);	
	
	$sql="SELECT * FROM visited_sites WHERE sitename LIKE 'www.%'";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$website=trim($ligne["sitename"]);
		if(preg_match("#^www\.(.+)#", $website,$re)){
			$ligne2=mysql_fetch_array($GLOBALS["Q"]->QUERY_SQL("SELECT sitename FROM visited_sites WHERE sitename='{$re[1]}'"));
			if($ligne2["sitename"]<>null){
				$GLOBALS["Q"]->QUERY_SQL("DELETE FROM visited_sites WHERE sitename='$website'");
			}else{
				$GLOBALS["Q"]->QUERY_SQL("UPDATE visited_sites SET sitename='{$re[1]}' WHERE sitename='{$ligne["sitename"]}'");
			}
			$GLOBALS["Q"]->UPDATE_WEBSITES_TABLES($ligne["sitename"],$re[1]);
		}
	}	
	
	
	$sql="SELECT * FROM visited_sites WHERE LENGTH(category)=0";
	if($GLOBALS["VERBOSE"]){echo "$sql\n";}
	
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	if(!$GLOBALS["Q"]->ok){writelogs_squid("Starting analyzing not categorized websites Failed ".$GLOBALS["Q"]->mysql_error,__FUNCTION__,__FILE__,__LINE__,"stats");return;}
	$num_rows = mysql_num_rows($results);
	$t=time();
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	writelogs_squid("Starting analyzing $num_rows not categorized websites",__FUNCTION__,__FILE__,__LINE__,"stats");
	$c=0;$d=0;
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$website=trim($ligne["sitename"]);
		$category=null;
		if($website==null){continue;}
		$t2=time();
		$c++;
		$d++;
		if($d>1000){if($GLOBALS["VERBOSE"]){echo "Analyzed $c websites\n";$d=0;}}
		$category=$GLOBALS["Q"]->GET_CATEGORIES($website,true);
		if(trim($category)<>null){
			$took=$unix->distanceOfTimeInWords($t2,time());
			writelogs_squid("$website = $category $took",__FUNCTION__,__FILE__,__LINE__,"stats");
			$GLOBALS["Q"]->UPDATE_CATEGORIES_TABLES($website,$category);
			if($GLOBALS["VERBOSE"]){echo "UPDATE_CATEGORIES_TABLES DONE..\n";}
			$GLOBALS["Q"]->QUERY_SQL("UPDATE visited_sites SET category='$category' WHERE sitename='$website'");
			if(!$GLOBALS["Q"]->ok){writelogs_squid("Fatal error while update visited_sites {$GLOBALS["Q"]->mysql_error}",__FUNCTION__,__FILE__,__LINE__,"stats");}
			return;
		}
	}
	$took=$unix->distanceOfTimeInWords($t,time());
	writelogs_squid("Analyze not categorized websites finish ($took)",__FUNCTION__,__FILE__,__LINE__,"stats");
	

	
}


function re_categorize(){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	$oldpid=@file_get_contents($pidfile);
	if($oldpid<100){$oldpid=null;}
	$unix=new unix();
	if($unix->process_exists($oldpid,basename(__FILE__))){if($GLOBALS["VERBOSE"]){echo "Already executed pid $oldpid\n";}die();}
	
	if(systemMaxOverloaded()){writelogs_squid("Fatal: VERY Overloaded system, die();",__FUNCTION__,__FILE__,__LINE__,"stats");return;	}		
	
	$mypid=getmypid();
	@file_put_contents($pidfile,$mypid);	
	$sock=new sockets();
	$RecategorizeSecondsToWaitOverload=$sock->GET_INFO("RecategorizeSecondsToWaitOverload");
	$RecategorizeMaxExecutionTime=$sock->GET_INFO("RecategorizeSecondsToWaitOverload");
	$RecategorizeProxyStats=$sock->GET_INFO("RecategorizeProxyStats");
	if(!is_numeric($RecategorizeProxyStats)){$RecategorizeProxyStats=1;}	
	if(!is_numeric($RecategorizeSecondsToWaitOverload)){$RecategorizeSecondsToWaitOverload=30;}
	if(!is_numeric($RecategorizeMaxExecutionTime)){$RecategorizeMaxExecutionTime=210;}
	if($RecategorizeProxyStats==0){return;}	
	$t=time();
	$sql="SELECT * FROM visited_sites";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	$num_rows = mysql_num_rows($results);
	
	$c=0;
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$website=trim($ligne["sitename"]);
		if($website==null){continue;}
		$category=trim($GLOBALS["Q"]->GET_CATEGORIES($website,true));
		$GLOBALS["Q"]->QUERY_SQL("UPDATE visited_sites SET category='$category' WHERE sitename='$website'");
		if(!$GLOBALS["Q"]->ok){writelogs_squid("Fatal: mysql error {$GLOBALS["Q"]->mysql_error}",__FUNCTION__,__FILE__,__LINE__,"stats");return;}	
		$c++;
		if($c>5000){
			$distanceInSeconds = round(abs(time() - $t));
	    	$distanceInMinutes = round($distanceInSeconds / 60);
	    	if($distanceInMinutes>$RecategorizeMaxExecutionTime){
	    		$took=$unix->distanceOfTimeInWords($t,time());
	    		writelogs_squid("Re-categorized websites task aborted (Max execution time {$RecategorizeMaxExecutionTime}Mn) ($took)",__FUNCTION__,__FILE__,__LINE__,"stats");
	    		return;
	    	}
	    	$c=0;
		}
		
		
		
	}
	$took=$unix->distanceOfTimeInWords($t,time());
	writelogs_squid("$num_rows re-categorized  websites in main table  ($took)",__FUNCTION__,__FILE__,__LINE__,"stats");
	__re_categorize_subtables($t);
	
}

function __re_categorize_subtables($oldT1=0){
	$unix=new unix();
	if(systemMaxOverloaded()){writelogs_squid("Fatal: VERY Overloaded system, die();",__FUNCTION__,__FILE__,__LINE__,"stats");return;	}	
	$sock=new sockets();
	$RecategorizeSecondsToWaitOverload=$sock->GET_INFO("RecategorizeSecondsToWaitOverload");
	$RecategorizeMaxExecutionTime=$sock->GET_INFO("RecategorizeSecondsToWaitOverload");
	if(!is_numeric($RecategorizeSecondsToWaitOverload)){$RecategorizeSecondsToWaitOverload=30;}
	if(!is_numeric($RecategorizeMaxExecutionTime)){$RecategorizeMaxExecutionTime=210;}
	if($oldT1>1){$t=$oldT1;}else{$t=time();}
	
	
	$tables_days=$GLOBALS["Q"]->LIST_TABLES_DAYS();
	$tables_hours=$GLOBALS["Q"]->LIST_TABLES_HOURS();
	$sql="SELECT * FROM visited_sites";	
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	$num_rows = mysql_num_rows($results);	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$website=trim($ligne["sitename"]);
		$category=trim($ligne["category"]);
		if($website==null){continue;}
		if($category==null){continue;}
		reset($tables_days);
		reset($tables_hours);
		while (list ($num, $tablename) = each ($tables_days) ){
			$category=addslashes($category);
			$GLOBALS["Q"]->QUERY_SQL("UPDATE $tablename SET category='$category' WHERE sitename='$website'");
			if(!$GLOBALS["Q"]->ok){writelogs_squid("Fatal: mysql error on table $tablename {$GLOBALS["Q"]->mysql_error}",__FUNCTION__,__FILE__,__LINE__,"stats");return;}
		}
		
		while (list ($num, $tablename) = each ($tables_hours) ){
			$category=addslashes($category);
			$GLOBALS["Q"]->QUERY_SQL("UPDATE $tablename SET category='$category' WHERE sitename='$website'");
			if(!$GLOBALS["Q"]->ok){writelogs_squid("Fatal: mysql error on table $tablename {$GLOBALS["Q"]->mysql_error}",__FUNCTION__,__FILE__,__LINE__,"stats");return;}
		}

		if(system_is_overloaded(__FILE__)){writelogs_squid("Fatal: Overloaded system, sleeping $RecategorizeSecondsToWaitOverload secondes...",__FUNCTION__,__FILE__,__LINE__,"stats");sleep($RecategorizeSecondsToWaitOverload);}
		if(systemMaxOverloaded()){writelogs_squid("Fatal: VERY Overloaded system, die();",__FUNCTION__,__FILE__,__LINE__,"stats");return;	}
		
		$distanceInSeconds = round(abs(time() - $t));
	    $distanceInMinutes = round($distanceInSeconds / 60);
	    if($distanceInMinutes>$RecategorizeMaxExecutionTime){$took=$unix->distanceOfTimeInWords($t,time());writelogs_squid("Re-categorized websites task aborted (Max execution time {$RecategorizeMaxExecutionTime}Mn) ($took)",__FUNCTION__,__FILE__,__LINE__,"stats");return;}		
		
	}
	
	$took=$unix->distanceOfTimeInWords($t,time());
	writelogs_squid("$num_rows re-categorized  websites updated in ". count($tables_days). " day tables, ". count($tables_hours). " hours tables, ($took)",__FUNCTION__,__FILE__,__LINE__,"stats");

}


function scan_hours(){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".". __FUNCTION__.".pid";
	$oldpid=@file_get_contents($pidfile);
	$unix=new unix();
	if($unix->process_exists($oldpid)){if($GLOBALS["VERBOSE"]){echo "Already executed pid $oldpid\n";}die();}
	$mypid=getmypid();
	@file_put_contents($pidfile,$mypid);
	$GLOBALS["Q"]->FixTables();
	$php5=$unix->LOCATE_PHP5_BIN();
	shell_exec("$php5 /usr/share/artica-postfix/exec.fcron.php --squid-recategorize-task &");
	table_days();
	clients_hours(true);
	members_hours(true);
}

function scan_months(){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".". __FUNCTION__.".pid";
	$oldpid=@file_get_contents($pidfile);
	$unix=new unix();
	if($unix->process_exists($oldpid)){if($GLOBALS["VERBOSE"]){echo "Already executed pid $oldpid\n";}die();}
	$mypid=getmypid();
	@file_put_contents($pidfile,$mypid);
	table_days();
	members_month(true);
	flow_month(true);
	block_days(true);	
}



function flow_month($nopid=false){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	if($nopid){
		$oldpid=@file_get_contents($pidfile);
		$myfile=basename(__FILE__);
		$unix=new unix();
		if($unix->process_exists($oldpid,$myfile)){die();}
	}
	$mypid=getmypid();
	@file_put_contents($pidfile,$mypid);
	
$sql="SELECT MONTH(zDate) AS smonth,YEAR(zDate) AS syear FROM tables_day WHERE zDate<DATE_SUB(NOW(),INTERVAL 1 DAY) AND month_flow=0 ORDER BY zDate";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	if(!$GLOBALS["Q"]->ok){events_tail("{$GLOBALS["Q"]->mysql_error}\n------\n$sql\n----");return;}
		
	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){

		$month=$ligne["smonth"];
		$year=$ligne["syear"];
		
		if(isset($already["$month$year"])){continue;}		
		
		flow_month_query($month,$year);
		$already["$month$year"]=true;
	}		
	
	
}
function flow_month_query($month,$year){
	events_tail("Processing $year/$month ".__LINE__);
	
	
	$sql="SELECT DATE_FORMAT(zDate,'%Y%m') AS suffix,DATE_FORMAT(zDate,'%Y%m%d') AS suffix2,DAY(zDate) as tday,YEAR(zDate) AS tyear,month(zDate) AS tmonth FROM tables_day 
	WHERE zDate<DATE_SUB(NOW(),INTERVAL 1 DAY) AND YEAR(zDate)=$year AND month(zDate)=$month ORDER BY zDate";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	if(!$GLOBALS["Q"]->ok){events_tail("{$GLOBALS["Q"]->mysql_error}");return;}
		
	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	
	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$next_table=$ligne["suffix"]."_day";
		$tabledatas=$ligne["suffix2"]."_hour";
		$day=$ligne["tday"];
		if(!$GLOBALS["Q"]->CreateMonthTable($next_table)){events_tail("Failed to create $next_table");return;}
		if(!_flow_month_query_perfom($tabledatas,$next_table,$day)){events_tail("Failed to process $tabledatas to $next_table");return;}
	}
	
	if("$year$month"<>date('Ym')){
		events_tail("Processing $year/$month -> Close UPDATE tables_day SET month_flow=1 WHERE MONTH(zDate)=$month AND YEAR(zDate)=$year line ".__LINE__);
		$GLOBALS["Q"]->QUERY_SQL("UPDATE tables_day SET month_flow=1 WHERE MONTH(zDate)=$month AND YEAR(zDate)=$year");
	}
	return true;
	
}

function _flow_month_query_perfom($SourceTable,$destinationTable,$day){
	
	$output_rows=false;
	$sql="SELECT sitename, familysite, client, remote_ip, country, SUM( size ) as QuerySize, SUM( hits ) as hits, uid, category, cached
	FROM $SourceTable GROUP BY sitename, familysite, client, remote_ip, country, uid, category, cached";
	
	
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	if(!$GLOBALS["Q"]->ok){events_tail("{$GLOBALS["Q"]->mysql_error}\n------\n$sql\n----");return;}

	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return true;}
	
	events_tail("Processing $SourceTable -> $destinationTable for day $day $num_rows  rows in line ".__LINE__);
	
	$prefix="INSERT IGNORE INTO $destinationTable (zMD5,sitename,client,`day`,remote_ip,country,size,hits,uid,category,cached,familysite) VALUES ";
	
	$f=array();
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$client=addslashes(trim(strtolower($ligne["client"])));
		$uid=addslashes(trim(strtolower($ligne["uid"])));
		$sitename=addslashes(trim(strtolower($ligne["sitename"])));
		$remote_ip=addslashes(trim(strtolower($ligne["remote_ip"])));
		$country=addslashes(trim(strtolower($ligne["country"])));
		$category=addslashes(trim(strtolower($ligne["category"])));
		$familysite=addslashes(trim(strtolower($ligne["familysite"])));
		
	
		$md5=md5("{$ligne["client"]}$day{$ligne["uid"]}{$ligne["QuerySize"]}$remote_ip$country{$ligne["hits"]}$sitename");
		$sql_line="('$md5','$sitename','$client','$day','$remote_ip','$country','{$ligne["QuerySize"]}','{$ligne["hits"]}','$uid','$category','{$ligne["cached"]}','$familysite')";
		$f[]=$sql_line;
		
		if($output_rows){if($GLOBALS["VERBOSE"]){echo "$sql_line\n";}}	
		
		if(count($f)>500){
			$GLOBALS["Q"]->QUERY_SQL("$prefix" .@implode(",", $f));
			if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error}");return;}
			$f=array();
		}
		
	}

	if(count($f)>0){
		$GLOBALS["Q"]->QUERY_SQL("$prefix" .@implode(",", $f));
		events_tail("Processing ". count($f)." rows");
		if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error}");return;}
	}
	
	return true;	

}





function members_month($nopid=false){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	if($nopid){
		$oldpid=@file_get_contents($pidfile);
		$unix=new unix();
		if($unix->process_exists($oldpid)){die();}
		$mypid=getmypid();
		@file_put_contents($pidfile,$mypid);		
	}
	
	

	
	$q=new mysql_squid_builder();
	
	
	
	$sql="SELECT MONTH(zDate) AS smonth,YEAR(zDate) AS syear FROM tables_day WHERE zDate<DATE_SUB(NOW(),INTERVAL 1 DAY) AND month_members=0 ORDER BY zDate";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	if(!$GLOBALS["Q"]->ok){events_tail("{$GLOBALS["Q"]->mysql_error}\n------\n$sql\n----");return;}
		
	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){

		$month=$ligne["smonth"];
		$year=$ligne["syear"];
		
		if(isset($already["$month$year"])){continue;}		
		
		members_month_query($month,$year);
		$already["$month$year"]=true;
	}
}


function members_month_query($month,$year){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	$oldpid=@file_get_contents($pidfile);
	$unix=new unix();
	if($unix->process_exists($oldpid)){die();}
	$mypid=getmypid();
	@file_put_contents($pidfile,$mypid);
	table_days();
	$q=new mysql_squid_builder();
	events_tail("Processing $year/$month ".__LINE__);
	
	
	$sql="SELECT DATE_FORMAT(zDate,'%Y%m') AS suffix,DATE_FORMAT(zDate,'%Y%m%d') AS suffix2,DAY(zDate) as tday,YEAR(zDate) AS tyear,month(zDate) AS tmonth FROM tables_day 
	WHERE zDate<DATE_SUB(NOW(),INTERVAL 1 DAY) AND YEAR(zDate)=$year AND month(zDate)=$month ORDER BY zDate";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	if(!$GLOBALS["Q"]->ok){events_tail("{$GLOBALS["Q"]->mysql_error}");return;}
		
	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	
	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$next_table=$ligne["suffix"]."_members";
		$tabledatas=$ligne["suffix2"]."_members";
		$day=$ligne["tday"];
		if(!$GLOBALS["Q"]->CreateMembersMonthTable($next_table)){events_tail("Failed to create $next_table");return;}
		if(!_members_month_perfom($tabledatas,$next_table,$day)){events_tail("Failed to process $tabledatas to $next_table");return;}
	}
	
	if("$year$month"<>date('Ym')){
		events_tail("Processing $year/$month -> Close UPDATE tables_day SET month_members=1 WHERE MONTH(zDate)=$month AND YEAR(zDate)=$year line ".__LINE__);
		$GLOBALS["Q"]->QUERY_SQL("UPDATE tables_day SET month_members=1 WHERE MONTH(zDate)=$month AND YEAR(zDate)=$year");
	}
	return true;
	
}

function _members_month_perfom($sourcetable,$destinationtable,$day){
	$output_rows=false;
	

	
	
	$sql="SELECT SUM( size ) AS QuerySize, SUM(hits) as hits,cached, client, uid,MAC
	FROM $sourcetable
	GROUP BY cached, client, uid,MAC
	HAVING QuerySize>0 ";	
	
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	if(!$GLOBALS["Q"]->ok){events_tail($GLOBALS["Q"]->mysql_error);return;}	
	$num_rows=mysql_num_rows($results);
	events_tail("Processing $sourcetable -> $destinationtable for day $day $num_rows  rows in line ".__LINE__);
	
	$prefix="INSERT IGNORE INTO $destinationtable (`zMD5`,`client`,`day`,`size`,`hits`,`uid`,`cached`,`MAC`) VALUES ";
	
	$f=array();
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$client=addslashes(trim(strtolower($ligne["client"])));
		$uid=addslashes(trim(strtolower($ligne["uid"])));
	
		$md5=md5("$client$day$uid{$ligne["QuerySize"]}{$ligne["hits"]}");
		$sql_line="('$md5','$client','$day','{$ligne["QuerySize"]}','{$ligne["hits"]}','$uid','{$ligne["cached"]}','{$ligne["MAC"]}')";
		$f[]=$sql_line;
		
		if($output_rows){if($GLOBALS["VERBOSE"]){echo "$sql_line\n";}}	
		
		if(count($f)>500){
			$GLOBALS["Q"]->QUERY_SQL("$prefix" .@implode(",", $f));
			if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error}");return;}
			$f=array();
		}
		
	}

	if(count($f)>0){
		$GLOBALS["Q"]->QUERY_SQL("$prefix" .@implode(",", $f));
		events_tail("Processing ". count($f)." rows");
		if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error}");return;}
	}
	return true;	
	
	
}



function members_hours($nopid=false){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	if(!$nopid){
		$oldpid=@file_get_contents($pidfile);
		$unix=new unix();
		if($unix->process_exists($oldpid)){die();}
		$mypid=getmypid();
		@file_put_contents($pidfile,$mypid);
	}
	
	
	$currenttable="dansguardian_events_".date('Ymd');
	$next_table=date('Ymd')."_members";
	_members_hours_perfom($currenttable,$next_table);	
	
	table_days();
	$q=new mysql_squid_builder();
	
	$sql="SELECT DATE_FORMAT(zDate,'%Y%m%d') as suffix,tablename FROM tables_day WHERE members=0";
	$results=$q->QUERY_SQL($sql);
	if(!$q->ok){events_tail("$q->mysql_error");return;}
	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$next_table=$ligne["suffix"]."_members";
		if(!$q->CreateMembersDayTable($next_table)){events_tail("Failed to create $next_table");return;}
		if(!_members_hours_perfom($ligne["tablename"],$next_table)){events_tail("Failed to process {$ligne["tablename"]} to $next_table");return;}
	}
}

function _members_hours_perfom($tabledata,$nexttable){
	$filter_hour=null;	
	$filter_hour_1=null;
	$filter_hour_2=null;
	$GLOBALS["Q"]->CreateMembersDayTable($nexttable);
	$todaytable=date('Ymd')."_members";
	$CloseTable=true;
	$output_rows=false;
	
	
	if($nexttable==$todaytable){
		$filter_hour_1="AND HOUR < HOUR( NOW())";
		$CloseTable=false;
	}

	$sql="SELECT hour FROM $nexttable ORDER BY hour DESC LIMIT 0,1";
	$ligne=mysql_fetch_array($GLOBALS["Q"]->QUERY_SQL($sql));
	if(!is_numeric($ligne["hour"])){$ligne["hour"]=-1;}
	events_tail("processing  $tabledata Last hour >{$ligne["hour"]}h");
	$filter_hour_2=" AND HOUR>{$ligne["hour"]}";
	
	
	$sql="SELECT SUM( QuerySize ) AS QuerySize, COUNT(zmd5) as hits,cached, HOUR( zDate ) AS HOUR , CLIENT, uid,MAC
	FROM $tabledata
	GROUP BY cached, HOUR( zDate ) , CLIENT, uid,MAC
	HAVING QuerySize>0  $filter_hour_1$filter_hour_2";
	
	
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	$num_rows=mysql_num_rows($results);
	events_tail("Processing $tabledata -> $nexttable CLOSE:$CloseTable (today is $todaytable) filter:'$filter_hour_2' $num_rows  rows in line ".__LINE__);
	if($num_rows<10){$output_rows=true;}

	if($num_rows==0){
		events_tail("$tabledata no rows...CloseTable=$CloseTable");
		if($CloseTable){
			events_tail("$tabledata -> Close table");
			$sql="UPDATE tables_day SET members=1 WHERE tablename='$tabledata'";
			$GLOBALS["Q"]->QUERY_SQL($sql);
		}
		return true;
	}

	$prefix="INSERT IGNORE INTO $nexttable (zMD5,client,hour,size,hits,uid,cached,MAC) VALUES ";
	
	$f=array();
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$client=addslashes(trim(strtolower($ligne["CLIENT"])));
		$uid=addslashes(trim(strtolower($ligne["uid"])));
	
		$md5=md5("{$ligne["CLIENT"]}{$ligne["HOUR"]}{$ligne["uid"]}{$ligne["QuerySize"]}{$ligne["hits"]}");
		$sql_line="('$md5','$client','{$ligne["HOUR"]}','{$ligne["QuerySize"]}','{$ligne["hits"]}','$uid','{$ligne["cached"]}','{$ligne["MAC"]}')";
		$f[]=$sql_line;
		
		if($output_rows){if($GLOBALS["VERBOSE"]){echo "$sql_line\n";}}	
		
		if(count($f)>500){
			$GLOBALS["Q"]->QUERY_SQL("$prefix" .@implode(",", $f));
			if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error}");return;}
			$f=array();
		}
		
	}

	if(count($f)>0){
		$GLOBALS["Q"]->QUERY_SQL("$prefix" .@implode(",", $f));
		events_tail("Processing ". count($f)." rows");
		if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error}");return;}
	}
	return true;
}



function clients_hours($nopid=false){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	$unix=new unix();
	if(!$nopid){
		$oldpid=@file_get_contents($pidfile);
		if($unix->process_exists($oldpid)){die();}
		$mypid=getmypid();
		@file_put_contents($pidfile,$mypid);		
	}
	
	
	$currenttable="dansguardian_events_".date('Ymd');
	$next_table=date('Ymd')."_hour";
	_clients_hours_perfom($currenttable,$next_table);	
	
	table_days();
	$q=new mysql_squid_builder();
	
	
	$sql="SELECT DATE_FORMAT(zDate,'%Y%m%d') as suffix,tablename FROM tables_day WHERE Hour=0";
	$results=$q->QUERY_SQL($sql);
	if(!$q->ok){events_tail("$q->mysql_error");return;}
	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$next_table=$ligne["suffix"]."_hour";
		if(!$q->CreateHourTable($next_table)){events_tail("Failed to create $next_table");return;}
		if(!_clients_hours_perfom($ligne["tablename"],$next_table)){events_tail("Failed to process {$ligne["tablename"]} to $next_table");return;}
	}
}




function _clients_hours_perfom($tabledata,$nexttable){
$filter_hour=null;	
$filter_hour_1=null;
$filter_hour_2=null;
$GLOBALS["Q"]->CreateHourTable($nexttable);
$todaytable=date('Ymd')."_hour";
$CloseTable=true;
$output_rows=false;


if($nexttable==$todaytable){
	$filter_hour_1="AND HOUR < HOUR( NOW())";
	$CloseTable=false;
}

$sql="SELECT hour FROM $nexttable ORDER BY hour DESC LIMIT 0,1";
$ligne=mysql_fetch_array($GLOBALS["Q"]->QUERY_SQL($sql));
if(!is_numeric($ligne["hour"])){$ligne["hour"]=-1;}
events_tail("processing  $tabledata Last hour >{$ligne["hour"]}h");
$filter_hour_2=" AND HOUR>{$ligne["hour"]}";


events_tail("Processing $tabledata -> $nexttable  (today is $todaytable) filter:'$filter_hour_2' in line ".__LINE__);

$sql="SELECT SUM( QuerySize ) AS QuerySize, COUNT(zmd5) as hits,cached, HOUR( zDate ) AS HOUR , CLIENT, Country, uid, sitename,MAC
FROM $tabledata
GROUP BY cached, HOUR( zDate ) , CLIENT, Country, uid, sitename,MAC
HAVING QuerySize>0  $filter_hour_1$filter_hour_2";

$results=$GLOBALS["Q"]->QUERY_SQL($sql);
$num_rows=mysql_num_rows($results);
events_tail("Processing $tabledata -> $num_rows  rows in line ".__LINE__);
if($num_rows<10){$output_rows=true;}

if($num_rows==0){
	events_tail("$tabledata no rows...");
	if($CloseTable){
		events_tail("$tabledata -> Close table");
		$sql="UPDATE tables_day SET Hour=1 WHERE tablename='$tabledata'";
		$GLOBALS["Q"]->QUERY_SQL($sql);
	}
	return true;
}

$prefix="INSERT IGNORE INTO $nexttable (zMD5,sitename,client,hour,remote_ip,country,size,hits,uid,category,cached,familysite,MAC) VALUES ";
$prefix_visited="INSERT IGNORE INTO visited_sites (sitename,category,country,familysite) VALUES ";
$f=array();
while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
	$sitename=addslashes(trim(strtolower($ligne["sitename"])));
	$client=addslashes(trim(strtolower($ligne["CLIENT"])));
	$uid=addslashes(trim(strtolower($ligne["uid"])));
	$Country=addslashes(trim(strtolower($ligne["Country"])));
	if(!isset($GLOBALS["MEMORYSITES"][$sitename])){
		$category=$GLOBALS["Q"]->GET_CATEGORIES($sitename);
		$GLOBALS["MEMORYSITES"][$sitename]=$category;
	}else{
		$category=$GLOBALS["MEMORYSITES"][$sitename];
	}
	if(preg_match("#[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+#", $sitename)){
		$familysite="ipaddr";
	}else{
		$tt=explode(".",$sitename);
		$familysite=$tt[count($tt)-2].".".$tt[count($tt)-1];
	}
	
	
	$SQLSITESVS[]="('$sitename','$category','{$ligne["Country"]}','$familysite')";
	
	
	
	$md5=md5("{$ligne["sitename"]}{$ligne["CLIENT"]}{$ligne["HOUR"]}{$ligne["MAC"]}{$ligne["Country"]}{$ligne["uid"]}{$ligne["QuerySize"]}{$ligne["hits"]}{$ligne["cached"]}$category$Country");
	$sql_line="('$md5','$sitename','$client','{$ligne["HOUR"]}','$client','$Country','{$ligne["QuerySize"]}','{$ligne["hits"]}','$uid','$category','{$ligne["cached"]}',
	'$familysite','{$ligne["MAC"]}')";
	$f[]=$sql_line;
	
	if($output_rows){if($GLOBALS["VERBOSE"]){echo "$sql_line\n";}}	
	
	if(count($f)>500){
		$GLOBALS["Q"]->QUERY_SQL("$prefix" .@implode(",", $f));
		if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error}");return;}
		$f=array();
	}
	if(count($SQLSITESVS)>0){
		$GLOBALS["Q"]->QUERY_SQL($prefix_visited.@implode(",", $SQLSITESVS));
		$SQLSITESVS=array();
	}

}

if(count($f)>0){
	$GLOBALS["Q"]->QUERY_SQL("$prefix" .@implode(",", $f));
	events_tail("Processing ". count($f)." rows");
	if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error}");return;}
	
	if(count($SQLSITESVS)>0){
		events_tail("Processing ". count($SQLSITESVS)." visited sites");
		$GLOBALS["Q"]->QUERY_SQL($prefix_visited.@implode(",", $SQLSITESVS));
		if(!$GLOBALS["Q"]->ok){events_tail("Failed to process query to $next_table {$GLOBALS["Q"]->mysql_error} in line " .	__LINE__);}
	}
}
	return true;
}

function events_tail($text){
		$pid=@getmypid();
		$date=@date("h:i:s");
		$logFile="/var/log/artica-postfix/proxy-injector.debug";
		$size=@filesize($logFile);
		if($size>1000000){@unlink($logFile);}
		$f = @fopen($logFile, 'a');
		$GLOBALS["CLASS_UNIX"]->events(basename(__FILE__)." $date $text");
		if($GLOBALS["VERBOSE"]){echo "$date $text\n";}
		@fwrite($f, "$pid ".basename(__FILE__)." $date $text\n");
		@fclose($f);	
		}


function events($text){
		if($GLOBALS["VERBOSE"]){echo $text."\n";}
		$common="/var/log/artica-postfix/squid.stats.log";
		$size=@filesize($common);
		if($size>100000){@unlink($common);}
		$pid=getmypid();
		$date=date("Y-m-d H:i:s");
		$GLOBALS["CLASS_UNIX"]->events(basename(__FILE__)."$date $text");
		$h = @fopen($common, 'a');
		$sline="[$pid] $text";
		$line="$date [$pid] $text\n";
		@fwrite($h,$line);
		@fclose($h);
}

function squid_cache_perfs(){
	
$q=new mysql();
$sql="SELECT DATE_FORMAT(zDate,'%Y-%m-%d %H:00:00') as tdate FROM squid_cache_perfs
		WHERE zDate<DATE_SUB(NOW(),INTERVAL 1 HOUR) ORDER BY zDate DESC LIMIT 0,1";
		$ligne=mysql_fetch_array($q->QUERY_SQL($sql,"artica_events"));
		if(!$q->ok){echo "$sql\n$q->mysql_error\n";}
		$lastDate=$ligne["tdate"];
		if($lastDate<>null){$lastDate=" AND DATE_FORMAT( zDate, '%Y-%m-%d %H:00:00' )>'$lastDate' ";}
		if($GLOBALS["VERBOSE"]){echo "lastDate=$lastDate\n";} 	
	
	$dansguardian_events="dansguardian_events_".date('Ymd');
	
	$sql="SELECT SUM( QuerySize ) AS tsize, cached, DATE_FORMAT( zDate, '%Y-%m-%d %H:00:00' ) AS tdate
		FROM $dansguardian_events
		WHERE zDate < DATE_SUB( NOW( ) , INTERVAL 1 HOUR ) $lastDate
		GROUP BY cached, tdate";
	
	$results=$q->QUERY_SQL($sql,"squidlogs");
	if(!$q->ok){echo "$sql\n$q->mysql_error\n";}
	if(mysql_num_rows($results)==0){return;}

	$prefix="INSERT IGNORE INTO squid_cache_perfs(zmd5,zDate,size,cached) VALUES ";
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$zmd5=md5("{$ligne["tdate"]}{$ligne["cached"]}");
		$sqltext="('$zmd5','{$ligne["tdate"]}',{$ligne["tsize"]},{$ligne["cached"]})";
		
		$sqlT[]=$sqltext;
		if(count($sqlT)>100){
			$q->QUERY_SQL("$prefix".@implode(",", $sqlT),"artica_events");
			$sqlT=array();
		}
		
	}	
	
		if(count($sqlT)>0){
			$q->QUERY_SQL("$prefix".@implode(",", $sqlT),"artica_events");
			$sqlT=array();
		}
}


function GeoIP($servername){
	
	
	
	if(!function_exists("geoip_record_by_name")){
		if($GLOBALS["VERBOSE"]){echo "geoip_record_by_name no such function\n";}
		return array();
	}
	$site_IP=gethostbyname($servername);
	if($site_IP==null){events("GeoIP():: $site_IP is Null");return array();}
	
	
	if(!preg_match("#[0-9]+\.[0-9]+\.[0-9]+#",$site_IP)){
		events("GeoIP():: $site_IP ->gethostbyname()");
		$site_IP=gethostbyname($site_IP);
		events("GeoIP():: $site_IP");
	}
	
	if(isset($GLOBALS["COUNTRIES"][$site_IP])){
		if(trim($GLOBALS["COUNTRIES"][$site_IP])<>null){
			events("GeoIP():: $site_IP {$GLOBALS["COUNTRIES"][$site_IP]}/{$GLOBALS["CITIES"][$site_IP]}");
			if($GLOBALS["VERBOSE"]){echo "$site_IP:: MEM={$GLOBALS["COUNTRIES"][$site_IP]}\n";}
			return array($GLOBALS["COUNTRIES"][$site_IP],$GLOBALS["CITIES"][$site_IP]);
		}
	}
	
	$record = geoip_record_by_name($site_IP);
	if ($record) {
		$Country=$record["country_name"];
		$city=$record["city"];
		$GLOBALS["COUNTRIES"][$site_IP]=$Country;
		$GLOBALS["CITIES"][$site_IP]=$city;
		events("GeoIP():: $site_IP $Country/$city");
		return array($GLOBALS["COUNTRIES"][$site_IP],$GLOBALS["CITIES"][$site_IP]);
	}else{
		events("GeoIP():: $site_IP No record");
		if($GLOBALS["VERBOSE"]){echo "$site_IP:: No record\n";}
		return array();
	}
		
	return array();
}

function show_tables(){
	$q=new mysql_squid_builder();
	$q->EVENTS_SUM();
}

function table_days(){
	events_tail("Executed table_days in line ".__LINE__);
	$tables=$GLOBALS["Q"]->LIST_TABLES_QUERIES();
	if(count($tables)==0){events_tail("No working tables ? in line ".__LINE__);return;}
	$today=date('Y-m-d');
	events_tail(count($tables)." tables to scan in line ".__LINE__);
	
	while (list ($tablename, $date) = each ($tables) ){
		if($today==$date){events_tail("Skipping Today table $tablename in line ".__LINE__);continue;}
		$sql="SELECT zDate FROM tables_day WHERE tablename='$tablename'";
		$ligne=mysql_fetch_array($GLOBALS["Q"]->QUERY_SQL($sql));
		if($ligne["zDate"]==null){
				$ligne=mysql_fetch_array($GLOBALS["Q"]->QUERY_SQL("SELECT SUM(QuerySize) as tsize FROM $tablename WHERE cached=0"));
				$notcached=$ligne["tsize"];
				$ligne=mysql_fetch_array($GLOBALS["Q"]->QUERY_SQL("SELECT SUM(QuerySize) as tsize FROM $tablename WHERE cached=1"));
				$cached=$ligne["tsize"];
				if(!is_numeric($notcached)){$notcached=0;}
				if(!is_numeric($cached)){$cached=0;}
				$totalsize=$notcached+$cached;
				$cache_perfs=round(($cached/$totalsize)*100);
				$requests=$GLOBALS["Q"]->COUNT_ROWS($tablename);
				
				
				if($GLOBALS["VERBOSE"]){echo "$date cached = $cached , not cached =$notcached total=$totalsize perf=$cache_perfs% requests=$requests\n";}
				$GLOBALS["Q"]->QUERY_SQL("INSERT INTO tables_day (tablename,zDate,size,size_cached,totalsize,cache_perfs,requests) 
				VALUES('$tablename','$date','$notcached','$cached','$totalsize','$cache_perfs','$requests');");
				if(!$GLOBALS["Q"]->ok){events_tail("$q->mysql_error in line ".__LINE__);}
			}
		}
}

function members_month_delete(){
	$sql="SELECT DATE_FORMAT(zDate,'%Y%m') AS suffix,DATE_FORMAT(zDate,'%Y%m%d') AS suffix2,DAY(zDate) as tday,YEAR(zDate) AS tyear,month(zDate) AS tmonth FROM tables_day";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	if(!$GLOBALS["Q"]->ok){events_tail("{$GLOBALS["Q"]->mysql_error}\n------\n$sql\n----");return;}
		
	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		if($GLOBALS["Q"]->TABLE_EXISTS("{$ligne["suffix"]}_members")){
			echo "Delete table {$ligne["suffix"]}_members\n";
			$GLOBALS["Q"]->QUERY_SQL("DROP TABLE `{$ligne["suffix"]}_members`");
		}
		
		
	}	
	$GLOBALS["Q"]->QUERY_SQL("UPDATE tables_day SET month_members=0");
	
	
	
	
}

function visited_sites(){
	
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".". __FUNCTION__.".pid";
	$oldpid=@file_get_contents($pidfile);
	if($oldpid<100){$oldpid=null;}
	$unix=new unix();
	if($unix->process_exists($oldpid)){if($GLOBALS["VERBOSE"]){echo "Already executed pid $oldpid\n";}return;}
	$mypid=getmypid();
	@file_put_contents($pidfile,$mypid);
	$t1=time();	
	
	$sql="SELECT sitename,country,category FROM visited_sites";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	$num_rows = mysql_num_rows($results);
	if($num_rows==0){if($GLOBALS["VERBOSE"]){echo "No datas ". __FUNCTION__." ".__LINE__."\n";}return;}
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$country=null;
		$array=_visited_sites_calculate($ligne["sitename"]);
		if(!is_array($array)){continue;}
		if(trim($ligne["country"]==null)){$array_country=GeoIP($ligne["sitename"]);}
		if(isset($array_country)){if(isset($array_country[0])){$country=$array_country[0];}}
		if($country<>null){$country=",country='".addslashes($country)."'";;}
		if($GLOBALS["VERBOSE"]){echo "{$ligne["sitename"]} {$array[0]} hits, {$array[1]} size Country '$country' on {$array[2]} tables\n";}
		$categories=$GLOBALS["Q"]->GET_CATEGORIES($ligne["sitename"],true);
		$categories=addslashes($categories);
		$sql="UPDATE visited_sites SET HitsNumber='{$array[0]}',Querysize='{$array[1]}'$country,category='$categories' WHERE sitename='{$ligne["sitename"]}'";
		$GLOBALS["Q"]->QUERY_SQL($sql);
	}
		
	$took=$unix->distanceOfTimeInWords($t1,time());
	writelogs_squid("Scanned $num_rows visisted websites $took",__FUNCTION__,__FILE__,__LINE__);
	
}

function _visited_sites_calculate($sitename){
	
		if(!isset($GLOBALS["HOURS_TABLES"])){
			$sql="SELECT table_name as c FROM information_schema.tables WHERE table_schema = 'squidlogs' AND table_name LIKE '%_hour'";
			$results=$GLOBALS["Q"]->QUERY_SQL($sql);
			if(!$GLOBALS["Q"]->ok){writelogs("Fatal Error: $this->mysql_error",__CLASS__.'/'.__FUNCTION__,__FILE__,__LINE__);return array();}
			if($GLOBALS["VERBOSE"]){echo $sql." => ". mysql_num_rows($results)."\n";}
			while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
				$GLOBALS["HOURS_TABLES"][$ligne["c"]]=$ligne["c"];
			}
			
		}
			
		$size=0;
		$hits=0;
		reset($GLOBALS["HOURS_TABLES"]);
		while (list ($num, $table) = each ($GLOBALS["HOURS_TABLES"]) ){
			$ligne2=mysql_fetch_array($GLOBALS["Q"]->QUERY_SQL("SELECT SUM(size) AS tsize,SUM(hits) AS thits FROM $table WHERE sitename='$sitename'"));
			$size=$size+$ligne2["tsize"];
			$hits=$hits+$ligne2["thits"];
		}
		
		return array($hits,$size,count($GLOBALS["HOURS_TABLES"]));			
}
function ifMustBeExecuted(){
	$users=new usersMenus();
	$sock=new sockets();
	$update=true;
	if(!$users->SQUID_INSTALLED){$update=false;}
	$CategoriesRepositoryEnable=$sock->GET_INFO("CategoriesRepositoryEnable");
	$EnableWebProxyStatsAppliance=$sock->GET_INFO("EnableWebProxyStatsAppliance");
	if(!is_numeric($CategoriesRepositoryEnable)){$CategoriesRepositoryEnable=0;}
	if(!is_numeric($EnableWebProxyStatsAppliance)){$EnableWebProxyStatsAppliance=0;}
	if($CategoriesRepositoryEnable==1){$update=true;}
	if($EnableWebProxyStatsAppliance==1){$update=true;}
	return $update;
}

function export_last_websites(){
	$q=new mysql_squid_builder();
	$categories=$q->LIST_TABLES_CATEGORIES();
	$prefix="INSERT IGNORE INTO categorize (zmd5,pattern,zdate,uuid,category) VALUES ";
	while (list ($num, $table) = each ($categories) ){
		$sql="SELECT * FROM $table WHERE enabled=1 ORDER BY zDate DESC LIMIT 0,1000";
		$results=$GLOBALS["Q"]->QUERY_SQL($sql);
		while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
			if(trim($ligne["pattern"])==null){
				$q->QUERY_SQL("DELETE FROM $table WHERE zmd5='{$ligne["zmd5"]}'");
				writelogs_squid("{$ligne["zmd5"]} has no website.\nIt has been deleted from table $table",__FUNCTION__,__FILE__,__LINE__);
				continue;
			}
			
			
			$f[]="('{$ligne["zmd5"]}','{$ligne["pattern"]}','{$ligne["zDate"]}','{$ligne["uuid"]}','{$ligne["category"]}')";
			
		}
		
		if(count($f)>0){
			$q->QUERY_SQL($prefix.@implode(",", $f));
			$f=array();
		}
		
		
	}
	
	
	
}

function block_days(){
	$pidfile="/etc/artica-postfix/pids/".basename(__FILE__).".".__FUNCTION__.".pid";
	if($nopid){
		$oldpid=@file_get_contents($pidfile);
		$myfile=basename(__FILE__);
		$unix=new unix();
		if($unix->process_exists($oldpid,$myfile)){die();}
	}
	$mypid=getmypid();
	@file_put_contents($pidfile,$mypid);
	$GLOBALS["Q"]->CheckTables();
	$sql="SELECT zDate FROM `tables_day` WHERE blocks=0 AND zDate<DATE_SUB(NOW(),INTERVAL 1 DAY)";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$zDate=$ligne["zDate"];
		$date=$ligne["zDate"]." 00:00:00";
		$time=strtotime($date);
		$TableSource=date('Ymd',$time)."_blocked";
		$TableDest=date('Ym',$time)."_blocked_days";
		if(!$GLOBALS["Q"]->TABLE_EXISTS("$TableSource")){
			writelogs("Checking table $TableSource does not exists",__FUNCTION__,__FILE__,__LINE__);
			$GLOBALS["Q"]->QUERY_SQL("UPDATE tables_day SET blocks=1 WHERE zDate='$zDate'");
			continue;
		}
		writelogs("Checking table $TableSource for $zDate -> $TableDest",__FUNCTION__,__FILE__,__LINE__);
		if(block_days_perform($TableSource,$TableDest)){
			$sql="SELECT SUM(hits) as tcount FROM $TableDest WHERE zDate='$date'";
			$ligne2=mysql_fetch_array($GLOBALS["Q"]->QUERY_SQL($sql));
			$count=$ligne2["tcount"];
			$GLOBALS["Q"]->QUERY_SQL("UPDATE tables_day SET blocks=1,totalBlocked=$count WHERE zDate='$zDate'");			
		}
	}
	
	
	
}

function block_days_perform($TableSource,$TableDest){
	
	$sql="SELECT COUNT(ID) as hits, DATE_FORMAT(zDate,'%Y-%m-%d') as zDate,client,website,category,rulename,public_ip FROM $TableSource GROUP BY zDate,client,website,category,rulename,public_ip ORDER BY zDate";
	$prefix="INSERT IGNORE INTO $TableDest (zmd5,hits,zDate,client,website,category,rulename,public_ip) VALUES ";
	$results=$GLOBALS["Q"]->QUERY_SQL($sql);
	while($ligne=@mysql_fetch_array($results,MYSQL_ASSOC)){
		$tt=array();
		while (list ($a, $b) = each ($ligne) ){$tt[]=$b;}
		$zmd5=md5(@implode("", $tt));
		$f[]="('$zmd5','{$ligne["hits"]}','{$ligne["zDate"]}','{$ligne["client"]}','{$ligne["website"]}','{$ligne["category"]}','{$ligne["rulename"]}','{$ligne["public_ip"]}')";
		if(count($f)>500){
			$GLOBALS["Q"]->QUERY_SQL($prefix.@implode(",", $f));
			$f=array();
			if(!$GLOBALS["Q"]->ok){echo $GLOBALS["Q"]->mysql_error."\n";return;}
		}
	}
	
	if(count($f)>0){
			$GLOBALS["Q"]->QUERY_SQL($prefix.@implode(",", $f));
			$f=array();
			if(!$GLOBALS["Q"]->ok){echo $GLOBALS["Q"]->mysql_error."\n";return;}
		}	
	
return true;
	
}




?>