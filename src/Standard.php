<?php
namespace wCommon;
/**
* Handy functions shared between websites.
*
* @copyright 2014-2015 Mike Weaver
*
* @license http://www.opensource.org/licenses/mit-license.html
*
* @author Mike Weaver
* @created 2014-03-01
*
* @version 1.0.1
*
*/

//
// !Keys for global variables.
//

define('g_SUBDOMAIN', 'g_SUBDOMAIN');
define('g_SITECODE', 'g_SITECODE');
define('g_SITENAME', 'g_SITENAME');
define('g_HOSTNAME', 'g_HOSTNAME');
define('g_SITEABBREV', 'g_SITEABBREV');
define('g_POSTMASTER', 'g_POSTMASTER');

//
// !Error functions
//

error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);

/** Returns a string with the information from the exception split out and labeled. */
function formatException($e) {
	return "Exception {$e->getCode()}: {$e->getMessage()} (line: {$e->getline()} of {$e->getfile()})\n{$e->getTraceAsString()}\n";
}

/**
* Prints error information, including details on an exception, if c’è, to the standard error log.
*@param array $ident is an array of items to be included as identifiers, they will be concatenated with '-'. If no $ident is provided, it will default to current path.
* @uses formatException()
*/
function errorLog($msg, $excp=null, $ident=[]) {
	if (!$ident) {
		$ident[] = getURLPath();
	}
	error_log('[' . implode($ident, '-') . "] $msg");
	if ($excp) { error_log(formatException($excp)); }
}

//
// !Site management functions
//

/** Gets the 'path' portion of a URL, either a specific URL passed in as the single parameter; or, by default, the current request URI. */
function getURLPath($url=null) {
	if (!$url) { $url = $_SERVER['REQUEST_URI']; }
	return parse_url($url, PHP_URL_PATH);
}

/** Confirms the hostname and subdomain stored under keys g_HOSTNAME and g_SUBDOMAIN and redirects if necessary. */
function confirmServer() {
	$domain = $GLOBALS[g_SUBDOMAIN];
	$hostname = $GLOBALS[g_HOSTNAME];
	if (!$_SERVER['HTTPS'] || 0!==strpos($_SERVER['SERVER_NAME'], $domain)) { header("Location: https://{$domain}.{$hostname}{$_SERVER['REQUEST_URI']}"); }
}

// Indicates if subdomain begins with 'stage'.
function isStageRegion($domain='stage') {
	return 0===strpos($GLOBALS[g_SUBDOMAIN], $domain);
}

/** Returns the host, including the current domain, which could be `www` or could be something else, like `stage`. */
function getHost() { return $GLOBALS[g_SUBDOMAIN] . '.' . $GLOBALS[g_HOSTNAME]; }

/**
* Sets the location and exits the script to redirect the browser.
* Typically used on scripts that handle both GET and POST and need to redirect between them.
* The baseURL will match the current URLPath, and extra URL parameters will come (usually) from the existing GET or POST request.
* Additional parameters, if needed, are specified with $others as an associative array in the form param_name=>param_value.
* @param array $keys list of parameters currently present in $_REQUEST that will be copied to the redirect URL
* @param array $others associative array of parameters not present in $_REQUEST to be added to the redirect URL
* @param object $target an object that responds to getFragment() to add a fragment to the redirect URL
* @param string $path desired location, if different from the current script (which is the default)
* @uses getURLPath()
* @uses prefixIfCe()
* @uses keyParam()
*/
function bailout($keys=[], $others=[], $target=null, $path=null) {
	if ($target && is_object($target) && method_exists($target, 'getFragment')) { $fragment = $target->getFragment(); }
	$query = array_merge(filterRequest($keys), $others);
	if (!$path) { $path = getURLPath(); }
	header('Location: ' . $path . prefixIfCe(implode('&', arrayKeyMap(__NAMESPACE__ . '\keyParam', array_filter($query, __NAMESPACE__ . '\isNotNull'))), '?') . prefixIfCe($fragment, '#'));
	exit;
}

//
// !Helper function for composing/parsing URLs.
//

/**
* Maps a function over the key-value pairs of an array
* @param callable $function a function that takes two parameters, the key and the value
* @param array $array the array to map
*/
function arrayKeyMap($function, $array=[]) {
	return array_map($function, array_keys($array), $array);
}

/** Returns the key and value joined by an equals sign, perfect for constructing URL parameters. */
function keyParam($key, $val) { return $key . '=' . $val; }

