<?php
/**
 * Proxy de portales de transportistas (Forza Delivery / Cargo Expreso).
 *
 * Uso:  /php/shipping/portal_proxy.php/{servicio}/{ruta}
 * Ej.:  /php/shipping/portal_proxy.php/forza/login-corporate
 *
 * Reenvía peticiones al portal real, mantiene las cookies de sesión del
 * portal en la sesión PHP del usuario (jar), reescribe URLs de origen a
 * rutas del proxy (HTML/JS/CSS/JSON) e inyecta un auto-login que completa
 * el formulario con las credenciales de config/shipping.php. Así el portal
 * queda "con inicio de sesión listo" dentro del iframe de CLINOVA.
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    exit('No autorizado');
}

$services = require __DIR__ . '/../../config/shipping.php';

// ---------- parseo de la ruta ----------
$pi = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
$segments = array_values(array_filter(explode('/', rawurldecode($pi)), fn($s) => $s !== ''));
$svc = array_shift($segments);
// el JS de IDCS compone hostName+':443' — aceptar y quitar el puerto
if ($svc !== null && preg_match('~^(idcs|cargo|forza):\d+$~', $svc, $m)) $svc = $m[1];

if ($svc === null || !isset($services[$svc])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Servicio no encontrado');
}
$conf = $services[$svc];

// normalizar "." / ".." (además de impedir salirse de la base del portal)
$clean = [];
foreach ($segments as $seg) {
    if ($seg === '.') continue;
    if ($seg === '..') { array_pop($clean); continue; }
    $clean[] = $seg;
}
$rest = implode('/', $clean);

$scheme   = parse_url($conf['base'], PHP_URL_SCHEME);
$host     = parse_url($conf['base'], PHP_URL_HOST);
$origin   = $scheme . '://' . $host;
$upstream = $conf['base'] . '/' . $rest;
if (!empty($_SERVER['QUERY_STRING'])) {
    $upstream .= '?' . $_SERVER['QUERY_STRING'];
}
$prefix = rtrim($_SERVER['SCRIPT_NAME'] ?? '/php/shipping/portal_proxy.php', '/');
// Origen propio: las reescrituras se hacen con URL absoluta en NUESTRO host
// (la composición de URLs de los portales -VB_SERVER+CONTEXT_ROOT, URI.segment-
// espera orígenes absolutos; con rutas relativas las manganetean y se rompen).
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (stripos($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '', 'https') === 0);
$selfOrigin = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$proxyRoot = $selfOrigin . $prefix . '/' . $svc; // raíz visible del portal dentro del proxy

// mapa de reescritura: origen absoluto -> raíz visible en el proxy
// (el propio servicio + hosts extra como el IDCS de Cargo)
$rewriteMap = [$conf['base'] => $proxyRoot];
foreach ($conf['extra_rewrites'] ?? [] as $originBase => $extraSvc) {
    if (isset($services[$extraSvc])) {
        $target = $selfOrigin . $prefix . '/' . $extraSvc;
        $rewriteMap[$originBase] = $target;
        // variantes con :443/:80 (p.ej. hostName de IDCS incluye :443)
        foreach ([':443', ':80'] as $port) {
            if (substr($originBase, -strlen($port)) !== $port) {
                $rewriteMap[$originBase . $port] = $target;
            }
        }
    }
}

function shipping_request_headers(): array
{
    $headers = [];
    // Apache/MAMP a veces strippea Authorization por seguridad: el navegador
    // lo manda, PHP no lo ve. Hay que sacarlo de varias fuentes posibles.
    $authSources = [
        $_SERVER['HTTP_AUTHORIZATION'] ?? null,
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
        function_exists('apache_request_headers') ? (function () {
            $h = apache_request_headers();
            return $h['Authorization'] ?? ($h['authorization'] ?? null);
        })() : null,
        function_exists('getallheaders') ? (function () {
            $h = getallheaders();
            return $h['Authorization'] ?? ($h['authorization'] ?? null);
        })() : null,
    ];
    foreach ($authSources as $a) {
        if ($a && !isset($headers['Authorization'])) $headers['Authorization'] = $a;
    }
    foreach ($_SERVER as $name => $value) {
        if (strpos($name, 'HTTP_') === 0) {
            $h = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            if (!isset($headers[$h])) $headers[$h] = $value;
        } elseif ($name === 'CONTENT_TYPE') {
            if (!isset($headers['Content-Type'])) $headers['Content-Type'] = $value;
        }
    }
    return $headers;
}

// ---------- jar de cookies del portal (guardado en la sesión) ----------
$jarMap = [];
$jarRaw = $_SESSION['shipping_jar'][$svc] ?? '';
foreach (array_filter(explode('; ', $jarRaw)) as $pair) {
    $eq = strpos($pair, '=');
    if ($eq !== false) $jarMap[trim(substr($pair, 0, $eq))] = trim(substr($pair, $eq + 1));
}
session_write_close(); // liberar el lock mientras corre el request remoto

// ---------- cabeceras de la petición ----------
$method  = $_SERVER['REQUEST_METHOD'];
$hasBody = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
$rawBody = $hasBody ? file_get_contents('php://input') : '';

// IDCS exige que redirect_uri coincida con el registrado para el client:
// el portal ve URLs de proxy (reescribimos su config), así que al hablar con
// IDCS hay que devolverle la URL original registrada. Mapa inverso global:
// raíz proxy -> origen real, para el servicio actual y todos los extra.
$proxyToReal = [$proxyRoot => $conf['base']];
foreach ($services as $sName => $sConf) {
    $proxyToReal[$selfOrigin . $prefix . '/' . $sName] = $sConf['base'];
    foreach ($sConf['extra_rewrites'] ?? [] as $oBase => $eSvc) {
        if (isset($services[$eSvc])) {
            $proxyToReal[$selfOrigin . $prefix . '/' . $eSvc] = $oBase;
        }
    }
}
foreach ($proxyToReal as $proxyBase => $realBase) {
    if ($proxyBase === $realBase) continue;
    $upstream = str_replace(rawurlencode($proxyBase), rawurlencode($realBase), $upstream);
    $upstream = str_replace($proxyBase, rawurlencode($realBase), $upstream);
    $rawBody  = str_replace(rawurlencode($proxyBase), rawurlencode($realBase), $rawBody);
    $rawBody  = str_replace($proxyBase, $realBase, $rawBody);
}

$skipReq = [
    'host', 'cookie', 'origin', 'referer', 'accept-encoding', 'connection',
    'keep-alive', 'transfer-encoding', 'te', 'trailer', 'upgrade', 'content-length',
];
$reqHeaders = [];
foreach (shipping_request_headers() as $name => $value) {
    if (in_array(strtolower($name), $skipReq, true)) continue;
    $reqHeaders[] = $name . ': ' . $value;
}
$reqHeaders[] = 'Origin: ' . ($conf['origin'] ?? $origin);
$reqHeaders[] = 'Referer: ' . ($conf['referer'] ?? ($origin . '/'));
if (!empty($jarMap)) {
    $reqHeaders[] = 'Cookie: ' . implode('; ', array_map(fn($n, $v) => "$n=$v", array_keys($jarMap), $jarMap));
}

// ---------- ejecutar la petición remota ----------
$gotStatus  = false;
$respStatus = 502;
$respFields = [];
$setCookies = [];
$jarChanged = false;
$respBody   = '';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $upstream,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_POSTFIELDS     => $hasBody ? $rawBody : null,
    CURLOPT_HTTPHEADER     => $reqHeaders,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_ENCODING       => '',           // decodifica gzip/br automáticamente
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HEADERFUNCTION => function ($ch2, $line) use (&$gotStatus, &$respStatus, &$respFields, &$setCookies, &$jarMap, &$jarChanged) {
        $len = strlen($line);
        $trim = rtrim($line, "\r\n");
        if ($trim === '') return $len;
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $trim, $m)) {
            $gotStatus = true;
            $respStatus = (int) $m[1];
            $respFields = [];
            return $len;
        }
        $pos = strpos($trim, ':');
        if ($pos === false) return $len;
        $name  = strtolower(trim(substr($trim, 0, $pos)));
        $value = trim(substr($trim, $pos + 1));
        if ($name === 'set-cookie') {
            $pair = explode(';', $value, 2)[0];
            $eq = strpos($pair, '=');
            if ($eq === false) return $len;
            $cname = trim(substr($pair, 0, $eq));
            $cval  = trim(substr($pair, $eq + 1));
            $delete = ($cval === '');
            if (preg_match('/max-age=0/i', $value)) $delete = true;
            if (preg_match('/expires=([^,]+)/i', $value, $em)) {
                $ts = strtotime(trim($em[1]));
                if ($ts !== false && $ts < time()) $delete = true;
            }
            $setCookies[] = $cname;
            $jarChanged = true;
            if ($delete) unset($jarMap[$cname]); else $jarMap[$cname] = $cval;
            return $len;
        }
        $respFields[$name] = $value;
        return $len;
    },
    CURLOPT_WRITEFUNCTION => function ($ch2, $chunk) use (&$respBody) {
        $respBody .= $chunk;
        return strlen($chunk);
    },
]);
$ok = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

// guardar el jar solo si hubo cambios (evita pisar cookies entre peticiones paralelas)
if ($jarChanged) {
    session_start();
    $_SESSION['shipping_jar'][$svc] = implode('; ', array_map(fn($n, $v) => "$n=$v", array_keys($jarMap), $jarMap));
    session_write_close();
}

if ($ok === false || !$gotStatus) {
    http_response_code(502);
    header('Content-Type: text/plain; charset=utf-8');
    exit('No se pudo conectar con el portal del transportista. (' . $curlErr . ')');
}

// ---------- reescritura de URLs del portal -> rutas del proxy ----------
function shipping_rewrite_urls(string $text, array $rewriteMap, string $prefix, string $type): string
{
    // 1) orígenes absolutos (portal + hosts extra como IDCS) -> proxy
    //    (las variantes con puerto antes que las sin puerto, o el :443 queda pegado)
    uksort($rewriteMap, fn($a, $b) => strlen($b) <=> strlen($a));
    foreach ($rewriteMap as $originBase => $proxyTarget) {
        $text = str_replace($originBase, '{PROXY:' . $proxyTarget . '}', $text);
        $text = str_replace(preg_replace('~^https:~', 'http:', $originBase), '{PROXY:' . $proxyTarget . '}', $text);
        $text = str_replace(preg_replace('~^https?:~', '', $originBase), '{PROXY:' . $proxyTarget . '}', $text);
    }

    // 2) prefijos de ruta raíz propios del portal (p.ej. /ic/ de Oracle)
    //    (solo los del servicio actual; los extra se resuelven por origen)
    $selfProxy = $prefix; // raíz del servicio actual (el mapa ya puede estar reordenado)
    foreach ($GLOBALS['__shipping_path_prefixes'] ?? [] as $p) {
        $text = preg_replace('~(["\'`\(\s,={])' . preg_quote($p, '~') . '~', '$1{PROXY:' . $selfProxy . '}' . $p, $text);
    }

    // 3) atributos con ruta absoluta en HTML
    if ($type === 'html') {
        $text = preg_replace('~\b(href|src|poster|action)="/(?!/)([^"]*)"~', '$1="{PROXY:' . $selfProxy . '}/$2"', $text);
        $text = preg_replace("~\b(href|src|poster|action)='/(?!/)([^']*)'~", "\$1='{PROXY:" . $selfProxy . "}/\$2'", $text);
        $text = preg_replace_callback('~\b(imagesrcset|srcset)="([^"]*)~', function ($m) use ($selfProxy) {
            $parts = array_map(function ($u) use ($selfProxy) {
                $u = trim($u);
                if (strpos($u, '/') === 0 && strpos($u, '{PROXY') !== 0) return '{PROXY:' . $selfProxy . '}' . $u;
                return $u;
            }, explode(',', $m[2]));
            return $m[1] . '="' . implode(',', $parts);
        }, $text);
    }

    // 4) placeholders -> prefijos del proxy
    $text = preg_replace_callback('~\{PROXY:([^}]+)\}~', fn($m) => $m[1], $text);
    return $text;
}

function shipping_rewrite_css(string $css, string $upstreamPath, string $prefix): string
{
    $dir = dirname($upstreamPath);
    if ($dir === '/' || $dir === '.' || $dir === DIRECTORY_SEPARATOR) $dir = '';
    // url() con ruta absoluta -> proxy
    $css = preg_replace('~url\(\s*([\'"]?)/(?!\{PROXY\})~', 'url($1{PROXY}/', $css);
    // url() relativo -> resuelto contra la carpeta del archivo CSS upstream
    $css = preg_replace_callback('~url\(\s*([\'"])(?![a-zA-Z]+:|//|#)([^)\'"]+)([\'"])~', function ($m) use ($dir) {
        return 'url(' . $m[1] . '{PROXY}' . $dir . '/' . ltrim($m[2], '/') . $m[3];
    }, $css);
    $css = preg_replace_callback('~url\(\s*(?![a-zA-Z]+:|//|#|\{PROXY|[\'"])([^)\'"\s]+)~', function ($m) use ($dir) {
        return 'url({PROXY}' . $dir . '/' . ltrim($m[1], '/');
    }, $css);
    return $css;
}

function shipping_fix_vbconfig(string $html, string $proxyRoot, string $selfOrigin): string
{
    // El portal original usa: VB_SERVER = origen puro y CONTEXT_ROOT/BASE_URL_TOKEN
    // = paths. El runtime los concatena (VB_SERVER + CONTEXT_ROOT), así que hay que
    // conservar esa forma con nuestro origen/prefijo o las URLs salen duplicadas.
    return preg_replace_callback('~(window\.vbInitConfig\s*=\s*\{.*?\n\s*\})~s', function ($m) use ($proxyRoot, $selfOrigin) {
        return preg_replace_callback("~(VB_SERVER|CONTEXT_ROOT|BASE_URL_TOKEN)(\s*:\s*')([^']*)(')~", function ($x) use ($proxyRoot, $selfOrigin) {
            $key = $x[1];
            $val = $x[3];
            if ($key === 'VB_SERVER') {
                if (strpos($val, $selfOrigin) === 0) $val = $selfOrigin;
            } else {
                if (strpos($val, $proxyRoot) === 0) {
                    $val = substr($val, strlen($selfOrigin)); // -> path bajo nuestro host
                } elseif (strpos($val, $selfOrigin) === 0) {
                    $val = substr($val, strlen($selfOrigin));
                }
            }
            return $x[1] . $x[2] . $val . $x[4];
        }, $m[1]);
    }, $html);
}

$ct   = strtolower($respFields['content-type'] ?? '');
$mime = trim(explode(';', $ct)[0]);
$skipResp = ['content-length', 'content-encoding', 'transfer-encoding', 'connection', 'keep-alive',
             'set-cookie', 'x-frame-options', 'content-security-policy', 'content-security-policy-report-only',
             'report-to', 'nel'];

http_response_code($respStatus);

$isText = (bool) preg_match('~^(text/|application/(json|javascript|xml|.*\+json|.*\+xml)|image/svg\+xml)~', $mime);

foreach ($respFields as $name => $value) {
    if (in_array($name, $skipResp, true)) continue;
    if ($name === 'location') {
        $rewrote = false;
        foreach ($rewriteMap as $originBase => $proxyTarget) {
            if (strpos($value, $originBase) === 0) {
                $value = $proxyTarget . substr($value, strlen($originBase));
                $rewrote = true;
                break;
            }
        }
        if (!$rewrote && preg_match('~^(https?:)?//[^/]+~', $value)) {
            // otro dominio (p.ej. IDCS si no está en el mapa): dejar tal cual
        } elseif (!$rewrote && strpos($value, '/') === 0) {
            $value = $proxyRoot . $value;
        }
        header('Location: ' . $value, true, $respStatus);
        continue;
    }
    if ($name === 'cache-control') continue;
    header(ucfirst($name) . ': ' . $value, true);
}

if ($isText && $respBody !== '') {
    $subtype = explode('/', $mime, 2)[1] ?? '';
    $GLOBALS['__shipping_path_prefixes'] = $conf['path_prefixes'] ?? [];
    if ($mime === 'text/css') {
        $respBody = shipping_rewrite_css($respBody, '/' . $rest, $proxyRoot);
        $respBody = shipping_rewrite_urls($respBody, $rewriteMap, $proxyRoot, 'css');
    } elseif ($mime === 'text/html') {
        $respBody = shipping_rewrite_urls($respBody, $rewriteMap, $proxyRoot, 'html');
        $respBody = shipping_fix_vbconfig($respBody, $proxyRoot, $selfOrigin);
        // ODCS (Cargo): romper el bucle de redirect post-login. La app setea
        // sessionStorage 'odcs.OdcsSecurityProvider.loginState' y redirige a
        // /protected, que rebota a /dcs/, que lo ve y vuelve a redirigir.
        if ($svc === 'cargo') {
            $clearLs = '<script>try{sessionStorage.removeItem("odcs.OdcsSecurityProvider.loginState");}catch(e){}</script>';
            if (preg_match('~<head[^>]*>~i', $respBody, $hm)) {
                $respBody = preg_replace('~<head[^>]*>~i', $hm[0] . $clearLs, $respBody, 1);
            } else {
                $respBody = $clearLs . $respBody;
            }
        }
        if (!empty($conf['autologin']['file']) && is_file($conf['autologin']['file'])) {
            $vars = json_encode($conf['autologin']['vars']);
            $js   = file_get_contents($conf['autologin']['file']);
            $inject = '<script>window.__SHIPPING_PORTAL=' . $vars . ';</script><script>' . $js . '</script>';
            if (stripos($respBody, '</head>') !== false) {
                $respBody = str_ireplace('</head>', $inject . '</head>', $respBody);
            } elseif (stripos($respBody, '</body>') !== false) {
                $respBody = str_ireplace('</body>', $inject . '</body>', $respBody);
            } else {
                $respBody .= $inject;
            }
        }
    } else {
        $respBody = shipping_rewrite_urls($respBody, $rewriteMap, $proxyRoot, $subtype);
    }
    header('Cache-Control: no-store');
} else {
    header('Cache-Control: public, max-age=600');
}

echo $respBody;
