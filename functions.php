<?php

/**
 * Basic CURL handler
 *
 * @param $url string Page URL
 * @return bool|string
 */
function get_page(string $url)
{
	// create curl resource
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, $url);

	//	curl_setopt($ch, CURLOPT_PROXY, '1.1.1.1:8888'); // TODO add proxy list if IP was blocked
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.66 Safari/537.36');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, 1);

	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_MAXREDIRS, 3);

	curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
	curl_setopt($ch, CURLOPT_TCP_NODELAY, TRUE);

	// wait for 1.5 minutes before page loads
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 90000);
	curl_setopt($ch, CURLOPT_TIMEOUT_MS, 90000);

    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    var_dump($http_code);
    // if something goes wrong
    if ($http_code !== 200)
        return FALSE;

    // close curl resource to free up system resources
    curl_close($ch);

    return $html;
}

/**
 * @return Google_Service_Sheets
 * @throws \Google\Exception
 */
function get_gsheets_obj()
{
	$client = new \Google_Client();
	$client->setApplicationName('Google Sheets and PHP');
	$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
	$client->setAccessType('offline');
	$client->setAuthConfig(__DIR__ . '/credentials.json');

	return new Google_Service_Sheets($client);
}