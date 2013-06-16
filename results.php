<?php

//
// Anonymize data
// Later on we will do this once, save the result to a JSON file and delete all original data.
// For now use the original data so people can see the current state.
//

$data = array();
foreach( glob("../../data/*.json") as $path ){
	$user_data = json_decode( file_get_contents($path), true );
	$filtered_user_data = array();
	
	foreach($user_data as $key => $value){
		// $edvnr_ps
		// $edvnr_reason
		// $edvnr_ps_forced
		// $edvnr
		if ( !preg_match('/^([^_]+)(_ps|_reason|_ps_forced||_reason)$/', $key, $matches) )
			die("unkown key: $key!\n");
		
		list(, $edvnr, $suffix) = $matches;
		$lecture_data = isset($filtered_user_data[$edvnr]) ? $filtered_user_data[$edvnr] : array(
			'ects' => null,
			'reason' => null,
			'personal_schedule' => false,
			'forced' => false
		);
		
		switch($suffix){
		case '_ps':
			$lecture_data['ects'] = intval($value);
			$lecture_data['personal_schedule'] = true;
			break;
		case '':
			$lecture_data['ects'] = intval($value);
			break;
		case '_reason':
			$lecture_data['reason'] = $value;
			break;
		case '_ps_forced':
			$lecture_data['forced'] = true;
			break;
		}
		
		$filtered_user_data[$edvnr] = $lecture_data;
	}
	
	foreach($filtered_user_data as $edvnr => $lecture_data){
		if ( !isset($data[$edvnr]) )
			$data[$edvnr] = array();
		
		if ( $lecture_data['ects'] !== null )
			$data[$edvnr][] = $lecture_data;
	}
}

// Shuffle lecture data of users to avoid identification via user name guessing and cross referencing
foreach($data as $edvnr => &$lecture_data)
	shuffle($lecture_data);


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
		if ( $a['mean_diff'] == $b['mean_diff'] )
			return 0;
		return ($a['mean_diff'] > $b['mean_diff']) ? -1 : 1;
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
		a { color: inherit; }
		a:hover, a:focus, a:active { color: black; }
		
		table { table-layout: fixed; border-collapse: collapse; }
		table tr:nth-of-type(4n+2), table tr:nth-of-type(4n+3) { background-color: hsl(0, 0%, 95%); }
		
		table th:nth-of-type(1) { width: 5em; }
		table th:nth-of-type(2) { width: 32.5em; }
		table th:nth-of-type(3), table th:nth-of-type(4) { width: 6.5em; }
		table th:nth-of-type(5) { width: 9em; }
		table th:nth-of-type(6) { width: 6.5em; }
		table tr.records td { width: 66em; }
		
		table td { padding: 0.25em 0; }
		table td:nth-of-type(3), table td:nth-of-type(4), table td:nth-of-type(5) { text-align: center; }
		
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
		table tr.records ul li p { margin: 0 0 0 7.5em; padding: 0; min-height: 3em; }
		table tr.records ul li p.empty { color: gray; font-style: italic; }
	</style>
	<script src="jquery-2.0.2.min.js"></script>
	<script src="jquery.sparkline.min.js"></script>
	<script>
		$(document).ready(function(){
			$('.distribution').sparkline('html', {
				type: 'bar',
				tooltipFormatter: function(sparkline, options, fields){
					return fields[0].value + ' Stimmen für ' + fields[0].offset + ' ECTS';
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

<? foreach($courses as $course_name => $lectures): ?>
<h2><?= $course_name ?></h2>

<table>
	<tr>
		<th>EDV-Nr.</th>
		<th>Vorlesung</th>
		<th>ECTS eingetragen</th>
		<th><abbr title="Durschnittliche ECTS aller Einträge von Studenten">ECTS Durchschnitt</abbr></th>
		<th>Abweichung vom Durchschnitt</th>
		<th>Verteilung</th>
	</tr>
<?	foreach($lectures as $edvnr => $lecture): ?>
	<tr>
		<td><?= $edvnr ?></td>
		<td><a href="#"><?= $lecture['name'] ?></a></td>
		<td><?= $lecture['ects'] ?></td>
		<td><?= $lecture['mean_ects'] ?></td>
		<td><?= round($lecture['mean_diff'] * 100) ?>%</td>
		<td class="distribution"><?= join(',', $lecture['distribution']) ?></span>
	</tr>
	<tr class="records">
		<td colspan="6">
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