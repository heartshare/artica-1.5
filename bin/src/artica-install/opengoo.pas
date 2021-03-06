unit opengoo;

{$MODE DELPHI}
{$LONGSTRINGS ON}

interface

uses
    Classes, SysUtils,variants,strutils,IniFiles, Process,logs,unix,RegExpr in 'RegExpr.pas',zsystem,apache_artica,roundcube,obm2,lighttpd;

  type
  topengoo=class


private
     LOGS:Tlogs;
     SYS:TSystem;
     artica_path:string;
     ApacheGroupware:integer;
     apachebinary_path:string;
     apache:tapache_artica;
     ocswebservernameEnabled:integer;
     EnableApacheSystem:integer;
     NoEngoughMemory:boolean;
     mem:integer;
     function WriteJoomlaApacheConf(organization:string;organisation_conf:string):string;
     procedure WriteJoomlaFirstConfig(organization:string);
     procedure WriteJoomlaConfig(organization:string;key:string;value:string);
     function WriteSugarCRMApacheConf(organization:string;organisation_conf:string):string;
     procedure STOP_APACHE_SYSTEM();

public
    procedure   Free;
    constructor Create(const zSYS:Tsystem);
    function  VERSION():string;
    function  PID_NUM():string;
    procedure START();
    procedure STOP();
    function  STATUS():string;
    procedure WRITE_APACHE_CONFIG();
    function  APACHE_VERSION():string;
    function  PHP_VERSION():string;
    procedure WRITE_CONFIG();
    function  OPENGOO_VERSION():string;
    function  JOOMLA_VERSION():string;
    function  EACCELERATOR_VERSION():string;
    procedure RELOAD();
    function GetJoomlaVersion(path:string):string;
    procedure WritePhpConfig();
    function  CheckRoundCube():string;

END;

implementation

constructor topengoo.Create(const zSYS:Tsystem);
begin
       forcedirectories('/etc/artica-postfix');
       LOGS:=tlogs.Create();
       SYS:=zSYS;
       ApacheGroupware:=1;
       NoEngoughMemory:=false;
       EnableApacheSystem:=1;
       if not TryStrToInt(SYS.GET_INFO('ApacheGroupware'),ApacheGroupware) then ApacheGroupware:=1;
       if not TryStrToInt(SYS.GET_INFO('EnableApacheSystem'),EnableApacheSystem) then EnableApacheSystem:=1;


       apachebinary_path:=SYS.LOCATE_APACHE_BIN_PATH();
       apache:=tapache_artica.Create(SYS);
       mem:=SYS.MEM_TOTAL_INSTALLEE();
       if mem>10 then begin
       if mem<526300 then begin
            NoEngoughMemory:=true;
            ApacheGroupware:=0;
          end;
       end;
       if not DirectoryExists('/usr/share/artica-postfix') then begin
              artica_path:=ParamStr(0);
              artica_path:=ExtractFilePath(artica_path);
              artica_path:=AnsiReplaceText(artica_path,'/bin/','');

      end else begin
          artica_path:='/usr/share/artica-postfix';
      end;
end;
//##############################################################################
procedure topengoo.free();
begin
    FreeAndNil(logs);
end;
//##############################################################################
function topengoo.VERSION():string;
  var
   RegExpr:TRegExpr;
   l:TstringList;
   i:integer;
   path:string;
begin



     path:=apachebinary_path;
     if not FileExists(path) then begin
        logs.Debuglogs('tobm2.VERSION():: apache is not installed');
        exit;
     end;


   result:=SYS.GET_CACHE_VERSION('APP_OBM2');
   if length(result)>0 then exit;

if not FileExists('/usr/share/obm2/obminclude/global.inc') then begin
   logs.Debuglogs('Unable to stat /usr/share/obm2/obminclude/global.inc obm seems not be installed');
   exit;
end;
     l:=TstringList.Create;
     RegExpr:=TRegExpr.Create;
     l.LoadFromFile('/usr/share/obm2/obminclude/global.inc');
     RegExpr.Expression:='\$obm_version.+?([0-9\.]+)';
     for i:=0 to l.Count-1 do begin
         if RegExpr.Exec(l.Strings[i]) then begin
            result:=RegExpr.Match[1];
            break;
         end;
     end;
l.Free;
RegExpr.free;
SYS.SET_CACHE_VERSION('APP_OBM2',result);
logs.Debuglogs('APP_OBM2:: -> ' + result);
end;
//#############################################################################
function topengoo.APACHE_VERSION():string;
  var
   RegExpr:TRegExpr;
   tmpstr:string;
   l:TstringList;
   i:integer;
   path:string;
begin



     path:=apachebinary_path;
     if not FileExists(path) then begin
        logs.Debuglogs('topengoo.VERSION():: apache is not installed');
        exit;
     end;


   result:=SYS.GET_CACHE_VERSION('APP_GROUPWARE_APACHE');
   if length(result)>0 then exit;

   tmpstr:=LOGS.FILE_TEMP();
   fpsystem(path +' -v >'+tmpstr+' 2>&1');
   if not FileExists(tmpstr) then exit;
     l:=TstringList.Create;
     RegExpr:=TRegExpr.Create;
     l.LoadFromFile(tmpstr);
     RegExpr.Expression:='([0-9\.]+)';
     for i:=0 to l.Count-1 do begin
         if RegExpr.Exec(l.Strings[i]) then begin
            result:=RegExpr.Match[1];
            break;
         end;
     end;