/** Returns the key and value joined by an equal sign, and the value wrapped in double quotes, just right for the attributes of an HTML entity. */
function attribParam($key, $val) { return "$key=\"$val\""; }

/** Returns !is_null($v) */
function isNotNull($val) { return !is_null($val); }

/** Returns the elements from $_REQUEST based on the keys in $keys. */
function filterRequest($keys=[]) {
	return array_intersect_key($_REQUEST, array_flip($keys));
}

/**
* Creates a link from the provided 'path', 'query', and 'fragment' keys in the provided array.
* @param array $components an array of URL components. It only looks for 'scheme', 'host', 'path', 'query', keys', and 'fragment'. The 'query' component is itself an array whose keys and values will be assembled to construct a valid query. The 'key' component is an array of keys whose values will be looked up in $_REQUEST. If 'path' is not provided, it will default to the current URL path. 'host' and 'scheme' can be left off, and the resulting URL will be relative.
* The approach here is similar to the bailout function.
*/
define('PATH', 'path');
define('QUERY', 'query');
define('KEYS', 'keys');
define('SCHEME', 'scheme');
define('HOST', 'host');
define('FRAGMENT', 'fragment');
function composeURL($components=[]) {
	$path = $components[PATH] or $path = getURLPath();
	$query = array_filter(array_merge(
		(array)$components[QUERY],
		filterRequest((array)$components[KEYS])
	));
	$query_str = http_build_query($query, null, '&', PHP_QUERY_RFC3986);
	return suffixIfCe($components[SCHEME], '://')
		. $components[HOST]
		. $path
		. prefixIfCe($query_str, '?')
		. prefixIfCe($components[FRAGMENT], '#');
}

//
// !String functions
//

/**
* Returns a random string of length $len.
* @param int $len
* @param string $chars characters to use, defaults to the uppercase letters
*/
function randString($len, $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ') {
	for ($i=0; $i<$len; $i++) { $ret .= $chars[mt_rand(0, strlen($chars)-1)]; }
	return $ret;
}

/** Truncates a too-long string, replacing the chopped-off tail with ellipses (using &hellip;). */
function truncateString($string, $maxLen=24) {
	return ( strlen($string)>$maxLen ? substr($string, 0, $maxLen) . '&hellip;' : $string );
}

/** Wraps $item in $prefix and $suffix if $test is boolean true, or if $test is an array and $item is in it. */
function wrap($test, $item, $prefix, $suffix) {
	if ((is_array($test) && in_array($item, $test)) || (!is_array($test) && $test)) { return $prefix.$item.$suffix; }
	else { return $item; }
}

/**
* Wraps a string, if it is not empty, with a specified prefix and suffix.
* 'Ce' comes from Italian c’è which roughly means "exists". It is pronounced "cheh".
* @param string $item the string to wrap if it is not empty
* @param string $prefix the prefix to prepend
* @param string $suffix the suffix to append
*/
function wrapIfCe($item, $prefix, $suffix) { return wrap((bool)$item, $item, $prefix, $suffix); }

/**
* Prepends a string, if it is not empty, with a space or other character.
* @param string $item the string to prepend to if it is not empty
* @param string $pre the prefix to use, defaults to a space
*/
function prefixIfCe($item, $pre=' ') { return ( $item ? $pre : '' ) . $item; }

/**
* Appends a string, if it is not empty, with a space or other character.
* @param string $item the string to append to if it is not empty
* @param string $suff the suffix to use, defaults to a space
*/
function suffixIfCe($item, $suff=' ') { return $item . ( $item ? $suff : '' ); }

//
// !Date functions
//

/**
* Returns date/time formatted as "7:45 PM Mon 3 Apr 1996" with the AM/PM styled
* in small caps (reyling on an `.apmp` class defined in CSS).
* @param int $timestamp integer timestamp such as that returned by
* `strtotime()`, if null it is set to `time()`.
* @uses `getTimeDisplay()` and `getDateDisplay()` to compose result.
*/
function getTimeDateDisplay($timestamp=null) {
	return getTimeDisplay($timestamp) . ' ' . getDateDisplay($timestamp);
}

