<?php
if (posix_getpwuid(posix_geteuid())['name'] <> "root") {
    exit("Please run as root!\n");
}
$rCronjob = "* * * * * /home/xc_vm/bin/php/bin/php /home/xc_vm/crons/callback.php # XC_VM Proxy\n";
$rTempName = tempnam("/tmp", "crontab");
file_put_contents($rTempName, $rCronjob);
shell_exec("sudo crontab -r");
shell_exec("sudo crontab -u root \"" . $rTempName . "\"");
@unlink($rTempName);