l.Free;
RegExpr.free;
SYS.SET_CACHE_VERSION('APP_GROUPWARE_APACHE',result);
logs.Debuglogs('APP_GROUPWARE_APACHE:: -> ' + result);
end;
//#############################################################################
function topengoo.PHP_VERSION():string;
  var
   RegExpr:TRegExpr;
   tmpstr:string;
   l:TstringList;
   i:integer;
   path:string;
begin

     path:=SYS.LOCATE_PHP5_BIN();
     if not FileExists(path) then begin
        logs.Debuglogs('tobm2.VERSION():: PHP is not installed');
        exit;
     end;


   result:=SYS.GET_CACHE_VERSION('APP_GROUPWARE_PHP');
   if length(result)>0 then exit;

   tmpstr:=LOGS.FILE_TEMP();
   fpsystem(path +' -v >'+tmpstr+' 2>&1');
   if not FileExists(tmpstr) then exit;
     l:=TstringList.Create;
     RegExpr:=TRegExpr.Create;
     l.LoadFromFile(tmpstr);
     RegExpr.Expression:='([0-9\.]+)';
     for i:=0 to l.Count-1 do begin
         if RegExpr.Exec(l.Strings[i]) then begin
            result:=RegExpr.Match[1];
            break;
         end;
     end;
l.Free;
RegExpr.free;
SYS.SET_CACHE_VERSION('APP_GROUPWARE_PHP',result);
logs.Debuglogs('APP_GROUPWARE_PHP:: -> ' + result);
end;
//#############################################################################
procedure topengoo.START();
var
   pid:string;
   count:integer;
   LOCATE_MIME_TYPES:string;
begin

   if not FileExists(apachebinary_path) then begin
     logs.DebugLogs('Starting......: Apache (groupware mode) is not installed');
     exit;
   end;

   STOP_APACHE_SYSTEM();



if ApacheGroupware=0 then begin
   logs.DebugLogs('Starting......: Apache Groupware is disabled');
   STOP();
   exit;
end;

if NoEngoughMemory then begin
    if mem>0 then begin
    logs.DebugLogs('Starting......: Apache Need more than 526300 kB memory installed to run');
    logs.NOTIFICATION('Apache groupware was disabled','Apache Need more than 526300 Kb (currently '+intToStr(mem)+' kB) memory installed to run','system' );
    SYS.set_INFO('ApacheGroupware','0');
    STOP();
    exit;
   end;
end;


if SYS.isoverloadedTooMuch() then begin
   logs.DebugLogs('Starting......: System is overloaded');
   exit;
end;



  pid:=PID_NUM();

if SYS.PROCESS_EXIST(pid) then begin
     logs.DebugLogs('Starting......: Apache groupware already running using PID ' +pid+ '...');
     exit;
end;
    logs.DebugLogs('topengoo.START():: -> WRITE_APACHE_CONFIG() ');
    WRITE_APACHE_CONFIG();
    logs.DebugLogs('topengoo.START():: -> WritePhpConfig() ');
    WritePhpConfig();
    logs.DebugLogs('topengoo.START():: Write configs done..');
    logs.DebugLogs('topengoo.START():: exec() -> '+apachebinary_path+' -f /usr/local/apache-groupware/conf/apache-groupware.conf');


    //mimes.types !

    if not FileExists('/usr/local/apache-groupware/conf/mime.types') then begin
       LOCATE_MIME_TYPES:=SYS.LOCATE_MIME_TYPES;
       if not FileExists(LOCATE_MIME_TYPES) then begin
          logs.Debuglogs('Starting......: Apache groupware daemon fatal error while try to find mime.types');
          exit;
       end;
       logs.OutputCmd('/bin/cp '+LOCATE_MIME_TYPES+' /usr/local/apache-groupware/conf/mime.types');
    end;







    fpsystem(apachebinary_path+' -f /usr/local/apache-groupware/conf/apache-groupware.conf');

 count:=0;
 while not SYS.PROCESS_EXIST(PID_NUM()) do begin
              sleep(150);
              inc(count);
              if count>20 then begin
                 logs.DebugLogs('Starting......: Apache groupware daemon. (timeout!!!)');
                 break;
              end;
        end;



if  not SYS.PROCESS_EXIST(PID_NUM()) then begin
    logs.DebugLogs('Starting......: Apache groupware daemon failed');
    exit;
end;

logs.DebugLogs('Starting......: Apache groupware daemon success with new PID ' + PID_NUM());



end;
//##############################################################################
function topengoo.PID_NUM():string;
var pid:string;
begin
pid:=SYS.GET_PID_FROM_PATH('/var/run/apache-groupware/httpd.pid');
if length(pid)>0 then begin
   if SYS.PROCESS_EXIST(pid) then exit(pid);
end;
result:=SYS.PIDOF_PATTERN(apachebinary_path+' -f /usr/local/apache-groupware/conf/apache-groupware.conf');
end;
//##############################################################################
procedure topengoo.RELOAD();
var
pid:string;
APACHECTL:string;
begin
STOP_APACHE_SYSTEM();
pid:=PID_NUM();



if not SYS.PROCESS_EXIST(pid) then begin
     START();
     exit;
end;

WRITE_APACHE_CONFIG();
WritePhpConfig();

 pid:=PID_NUM();
if not SYS.PROCESS_EXIST(pid) then begin
     START();
     exit;
end;

