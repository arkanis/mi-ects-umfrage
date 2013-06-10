<?php

$user = 'xy123';
$pass = 'secret';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$user_data = array();
	foreach($_POST as $edvnr => $ects) {
		if ( !empty($ects) )
			$user_data[$edvnr] = $ects;
	}
	file_put_contents("data/$user.json", json_encode($user_data));
	
	$proto = $_SERVER['HTTPS'] ? 'https' : 'http';
	$server = $_SERVER['SERVER_NAME'];
	$path = $_SERVER['PHP_SELF'];
	header("Location: $proto://$server$path", true, 303);
	exit();
}

function parse_html_page($url, $xpath_selector){
	global $user, $pass;
	
	$context = stream_context_create(array(
		'http' => array(
			'header' => array(
				'Accept: text/html',
				'Authorization: Basic ' . base64_encode("$user:$pass")
			),
			'user_agent' => 'HdM MI ECTS Umfrage/1.0'
		),
		'ssl' => array(
			'verify_peer' => false
		)
	));
	
	$html_source = file_get_contents($url, false, $context);
	$doc = @DOMDocument::loadHTML($html_source);
	$xpath = new DOMXPath($doc);
	$node_of_interest = $xpath->query($xpath_selector)->item(0);
	
	return simplexml_import_dom($node_of_interest);
}

$lecture_list = parse_html_page('https://www.hdm-stuttgart.de/studenten/stundenplan/pers_stundenplan', "//div[@id='center_content']/div/table[1]");

$personal_lectures = array();
foreach($lecture_list->tr as $tr) {
	// Skip header (it has no 7th TD element, only TH elements)
	if ($tr->td[6] === null)
		continue;
	
	$edvnr = trim($tr->td[1]);
	$name = trim($tr->td[2]->a);
	$ects = trim($tr->td[6]);
	
	$personal_lectures[$edvnr] = array(
		'name' => $name,
		'ects' => $ects
	);
}

// Sort lectures by name
uasort($personal_lectures, function($a, $b){
	if ( strtolower($a['name']) == strtolower($b['name']) )
		return 0;
	return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
});

// The course name is shown on the page to change the personal data...
// Fetch it from there and use it to load the prepared lecture list
$course_name = parse_html_page('https://www.hdm-stuttgart.de/studenten/stundenplan/pers_stundenplan/pers_daten', "//div[@id='center_content']//label[@for='sgang_wahl']");
$course_name = trim(preg_replace('/^Ihr Studiengang\: /', '', $course_name));
$course_lectures = json_decode(@file_get_contents($course_name . '.json'), true);

// Remove course lectures already in the personal lecture list
foreach($course_lectures as $evdnr => $lecture){
	if ( array_key_exists($edvnr, $personal_lectures) )
		unset($course_lectures[$edvnr]);
}

// Sort lectures by name
uasort($course_lectures, function($a, $b){
	if ( strtolower($a['name']) == strtolower($b['name']) )
		return 0;
	return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
});

// Read previous user data if it exists
$user_data_json = @file_get_contents("data/$user.json");
if ($user_data_json)
	$user_data = json_decode($user_data_json, true);
else
	$user_data = array();

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>MI ECTS Umfrage</title>
	<meta name="author" content="Stephan Soller <ss312@hdm-stuttgart.de>">
	<style>
		html, body { margin: 0; padding: 0; }
		body { padding: 1em; color: #333; font-size: small; font-family: sans-serif; }
		
		h1 { font-size: 1.5em; margin: 1em 0 0.5em 0; }
		h1:first-of-type { margin-top: 0; }
		
		table { table-layout: fixed; }
		table > tr > th:nth-child(0) { width: 5em; }
		table > tr:nth-child(odd) > td { background-color: gray; }
		
		tr textarea { display: none; }
		tr.not-empty textarea { display: block; }
		input:invalid { border: 1px solid red; }
	</style>
	<script src="jquery-2.0.2.min.js"></script>
	<script>
		$(document).ready(function(){
			var change_handler = function(){
				if ( $(this).val() != '' )
					$(this).closest('tr').addClass('not-empty');
				else
					$(this).closest('tr').removeClass('not-empty');
			};
			$('input').keyup(change_handler).change(change_handler).each(change_handler);
		});
	</script>
</head>
<body>

<form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
	<h1>Vorlesungen im persönlichen Stundenplan</h1>
	
	<table>
		<tr>
			<th>EDV-Nr.</th>
			<th>Vorlesung</th>
			<th>Bisherige ECTS</th>
			<th>Realistische ECTS</th>
		</tr>
<?		foreach($personal_lectures as $edvnr => $lecture): ?>
		<tr>
			<td><?= $edvnr ?></td>
			<td><?= $lecture['name'] ?></td>
			<td><?= $lecture['ects'] ?></td>
			<td>
				<input type="number" name="<?= $edvnr ?>" value="<?= @$user_data[$edvnr] ?>" />
				<textarea name="<?= $edvnr ?>_reason" placeholder="Optional: Begründung warum die ECTS zu hoch oder zu niedrig sind"><?= @$user_data[$edvnr . '_reason'] ?></textarea>
			</td>
		</tr>
<?		endforeach ?>
	</table>
	
	<h1>Restliche Vorlesungen von <?= $course_name ?></h1>
	
	<table>
		<tr>
			<th>EDV-Nr.</th>
			<th>Vorlesung</th>
			<th>Bisherige ECTS</th>
			<th>Realistische ECTS</th>
		</tr>
<?		foreach($course_lectures as $edvnr => $lecture): ?>
		<tr>
			<td><?= $edvnr ?></td>
			<td><?= $lecture['name'] ?></td>
			<td><?= $lecture['ects'] ?></td>
			<td>
				<input type="number" name="<?= $edvnr ?>" value="<?= @$user_data[$edvnr] ?>" />
				<textarea name="<?= $edvnr ?>_reason" placeholder="Optional: Begründung warum die ECTS zu hoch oder zu niedrig sind"><?= @$user_data[$edvnr . '_reason'] ?></textarea>
			</td>
		</tr>
<?		endforeach ?>
	</table>
	
	<p>
		<button>Speichern</button>
	</p>
</form>

</body>
</html>
