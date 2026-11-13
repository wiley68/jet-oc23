#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
command -v php >/dev/null || { echo 'PHP CLI is required.' >&2; exit 1; }
php -- "$repo_root" <<'PHP'
<?php
// Packaging only: no source writes or Git operations.
date_default_timezone_set('UTC');
function fail($message) { throw new RuntimeException($message); }
function xmlVersion($contents) {
    $xml = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NONET);
    if (!$xml || count($xml->version) !== 1 || (string)$xml->code !== 'cc_jet') {
        fail('Invalid JetCredit OCMOD XML.');
    }
    if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*) for OpenCart 2\.3\.x$/D', (string)$xml->version, $match)) {
        fail('Expected MAJOR.MINOR.PATCH for OpenCart 2.3.x in cc_jet.ocmod.xml.');
    }
    return $match[1] . '.' . $match[2] . '.' . $match[3];
}
function artifactVersion($name) {
    return preg_match('/^CC_OpenCartv\.2\.3\.x_JET_v\.((?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*))\.zip$/D', $name, $m) ? $m[1] : null;
}
$temp = null;
try {
    if (!extension_loaded('zip') || !extension_loaded('SimpleXML')) {
        fail('PHP extensions zip and SimpleXML are required.');
    }
    if (!method_exists('ZipArchive', 'setMtimeName')) { fail('PHP 8.0+ is required.'); }
    $root = $argv[1];
    $xml = file_get_contents($root . '/cc_jet.ocmod.xml');
    $version = xmlVersion($xml);
    $entries = ['cc_jet.ocmod.xml' => $xml];
    $metadata = 0;
    foreach (['admin', 'catalog', 'system'] as $section) {
        $directory = $root . '/upload/' . $section;
        if (!is_dir($directory) || is_link($directory)) { fail('Missing/unsafe upload/' . $section); }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);
            if ($file->isLink() || !$file->isFile() || preg_match('~(?:^|/)\.|(?:\~|\.bak|\.tmp)$~', $path)
                || !preg_match('/\.(php|tpl|js|css|png)$/D', $path)) {
                fail('Unexpected installation file: ' . $path);
            }
            $contents = file_get_contents($file->getPathname());
            if (substr($path, -4) === '.php') {
                preg_match_all('/\$data\[\x27JET_VERSION\x27\]\s*=\s*\x27([^\x27]+)\x27\s*;/', $contents, $matches);
                foreach ($matches[1] as $definedVersion) {
                    if ($definedVersion !== $version) { fail('Version mismatch: ' . $path); }
                    $metadata++;
                }
            }
            $entries[$path] = $contents;
        }
    }
    if ($metadata !== 2) { fail('Expected two checkout JET_VERSION identifiers.'); }
    ksort($entries, SORT_STRING);
    $dist = $root . '/dist';
    if (is_link($dist) || (file_exists($dist) && !is_dir($dist))) { fail('Unsafe dist path.'); }
    $oldArtifacts = [];
    if (is_dir($dist)) {
        foreach (new DirectoryIterator($dist) as $file) {
            if ($file->isDot()) { continue; }
            $oldVersion = artifactVersion($file->getFilename());
            if (!$oldVersion || !$file->isFile() || $file->isLink()) { fail('Unknown dist entry; preserved: ' . $file->getFilename()); }
            $old = new ZipArchive();
            if ($old->open($file->getPathname(), ZipArchive::CHECKCONS) !== true) { fail('Unrecognized installer; preserved: ' . $file->getFilename()); }
            // Recognize both corrected packages and earlier generated installer artifacts.
            $oldXml = $old->getFromName('cc_jet.ocmod.xml');
            if ($oldXml === false) { $oldXml = $old->getFromName('install.xml'); }
            if ($old->getArchiveComment() !== 'jet-oc23 installer build v1' || xmlVersion($oldXml) !== $oldVersion) {
                fail('Unrecognized build artifact; preserved: ' . $file->getFilename());
            }
            $old->close();
            $oldArtifacts[] = $file->getPathname();
        }
    } else {
        if (!mkdir($dist, 0755)) { fail('Cannot create dist.'); }
    }
    $temp = tempnam(sys_get_temp_dir(), 'jet-oc23-installer-');
    $zip = new ZipArchive();
    if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { fail('Cannot create ZIP.'); }
    $zip->setArchiveComment('jet-oc23 installer build v1');
    foreach ($entries as $path => $contents) {
        if (!$zip->addFromString($path, $contents)
            || !$zip->setCompressionName($path, ZipArchive::CM_DEFLATE, 9)
            || !$zip->setMtimeName($path, 946684800)
            || !$zip->setExternalAttributesName($path, ZipArchive::OPSYS_UNIX, 0100644 << 16)) {
            fail('Cannot package ' . $path);
        }
    }
    if (!$zip->close()) { fail('Cannot finalize ZIP.'); }
    $check = new ZipArchive();
    if ($check->open($temp, ZipArchive::CHECKCONS) !== true || $check->numFiles !== count($entries)) { fail('ZIP validation failed.'); }
    foreach ($entries as $path => $contents) {
        if ($check->getFromName($path) !== $contents) { fail('ZIP content mismatch: ' . $path); }
    }
    $check->close();
    $output = $dist . '/CC_OpenCartv.2.3.x_JET_v.' . $version . '.zip';
    if (!copy($temp, $output)) { fail('Cannot publish installer.'); }
    foreach ($oldArtifacts as $old) {
        if ($old !== $output && !unlink($old)) { fail('Cannot remove old generated installer.'); }
    }
    unlink($temp);
    $temp = null;
    echo $output . "\n" . filesize($output) . " bytes\nSHA-256: " . hash_file('sha256', $output) . "\n";
} catch (Throwable $error) {
    if ($temp !== null && is_file($temp)) { unlink($temp); }
    fwrite(STDERR, 'Build failed: ' . $error->getMessage() . "\n");
    exit(1);
}
PHP