APACHECTL:=SYS.LOCATE_APACHECTL();
if FileExists(APACHECTL) then begin
   logs.OutputCmd(SYS.LOCATE_APACHECTL() +' -f /usr/local/apache-groupware/conf/apache-groupware.conf -k restart');
   exit;
end;

STOP();
START();





end;

//##############################################################################
procedure topengoo.STOP_APACHE_SYSTEM();
begin
   if EnableApacheSystem=1 then exit;
   if FileExists(SYS.LOCATE_APACHE_INITD()) then fpsystem(SYS.LOCATE_APACHE_INITD()+' stop >/dev/null 2>&1');
end;
//##############################################################################
procedure topengoo.STOP();
var
   count,pidInt,i:integer;
   pid:string;
   pids:Tstringlist;
begin

    if not FileExists(apachebinary_path) then begin
    writeln('Stopping Apache groupware....: Not installed');
    exit;
    end;
    pid:=PID_NUM();
    if SYS.PROCESS_EXIST(pid) then begin
        writeln('Stopping Apache groupware....: ' +pid + ' PID..');
       fpsystem('/bin/kill '+ pid);
    end;

    if FileExists(SYS.LOCATE_APACHECTL()) then begin
       logs.OutputCmd(SYS.LOCATE_APACHECTL() +' -f /usr/local/apache-groupware/conf/apache-groupware.conf -k stop');
    end else begin
       writeln('Stopping Apache Daemon.......: failed to stat apachectl');
    end;
 pid:=PID_NUM();
 count:=0;
 while SYS.PROCESS_EXIST(pid) do begin
              sleep(150);
              inc(count);
              if count>20 then begin
                 writeln('Stopping Apache groupware....: ' + pid + ' PID.. (timeout)');
                 fpsystem('/bin/kill -9 ' + pid);
                 break;
              end;

              pid:=PID_NUM();
        end;

 count:=0;
 pids:=Tstringlist.Create;
 try
 pids.AddStrings(SYS.PIDOF_PATTERN_PROCESS_LIST(apachebinary_path+' -f /usr/local/apache-groupware/conf/apache-groupware.conf'));
 writeln('Stopping Apache groupware....: ',pids.Count,' childs');
  for i:=0 to pids.Count-1 do begin
              if TryStrToInt(pids.Strings[i],pidInt) then begin
                if pidInt>1 then begin
                   writeln('Stopping Apache groupware....: PID ',pidInt,' pid child');
                   fpsystem('/bin/kill -9 '+intTostr(pidInt));
                end;
              end;
        end;
 finally
 end;



if  not SYS.PROCESS_EXIST(pid) then begin
    writeln('Stopping Apache groupware....: success');
    fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.www.webdav.php --users');
    fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.joomla.install.php --vhosts');
    exit;
end;
    writeln('Stopping Apache groupware....: failed');
    STOP_APACHE_SYSTEM();

end;
//#############################################################################
procedure topengoo.WRITE_APACHE_CONFIG();
var
   l:Tstringlist;
   RegExpr:TRegExpr;
   ApacheGroupWarePort:string;
   joomla:string;
   rdcube:string;
   obm:tobm2;
   ocswebservername:string;
   lighttpd:tlighttpd;
   userfull:string;
   user:string;
   group:string;
   FreeWebsDisableSSLv2:integer;
begin
 joomla:='';
 FreeWebsDisableSSLv2:=0;
if not TryStrToInt(SYS.GET_INFO('FreeWebsDisableSSLv2'),FreeWebsDisableSSLv2) then FreeWebsDisableSSLv2:=0;
ApacheGroupWarePort:=SYS.GET_INFO('ApacheGroupWarePort');
if length(ApacheGroupWarePort)=0 then begin
   SYS.set_INFO('ApacheGroupWarePort','81');
   ApacheGroupWarePort:='81';
end;
l:=Tstringlist.Create;
l.Add('ServerRoot "/usr/local/apache-groupware"');
l.Add('Listen '+ApacheGroupWarePort);



l.Add('');
l.add(apache.SET_MODULES());
lighttpd:=Tlighttpd.Create(SYS);
lighttpd.free;
userfull:=lighttpd.LIGHTTPD_GET_USER();
RegExpr:=TRegExpr.Create;
RegExpr.Expression:='(.+?):(.+)';
RegExpr.Exec(userfull);
user:=RegExpr.Match[1];
group:=RegExpr.Match[2];

forceDirectories('/var/log/apache-groupware');
forceDirectories('/usr/share/artica-groupware');
forceDirectories('/usr/local/apache-groupware/logs');
ForceDirectories('/var/run/apache-groupware');


logs.OutputCmd('/bin/chown -R '+userfull+' /var/log/apache-groupware');
logs.OutputCmd('/bin/chown -R '+userfull+' /usr/local/apache-groupware/logs');
logs.OutputCmd('/bin/chown -R '+userfull+' /var/run/apache-groupware');

if DirectoryExists('/usr/share/artica-groupware') then logs.OutputCmd('/bin/chown -R '+userfull+' /usr/share/artica-groupware');

logs.DebugLogs('Starting......: Apache groupware using '+user+' user to run');


l.Add('User '+user);
l.Add('Group '+group);
l.Add('PidFile /var/run/apache-groupware/httpd.pid');
l.Add('<IfModule !mpm_netware_module>');
l.Add('          <IfModule !mpm_winnt_module>');
l.Add('             User '+user);
l.Add('             Group '+group);
l.Add('          </IfModule>');
l.Add('</IfModule>');
l.Add('');
l.Add('ServerAdmin you@example.com');
l.Add('ServerName ' + SYS.HOSTNAME_g());
l.Add('DocumentRoot "/usr/share/artica-groupware"');
l.Add('');