/**
* Returns time with am/pm in small caps. Won't include minutes if they are zero.
* So "7:00" prints as "7", but "6:52" will print with minutes. Uses a span
* element and class "ampm" to format meridian. Projects using this should have a
* class defined in CSS: ".ampm { font-variant: small-caps; }". Times of "00:00"
* and "12:00" will be repsented as "midnight" and "noon", respectively.
* @param int $timestamp timestamp value such as that returned by `strtotime()`,
* if null it is set to `time()`.
* @param string $sep separator between time and meridian, defaults to '&nbsp'.
*/
function getTimeDisplay($timestamp=null, $sep='&nbsp;') {
	if (is_null($timestamp)) { $timestamp = time(); }
	$test24h = date('H:i', $timestamp);
	if ($test24h == '12:00') { return 'noon'; }
	else if ($test24h == '00:00') { return 'midnight'; }
	$test = date('g:i', $timestamp);
	return implode('', [
		( '00' == substr($test, -2) ?  substr($test, 0, -3) : $test ),
		$sep,
		'<span class="ampm">',
		date('a', $timestamp),
		'</span>',
	]);
}

/**
* Returns the date formatted as: "Mon 3 Jun 1997". The format string is "D j M
* Y". If time is "00:00" (midnight), which the computer considers the beginning
* of a day, we instead return the day before, considering it the _end_ of the
* previous day.
* @param int $timestamp integer timestamp such as that returned by strtotime(),
* if null it is set to time()
*/
function getDateDisplay($timestamp=null) {
	if (is_null($timestamp)) { $timestamp = time(); }
	if (date('H:i', $timestamp) == '00:00') {
		$timestamp = strtotime('-1 day', $timestamp);
	}
	return date('D j M Y', $timestamp);
}

/**
* Returns a date in standard database format: 2016-03-16 14:03:23.
* @param int $timestamp integer timestamp such as that returned by strtotime(),
* defaults to `null` which will be replaced with the current time()
* @param string $fmt date format string to use, defaults to 'Y-m-d H:i:s'
*/
function dbDate($timestamp=null, $fmt='Y-m-d H:i:s') {
	if (is_null($timestamp)) { $timestamp = time(); }
	return date($fmt, $timestamp);
}

/**
* Returns a string explaining the time between the $timestamp and now. The gap
* is described in units (years, months, weeks, days, etc.) that are the best
* match for the size of the gap. Simplicity is preferred over precision. So, for
* instance, a $timestamp from 7 years, 3 months and 2 days ago will return "7
* years ago". Smaller quantities are preferred, so it won't return "25 days",
* but instead will return "3 weeks"; and "12 weeks" will turn into "3 months".
* @param int $timestamp integer timestamp such as that returned by strtotime()
* @todo Should include a second parameter that can override 'now'.
*/
function getTimeIntervalDisplay($timestamp) {
	$then = new \DateTime(); $then->setTimestamp($timestamp);
	$now = new \DateTime();
	$interval = $now->diff($then);
	$seconds = abs($timestamp-$now->getTimestamp());
	$string = '';
	if ($interval->y > 1) { $string = sprintf('%d years', $interval->y); }
	else if (($months = $interval->m+12*$interval->y) > 2) {
		$string = sprintf('%d months', $months);
	}
	else if ($interval->days > 13) {
		$string = sprintf('%d weeks', $interval->days/7);
	}
	else if ($interval->days > 1) {
		$string = sprintf('%d days', $interval->days);
	}
	else if ($seconds >= 7200) {
		$string = sprintf('%d hours', (int)($seconds/3600));
	}
	else if ($seconds >= 120) {
		$string = sprintf('%d minutes', (int)($seconds/60));
	}
	else if ($seconds > 1) { $string = sprintf('%d seconds', $seconds); }
	else { return 'now'; }
	return ( $interval->invert ? $string . ' ago' : 'in ' . $string );
}

/**
* Returns a string with the time date display and the interval: "7:23 Monday 5
* December 2006 (4 years ago)".
* @param string $datestring string representation of a date
* @uses getTimeDateDisplay(), getTimeIntervalDisplay()
*/
function getDateAndIntervalDisplay($datestring) {
	if (!$datestring) { return ''; }
	$stamp = strtotime($datestring);
	return implode('', [
		getTimeDateDisplay($stamp, false),
		' (',
		getTimeIntervalDisplay($stamp),
		')',
	]);
}

//
// !Custom HTML element processing
//

