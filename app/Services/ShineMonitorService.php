<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShineMonitorService
{
    protected const BASE_URL = 'http://api.shinemonitor.com/public/';

    // TP-Link manufacturer identification assigned by ShineMonitor
    // (3rd character is uppercase "I", not lowercase "l")
    protected const COMPANY_KEY = 'PgIs6rSm5b7GnpXk';
    protected const SOURCE = '0';
    protected const APP_ID = 'com.demo.test';
    protected const APP_VERSION = '3.6.2.1';
    protected const APP_CLIENT = 'android';

    /**
     * Register a Plant Owner account (action=reg).
     * Returns the decoded JSON response: err=0 means success.
     */
    public function registerOwner(string $usr, string $password, string $sn, ?string $mobile = null, ?string $email = null): array
    {
        $response = Http::withOptions(['verify' => false])
            ->timeout(30)
            ->get($this->buildRegUrl($usr, $password, $sn, $mobile, $email));

        Log::channel('registration')->info('ShineMonitor reg response', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'usr'    => $usr,
        ]);

        return $response->json() ?? [];
    }

    /**
     * Build the signed action=reg URL.
     * pwd = RC4(SHA1(sn), SHA1(password)) as lowercase hex.
     * sign = SHA1(salt + SHA1(password) + actionPart).
     */
    public function buildRegUrl(string $usr, string $password, string $sn, ?string $mobile = null, ?string $email = null): string
    {
        $pwdSha1 = sha1($password);
        $pnSha1 = sha1($sn);
        $encPwd = strtolower(bin2hex($this->rc4($pnSha1, $pwdSha1)));

        $action = '&action=reg'
            . '&usr=' . rawurlencode($usr)
            . '&pwd=' . $encPwd;

        if (!empty($mobile)) {
            $action .= '&mobile=' . rawurlencode($mobile);
        }
        if (!empty($email)) {
            $action .= '&email=' . rawurlencode($email);
        }

        $action .= '&sn=' . rawurlencode($sn) . $this->appParams();

        $salt = $this->salt();
        $sign = sha1($salt . $pwdSha1 . $action);

        return self::BASE_URL . '?sign=' . $sign . '&salt=' . $salt . $action;
    }

    /**
     * Authenticate (action=auth). Returns decoded JSON with dat.token/dat.secret on success.
     */
    public function auth(string $usr, string $password): array
    {
        $action = '&action=auth'
            . '&usr=' . rawurlencode($usr)
            . '&company-key=' . self::COMPANY_KEY
            . '&source=' . self::SOURCE
            . '&_app_client_=' . self::APP_CLIENT
            . '&_app_id_=' . self::APP_ID
            . '&_app_version_=' . self::APP_VERSION;

        $salt = $this->salt();
        $sign = sha1($salt . sha1($password) . $action);

        $response = Http::withOptions(['verify' => false])
            ->timeout(30)
            ->get(self::BASE_URL . '?sign=' . $sign . '&salt=' . $salt . $action);

        Log::channel('registration')->info('ShineMonitor auth response', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'usr'    => $usr,
        ]);

        return $response->json() ?? [];
    }

    protected function appParams(): string
    {
        return '&company-key=' . self::COMPANY_KEY
            . '&source=' . self::SOURCE
            . '&_app_client_=' . self::APP_CLIENT
            . '&_app_id_=' . self::APP_ID
            . '&_app_version_=' . self::APP_VERSION;
    }

    protected function salt(): string
    {
        return (string) (int) round(microtime(true) * 1000);
    }

    /**
     * RC4 stream cipher in pure PHP (mcrypt removed, OpenSSL 3 lacks RC4).
     */
    protected function rc4(string $key, string $data): string
    {
        $s = range(0, 255);
        $j = 0;
        $keyLen = strlen($key);

        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLen])) % 256;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
        }

        $i = $j = 0;
        $out = '';
        $len = strlen($data);
        for ($n = 0; $n < $len; $n++) {
            $i = ($i + 1) % 256;
            $j = ($j + $s[$i]) % 256;
            [$s[$i], $s[$j]] = [$s[$j], $s[$i]];
            $out .= $data[$n] ^ chr($s[($s[$i] + $s[$j]) % 256]);
        }

        return $out;
    }
}