l.Add('');
l.Add('<Directory />');
l.Add('    Options FollowSymLinks');
l.Add('    AllowOverride None');
l.Add('    Order deny,allow');
l.Add('    Deny from all');
l.Add('</Directory>');
l.Add('');
l.Add('');
l.Add('<Directory "/usr/share/artica-groupware">');
l.Add('    DirectoryIndex index.php');
l.Add('    AddDefaultCharset ISO-8859-15');
l.Add('    Options Indexes FollowSymLinks');
l.Add('    AllowOverride None');
l.Add('    Order allow,deny');
l.Add('    Allow from all');
l.Add('');
l.Add('</Directory>');
l.Add('');
l.Add('<IfModule dir_module>');
l.Add('    DirectoryIndex index.php');
l.Add('</IfModule>');
l.Add('');
l.Add('');
l.Add('<FilesMatch "^\.ht">');
l.Add('    Order allow,deny');
l.Add('    Deny from all');
l.Add('    Satisfy All');
l.Add('</FilesMatch>');
l.Add('');
l.Add('');
l.Add('ErrorLog "/var/log/apache-groupware/error.log"');
l.Add('LogLevel info');
l.Add('LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-agent}i\"" combined');
l.Add('CustomLog /var/log/apache-groupware/acces.log combined');
l.Add('');
l.Add('<IfModule alias_module>');
l.Add('    ScriptAlias /cgi-bin/ "/usr/local/apache-groupware/data/cgi-bin/"');
l.Add('    Alias /images /usr/share/obm2/resources');
if DirectoryExists('/usr/share/obm2') then begin
   l.Add('    Alias /obm /usr/share/obm2/php');
end;

l.Add('');
l.Add('</IfModule>');
l.Add('');
l.Add('<IfModule cgid_module>');
l.Add('');
l.Add('</IfModule>');
l.Add('');
l.Add('');

if DirectoryExists('/usr/share/obm2') then begin
obm:=tobm2.Create(SYS);
obm.WRITE_CONFIG();
l.Add('<Directory "/usr/share/obm2/php">');
l.Add('    SetEnv OBM_INCLUDE_VAR obminclude');
l.Add('    DirectoryIndex obm.php');
l.Add('    AddDefaultCharset ISO-8859-15');
l.Add('    AllowOverride None');
l.Add('    Options None');
l.Add('    Order allow,deny');
l.Add('    Allow from all');
l.Add('</Directory>');

l.Add('<Directory "/usr/share/obm2/resources">');
l.Add('    AllowOverride None');
l.Add('    Options None');
l.Add('    Order allow,deny');
l.Add('    Allow from all');
l.Add('</Directory>');


end;



l.Add('<Directory "/usr/local/apache-groupware/data/cgi-bin">');
l.Add('    AllowOverride None');
l.Add('    Options None');
l.Add('    Order allow,deny');
l.Add('    Allow from all');
l.Add('</Directory>');
l.Add('');
l.Add('');
l.Add('DefaultType text/plain');
l.Add('');
l.Add('<IfModule mime_module>');
l.Add('   ');
l.Add('    TypesConfig conf/mime.types');
l.Add('    #AddType application/x-gzip .tgz');
l.Add('    AddType application/x-compress .Z');
l.Add('    AddType application/x-gzip .gz .tgz');
l.add('    AddType application/x-httpd-php .php .phtml');
l.Add('    #AddHandler cgi-script .cgi');
l.Add('    #AddHandler type-map var');
l.Add('    #AddType text/html .shtml');
l.Add('    #AddOutputFilter INCLUDES .shtml');
l.Add('</IfModule>');
l.Add('');
l.Add('<IfModule ssl_module>');
l.Add('SSLRandomSeed startup builtin');
l.Add('SSLRandomSeed connect builtin');
if FreeWebsDisableSSLv2=1 then begin
     logs.DebugLogs('Starting......: Apache groupware SSLv2 is disabled');
     l.add('SSLProtocol -ALL +SSLv3 +TLSv1');
     l.add('SSLCipherSuite ALL:!aNULL:!ADH:!eNULL:!LOW:!EXP:RC4+RSA:+HIGH:+MEDIUM');
end;
l.Add('</IfModule>');


rdcube:=CheckRoundCube();
ocswebservername:=SYS.GET_INFO('ocswebservername');
if length(ocswebservername)=0 then ocswebservername:='ocs.localhost.localdomain';

l.add('NameVirtualHost *:'+ApacheGroupWarePort);
if length(joomla)>0 then l.add(joomla);
if length(rdcube)>0 then l.add(rdcube);


logs.Debuglogs('Starting......: Apache groupware Check organization hosts');
fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.www.install.php --Wvhosts');
logs.Debuglogs('Starting......: Apache groupware Check webdav hosts');
fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.www.webdav.php --users');

logs.Debuglogs('Starting......: Apache groupware Check BackupPC hosts');
fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.www.backuppc.php --vhosts');

logs.Debuglogs('Starting......: Apache groupware Check Joomla hosts');
fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.joomla.install.php --vhosts');

L.Add(LOGS.ReadFromFile('/usr/local/apache-groupware/conf/vhosts'));

