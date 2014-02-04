<?php

require("lib/phphtml/simple_html_dom.php");

class SolarApi {

	private $ch = NULL;
	private $fh = NULL;
	private $postFields = array(
		'ICAJAX' 				=> 1,
		'ICNAVTYPEDROPDOWN' 	=> 0,
		'ICType' 				=> 'Panel',
		'ICElementNum' 			=> 0,
		'ICXPos'				=> 0,
		'ICYPos'				=> 0,
		'ResponsetoDiffFrame'	=> -1,
		'TargetFrameName'		=> 'None',
		'GSrchRaUrl'			=> 'None',
		'FacetPath'				=> 'None',
		'ICSaveWarningFilter' 	=> 0,
		'ICChanged'				=> -1,
		'ICResubmit'			=> 0,
		'ICActionPrompt'		=> 'false',
		'ICStateNum'			=> 0,
		'SSR_CLSRCH_WRK_ACAD_CAREER$2'	=> 'UGRD',
		'SSR_CLSRCH_WRK_SSR_OPEN_ONLY$3' => 'N',
		'SSR_CLSRCH_WRK_SSR_OPEN_ONLY$chk$3' => 'N'
	);
	private $response = NULL;
	public $htmlParser = NULL;
	public $contentType;
	private $fieldsToUnset = array();

	public function __construct($autostart = true){
		if($autostart === TRUE) $this->startSession();
	}

	public function __destruct(){
		curl_close($this->ch);
		fclose($this->fh);
		$fh = fopen("cookie.txt","w");
		fwrite($fh,"");
		fclose($fh);
	}

	public function initCurl(){
		$this->ch = curl_init();
		$this->fh = fopen("log.txt","w");
		curl_setopt_array($this->ch,array(
			CURLOPT_URL				=> "https://psns.cc.stonybrook.edu/psc/he90prodg/EMPLOYEE/HRMS/c/COMMUNITY_ACCESS.CLASS_SEARCH.GBL",
			CURLOPT_VERBOSE			=> true,
			CURLOPT_STDERR			=> $this->fh,
			CURLOPT_REFERER			=> "https://psns.cc.stonybrook.edu/psc/he90prodg/EMPLOYEE/HRMS/c/COMMUNITY_ACCESS.CLASS_SEARCH.GBL",
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_USERAGENT		=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.77 Safari/537.36",
			CURLOPT_HTTPHEADER		=> array('Accept-Language: en-US,en;q=0.8,zh-CN;q=0.6','Connection: keep-alive','DNT: 1','Origin: https://psns.cc.stonybrook.edu','Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_FOLLOWLOCATION	=> true,
			CURLOPT_COOKIEFILE		=> 'cookie.txt',
			CURLOPT_COOKIEJAR		=> 'cookie.txt'
		));
	}

	public function execCurl($addPostData = TRUE){
		//echo '<pre>';print_r($this->postFields);print_r($this->createPostURI());echo '</pre>';
		if($addPostData === TRUE){
			curl_setopt($this->ch,CURLOPT_POST,true);
			curl_setopt($this->ch,CURLOPT_POSTFIELDS,$this->createPostURI());
		}
		$this->response = curl_exec($this->ch);
		$this->contentType = curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);
		
		$this->ICStateNum++;

		foreach($this->fieldsToUnset as $index => $fieldKey){
			unset($this->postFields[$fieldKey]);
		}
	}

	public function addPostField($key,$value,$temp = FALSE){
		$this->postFields[$key] = $value;
		if($temp) $this->fieldsToUnset[] = $key;
	}

	private function createPostURI(){
		return http_build_query($this->postFields);
	}

	public function setAction($action){
		$this->addPostField('ICAction', $action);
	}

	private function extractICSID(){
		if(!$this->response) return FALSE;
		$htmlParser = str_get_html($this->response);

		$this->postFields['ICSID'] = $htmlParser->find("input[id=ICSID]")[0]->value;
		$htmlParser->clear();

		return $this->postFields['ICSID'];
	}

