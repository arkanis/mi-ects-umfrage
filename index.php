<?php

$user = basename($_SERVER['PHP_AUTH_USER']);
$pass = $_SERVER['PHP_AUTH_PW'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$user_data = array();
	foreach($_POST as $edvnr => $ects) {
		if ( !empty($ects) )
			$user_data[strval($edvnr)] = $ects;
	}
	file_put_contents("../../data/$user.json", json_encode($user_data));
	
	$proto = $_SERVER['HTTPS'] ? 'https' : 'http';
	$server = $_SERVER['SERVER_NAME'];
	$path = $_SERVER['PHP_SELF'];
	header("Location: $proto://$server$path?saved", true, 302);
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
$course_lectures = json_decode(@file_get_contents(urlencode($course_name) . '.json'), true);

// Remove course lectures already in the personal lecture list
foreach($personal_lectures as $edvnr => $lecture)
	unset($course_lectures[$edvnr]);

// Sort lectures by name
uasort($course_lectures, function($a, $b){
	if ( strtolower($a['name']) == strtolower($b['name']) )
		return 0;
	return (strtolower($a['name']) < strtolower($b['name'])) ? -1 : 1;
});

// Read previous user data if it exists
$user_data_json = @file_get_contents("../../data/$user.json");
if ($user_data_json)
	$user_data = json_decode($user_data_json, true);
else
	$user_data = array();

// Prevent browsers from caching the page. Neccessary because we insert the last user
// input into the HTML directly.
header('Cache-Control: no-cache');
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>MI ECTS Liste</title>
	<meta name="author" content="Stephan Soller <ss312@hdm-stuttgart.de>">
	<style>
		html, body { margin: 0; padding: 0; }
		body { padding: 1em; color: #333; font-size: small; font-family: sans-serif; }
		
		h1 { font-size: 1.75em; margin: 0; padding: 0; }
		h2 { font-size: 1.5em; margin: 1em 0 0.5em 0; }
		h2:first-of-type { margin-top: 0; }
		
		table { table-layout: fixed; border-collapse: collapse; }
		table tr:nth-of-type(even) { background-color: hsl(0, 0%, 95%); }
		table th:nth-of-type(1) { width: 5em; }
		table th:nth-of-type(2) { width: 32.5em; }
		table th:nth-of-type(3) { width: 6.5em; }
		table th:nth-of-type(4) { width: 6.5em; }
		table th:nth-of-type(5) { width: 10em; }
		
		table td:nth-of-type(3), table td:nth-of-type(5) { text-align: center; }
		table td { vertical-align: top; padding: 0.25em 0; }
		table td input[type="number"] { display: block; width: 5em; }
		table td input:invalid { border-color: red; background-color: hsl(0, 50%, 95%); }
		
		table td textarea { display: none; }
		table tr.not-empty td textarea { display: block; width: 44.25em; min-height: 4em; margin: 0.25em 0 0 -39.25em; }
		
		input, textarea { font: inherit; font-size: 1em; box-sizing: border-box; margin: 0; padding: 1px; border: 1px inset gray; white-space: normal; }
		
		kbd { padding: 1px 2px; border: 1px outset hsl(0, 0%, 75%); background: hsl(0, 0%, 95%); }
		form { margin: 1em 0; padding: 0 0 1.5em 0; }
		form footer { margin: 0.5em 0; padding: 0; }
		form p:last-child { position: fixed; left: 0; right: 0; bottom: 0; margin: 0; padding: 0.5em;
			background: white; box-shadow: 0 0 10px black; }
		form p:last-child em { color: hsl(0, 50%, 25%); }
		form p:last-child a { float: right; line-height: 1.75em; }
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
			$('input[type=number]').keyup(change_handler).change(change_handler).each(change_handler);
			
			$('form p:last-child em').delay(3000).fadeOut(3000);
		});
	</script>
</head>
<body>

<h1>MI ECTS Liste</h1>

<p>Bitte benutzt die Browsersuche (<kbd>Strg</kbd> + <kbd>f</kbd>) um schnell eine Vorlesung zu finden.</p>
<p>Du kannst die Seite beliebig oft aufrufen, ändern und speichern (Button unten).</p>

<form action="<?= $_SERVER['PHP_SELF'] ?>" method="POST">
	<h2>Vorlesungen im persönlichen Stundenplan</h2>
	
	<table>
		<tr>
			<th>EDV-Nr.</th>
			<th>Vorlesung</th>
			<th>Bisherige ECTS</th>
			<th>Realistische ECTS</th>
			<th>Nur belegt um Punkte zu sammeln</th>
		</tr>
<?		foreach($personal_lectures as $edvnr => $lecture): ?>
		<tr>
			<td><?= $edvnr ?></td>
			<td><?= $lecture['name'] ?></td>
			<td><?= $lecture['ects'] ?></td>
			<td>
				<input type="number" name="<?= $edvnr ?>_ps" value="<?= @$user_data[$edvnr . '_ps'] ?>" />
				<textarea name="<?= $edvnr ?>_reason" placeholder="Optionale Bemerkung, z.B. Begründung warum die ECTS zu hoch oder zu niedrig sind, usw."><?= @$user_data[$edvnr . '_reason'] ?></textarea>
			</td>
			<td>
				<input type="checkbox" value="y" name="<?= $edvnr ?>_ps_forced"<? if( isset($user_data[$edvnr . '_ps_forced']) ): ?> checked="checked"<? endif ?> />
			</td>
		</tr>
<?		endforeach ?>
	</table>
	
	<h2>Restliche Vorlesungen von <?= $course_name ?></h2>
	
	<table>
		<tr>
			<th>EDV-Nr.</th>
			<th>Vorlesung</th>
			<th>Bisherige ECTS</th>
			<th>Realistische ECTS</th>
			<th>Nur belegt um Punkte zu sammeln</th>
		</tr>
<?		foreach($course_lectures as $edvnr => $lecture): ?>
		<tr>
			<td><?= $edvnr ?></td>
			<td><?= $lecture['name'] ?></td>
			<td><?= $lecture['ects'] ?></td>
			<td>
				<input type="number" name="<?= $edvnr ?>" value="<?= @$user_data[$edvnr] ?>" />
				<textarea name="<?= $edvnr ?>_reason" placeholder="Optionale Bemerkung, z.B. wann du die Vorlesung besucht hast, Begründung warum die ECTS zu hoch oder zu niedrig sind, usw."><?= @$user_data[$edvnr . '_reason'] ?></textarea>
			</td>
			<td>
				<input type="checkbox" value="y" name="<?= $edvnr ?>_ps_forced"<? if( isset($user_data[$edvnr . '_ps_forced']) ): ?> checked="checked"<? endif ?> />
			</td>
		</tr>
<?		endforeach ?>
	</table>
	
	<footer>Source code der Liste ist <a href="https://github.com/arkanis/mi-ects-umfrage">bei GitHub online</a>.</footer>
	
	<p>
		<button>Speichern</button>
<?		if ( isset($_GET['saved']) ): ?>
		<em>gespeichert</em>
<?		endif ?>
		<a href="results.php">Ergebnisse ansehen</a>
	</p>
</form>

</body>
</html>