l.add('Include /usr/local/apache-groupware/conf/webdav-vhosts.conf');
l.add('Include /usr/local/apache-groupware/conf/backuppc-vhosts.conf');
l.add('Include /usr/local/apache-groupware/conf/joomla-vhosts.conf');
l.add('Include /usr/local/apache-groupware/conf/mirror-vhosts.conf');
l.add('# If you want create others vhost, file the file below');
l.add('Include /usr/local/apache-groupware/conf/others-vhosts.conf');


forceDirectories('/usr/local/apache-groupware/conf');
if not FileExists('/usr/local/apache-groupware/conf/others-vhosts.conf') then logs.WriteToFile('#','/usr/local/apache-groupware/conf/others-vhosts.conf');
if not FileExists('/usr/local/apache-groupware/conf/webdav-vhosts.conf') then logs.WriteToFile('#','/usr/local/apache-groupware/conf/webdav-vhosts.conf');
if not FileExists('/usr/local/apache-groupware/conf/backuppc-vhosts.conf') then logs.WriteToFile('#','/usr/local/apache-groupware/conf/backuppc-vhosts.conf');
if not FileExists('/usr/local/apache-groupware/conf/joomla-vhosts.conf') then logs.WriteToFile('#','/usr/local/apache-groupware/conf/joomla-vhosts.conf');
if not FileExists('/usr/local/apache-groupware/conf/mirror-vhosts.conf') then logs.WriteToFile('#','/usr/local/apache-groupware/conf/mirror-vhosts.conf');




forceDirectories('/usr/local/apache-groupware/php5/lib/php');
logs.Debuglogs('Starting......: Apache groupware daemon writing apache-groupware.conf');
logs.WriteToFile(l.Text,'/usr/local/apache-groupware/conf/apache-groupware.conf');

l.clear;
forceDirectories('/usr/local/apache-groupware/php5/lib');
WRITE_CONFIG();
RegExpr.free;
l.free;

end;
//#############################################################################
function topengoo.OPENGOO_VERSION():string;
 var
   RegExpr:TRegExpr;
   tmpstr:string;
   l:TstringList;
   i:integer;
   path:string;
begin

 result:=SYS.GET_CACHE_VERSION('APP_OPENGOO');
   if length(result)>0 then exit;

     path:='/usr/local/share/artica/opengoo/version.php';
     if not FileExists(path) then begin
        logs.Debuglogs('topengoo.OPENGOO_VERSION():: opengoo is not installed');
        exit;
     end;


  tmpstr:=path;
   if not FileExists(tmpstr) then exit;
     l:=TstringList.Create;
     RegExpr:=TRegExpr.Create;
     l.LoadFromFile(tmpstr);
     RegExpr.Expression:='return.+?([0-9\.a-z]+)';
     for i:=0 to l.Count-1 do begin
         if RegExpr.Exec(l.Strings[i]) then begin
            result:=RegExpr.Match[1];
            break;
         end;
     end;
l.Free;
RegExpr.free;
SYS.SET_CACHE_VERSION('APP_OPENGOO',result);
logs.Debuglogs('APP_OPENGOO:: -> ' + result);


end;
//#############################################################################
procedure topengoo.WRITE_CONFIG();
var
   l:TstringList;
   ApacheGroupWarePort:string;
begin

ApacheGroupWarePort:=SYS.GET_INFO('ApacheGroupWarePort');
if length(ApacheGroupWarePort)=0 then begin
   SYS.set_INFO('ApacheGroupWarePort','81');
   ApacheGroupWarePort:='81';
end;

   if not FileExists('/usr/share/artica-groupware/opengoo/version.php') then begin
      logs.DebugLogs('Starting......: opengoo is not installed');
      exit;
   end;
l:=Tstringlist.Create;
forceDirectories('/usr/share/artica-groupware/opengoo/config');
l.add('<?php');
l.add('define("DB_ADAPTER", "mysql"); ');
l.add('define("DB_HOST", "'+logs.MYSQL_INFOS('mysql_server')+':'+logs.MYSQL_INFOS('port') +'"); ');
l.add('define("DB_USER", "'+logs.MYSQL_INFOS('database_admin')+'"); ');
l.add('define("DB_PASS", "'+logs.MYSQL_INFOS('database_password')+'"); ');
l.add('define("DB_NAME", "opengoo"); ');
l.add('define("DB_PERSIST", true); ');
l.add('define("TABLE_PREFIX", "og_"); ');
l.add('define("DB_ENGINE", "InnoDB"); ');
l.add('define("ROOT_URL", "http://'+SYS.HOSTNAME_g()+':'+ApacheGroupWarePort+'/opengoo"); ');
l.add('define("DEFAULT_LOCALIZATION", "en_us"); ');
l.add('define("COOKIE_PATH", "/"); ');
l.add('define("DEBUG", false); ');
l.add('define("SEED", "51701f4d7211bfc91036be7d5ee4958f"); ');
l.add('define("DB_CHARSET", "utf8"); ');
l.add('return true;');
l.add('?>');
logs.WriteToFile(l.Text,'/usr/share/artica-groupware/opengoo/config/config.php');


 if not logs.IF_DATABASE_EXISTS('opengoo') then begin
       logs.DebugLogs('Starting......: opengoo database is not installed');
       logs.DebugLogs('Starting......: creating opengoo database');
       logs.EXECUTE_SQL_FILE('/usr/share/artica-postfix/bin/install/opengoo/opengoo.sql','opengoo');
 end;

 logs.OutputCmd('/bin/chmod 777 /usr/share/artica-groupware/opengoo/cache');
 logs.OutputCmd('/bin/chmod 777 /usr/share/artica-groupware/opengoo/upload');
 logs.OutputCmd('/bin/chmod 777 /usr/share/artica-groupware/opengoo/tmp');
 fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.opengoo.php --all >/dev/null 2>&1 &');
                                                                 
