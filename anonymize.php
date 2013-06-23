<?php

//
// Anonymize data
// Later on we will do this once, save the result to a JSON file and delete all original data.
// For now use the original data so people can see the current state.
//

$participant_count = count(glob("../../data/*.json"));
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

file_put_contents("../../data/data.json", json_encode($data));

?>