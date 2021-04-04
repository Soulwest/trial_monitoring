<?php
set_time_limit(0);
libxml_use_internal_errors(TRUE);

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/functions.php';

$courts = [
	'Ленинский' => 'https://lenin--perm.sudrf.ru/modules.php?name=sud_delo',
	'Дздержинский' => 'https://dzerjin--perm.sudrf.ru/modules.php?name=sud_delo',
	'Индустриальный' => 'https://industry--perm.sudrf.ru/modules.php?name=sud_delo',
	'Свердловский' => 'https://sverdlov--perm.sudrf.ru/modules.php?name=sud_delo',
	'Орджоникидзовский' => 'https://ordgonik--perm.sudrf.ru/modules.php?name=sud_delo&srv_num=1',
	'Мотовилихинский' => 'https://motovil--perm.sudrf.ru/modules.php?name=sud_delo',
	'Кировский' => 'https://kirov--perm.sudrf.ru/modules.php?name=sud_delo',
	'Краевой' => 'https://oblsud--perm.sudrf.ru/modules.php?name=sud_delo&srv_num=1',
];
$courts_retry_count = 3; // How many time we'll try to get data from URL.

// Which laws we try to search
$laws = [
	'20.2',
	// '20.6.1'
	//'228.1'
];

// Randomly pick date (0-4 days from today)
$query_date = date('d.m.Y', time() + mt_rand(0, 10) * 86400);
echo 'Check cases at '.$query_date."\n";

// Google sheets object and settings
$service = get_gsheets_obj();
$spreadsheet_id = "1r7P9cX8r6m5RE335-1i1B2fW_C76DaqUdPX1WgWCClE";
$spreadsheet_page = 'Лист1';

foreach ($courts as $court_name => $court_url)
{
	// Fetch page, try several times because servers return HTTP errors
	$i = 0;
	$court_url .= '&H_date='.$query_date;
	do
	{
		$i++;
		echo 'GET: '.$court_url."\n";
		$html = get_page($court_url);
	} while ($i < $courts_retry_count and ! $html);

	// if page didn't fetch, then stop
	if ( ! $html)
	{
		continue;
	}

	// Parse page
	$dom = new DOMDocument;
	$dom->strictErrorChecking = FALSE;
	$dom->loadHTML($html);

	$table = $dom->getElementById('tablcont');
	if ($table === NULL)
	{
		continue;
	}
	$rows = $table->getElementsByTagName("tr");

	// Get cases from G.docs
	$added_cases = [];
	$response = $service->spreadsheets_values->get($spreadsheet_id, $spreadsheet_page.'!A2:A999', ['valueRenderOption' => 'FORMATTED_VALUE']);
	foreach ((array) $response->values as $value)
	{
		$added_cases[] = $value[0];
	}

	$cases = [];
	foreach ($rows as $row)
	{
		$cols = $row->getElementsByTagName("td");
		if ($cols->item(6) === NULL)
		{
			//filter table headers
			continue;
		}

		$cases[] = [
			//$cols->item(0)->nodeValue,
			$cols->item(1)->nodeValue, // Num
			$query_date, // date
			$cols->item(2)->nodeValue, // Time
			$court_name.' суд '.$cols->item(3)->nodeValue, // Where?
			$cols->item(4)->nodeValue, // About
			$cols->item(5)->nodeValue, // Judge
			$cols->item(6)->nodeValue, // Result
			$cols->item(7)->nodeValue, // Acts
		];
	}

	// Filtering cases
	$filtered_cases = [];
	foreach ($cases as $case)
	{
		$should_append_case = FALSE;
		foreach ($laws as $law)
		{
			if (preg_match('~'.preg_quote($law).' ~msi', $case[4]))
			{
				$should_append_case = TRUE;
				break;
			}
		}

		if ($should_append_case and ! in_array($case[0], $added_cases))
		{
			$filtered_cases[] = $case;
		}
	}

	// Save to G.docs
	// https://www.srijan.net/blog/integrating-google-sheets-with-php-is-this-easy-know-how
	// https://codd-wd.ru/primery-google-sheets-tablicy-api-php/
	$body = new Google_Service_Sheets_ValueRange([
		'values' => $filtered_cases,
	]);
	$service->spreadsheets_values->append($spreadsheet_id, $spreadsheet_page, $body, [
		'valueInputOption' => 'USER_ENTERED',
	]);

}