end;
//##############################################################################
function topengoo.STATUS();
var
pidpath:string;
begin
   pidpath:=logs.FILE_TEMP();
   fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.status.php --apache-groupware >'+pidpath +' 2>&1');
   result:=logs.ReadFromFile(pidpath);
   logs.DeleteFile(pidpath);

end;

//##############################################################################
function topengoo.CheckRoundCube():string;
var
   ff:TiniFIle;
   inifile:string;
   rcube:troundcube;
   webfolder:string;
   l:Tstringlist;
   i:integer;
   servername:string;
   ApacheGroupWarePort:string;
   t:tstringlist;
   ROTATELOGS:string;
begin

inifile:='/etc/artica-postfix/settings/Daemons/RoundCubeArticaConfigurationFile';
if not FileExists(inifile) then exit;
rcube:=Troundcube.Create(SYS);
webfolder:=rcube.web_folder();
if not DirectoryExists(webfolder) then exit;
ApacheGroupWarePort:=SYS.GET_INFO('ApacheGroupWarePort');
ROTATELOGS:=SYS.LOCATE_ROTATELOGS();

 ff:=TiniFile.Create(inifile);
 l:=Tstringlist.Create;
 ff.ReadSections(l);
 t:=tstringlist.Create;
 for i:=0 to l.Count-1 do begin
     servername:=trim(ff.ReadString(l.Strings[i],'servername',''));
     if length(servername)=0 then continue;
    logs.DebugLogs('Starting......: Apache groupware::Roundcube organization "'+l.Strings[i]+'"');
    t.Add('');
    t.add('<VirtualHost *:'+ApacheGroupWarePort+'>');
    t.add('ServerAdmin webmaster@'+SYS.HOSTNAME_g());
    t.add('DocumentRoot '+webfolder);
    t.add('ServerName '+servername);
    t.Add('   <Directory "'+webfolder+'">');
    t.Add('      DirectoryIndex index.php');
    t.Add('      AddDefaultCharset ISO-8859-15');
    t.Add('      Options Indexes FollowSymLinks MultiViews');
    t.Add('      AllowOverride all');
    t.Add('      Order allow,deny');
    t.Add('      Allow from all');
    t.Add('  </Directory>');

    if FileExists(ROTATELOGS) then begin
       t.add('CustomLog "|'+ROTATELOGS+' /usr/local/apache-groupware/logs/'+servername+' 86400" "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %V"');
    end else begin
        t.add('CustomLog /usr/local/apache-groupware/logs/'+servername+' combinedv');
    end;
    t.add('ErrorLog /usr/local/apache-groupware/logs/'+servername+'_err.log');
    t.add('</VirtualHost>');
    t.Add('');
end;
if t.Count>0 then result:=t.Text;
l.Free;
end;


function topengoo.GetJoomlaVersion(path:string):string;
var
l:Tstringlist;
RegExpr:TRegExpr;
i:integer;

release:string;
build:string;
begin
   if not FileExists(path) then exit;

   l:=TstringList.Create;
   l.LoadFromFile(path);
   RegExpr:=TRegExpr.Create;

   for i:=0 to l.Count-1 do begin
       RegExpr.Expression:='var\s+\$RELEASE.+?([0-9\.]+)';
       if RegExpr.Exec(l.Strings[i]) then release:=RegExpr.Match[1];
       RegExpr.Expression:='var\s+\$DEV_LEVEL.+?([0-9\.]+)';
       if RegExpr.Exec(l.Strings[i]) then build:=RegExpr.Match[1];
       if length(build)>0 then begin
          if length(release)>0 then begin
          result:=release+'.'+build;
          break;
       end;
       end;

   end;

   l.free;
   RegExpr.free;


end;
//##############################################################################
function topengoo.JOOMLA_VERSION():string;
begin
if not FileExists('/usr/local/share/artica/joomla_src/libraries/joomla/version.php') then exit;
result:=SYS.GET_CACHE_VERSION('APP_JOOMLA');
if length(result)>0 then exit;
result:=GetJoomlaVersion('/usr/local/share/artica/joomla_src/libraries/joomla/version.php');

SYS.SET_CACHE_VERSION('APP_JOOMLA',result);
logs.Debuglogs('APP_JOOMLA:: -> ' + result);
end;
//##############################################################################
function topengoo.EACCELERATOR_VERSION():string;
var
l:Tstringlist;
RegExpr:TRegExpr;
i:integer;
release:string;
begin
     result:=SYS.GET_CACHE_VERSION('APP_EACCELERATOR');
     release:=logs.FILE_TEMP();

   fpsystem(SYS.LOCATE_PHP5_BIN()+' /usr/share/artica-postfix/exec.eaccelerator.php >'+release+' 2>&1');
   l:=TstringList.Create;
   if not FileExists(release) then exit;
  try
    l.LoadFromFile(release);
   RegExpr:=TRegExpr.Create;
   RegExpr.Expression:='([0-9\.]+)';
   for i:=0 to l.Count-1 do begin
       if RegExpr.Exec(l.Strings[i]) then begin
          result:=RegExpr.Match[1];
          break;
       end;

   end;

  finally

  end;



   l.free;
   RegExpr.free;
   SYS.SET_CACHE_VERSION('APP_EACCELERATOR',result);