/**
* Repeatedly searches text for an element with custom opening and closing tags, and then calls the $replace function to substitute the text.
* The "tags" will typically look like custom HTML tags, but can technically be any text.
* The $replacer function should take a single string argument, which will be the text in between the opening and closing tags.
* The tags are not passed to $replacer. The result returned by $replacer will replace the original tags and content.
* @param string $string the text to search
* @param string $open_tag the custom opening tag
* @param string $close_tag the custom closing tag
* @param callable $replacer a function to replace the found text
*/
function scanElement($string, $open_tag, $close_tag, $replacer) {
	while (false !== ($pos = strpos($string, $open_tag))) {
		// Find the closing tag and process the text inbetween.
		if (false === ($close = strpos($string, $close_tag, $pos))) { break; }
		$target = substr($string, $pos+strlen($open_tag), $close-($pos+strlen($open_tag)));
		$elem = $replacer($target);
		$string = substr_replace($string, $elem, $pos, $close+strlen($close_tag)-$pos);
	}
	return $string;
}

const DCODE_OPEN_TAG = '<!--encode>';
const DCODE_CLOSE_TAG = '</encode-->';

/**
* Replaces text in a string with a scrambled version and a javascript function to descramble it.
* The string can contain multiple, non-nested instances of sensitive text surrounded by the DCODE OPEN and DCODE CLOSE tags, each instance will be replaced.
* @param string $string the string which contains special tags to replace with the scrambler
* @param bool $addNoScript flag to control whether additional <noscript> element is added
* @uses scanElement()
*/
function dcode($string, $addNoScript=true) {
	$replacer = function($shifted) use ($addNoScript) {
		// Shift all 95 printable ASCII characters from 32 (space) to 126 (tilde) by some random amount, wrapping back around to the start if we fall off the upper end.
		$shift = mt_rand(5, 90);
		$shifted = preg_replace_callback('/[\x20-\x7E]/', function ($m) use ($shift) { return chr( 126>=($c=ord($m[0])+$shift) ? $c : $c-95); }, $shifted);
		$coded = '<script type="text/javascript">' . PHP_EOL . '//<![CDATA[' . PHP_EOL . 'document.write("'. addslashes($shifted) . '".replace(/[\x20-\x7E]/g,function(c){return String.fromCharCode(126>=(c=c.charCodeAt(0)+' . (95-$shift) . ')?c:c-95);}));' . PHP_EOL . '//]]>' . PHP_EOL . '</script>';
		if ($addNoScript) { $coded .= '<noscript><span class="bmatch">[enable javascript to view this content]</span></noscript>'; }
		return $coded;
	};
	return scanElement($string, DCODE_OPEN_TAG, DCODE_CLOSE_TAG, $replacer);
}

//
// !Email
//

/** Send an email. */
function sendEmail($message, $headers, $stageTo) {
	$lc_headers = array_change_key_case($headers);
	if (!array_key_exists('date', $lc_headers)) { $headers['Date'] = date('r'); }
	if (!array_key_exists('from', $lc_headers)) { $headers['From'] = $GLOBALS[g_POSTMASTER]; }
	if (!array_key_exists('sender', $lc_headers)) { $headers['Sender'] = $GLOBALS[g_POSTMASTER]; }
	if (isStageRegion()) {
		$origToKey = "X-{$GLOBALS[g_SITEABBREV]}-Original-To";
		$headers[$origToKey] = $headers['To'];
		$headers['To'] = $stageTo; // Override 'To' in stage mode.
	}
	if (!array_key_exists('content-type', $lc_headers)) { $headers['Content-Type'] = 'text/plain; charset=ISO-8859-1'; }
	if (!array_key_exists('mime-version', $lc_headers)) { $headers['MIME-Version'] = '1.0'; }
	//NOTE: Flag -f sets the envelope sender. Without this, the envelope sender
	//becomes the user running the script ("dac" or "www-data"), which is fine
	//for delivery, and maybe fine for replies, but it is definitely NOT fine for
	//bounces, which disappear.
	$parsed = \mailparse_rfc822_parse_addresses($headers['From']);
	if ($parsed) { $envelope_sender = $parsed[0]['address']; }
	else { $envelope_sender = $GLOBALS[g_POSTMASTER]; }
	$smtp = \Mail::factory('mail', '-f' . $envelope_sender);
	$res = $smtp->send($headers['To'], $headers, $message);
	if (\PEAR::isError($res)) {
		throw new \Exception('Error sending email `' . $headers['Subject'] . '` to `' . $headers['To'] . '` from `' . $headers['From'] . '`. SMTP error: ' . $res->getMessage());
	}
}
