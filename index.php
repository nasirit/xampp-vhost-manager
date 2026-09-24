<?php
/**
 * XAMPP Automated VirtualHost & Windows Hosts Manager
 * Features: Auto XAMPP path detection, duplicate checking, non-blocking restart, list, and delete.
 * Run this script with Administrator privileges or run xampp as service.
 * include('xampp-vhost-manager/index.php');
 */

// Restrict access to localhost only
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$allowedIps = ['127.0.0.1', '::1'];

$isLocalhost = in_array($clientIp, $allowedIps);
$isLocalNetwork = str_starts_with($clientIp, '192.168.');

if (!$isLocalhost && !$isLocalNetwork) {
    http_response_code(403);
    die("Server is running");
}

// Automatically detect XAMPP path from the current script's location
$scriptPath = str_replace('\\', '/', __DIR__);
$xamppPath = preg_match('/^(.*?\/xampp)\/htdocs/i', $scriptPath, $matches) 
    ? $matches[1] 
    : "D:/xampp"; // Fallback default

$htdocsPath       = "{$xamppPath}/htdocs";
$vhostsFile       = "{$xamppPath}/apache/conf/extra/httpd-vhosts.conf";
$windowsHostsFile = "C:/Windows/System32/drivers/etc/hosts";
$apacheService    = "Apache2.4"; // Adjust if your XAMPP Apache service name differs

$message = "";
$status = "";

// Handle Delete Request
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['domain'])) {
    $domainToDelete = trim($_GET['domain']);
    $subDomainToDelete = "admin" . $domainToDelete;

    // 1. Clean httpd-vhosts.conf
    if (file_exists($vhostsFile)) {
        $vhostsData = file_get_contents($vhostsFile);
        // Regex pattern to strip out the virtual host blocks matching this domain and its admin variant
        $pattern = '/# Added automatically.*?(<VirtualHost \*:80>.*?ServerName (' . preg_quote($subDomainToDelete, '/') . '|' . preg_quote($domainToDelete, '/') . ').*?<\/VirtualHost>)/is';
        
        // Remove blocks systematically
        $vhostsDataCleaned = preg_replace_callback('/(<VirtualHost \*:80>.*?<\/VirtualHost>)/is', function($match) use ($domainToDelete, $subDomainToDelete) {
            if (strpos($match[1], $domainToDelete) !== false || strpos($match[1], $subDomainToDelete) !== false) {
                return ''; // Remove this block
            }
            return $match[0];
        }, $vhostsData);

        // Also clean up tracking comment header lines if any left over
        $vhostsDataCleaned = preg_replace('/# Added automatically on [^\r\n]*/', '', $vhostsDataCleaned);
        file_put_contents($vhostsFile, trim($vhostsDataCleaned) . "\n");
    }

    // 2. Clean Windows Hosts File
    if (file_exists($windowsHostsFile)) {
        $hostsLines = file($windowsHostsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $newHostsLines = [];
        foreach ($hostsLines as $line) {
            if (stripos($line, $domainToDelete) === false) {
                $newHostsLines[] = $line;
            }
        }
        file_put_contents($windowsHostsFile, implode("\r\n", $newHostsLines) . "\r\n");
    }

    // 3. Restart Apache
    pclose(popen("start /b cmd /c \"net stop {$apacheService} && net start {$apacheService}\"", "r"));

    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?') . "?msg=" . urlencode("Successfully deleted domain configuration for {$domainToDelete}!"));
    exit;
}

// Handle Form Submission (Create VHost)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domain    = trim($_POST['domain'] ?? '');
    $dirName   = trim($_POST['dirname'] ?? '');

    if (empty($domain) || empty($dirName)) {
        $message = "Please provide both a domain name and a directory name.";
        $status = "error";
    } else {
        $targetDir = "{$htdocsPath}/{$dirName}";
        $subDomain = "admin" . $domain;

        $vhostsContent = file_exists($vhostsFile) ? file_get_contents($vhostsFile) : "";
        $hostsContent  = file_exists($windowsHostsFile) ? file_get_contents($windowsHostsFile) : "";

        if (stripos($vhostsContent, "ServerName {$domain}") !== false || stripos($vhostsContent, "ServerName {$subDomain}") !== false) {
            $message = "Error: VirtualHost configuration for <a href='http://{$domain}' target='_blank'>{$domain}</a> or <a href='http://{$subDomain}' target='_blank'>{$subDomain}</a> already exists!";
            $status = "error";
        } else {
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0777, true)) {
                    $message = "Failed to create directory: {$targetDir}";
                    $status = "error";
                } else {
                    file_put_contents("{$targetDir}/index.php", "<?php echo 'Welcome to {$domain}!'; ?>");
                }
            }

            if ($status !== "error") {
                $vhostConfig = "\n\n" .
                    "# Added automatically on " . date('Y-m-d H:i:s') . "\n" .
                    "<VirtualHost *:80>\n" .
                    "    ServerName {$subDomain}\n" .
                    "    DocumentRoot \"{$targetDir}\"\n" .
                    "    SetEnv APPLICATION_ENV \"development\"\n" .
                    "    <Directory \"{$targetDir}\">\n" .
                    "        DirectoryIndex index.php\n" .
                    "        AllowOverride All\n" .
                    "        Order allow,deny\n" .
                    "        Allow from all\n" .
                    "    </Directory>\n" .
                    "</VirtualHost>\n\n" .
                    "<VirtualHost *:80>\n" .
                    "    ServerName {$domain}\n" .
                    "    DocumentRoot \"{$targetDir}\"\n" .
                    "    SetEnv APPLICATION_ENV \"development\"\n" .
                    "    <Directory \"{$targetDir}\">\n" .
                    "        DirectoryIndex index.php\n" .
                    "        AllowOverride All\n" .
                    "        Order allow,deny\n" .
                    "        Allow from all\n" .
                    "    </Directory>\n" .
                    "</VirtualHost>";

                if (file_put_contents($vhostsFile, $vhostConfig, FILE_APPEND) === false) {
                    $message = "Failed to write to Apache vhosts configuration file.";
                    $status = "error";
                } else {
                    $hostsAppend = "";
                    if (stripos($hostsContent, $domain) === false) {
                        $hostsAppend .= "\n127.0.0.1\t{$domain}";
                    }
                    if (stripos($hostsContent, $subDomain) === false) {
                        $hostsAppend .= "\n127.0.0.1\t{$subDomain}";
                    }

                    if (!empty($hostsAppend)) {
                        if (file_put_contents($windowsHostsFile, "\n" . trim($hostsAppend) . "\n", FILE_APPEND) === false) {
                            $message = "Failed to update Windows hosts file. Ensure script runs as Administrator.";
                            $status = "error";
                        }
                    }

                    if ($status !== "error") {
                        pclose(popen("start /b cmd /c \"net stop {$apacheService} && net start {$apacheService}\"", "r"));
                        $message = "Successfully created directory, verified/updated configurations, and signaled Apache to restart! <a href='http://{$domain}' target='_blank'>{$domain}</a> and <a href='http://{$subDomain}' target='_blank'>{$subDomain}</a> are now accessible.";
                        $status = "success";
                    }
                }
            }
        }
    }
}