	private function extractICStateNum(){
		if(!$this->response) return FALSE;
		$htmlParser = str_get_html($this->response);


		$this->postFields['ICStateNum'] = $htmlParser->find("input[id=ICStateNum]")[0]->value;

		return $this->postFields['ICStateNum'];
	}

	public function startSession(){
		$this->printDebug("Starting browse session...");

		$this->initCurl();
		$this->execCurl(false);
		$this->extractICSID();
	}

	// Set The Subject We Are Looking For
	public function setSubject($subject){
		$this->printDebug("Setting subject to: ".$subject);

		$this->setAction("SSR_CLSRCH_WRK_SUBJECT$74$$0");
		$this->addPostField("SSR_CLSRCH_WRK_SUBJECT$74$$0",$subject,true);
		$this->execCurl();
	}

	public function setRequirementDesignation($requirement){
		$this->addPostField("SSR_CLSRCH_WRK_RQMNT_DESIGNTN$15",$requirement);
	}

	public function findAllClassesInSubject(){
		$this->printDebug("Retrieving all classes in the subject...");

		$this->setAction("CLASS_SRCH_WRK2_SSR_PB_CLASS_SRCH");
		$this->addPostField("SSR_CLSRCH_WRK_SSR_EXACT_MATCH1$1","E",true);
		$this->execCurl();
		// Bypass the "Are you sure you want to load 50+ classes" bullshit
		$this->setAction("#ICSave");
		$this->execCurl();
		
		$xml = simplexml_load_string($this->response,"SimpleXMLElement");
		$courseHTML = str_get_html((string)$xml->FIELD[2]);
		$courseList = $courseHTML->find("div[id^=win0divDERIVED_CLSRCH_DESCR200$]");
		$courses = array();
		for($i=0;$i<count($courseList);$i++){
			$courseString = $courseHTML->find('span[id=DERIVED_CLSRCH_DESCR200$'.$i.']')[0]->innertext;
			$result = preg_match("/([a-z0-9A-Z]{3})&nbsp; (\d{3}) - (.*)/", $courseString, $matches);
			if($result === 0 || $result === FALSE){
				$this->printDebug("Error in pattern matching course. Skipping to next.");
				var_dump($courseString);
				continue;
			}

			list($courseString,$courseCategory,$courseNumber,$courseTitle) = $matches;

			$courses[$courseCategory.$courseNumber] = array(
				'subject'	=> $matches[1],
				'number'	=> $matches[2],
				'name'		=> $matches[3],
				'sections'	=> array()
			);
			
			$this->printDebug("Analyzing course ".$courseTitle."...");

			// Check if we need to do a "view all sections" request
			$anchorFound = $courseHTML->find('a[id=$ICField234$hviewall$'.$i.']');
			if(!empty($anchorFound)){
				$this->printDebug("Course has additional sections. Retrieving full list...");

				$this->setAction('$ICField234$hviewall$'.$i);
				$this->execCurl();
				$xml = simplexml_load_string($this->response,"SimpleXMLElement");
				
				$newResponse = str_get_html((string)$xml->FIELD[1]);
				$courses[$courseCategory.$courseNumber]['sections'] = $this->extractClassSections($newResponse,$i);
				$newResponse->clear();
				unset($newResponse);
			}else{
				$courses[$courseCategory.$courseNumber]['sections'] = $this->extractClassSections($courseHTML,$i);
			}

			$this->printDebug("Course ".$courseTitle." analyzed and processed.");
		}
		$courseHTML->clear();

		$this->setAction("CLASS_SRCH_WRK2_SSR_PB_NEW_SEARCH");
		$this->execCurl();

		return $courses;
	}

