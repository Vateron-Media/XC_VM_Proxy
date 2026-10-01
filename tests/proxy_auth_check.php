<?php
// The panel's ProxyKeyTest vectors (XC_VM, tests/Unit/ProxyKeyTest.php): the
// proxy signs and verifies exactly as MAIN does. Run: php tests/proxy_auth_check.php
require __DIR__ . '/../src/includes/proxy_auth.php';

$rKey = str_repeat("\x02", 32);
$rNonce = str_repeat('0', 32);
$rOk = proxyAuthHeader($rKey, 7, 1800000000000, $rNonce, 'server_id=7&stats%5Bcpu%5D=1')
    === '1800000000000.00000000000000000000000000000000.af67cd2b8e51c1a449759f4fbf3aed523a75c26c203d9727fb230832529b42fd';
$rAnswer = '{"payload":"[{\"action\":\"flush\"}]","mac":"be3ab90d1b93347ab25ee8dc3d9704014194f5a11beecdf39bf2f2af795dbe70"}';
$rOk = $rOk && proxyOpenAnswer($rKey, 7, $rNonce, $rAnswer) === array(array('action' => 'flush'));
$rOk = $rOk && proxyOpenAnswer($rKey, 7, str_repeat('1', 32), $rAnswer) === null; // another request's answer
$rOk = $rOk && proxyOpenAnswer($rKey, 7, $rNonce, str_replace('flush', 'restart', $rAnswer)) === null; // altered
$rOk = $rOk && proxyOpenAnswer($rKey, 7, $rNonce, '[{"action":"flush"}]') === null; // unsigned
$rDir = sys_get_temp_dir() . '/proxy_key_check_' . getmypid();
file_put_contents($rDir, str_repeat('02', 32) . "\n");
$rOk = $rOk && proxyKey($rDir) === $rKey && proxyKey($rDir . '.missing') === null;
unlink($rDir);
echo $rOk ? "proxy auth: ok\n" : "proxy auth: FAILED\n";
exit($rOk ? 0 : 1);