end;
//##############################################################################

function topengoo.WriteJoomlaApacheConf(organization:string;organisation_conf:string):string;
var
l:Tstringlist;
ini:TiniFile;
org_conf:string;
joomlaservername:string;
ApacheGroupWarePort:string;
ROTATELOGS:string;
begin

ApacheGroupWarePort:=SYS.GET_INFO('ApacheGroupWarePort');
if length(ApacheGroupWarePort)=0 then begin
   SYS.set_INFO('ApacheGroupWarePort','81');
   ApacheGroupWarePort:='81';
end;
org_conf:='/etc/artica-postfix/settings/Daemons/'+organisation_conf;
if not FileExists(org_conf) then begin
   logs.DebugLogs('Starting......: Apache groupware "'+organization+'" unable to stat '+org_conf);
   exit;
end;

ini:=TiniFile.Create(org_conf);
joomlaservername:=ini.ReadString('CONF','joomlaservername','');
if length(trim(joomlaservername))=0 then begin
    logs.DebugLogs('Starting......: Apache groupware "'+organization+'" unable to define server name');
    exit;
end;
ROTATELOGS:=SYS.LOCATE_ROTATELOGS();
l:=Tstringlist.Create;

l.Add('');
l.add('<VirtualHost *:'+ApacheGroupWarePort+'>');
l.add('ServerAdmin webmaster@'+SYS.HOSTNAME_g());
l.add('DocumentRoot /usr/share/artica-groupware/domains/joomla/'+organization);
l.add('ServerName '+joomlaservername);

l.Add('   <Directory "/usr/share/artica-groupware/domains/joomla/'+organization+'">');
l.Add('      DirectoryIndex index.php');
l.Add('      AddDefaultCharset ISO-8859-15');
l.Add('      Options Indexes FollowSymLinks MultiViews');
l.Add('      AllowOverride all');
l.Add('      Order allow,deny');
l.Add('      Allow from all');
l.Add('  </Directory>');

if FileExists(ROTATELOGS) then begin
   l.add('CustomLog "|'+ROTATELOGS+' /usr/local/apache-groupware/logs/'+joomlaservername+' 86400" "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %V"');
end else begin
   l.add('CustomLog /usr/local/apache-groupware/logs/'+joomlaservername+' combinedv');
end;
l.add('ErrorLog /usr/local/apache-groupware/logs/'+joomlaservername+'_err.log');
l.add('</VirtualHost>');

result:=l.Text;
l.free;
end;
//##############################################################################
function topengoo.WriteSugarCRMApacheConf(organization:string;organisation_conf:string):string;
var
l:Tstringlist;
ini:TiniFile;
org_conf:string;
sugar_servername:string;
ApacheGroupWarePort:string;
ROTATELOGS:String;
begin

ApacheGroupWarePort:=SYS.GET_INFO('ApacheGroupWarePort');
if length(ApacheGroupWarePort)=0 then begin
   SYS.set_INFO('ApacheGroupWarePort','81');
   ApacheGroupWarePort:='81';
end;
org_conf:='/etc/artica-postfix/settings/Daemons/'+organisation_conf;
if not FileExists(org_conf) then begin
   logs.DebugLogs('Starting......: Apache groupware "'+organization+'" unable to stat '+org_conf);
   exit;
end;

ini:=TiniFile.Create(org_conf);
sugar_servername:=ini.ReadString('CONF','sugarservername','');
if length(trim(sugar_servername))=0 then begin
    logs.DebugLogs('Starting......: Apache groupware "'+organization+'" server name is not define for SugarCRM');
    exit;
end;

ROTATELOGS:=SYS.LOCATE_ROTATELOGS();
l:=Tstringlist.Create;

l.Add('');
l.add('<VirtualHost *:'+ApacheGroupWarePort+'>');
l.add('ServerAdmin webmaster@'+SYS.HOSTNAME_g());
l.add('DocumentRoot /usr/share/artica-groupware/domains/sugarcrm/'+organization);
l.add('ServerName '+sugar_servername);

l.Add('   <Directory "/usr/share/artica-groupware/domains/sugarcrm/'+organization+'">');
l.Add('      DirectoryIndex index.php');
l.Add('      AddDefaultCharset ISO-8859-15');
l.Add('      Options Indexes FollowSymLinks MultiViews');
l.Add('      AllowOverride all');
l.Add('      Order allow,deny');
l.Add('      Allow from all');
l.Add('  </Directory>');

if FileExists(ROTATELOGS) then begin
   l.add('CustomLog "|'+ROTATELOGS+' /usr/local/apache-groupware/logs/'+sugar_servername+' 86400" "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %V"');
end else begin
   l.add('CustomLog /usr/local/apache-groupware/logs/'+sugar_servername+' combinedv');
end;
l.add('ErrorLog /usr/local/apache-groupware/logs/'+sugar_servername+'_err.log');
l.add('</VirtualHost>');

result:=l.Text;
l.free;
end;
//##############################################################################
procedure topengoo.WriteJoomlaConfig(organization:string;key:string;value:string);
var
l:Tstringlist;
RegExpr:TRegExpr;
conf:string;
i:integer;
xwrite:boolean;
begin
conf:='/usr/share/artica-groupware/domains/joomla/'+organization+'/configuration.php';
if not FileExists(conf) then exit;
l:=Tstringlist.Create;
l.LoadFromFile(conf);
RegExpr:=tRegExpr.Create;
RegExpr.Expression:='^var\s+\$'+key+'[\s=]+';
 xwrite:=false;
