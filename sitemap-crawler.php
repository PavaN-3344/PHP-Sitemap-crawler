<?php
/**
 * Quicksprout Sitemap Crawler - Go style output
 */

date_default_timezone_set("Asia/Kolkata"); // adjust if needed
$sitemapIndex = "https://www.quicksprout.com/sitemap_index.xml";
$limit = 20;

function logMessage($message) {
    echo date("Y/m/d H:i:s") . " " . $message . PHP_EOL;
}

function fetchHtml($url) {
    $context = stream_context_create([
        "http" => [
            "follow_location" => 1,
            "timeout" => 10,
            "header" => "User-Agent: PHP-Sitemap-Crawler/1.0\r\n"
        ]
    ]);

    return @file_get_contents($url, false, $context);
}

echo "📡 Requesting sitemap index: $sitemapIndex" . PHP_EOL;
$indexContent = @file_get_contents($sitemapIndex);
if ($indexContent === false) {
    die("❌ Failed to fetch sitemap index." . PHP_EOL);
}

$indexXml = @simplexml_load_string($indexContent);
if ($indexXml === false) {
    die("❌ Failed to parse sitemap index." . PHP_EOL);
}

$firstSitemap = (string)$indexXml->sitemap[0]->loc;
echo "✅ Found first sitemap: $firstSitemap" . PHP_EOL;

$sitemapContent = @file_get_contents($firstSitemap);
if ($sitemapContent === false) {
    die("❌ Failed to fetch sitemap: $firstSitemap" . PHP_EOL);
}

$sitemapXml = @simplexml_load_string($sitemapContent);
if ($sitemapXml === false) {
    die("❌ Failed to parse sitemap XML." . PHP_EOL);
}

$count = 0;
foreach ($sitemapXml->url as $urlEntry) {
    $count++;
    if ($count > $limit) {
        break;
    }

    $loc = (string)$urlEntry->loc;

    logMessage("Requesting: $loc");
    $html = fetchHtml($loc);

    if ($html === false) {
        echo "❌ Failed to fetch $loc" . PHP_EOL;
        continue;
    }

    // Parse HTML
    $doc = new DOMDocument();
    @$doc->loadHTML($html);

    $title = "";
    $h1 = "";
    $metaDesc = "";

    $titles = $doc->getElementsByTagName("title");
    if ($titles->length > 0) {
        $title = trim($titles->item(0)->nodeValue);
    }

    $h1Tags = $doc->getElementsByTagName("h1");
    if ($h1Tags->length > 0) {
        $h1 = trim($h1Tags->item(0)->nodeValue);
    }

    $metas = $doc->getElementsByTagName("meta");
    foreach ($metas as $meta) {
        if (strtolower($meta->getAttribute("name")) === "description") {
            $metaDesc = trim($meta->getAttribute("content"));
            break;
        }
    }

    // Status Code (via get_headers)
    $headers = @get_headers($loc);
    $status = "Unknown";
    if ($headers && preg_match("#HTTP/\d+\.\d+\s+(\d+)#", $headers[0], $matches)) {
        $status = $matches[1];
    }

    echo "URL: $loc" . PHP_EOL;
    echo "Title: $title" . PHP_EOL;
    echo "H1: $h1" . PHP_EOL;
    echo "Meta: $metaDesc" . PHP_EOL;
    echo "Status: $status" . PHP_EOL;

    logMessage("Fetched: $loc [$status]");
    echo "----------------------------------------" . PHP_EOL;
}