// Read configured virtual hosts for listing
$configuredHosts = [];
if (file_exists($vhostsFile)) {
    preg_match_all('/<VirtualHost \*:80>(.*?)<\/VirtualHost>/is', file_get_contents($vhostsFile), $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $block) {
            if (preg_match('/ServerName\s+([^\r\n]+)/i', $block, $snMatch) && preg_match('/DocumentRoot\s+"([^"]+)"/i', $block, $drMatch)) {
                $serverName = trim($snMatch[1]);
                $docRoot = trim($drMatch[1]);
                // Exclude 'admin' prefix and 'localhost' from the listing
                if (strpos($serverName, 'admin') !== 0 && strtolower($serverName) !== 'localhost') {
                    $configuredHosts[$serverName] = $docRoot;
                }
            }
        }
    }
}
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $status = "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>XAMPP VHost & Host Automator | Nasir IT</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; margin: 0; padding: 30px; }
        .container { max-width: 750px; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); margin: auto; }
        h2, h3 { margin-top: 0; color: #333; }
        .path-info { font-size: 12px; background: #e9ecef; padding: 8px; border-radius: 4px; margin-bottom: 15px; color: #495057; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; border: none; padding: 10px 15px; width: 100%; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #dee2e6; padding: 10px; text-align: left; font-size: 14px; }
        th { background: #f8f9fa; color: #333; }
        .btn-delete { background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-size: 12px; }
        .btn-delete:hover { background: #c82333; }
        .btn-link { color: #007bff; text-decoration: none; font-weight: bold; }
        .btn-link:hover { text-decoration: underline; }
    </style>
</head>
<body>

<div class="container">
    <h2>XAMPP VHost Automator</h2>
    <div class="path-info">
        <strong>Auto-detected XAMPP Path:</strong> <?= htmlspecialchars($xamppPath); ?>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="alert <?= $status; ?>">
            <?= $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="domain">Domain Name:</label>
            <input type="text" id="domain" name="domain" placeholder="e.g., nasiritx.com" required>
        </div>
        
        <div class="form-group">
            <label for="dirname">Directory Name (inside htdocs):</label>
            <input type="text" id="dirname" name="dirname" placeholder="e.g., nasiritx" required>
        </div>

        <button type="submit">Create & Setup VHost</button>
    </form>

    <hr style="margin: 30px 0; border:0; border-top:1px solid #eee;">

    <h3>Active Virtual Hosts</h3>
    <?php if (empty($configuredHosts)): ?>
        <p style="color: #6c757d; font-size: 14px;">No custom virtual hosts found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Document Root</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($configuredHosts as $domainName => $docRoot): ?>
                    <tr>
                        <td>
                            <a class="btn-link" href="http://<?= htmlspecialchars($domainName); ?>" target="_blank"><?= htmlspecialchars($domainName); ?></a><br>
                            <a class="btn-link" style="font-size: 12px; color: #6c757d;" href="http://admin<?= htmlspecialchars($domainName); ?>" target="_blank">admin<?= htmlspecialchars($domainName); ?></a>
                        </td>
                        <td style="word-break: break-all; color: #555;"><?= htmlspecialchars($docRoot); ?></td>
                        <td>
                            <a href="?action=delete&domain=<?= urlencode($domainName); ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete configuration for <?= htmlspecialchars($domainName); ?>?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>