for i:=0 to l.Count-1 do begin
     if RegExpr.Exec(l.Strings[i]) then begin
        l.Strings[i]:='var $'+key+' = '''+value+''';';
        xwrite:=true;
        break;
     end;
end;

if xwrite then logs.WriteToFile(l.Text,conf);
   l.Free;
   RegExpr.free;

end;
//##############################################################################
procedure topengoo.WritePhpConfig();
var
   lighttpd:Tlighttpd;
begin
if not FileExists('/usr/local/apache-groupware/php5/lib/php.ini') then begin
   logs.Debuglogs('Apache groupware unable to stat /usr/local/apache-groupware/php5/lib/php.ini');

end;
forcedirectories('/usr/local/apache-groupware/php5/sessions');
logs.OutputCmd('/bin/chmod 755 /usr/local/apache-groupware/php5/sessions');
logs.OutputCmd('/bin/chown -R www-data:www-data /usr/local/apache-groupware/php5/sessions');
lighttpd:=Tlighttpd.Create(SYS);
lighttpd.LIGHTTPD_ADD_INCLUDE_PATH();
end;
//##############################################################################




procedure topengoo.WriteJoomlaFirstConfig(organization:string);
var
l:Tstringlist;
root,password,port,server,mailserver:string;
begin
  if FileExists('/usr/share/artica-groupware/domains/joomla/'+organization+'/configuration.php') then exit;
  root    :=logs.MYSQL_INFOS('database_admin');
  password:=logs.MYSQL_INFOS('database_password');
  port    :=logs.MYSQL_INFOS('port');
  server  :=logs.MYSQL_INFOS('mysql_server');
  mailserver:=SYS.HOSTNAME_g();
  if length(server)=0 then server:='127.0.0.1';
  if length(port)=0 then port:='3306';

l:=Tstringlist.Create;
l.add('<?php');
l.add('class JConfig {');
l.add('/* Site Settings */');
l.add('var $offline = ''0'';');
l.add('var $offline_message = ''offline message'';');
l.add('var $sitename = ''Joomla First web site'';');
l.add('var $editor = ''tinymce'';');
l.add('var $list_limit = ''20'';');
l.add('var $legacy = ''0'';');
l.add('/* Debug Settings */');
l.add('var $debug = ''0'';');
l.add('var $debug_lang = ''0'';');
l.add('/* Database Settings */');
l.add('var $dbtype = ''mysql'';');
l.add('var $host = '''+server+':'+port+''';');
l.add('var $user = '''+root+''';');
l.add('var $password = '''+password+''';');
l.add('var $db = ''joomla_'+organization+''';');
l.add('var $dbprefix = ''jos_'';');
l.add('/* Server Settings */');
l.add('var $live_site = '''';');
l.add('var $secret = ''bHAc5Mac9lWxp3vC'';');
l.add('var $gzip = ''0'';');
l.add('var $error_reporting = ''-1'';');
l.add('var $helpurl = ''http://help.joomla.org'';');
l.add('var $xmlrpc_server = ''0'';');
l.add('var $ftp_host = ''127.0.0.1'';');
l.add('var $ftp_port = ''21'';');
l.add('var $ftp_user = '''';');
l.add('var $ftp_pass = '''';');
l.add('var $ftp_root = '''';');
l.add('var $ftp_enable = ''0'';');
l.add('var $force_ssl = ''0'';');
l.add('/* Locale Settings */');
l.add('var $offset = ''0'';');
l.add('var $offset_user = ''0'';');
l.add('/* Mail Settings */');
l.add('var $mailer = ''mail'';');
l.add('var $mailfrom = ''root@'+mailserver+''';');
l.add('var $fromname = ''Joomla First web site'';');
l.add('var $sendmail = ''/usr/sbin/sendmail'';');
l.add('var $smtpauth = ''0'';');
l.add('var $smtpsecure = ''none'';');
l.add('var $smtpport = ''25'';');
l.add('var $smtpuser = '''';');
l.add('var $smtppass = '''';');
l.add('var $smtphost = ''localhost'';');
l.add('/* Cache Settings */');
l.add('var $caching = ''0'';');
l.add('var $cachetime = ''15'';');
l.add('var $cache_handler = ''file'';');
l.add('/* Meta Settings */');
l.add('var $MetaDesc = ''Joomla'';');
l.add('var $MetaKeys = ''joomla, Joomla'';');
l.add('var $MetaTitle = ''1'';');
l.add('var $MetaAuthor = ''1'';');
l.add('/* SEO Settings */');
l.add('var $sef           = ''0'';');
l.add('var $sef_rewrite   = ''0'';');
l.add('var $sef_suffix    = ''0'';');
l.add('/* Feed Settings */');
l.add('var $feed_limit   = 10;');
l.add('var $feed_email   = ''author'';');
l.add('var $log_path = ''/usr/share/artica-groupware/domains/joomla/'+organization+'/logs'';');
l.add('var $tmp_path = ''/usr/share/artica-groupware/domains/joomla/'+organization+'/tmp'';');
l.add('/* Session Setting */');
l.add('var $lifetime = ''15'';');
l.add('var $session_handler = ''database'';');
l.add('}');
l.add('?>');

logs.WriteToFile(l.Text,'/usr/share/artica-groupware/domains/joomla/'+organization+'/configuration.php');

end;



end.

