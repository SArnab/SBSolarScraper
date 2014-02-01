<?php
ini_set("memory_limit","256M");
set_time_limit(0);
error_reporting(E_ALL);
require("lib/solarapi.php");
require("lib/mysql.php");

$mysql = new mySQL('127.0.0.1','solarplus','root','');

echo "<pre>";

$api = new SolarAPI();

/*$subjects = $api->subjectList("9");

foreach($subjects as $details){
	mysql_query("INSERT INTO subjects (acronym,name) VALUES('".$details['acronym']."','".$details['name']."')") or die(mysql_error());
}*/

$statement = $mysql->query("SELECT id,acronym FROM subjects WHERE id > 397");
$statement->execute();
$subjects = $statement->fetchAll();
for($i = 0; $i < count($subjects); $i++){
	$subjectId = $subjects[$i]['id'];
	$acronym = $subjects[$i]['acronym'];
	$api->setSubject($acronym);
	$courses = $api->findAllClassesInSubject();
	foreach($courses as $courseName => $courseInfo){

		$courses = $mysql->prepare("REPLACE INTO courses (number,subjectId,name) VALUES (:number,:subjectId,:name)");
		$courses->execute(array(
			':number'	=> $courseInfo['number'],
			':subjectId'=> $subjectId,
			':name'		=> $courseInfo['name']
		));

		foreach($courseInfo['sections'] as $section){
			$api->printDebug("Attempting to add section ".$section['id']." of class ".$courseInfo['name']);
			$statement = $mysql->prepare("
				 REPLACE INTO
					sections
					(id,courseNumber,sectionNumber,type,subjectId,instructor,location,meetingTime,monStart,monEnd,tueStart,tueEnd,wedStart,wedEnd,thuStart,thuEnd,friStart,friEnd,satStart,satEnd,sunStart,sunEnd)
				VALUES
					(:id,:courseNumber,:sectionNumber,:type,:subjectId,:instructor,:location,:meetingTime,:monStart,:monEnd,:tueStart,:tueEnd,:wedStart,:wedEnd,:thuStart,:thuEnd,:friStart,:friEnd,:satStart,:satEnd,:sunStart,:sunEnd)
			");

			$result = $statement->execute(array(
				':id'			=> $section['id'],
				':courseNumber'	=> $courseInfo['number'],
				':sectionNumber'=> $section['number'],
				':type'			=> $section['type'],
				':subjectId'	=> $subjectId,
				':instructor'	=> $section['instructor'],
				':location'		=> $section['location'],
				':meetingTime'	=> $section['time'],
				':monStart'		=> (isset($section['monStart']) ? $section['monStart'] : NULL),
				':monEnd'		=> (isset($section['monEnd']) ? $section['monEnd'] : NULL),
				':tueStart'		=> (isset($section['tueStart']) ? $section['tueStart'] : NULL),
				':tueEnd'		=> (isset($section['tueEnd']) ? $section['tueEnd'] : NULL),
				':wedStart'		=> (isset($section['wedStart']) ? $section['wedStart'] : NULL),
				':wedEnd'		=> (isset($section['wedEnd']) ? $section['wedEnd'] : NULL),
				':thuStart'		=> (isset($section['thuStart']) ? $section['thuStart'] : NULL),
				':thuEnd'		=> (isset($section['thuEnd']) ? $section['thuEnd'] : NULL),
				':friStart'		=> (isset($section['friStart']) ? $section['friStart'] : NULL),
				':friEnd'		=> (isset($section['friEnd']) ? $section['friEnd'] : NULL),
				':satStart'		=> (isset($section['satStart']) ? $section['satStart'] : NULL),
				':satEnd'		=> (isset($section['satEnd']) ? $section['satEnd'] : NULL),
				':sunStart'		=> (isset($section['sunStart']) ? $section['sunStart'] : NULL),
				':sunEnd'		=> (isset($section['sunEnd']) ? $section['sunEnd'] : NULL)
			));
			if($result){
				$api->printDebug("Section ".$section['id']." added.");
			} else {
				$api->printDebug($statement->errorInfo()[2]);
				print_r($section);
				die();
			}
		}
	}
}
?>