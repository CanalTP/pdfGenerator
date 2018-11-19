<?php

require_once realpath(dirname(__FILE__) . '/../vendor/autoload.php');

use Knp\Snappy\Pdf;

// encode url when is not
$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")."://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
if (preg_match('(amp;|&)', $actual_link) === 1) {
    $url = str_replace('&', '%26', $actual_link);
    $url = str_replace('amp;', '', $url);
    header('Location: '.$url);
    die();
}
$url = htmlspecialchars_decode($_GET['url']);
$parsedUrl = parse_url($url);

$booleanParameters = array(
    'background', 'collate', 'custom-header-propagation', 'debug-javascript',
    'default-header', 'disable-dotted-lines', 'disable-external-links',
    'disable-forms', 'disable-internal-links', 'disable-javascript',
    'disable-local-file-access', 'disable-plugins', 'disable-smart-shrinking',
    'disable-toc-back-links', 'disable-toc-links', 'dump-default-toc-xsl',
    'enable-external-links', 'enable-forms', 'enable-internal-links',
    'enable-javascript', 'enable-local-file-access', 'enable-plugins',
    'enable-smart-shrinking', 'enable-toc-back-links', 'exclude-from-outline',
    'extended-help', 'footer-line', 'grayscale', 'header-line', 'help', 'htmldoc',
    'images', 'include-in-outline', 'license', 'lowquality', 'manpage',
    'no-background', 'no-collate', 'no-custom-header-propagation',
    'no-debug-javascript', 'no-footer-line', 'no-header-line', 'no-images',
    'no-outline', 'no-pdf-compression', 'no-print-media-type',
    'no-stop-slow-scripts', 'outline', 'print-media-type', 'quiet',
    'read-args-from-stdin', 'readme', 'stop-slow-scripts', 'use-xserver', 'version'
);
if ($parsedUrl != false && checkContentTypeIsHtml($url)) {
    // add http if needed
    if (!isset($parsedUrl['scheme'])) {
        $url = 'http://' . $_GET['url'];
    }

    $snappy = new Pdf('xvfb-run -s \'-screen 0 1100x1024x16\' -a wkhtmltopdf');

    $snappy->setOption('lowquality', false);
    $snappy->setOption('disable-javascript', true);
    $snappy->setOption('disable-smart-shrinking', false);
    $snappy->setOption('print-media-type', true);
    checkSnappyparams($snappy);

    // Display the resulting pdf in the browser
    // by setting the Content-type header to pdf
    header('Content-Type: application/pdf');

    // Download file instead of viewing it in the browser
    if (isset($_GET['ddl'])) {
        $filename = (empty($_GET['ddl']))? 'file' : $_GET['ddl'];
        header('Content-Disposition: attachment; filename="'.$filename.'.pdf"');
    }

    // Convert pdf in CMYK colorspace
    // Need GhostScript
    // Need a writeable temporary directory for php process
    if (filter_input(INPUT_GET, 'cmyk', FILTER_VALIDATE_BOOLEAN)) {
        $tmpRGBFileName = tempnam(sys_get_temp_dir(), 'pdf-rgb');
        $tmpCMYKFileName = tempnam(sys_get_temp_dir(), 'pdf-cmyk');

        // Write snappy RGB output in file
        $tmpRGBFile = fopen($tmpRGBFileName, 'wb');
        fwrite($tmpRGBFile, $snappy->getOutput($url));
        fclose($tmpRGBFile);

        // Convert to CMYK with GhostScript command
        exec('gs -o '.$tmpCMYKFileName.' -dAutoRotatePages=/None -sDEVICE=pdfwrite -sProcessColorModel=DeviceCMYK -sColorConversionStrategy=CMYK -dAutoFilterColorImages=false -dColorImageFilter=/FlateEncode '.$tmpRGBFileName);

        //Display output in stream
        $tmpCMYKFile = fopen($tmpCMYKFileName, 'rb');
        $cmykOutput = fread($tmpCMYKFile, filesize($tmpCMYKFileName));
        fclose($tmpCMYKFile);
        echo $cmykOutput;

        //Cleanup temporary files
        unlink($tmpRGBFileName);
        unlink($tmpCMYKFileName);
    } else {
        echo $snappy->getOutput($url);
    }
} else {
    throw new Exception("$url is not a valid url.");
}

/*
 * @function check if one the wkhtml params is present in the url and set if needed.
 */
function checkSnappyparams($snappy)
{
    global $booleanParameters;
    foreach (array_keys($snappy->getOptions()) as $option) {
        if (isset($_GET[$option])) {
            $isBooleanParam = array_search($option, $booleanParameters) !== false;
            $optValue = $isBooleanParam ? convertToBoolean($_GET[$option]) : $_GET[$option];
            $snappy->setOption($option, $optValue);
        }
    }

    if (isset($_GET['margin'])) {
        $snappy->setOption('margin-bottom', $_GET['margin']);
        $snappy->setOption('margin-top', $_GET['margin']);
        $snappy->setOption('margin-right', $_GET['margin']);
        $snappy->setOption('margin-left', $_GET['margin']);
    }
}

function convertToBoolean($param)
{
    $newParam = filter_var($param, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $newParam === null ? $param : $newParam;
}

/*
 * @function to check the content type
 */
function checkContentTypeIsHtml($url)
{
    $urlContent = get_headers($url, 1);
    if (is_array($urlContent['Content-Type'])) {
        $urlContent = $urlContent['Content-Type'][1];
    } else {
        $urlContent = $urlContent['Content-Type'];
    }
    if (strpos($urlContent, 'text/html') != 'false') {
        return false;
    }

    return true;
}
