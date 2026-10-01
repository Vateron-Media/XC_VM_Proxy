<?php
/**
 * The proxy's side of its signed channel with MAIN (the panel's ProxyKey, D8).
 *
 * The panel's install writes the key to config/proxy.key (hex, 0600 root).
 * Each callback then carries X-XCVM-Proxy-Auth: <ts ms>.<nonce>.<mac>, and
 * MAIN answers {"payload": <signals as JSON>, "mac": ...} bound to that nonce.
 * Signals from an answer that does not verify are never run.
 */

/** The installed key (32 bytes), or null when there is none (unsigned, as before). */
function proxyKey(string $rPath): ?string {
    $rHex = trim((string) @file_get_contents($rPath));
    return preg_match('/^[0-9a-f]{64}$/', $rHex) ? hex2bin($rHex) : null;
}

/** The request header for $rBody. */
function proxyAuthHeader(string $rKey, int $rServerID, int $rTsMs, string $rNonceHex, string $rBody): string {
    $rMac = hash_hmac('sha256', 'xcvm proxy req v1|' . $rServerID . '|' . $rTsMs . '|' . $rNonceHex . '|' . hash('sha256', $rBody), $rKey);
    return $rTsMs . '.' . $rNonceHex . '.' . $rMac;
}

/** The signals of MAIN's answer to the request with $rNonceHex, or null when it does not verify. */
function proxyOpenAnswer(string $rKey, int $rServerID, string $rNonceHex, string $rRaw): ?array {
    $rAnswer = json_decode($rRaw, true);
    if (!is_array($rAnswer) || !is_string($rAnswer['payload'] ?? null) || !is_string($rAnswer['mac'] ?? null)) {
        return null;
    }
    $rMac = hash_hmac('sha256', 'xcvm proxy resp v1|' . $rServerID . '|' . $rNonceHex . '|' . $rAnswer['payload'], $rKey);
    if (!hash_equals($rMac, $rAnswer['mac'])) {
        return null;
    }
    $rSignals = json_decode($rAnswer['payload'], true);
    return is_array($rSignals) ? $rSignals : null;
}
