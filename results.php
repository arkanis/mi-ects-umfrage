<?php

// Load anonymized data
$data = json_decode(file_get_contents("data.json"), true);

//
// Load, sort and merge course lecture lists
//

function array_merge_stringified($a, $b){
	$result = array();
	foreach($a as $key => $value)
		$result[strval($key)] = $value;
	foreach($b as $key => $value)
		$result[strval($key)] = $value;
	return $result;
}

$course_names = array(
	'Computer Science and Media (Master)',
	'Medieninformatik (Bachelor)',
	'Medieninformatik (Bachelor, 7 Semester)',
	'Mobile Medien (Studienstart vor WS11/12)',
	'Mobile Medien (Bachelor, 7 Semester)'
);
$courses = array();
foreach($course_names as $name){
	$lecture_list = json_decode(file_get_contents(urlencode($name) . '.json'), true);
	$courses[$name] = $lecture_list;
}

// Merge some courses together because they share most of the lectures anyway
$courses['Medieninformatik'] = array_merge_stringified(
	$courses['Medieninformatik (Bachelor)'],
	$courses['Medieninformatik (Bachelor, 7 Semester)']
);
unset($courses['Medieninformatik (Bachelor)']);
unset($courses['Medieninformatik (Bachelor, 7 Semester)']);

$courses['Mobile Medien'] = array_merge_stringified(
	$courses['Mobile Medien (Studienstart vor WS11/12)'],
	$courses['Mobile Medien (Bachelor, 7 Semester)']
);
unset($courses['Mobile Medien (Studienstart vor WS11/12)']);
unset($courses['Mobile Medien (Bachelor, 7 Semester)']);

