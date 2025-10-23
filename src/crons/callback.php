<?php
if (posix_getpwuid(posix_geteuid())['name'] <> "root") {
    exit("Please run as root!\n");
}
if (!@$argc) {
    exit(0);
}

cli_set_process_title('XC_VM[Stats]');
set_time_limit(60);
ini_set('max_execution_time', 60);
define('MAIN_HOME', '/home/xc_vm/');

function getTotalCPU() {
    $rCPU = 0;
    exec('ps -Ao pid,pcpu', $rProcesses);
    foreach ($rProcesses as $rProcess) {
        $rCols = explode(' ', preg_replace('!\s+!', ' ', trim($rProcess)));
        $rCPU += floatval($rCols[1]);
    }
    $rCPUUsage = $rCPU / intval(shell_exec("grep -P '^processor' /proc/cpuinfo|wc -l"));
    return $rCPUUsage;
}

function secondsToTime($rSeconds) {
    $rDays = (int)floor($rSeconds / 86400);
    $rHourSeconds = $rSeconds % 86400;
    $rHours = (int)floor($rHourSeconds / 3600);
    $rMinuteSeconds = $rHourSeconds % 3600;
    $rMinutes = (int)floor($rMinuteSeconds / 60);
    $rRemaining = $rMinuteSeconds % 60;
    $rSeconds = (int)ceil($rRemaining);
    $rReturn = "";
    if ($rDays != 0) {
        $rReturn .= "{$rDays}d ";
    }
    if ($rHours != 0) {
        $rReturn .= "{$rHours}h ";
    }
    if ($rMinutes != 0) {
        $rReturn .= "{$rMinutes}m ";
    }
    $rReturn .= "{$rSeconds}s";
    return $rReturn;
}

function getUptime() {
    if (file_exists('/proc/uptime') and is_readable('/proc/uptime')) {
        return secondsToTime(intval(explode(' ', file_get_contents('/proc/uptime'))[0]));
    }
    return '';
}

function getNetworkInterfaces() {
    $rReturn = array();
    exec("ls /sys/class/net/", $rOutput, $rReturnVar);
    foreach ($rOutput as $rInterface) {
        $rInterface = trim(rtrim($rInterface, ":"));
        if ($rInterface <> "lo") {
            $rReturn[] = $rInterface;
        }
    }
    return $rReturn;
}

function blockIP($rIP) {
    if (filter_var($rIP, FILTER_VALIDATE_IP)) {
        exec("sudo iptables -A INPUT -s " . escapeshellarg($rIP) . " -j DROP");
    }
}

function unblockIP($rIP) {
    if (filter_var($rIP, FILTER_VALIDATE_IP)) {
        exec("sudo iptables -D INPUT -s " . escapeshellarg($rIP) . " -j DROP");
    }
}

function flushIPs() {
    exec("sudo iptables -F");
}

function saveIPTables() {
    exec("sudo iptables-save && sudo ip6tables-save");
}

