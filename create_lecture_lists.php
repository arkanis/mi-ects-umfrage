<?php

/**

This script fetches the lecture lists for each course in the `$course_ids` array and saves
them as JSON.

The `php5-tidy` package is required. The HdM websites markup is broken (end tags for
table elements missing) so we need tidy to repair it first. Otherwise the DOM loadHTML
function drifts into insanity...

*/

$course_ids = array(
	'Computer Science and Media (Master)' => '350013',
	'Medieninformatik (Bachelor)' => '250009',
	'Medieninformatik (Bachelor, 7 Semester)' => '550033',
	'Medienwirtschaft (Bachelor)' => '250006',
	'Medienwirtschaft (Bachelor, 7 Semester)' => '550039'
);

function tidy_and_parse_html_page($url, $xpath_selector){
	$context = stream_context_create(array(
		'http' => array(
			'header' => array(
				'Accept: text/html'
			),
			'user_agent' => 'HdM MI ECTS Umfrage/1.0'
		),
		'ssl' => array(
			'verify_peer' => false
		)
	));
	
	// Fetch the HTML and repair it with tidy
	$html_source = file_get_contents($url, false, $context);
	$html_source = tidy_repair_string($html_source);
	
	$doc = DOMDocument::loadHTML($html_source);
	$xpath = new DOMXPath($doc);
	$node_of_interest = $xpath->query($xpath_selector)->item(0);
	
	return simplexml_import_dom($node_of_interest);
}


foreach($course_ids as $course_name => $course_id) {
	echo("Fetching list for $course_name...\n");
	$list = array();
	
	// TdiyXML merges all the broken tables within the DIV into one TABLE element. Fetch it.
	$merged_lecture_table = tidy_and_parse_html_page("https://www.hdm-stuttgart.de/studenten/stundenplan/studieninhalte/studiengang?sgang_ID=$course_id&alles=1", "//div[@id='center_content']/div/table");

	foreach($merged_lecture_table->tr as $tr) {
		// Skip layout parts of the table (just general stuff in there), the header row
		// and rows with less than 5 TD elements
		if ($tr->td['colspan'] !== null or $tr->th[0] !== null or $tr->td[4] === null)
			continue;
		
		$edvnr = trim($tr->td[0]);
		$list[$edvnr] = array(
			// Many names contain line breaks and muliple spaces. So collapse the whitespaces in there.
			'name' => preg_replace('/\s+/', ' ', trim($tr->td[1]->a)),
			'ects' => trim($tr->td[4])
		);
	}
	
	file_put_contents($course_name . '.json', json_encode($list));
}

?>