//
// Calculate some statistics
//
foreach($courses as $course_name => &$lectures){
	foreach($lectures as $edvnr => &$lecture){
		$lecture['records'] = isset($data[$edvnr]) ? $data[$edvnr] : array();
		$ects_list = array_map(function($r){
			return $r['ects'];
		}, $lecture['records']);
		
		if ( count($ects_list) > 0 ) {
			$lecture['mean_ects'] = array_sum($ects_list) / count($ects_list);
			$lecture['distribution'] = array_pad(array(), round(max($ects_list)), 0);
			foreach($lecture['records'] as $record)
				$lecture['distribution'][round($record['ects'])]++;
		} else {
			$lecture['mean_ects'] = $lecture['ects'];
			$lecture['distribution'] = array();
		}
		
		if ($lecture['ects'] > 0)
			$lecture['mean_diff'] = abs($lecture['ects'] - $lecture['mean_ects']) / $lecture['ects'];
		else
			$lecture['mean_diff'] = 0;
	}
	
	// Sort by lecture name
	uasort($lectures, function($a, $b){
		if ( count($a['records']) == count($b['records']) )
			return 0;
		return (count($a['records']) > count($b['records'])) ? -1 : 1;
	});
}

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>MI ECTS Liste - Auswertung</title>
	<meta name="author" content="Stephan Soller <ss312@hdm-stuttgart.de>">
	<style>
		html, body { margin: 0; padding: 0; }
		body { padding: 1em; color: #333; font-size: small; font-family: sans-serif; }
		
		h1 { font-size: 1.75em; margin: 0; padding: 0; }
		h2 { font-size: 1.5em; margin: 1.5em 0 0 0; padding: 0; }
		a { color: inherit; }
		a:hover, a:focus, a:active { color: black; }
		
		table { table-layout: fixed; border-collapse: collapse; }
		table tr:nth-of-type(4n+2), table tr:nth-of-type(4n+3) { background-color: hsl(0, 0%, 95%); }
		
		table th:nth-of-type(1) { width: 5em; }
		table th:nth-of-type(2) { width: 32.5em; }
		table th:nth-of-type(3), table th:nth-of-type(4), table th:nth-of-type(5) { width: 6.5em; }
		table tr.records td { width: 57em; }
		
		table td { padding: 0.25em 0; }
		table td:nth-of-type(3), table td:nth-of-type(4) { text-align: center; }
		
		table tr.records ul { margin: 0 0 1em 0; padding: 0; list-style: none; }
		table tr.records ul li { position: relative; margin: 0.5em 0; padding: 0; }
		table tr.records ul li:first-of-type { margin-top: 0; }
		table tr.records ul li:last-of-type { margin-bottom: 0; }
		table tr.records ul li abbr {	position: absolute; left: 1em; top: 0; overflow: hidden; padding: 0.125em 0.25em;
			background: hsl(0, 0%, 90%); border: 2px solid hsl(0, 0%, 75%); border-radius: 5px; }
		table tr.records ul li abbr span.ects { font-size: 2em; float: left; }
		table tr.records ul li abbr span.label { display: block; margin: 0 0 0 2.5em; }
		table tr.records ul li abbr span.in_schedule { position: absolute; left: 2.75em; top: 1.25em; }
		table tr.records ul li abbr span.forced { position: absolute; left: 3.75em; top: 1.25em; }
		table tr.records ul li abbr.ps { border-style: dashed; }
		table tr.records ul li abbr.forced { background-color: hsl(0, 50%, 90%); }
		table tr.records ul li p { margin: 0 0 0 7.5em; padding: 0; min-height: 3em; white-space: pre-line; }
		table tr.records ul li p.empty { color: gray; font-style: italic; }
	</style>
	<script src="jquery-2.0.2.min.js"></script>
	<script src="jquery.sparkline.min.js"></script>
	<script>
		$(document).ready(function(){
			$('.distribution').sparkline('html', {
				type: 'bar',
				tooltipFormatter: function(sparkline, options, fields){
					return fields[0].value + ' Einträge mit ' + fields[0].offset + ' ECTS';
				}
			});
			
			$('tr.records').hide();
			$('tr a').click(function(){
				$(this).closest('tr').next('tr').toggle();
				return false;
			});
			
		});
	</script>
</head>
<body>

<h1>MI ECTS Liste - Auswertung</h1>

<p><?= $participant_count ?> Teilnehmer insgesamt</p>

<ul>
<? foreach($courses as $course_name => $lectures): ?>
	<li><a href="#<?= urlencode($course_name) ?>"><?= $course_name ?></a></li>
<?endforeach ?>
</ul>

<p>Auf Vorlesungen klicken um alle Einträge zu sehen. Legende:</p>
<ul>
	<li>Rot: Nur belegt um Punkte zu sammeln.</li>
	<li>Gestrichelter Rahmen: Momentan im Stundenplan eingetragen.</li>
</ul>

<? foreach($courses as $course_name => $lectures): ?>
<h2 id="<?= urlencode($course_name) ?>"><?= $course_name ?></h2>

<table>
	<tr>
		<th>EDV-Nr.</th>
		<th>Vorlesung</th>
		<th>ECTS eingetragen</th>
		<th>Einträge</th>
		<th>Verteilung</th>
	</tr>
<?	foreach($lectures as $edvnr => $lecture): ?>
	<tr>
		<td><?= $edvnr ?></td>
		<td><a href="#"><?= $lecture['name'] ?></a></td>
		<td><?= $lecture['ects'] ?></td>
		<td><?= count($lecture['records']) ?></td>
		<td class="distribution"><?= join(',', $lecture['distribution']) ?></span>
	</tr>
	<tr class="records">
		<td colspan="5">
			<ul>
<?				foreach($lecture['records'] as $record): ?>
				<li>
<?					if ($record['personal_schedule'] and $record['forced']): ?>
					<abbr class="ps forced" title="In persönlichen Stundenplan eingetragen (gestrichelter Rahmen)<?= "\n" ?>Nur belegt um Punkte zu sammeln (rot)">
<?					elseif ($record['personal_schedule']): ?>
					<abbr class="ps" title="In persönlichen Stundenplan eingetragen (gestrichelter Rahmen)">
<?					elseif ($record['forced']): ?>
					<abbr class="forced" title="Nur belegt um Punkte zu sammeln (rot)">
<?					else: ?>
					<abbr>
<?					endif ?>
						<span class="ects"><?= $record['ects'] ?></span>
						<span class="label">ECTS</span>
<?						if ($record['personal_schedule']): ?>
						<span class="in_schedule">S</span>
<?						endif ?>
<?						if ($record['forced']): ?>
						<span class="forced">F</span>
<?						endif ?>
					</abbr>
					
<?					if ( empty($record['reason']) ): ?>
					<p class="empty">Keine Bemerkung angegeben</p>
<?					else: ?>
					<p><?= $record['reason'] ?></p>
<?					endif ?>
				</li>
<?				endforeach ?>
			</ul>
		</td>
	</tr>
<?	endforeach ?>
</table>
<? endforeach ?>

</body>
</html>