function getStats() {
    $rJSON = array();
    $rJSON['cpu'] = round(getTotalCPU(), 2);
    $rJSON['cpu_cores'] = intval(shell_exec("cat /proc/cpuinfo | grep \"^processor\" | wc -l"));
    $rJSON['cpu_avg'] = round(sys_getloadavg()[0] * 100 / $rJSON['cpu_cores'], 2);
    $rJSON['cpu_name'] = trim(shell_exec("cat /proc/cpuinfo | grep 'model name' | uniq | awk -F: '{print $2}'"));
    if ($rJSON['cpu_avg'] > 100) $rJSON['cpu_avg'] = 100;
    $rFree = explode("\n", trim(shell_exec('free')));
    $rMemory = preg_split("/[\s]+/", $rFree[1]);
    $rTotalUsed = intval($rMemory[2]);
    $rTotalRAM = intval($rMemory[1]);
    $rJSON['total_mem'] = $rTotalRAM;
    $rJSON['total_mem_free'] = $rTotalRAM - $rTotalUsed;
    $rJSON['total_mem_used'] = $rTotalUsed;
    $rJSON['total_mem_used_percent'] = round($rJSON['total_mem_used'] / $rJSON['total_mem'] * 100, 2);
    $rJSON['total_disk_space'] = disk_total_space(MAIN_HOME);
    $rJSON['kernel'] = trim(shell_exec("uname -r"));
    $rJSON['uptime'] = getUptime();
    $rJSON['total_running_streams'] = 0;
    $rJSON['bytes_sent'] = 0;
    $rJSON['bytes_received'] = 0;
    $rJSON['bytes_sent_total'] = 0;
    $rJSON['bytes_received_total'] = 0;
    $rJSON['network_speed'] = 0;
    $rJSON['interfaces'] = getNetworkInterfaces();
    foreach ($rJSON["interfaces"] as $rInterface) {
        if (file_exists("/sys/class/net/$rInterface/statistics/tx_bytes")) {
            $rJSON['network_speed'] = file_get_contents("/sys/class/net/$rInterface/speed");
            $rBytesSentOld = trim(file_get_contents("/sys/class/net/$rInterface/statistics/tx_bytes"));
            $rBytesReceivedOld = trim(file_get_contents("/sys/class/net/$rInterface/statistics/rx_bytes"));
            sleep(1);
            $rBytesSentNew = trim(file_get_contents("/sys/class/net/$rInterface/statistics/tx_bytes"));
            $rBytesReceivedNew = trim(file_get_contents("/sys/class/net/$rInterface/statistics/rx_bytes"));
            $rTotalBytesSent = $rBytesSentNew - $rBytesSentOld;
            $rTotalBytesReceived = $rBytesReceivedNew - $rBytesReceivedOld;
            $rJSON['bytes_sent'] += $rTotalBytesSent;
            $rJSON['bytes_received'] += $rTotalBytesReceived;
            $rJSON['bytes_sent_total'] += $rBytesSentNew;
            $rJSON['bytes_received_total'] += $rBytesReceivedNew;
        }
    }
    $rJSON['iostat_info'] = $rJSON['gpu_info'] = $rJSON['video_devices'] = $rJSON['audio_devices'] = array();
    $rJSON['cpu_load_average'] = sys_getloadavg()[0];
    return $rJSON;
}

$rConfig = parse_ini_file(MAIN_HOME . "config/config.ini");
$rStats = getStats();
$rAddresses = array_values(array_unique(array_map("trim", explode("\n", shell_exec("ip -4 addr | grep -oP '(?<=inet\s)\d+(\.\d+){3}'")))));
$rData = array("stats" => $rStats, "addresses" => $rAddresses, "server_id" => intval($rConfig["server_id"]));
$rURL = "http://" . $rConfig["hostname"] . ":" . $rConfig["port"] . "/admin/proxy_api";

$rCurl = curl_init();
curl_setopt($rCurl, CURLOPT_URL, $rURL);
curl_setopt($rCurl, CURLOPT_POST, 1);
curl_setopt($rCurl, CURLOPT_POSTFIELDS, http_build_query($rData));
curl_setopt($rCurl, CURLOPT_RETURNTRANSFER, true);
$rSignals = json_decode(curl_exec($rCurl), True);
curl_close($rCurl);

$rSaveIPTables = false;
foreach ($rSignals as $rSignal) {
    switch ($rSignal["action"]) {
        case 'restart':
            echo "Rebooting system...\n";
            shell_exec("sudo reboot");
            break;

        case "block_ip":
            echo "Blocking IP " . $rSignal["ip"] . "...\n";
            blockIP($rSignal["ip"]);
            $rSaveIPTables = true;
            break;

        case "unblock_ip":
            echo "Unblocking IP " . $rSignal["ip"] . "...\n";
            unblockIP($rSignal["ip"]);
            $rSaveIPTables = true;
            break;

        case "flush":
            echo "Flushing IP's...";
            flushIPs();
            $rSaveIPTables = true;
            break;

        case "restart_services":
            echo "Restarting services...\n";
            shell_exec("sudo systemctl restart xc_vm");
            break;

        case "stop_services":
            echo "Stopping services...\n";
            shell_exec("sudo systemctl stop xc_vm");
            break;

        case "reload_nginx":
            echo "Reloading nginx...\n";
            shell_exec("sudo -u xc_vm " . MAIN_HOME . "bin/nginx/sbin/nginx -s reload");
            break;
    }
}
if ($rSaveIPTables) {
    saveIPTables();
}
