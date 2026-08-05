<?php
/** Klien HTTP ringkas untuk ujian (guna cURL + kuki sesi). */

declare(strict_types=1);

class Klien
{
    private string $base;
    private string $cookieFile;
    private ?string $csrf = null;
    public array $lastHeaders = [];
    public int $lastCode = 0;

    public function __construct(string $base, string $tag)
    {
        $this->base = rtrim($base, '/');
        $this->cookieFile = sys_get_temp_dir() . "/merdeka_ck_{$tag}_" . getmypid() . '.txt';
        @unlink($this->cookieFile);
    }

    public function get(string $path, array $q = [], array $headers = []): array
    {
        $url = $this->base . $path . ($q ? ('?' . http_build_query($q)) : '');
        return $this->call($url, null, $headers);
    }

    public function post(string $path, array $q, array $body): array
    {
        $url = $this->base . $path . ($q ? ('?' . http_build_query($q)) : '');
        $h = ['Content-Type: application/json'];
        if ($this->csrf) $h[] = 'X-CSRF-Token: ' . $this->csrf;
        return $this->call($url, json_encode($body), $h);
    }

    private function call(string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new RuntimeException('cURL: ' . curl_error($ch));
        }
        $hlen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->lastCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $rawH = substr($resp, 0, $hlen);
        $badan = substr($resp, $hlen);
        curl_close($ch);

        $this->lastHeaders = [];
        foreach (explode("\r\n", $rawH) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $this->lastHeaders[strtolower(trim($k))] = trim($v);
            }
        }

        if ($badan === '') return [];
        $d = json_decode($badan, true);
        return is_array($d) ? $d : ['_mentah' => $badan];
    }

    public function login(string $email, string $pass): array
    {
        $r = $this->post('/api/auth.php', ['action' => 'login'], ['email' => $email, 'password' => $pass]);
        if (!empty($r['csrf'])) $this->csrf = $r['csrf'];
        return $r;
    }

    public function segarkanCsrf(): void
    {
        $r = $this->get('/api/auth.php', ['action' => 'me']);
        if (!empty($r['csrf'])) $this->csrf = $r['csrf'];
    }
}

// ---- Kemudahan ujian --------------------------------------------------
$GLOBALS['UJIAN_LULUS'] = 0;
$GLOBALS['UJIAN_GAGAL'] = 0;

function sahkan(bool $syarat, string $tajuk, string $butiran = ''): void
{
    if ($syarat) {
        $GLOBALS['UJIAN_LULUS']++;
    } else {
        $GLOBALS['UJIAN_GAGAL']++;
        echo "  ✗ GAGAL: $tajuk" . ($butiran ? " — $butiran" : '') . "\n";
    }
}

function tajukUjian(string $t): void
{
    echo "\n=== $t ===\n";
}

function ringkasanUjian(): int
{
    $l = $GLOBALS['UJIAN_LULUS'];
    $g = $GLOBALS['UJIAN_GAGAL'];
    echo "\n" . str_repeat('=', 60) . "\n";
    echo $g === 0
        ? "SEMUA UJIAN LULUS — $l semakan.\n"
        : "ADA KEGAGALAN — lulus: $l, gagal: $g\n";
    echo str_repeat('=', 60) . "\n";
    return $g === 0 ? 0 : 1;
}