	private function extractClassSections($html,$id){
		// Find All Sections Of The Course Represented By ID In The HTML
		$sectionHTML = str_get_html($html->find('table[id=$ICField234$scroll$'.$id.']')[0]->innertext);
		$sectionList = $sectionHTML->find('a[id^=DERIVED_CLSRCH_SSR_CLASSNAME_LONG$]');
		$instructorList = $sectionHTML->find('span[id^=MTG_INSTR$]');
		$locationList = $sectionHTML->find('span[id^=MTG_ROOM$]');
		$timeList = $sectionHTML->find('span[id^=MTG_DAYTIME$]');
		$sections = array();
		for($x = 0;$x < count($sectionList); $x++){
			/*$this->printDebug($sectionList[$x]->innertext);
			$this->printDebug("Instructor: ".$instructorList[$x]->innertext);
			$this->printDebug("Location: ".$locationList[$x]->innertext);
			$this->printDebug("Time: ".$timeList[$x]->innertext);*/

			preg_match("/([0-9]{2})-([A-Z]+)\(([0-9]+)\)/",$sectionList[$x]->innertext,$matches);

			// Process The Time
			$meetingTimes = array();
			if($timeList[$x]->innertext !== "TBA"){
				preg_match("/([a-zA-Z]+) ([0-9]+):([0-9]+)(AM|PM) - ([0-9]+):([0-9]+)(AM|PM)/",$timeList[$x]->innertext,$time);

				$startTime = $this->convertToMilitaryTime($time[2],$time[3],$time[4]);
				$endTime = $this->convertToMilitaryTime($time[5],$time[6],$time[7]);

				for($k=0; $k <= strlen($time[1]) / 2; $k += 2){
					$day = substr($time[1],$k,2);
					switch($day){
						case "Mo":
							$meetingTimes['monStart'] = $startTime;
							$meetingTimes['monEnd'] = $endTime;
							break;
						case "Tu":
							$meetingTimes['tueStart'] = $startTime;
							$meetingTimes['tueEnd'] = $endTime;
							break;
						case "We":
							$meetingTimes['wedStart'] = $startTime;
							$meetingTimes['wedEnd'] = $endTime;
							break;
						case "Th":
							$meetingTimes['thuStart'] = $startTime;
							$meetingTimes['thuEnd'] = $endTime;
							break;
						case "Fr":
							$meetingTimes['friStart'] = $startTime;
							$meetingTimes['friEnd'] = $endTime;
							break;
						case "Sa":
							$meetingTimes['satStart'] = $startTime;
							$meetingTimes['satEnd'] = $endTime;
							break;
						case "Su":
							$meetingTimes['sunStart'] = $startTime;
							$meetingTimes['sunEnd'] = $endTime;
							break;
					}
				}
			}

			$sections[] = array(
				'location'	=> $locationList[$x]->innertext,
				'instructor'=> $instructorList[$x]->innertext,
				'number'	=> $matches[1],
				'type'		=> $matches[2],
				'id'		=> $matches[3],
				'time'		=> $timeList[$x]->innertext
			) + $meetingTimes;

		}
		$sectionHTML->clear();
		return $sections;
	}

	private function convertToMilitaryTime($hour,$minutes,$meridiem){
		if($meridiem == "PM"){
			if($hour < 12){
				$hour = $hour + 12;
			}
		} else if($meridiem == "AM"){
			if($hour == 12){
				$hour = 0;
			}
		} else {
			return FALSE;
		}

		return $hour.":".$minutes;
	}

	public function printDebug($message){
		echo $message."\n";
		flush();
	}

	public function subjectList($character){
		$this->setAction("CLASS_SRCH_WRK2_SSR_PB_SUBJ_SRCH$0");
		$this->execCurl();

		$this->setAction("SSR_CLSRCH_WRK2_SSR_ALPHANUM_".$character);
		$this->execCurl();

		$xml = simplexml_load_string($this->response,"SimpleXMLElement");
		$subjectHTML = str_get_html((string)$xml->FIELD[0]);
		$subjectAcronymList = $subjectHTML->find('span[id^=SSR_CLSRCH_SUBJ_SUBJECT$]');
		$subjectNameList = $subjectHTML->find('span[id^=SUBJECT_TBL_DESCRFORMAL$]');
		$subjects = array();

		for($i = 0; $i < count($subjectAcronymList); $i++){
			if(isset($subjectAcronymList[$i]->innertext{3})){
				continue;
			}
			$subjects[] = array(
				'acronym'	=> $subjectAcronymList[$i]->innertext,
				'name'		=> $subjectNameList[$i]->innertext
			);
		}

		return $subjects;
	}
}
?>