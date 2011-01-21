<?php 

// MANTIS CoDev User Authorization Management

// LoB 23 Jun 2010

include_once "issue.class.php";

// =======================================
class User {

	var $id;
	var $name;
	
   // --------------------
	public function User($user_id) {
	  $this->id = $user_id;	
	}
	
   // --------------------
	public function getName() {
      
      if (NULL == $name) {
         $query = "SELECT mantis_user_table.username ".
                  "FROM  `mantis_user_table` ".
                  "WHERE  id = $this->id";
         $result = mysql_query($query) or die("Query failed: $query");
         $this->name  = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : "(unknown $this->id)";
      }
      return $this->name;
   }
	
	
	// --------------------
	public function getFirstname() {
		
		if (NULL == $name) {
         $query = "SELECT mantis_user_table.realname ".
                  "FROM  `mantis_user_table` ".
                  "WHERE  id = $this->id";
         $result = mysql_query($query) or die("Query failed: $query");
         $this->name  = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : "(unknown $this->id)";
		}
		
		$tok = strtok($this->name, " "); // 1st token: firstname
		
      return $tok;
	}
	
   // --------------------
	public function getLastname() {
      if (NULL == $name) {
         $query = "SELECT mantis_user_table.realname ".
                  "FROM  `mantis_user_table` ".
                  "WHERE  id = $this->id";
         $result = mysql_query($query) or die("Query failed: $query");
         $this->name  = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : "unknown $this->id";
      }
      
      $tok = strtok($this->name, " ");  // 1st token: firstname
      $tok = strtok(" ");  // 2nd token: lastname
      
      return $tok;
   }
	
   // --------------------
   /** retourne le trigramme. ex: Louis BAYLE => LBA */
   public function getShortname() {
   	
   	if (0 == $this->id) { return "";	}
   	
      if (NULL == $name) {
         $query = "SELECT mantis_user_table.realname ".
                  "FROM  `mantis_user_table` ".
                  "WHERE  id = $this->id";
         $result = mysql_query($query) or die("Query failed: $query");
         $this->name  = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : "unknown $this->id";
      }
      
      $tok1 = strtok($this->name, " ");  // 1st token: firstname
      $tok2 = strtok(" ");  // 2nd token: lastname
      
      if (false == $tok2) {
      	$trigramme = $tok1[0].$tok1[1].$tok1[2];
      } else {
         $trigramme = $tok1[0].$tok2[0].$tok2[1];
      } 
      return $trigramme;
   }
   
   
   // --------------------
	public function isTeamLeader($team_id) {
		$query = "SELECT leader_id FROM `codev_team_table` WHERE id = $team_id";
      $result = mysql_query($query) or die("Query failed: $query");
      $leaderid = mysql_result($result, 0);
      
		return ($leaderid == $this->id);
	}
	
   // --------------------
   public function isTeamDeveloper($team_id, $startTimestamp=NULL, $endTimestamp=NULL) {
      global $accessLevel_dev;
   	return $this->isTeamMember($team_id, $accessLevel_dev, $startTimestamp, $endTimestamp);
   }

   // --------------------
   // REM isTeamObserver not used for now
   public function isTeamObserver($team_id, $startTimestamp=NULL, $endTimestamp=NULL) {
      global $accessLevel_observer;
      return $this->isTeamMember($team_id, $accessLevel_observer, $startTimestamp, $endTimestamp);
   }
   
   // --------------------
   public function isTeamManager($team_id, $startTimestamp=NULL, $endTimestamp=NULL) {
      global $accessLevel_manager;
      return $this->isTeamMember($team_id, $accessLevel_manager, $startTimestamp, $endTimestamp);
   }

   // --------------------
   public function isTeamMember($team_id, $accessLevel=NULL, $startTimestamp=NULL, $endTimestamp=NULL) {
      global $accessLevel_dev;
      
      if (NULL == $accessLevel) { $accessLevel = $accessLevel_dev; }
      
      $query = "SELECT COUNT(id) FROM `codev_team_user_table` ".
               "WHERE team_id = $team_id ".
               "AND user_id = $this->id ".
               "AND access_level = $accessLevel ";
      
      if ((NULL != $startTimestamp) && (NULL != $endTimestamp)) {
         $query .= "AND arrival_date < $endTimestamp AND ".
                   "(departure_date >= $startTimestamp OR departure_date = 0)";
          // REM: if departure_date = 0, then user stays until the end of the world. 
      }
      
      $result = mysql_query($query) or die("Query failed: $query");
      $nbTuples  = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : 0;

      return (0 != $nbTuples);
   }

   
   
   // --------------------
   /** if no team specified, choose the oldest arrival date */
   public function getArrivalDate($team_id = NULL) {
      
   	$arrival_date = time();
   	
      $query = "SELECT arrival_date FROM `codev_team_user_table` ".
               "WHERE user_id = $this->id ";
      if (isset($team_id)) {
               $query .= "AND team_id = $team_id";
      }
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
      	if ($row->arrival_date < $arrival_date) {
      		$arrival_date = $row->arrival_date;
      	}
      }
      
      //echo "DEBUG arrivalDate = ".date('Y - m - d', $arrival_date)."<br>";
      
      return $arrival_date;
   }

   // --------------------
   /** if no team specified, choose the most future departureDate */
   public function getDepartureDate($team_id = NULL) {
   	
   	$departureDate = 0;
   	
      $query = "SELECT departure_date FROM `codev_team_user_table` ".
               "WHERE user_id = $this->id ";
      if (isset($team_id)) {
               $query .= "AND team_id = $team_id";
      }
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         if ($row->departure_date > $departureDate) {
            $departureDate = $row->departure_date;
         }
      }
      
      //echo "DEBUG departureDate = ".date('Y - m - d', $departureDate)."<br>";
      
      return $departureDate;
   }

   // --------------------
   /** REM: period cannot exceed 1 month. */
   public function getDaysOfInPeriod($startTimestamp, $endTimestamp) {
    $daysOf = array();  // day => duration
      
    $query     = "SELECT bugid, date, duration ".
                 "FROM `codev_timetracking_table` ".
                 "WHERE date >= $startTimestamp AND date <= $endTimestamp ".
                 "AND userid = $this->id";
    $result    = mysql_query($query) or die("Query failed: $query");
    while($row = mysql_fetch_object($result)) {
         
      $issue = new Issue ($row->bugid);
      if ($issue->isVacation()) {
        $daysOf[date("j", $row->date)] += $row->duration;
        //echo "DEBUG user $this->userid daysOf[".date("j", $row->date)."] = ".$daysOf[date("j", $row->date)]." (+$row->duration)<br/>";
      }
    }
    return $daysOf;
  }
   
   // --------------------
   public function getProductionDaysForecast($startTimestamp, $endTimestamp, $team_id = NULL) {
      global $globalHolidaysList;
   	
      $prodDaysForecast = 0;
      $nbOpenDaysInPeriod = 0;
      
   	$arrivalDate   = $this->getArrivalDate($team_id);
   	$departureDate = $this->getDepartureDate($team_id);
   	
   	if ($arrivalDate   > $endTimestamp)   return 0;
   	
   	// if not specified, $departureDate = $endTimestamp
   	if (0 == $departureDate) {$departureDate = $endTimestamp; }

   	if ($departureDate < $startTimestamp) return 0;
   	
      // restrict timestamp to the period where the user is working on the project
      $startT = ($arrivalDate > $startTimestamp) ? $arrivalDate : $startTimestamp;
      $endT   = ($departureDate < $endTimestamp) ? $departureDate : $endTimestamp;
   	
     
      // get $nbOpenDaysInPeriod
      for ($i = $startT; $i <= $endT; $i += (60 * 60 * 24)) {
        $dayOfWeek = date("N", $i);
        if (($dayOfWeek < 6) && (!in_array(date("Y-m-d", $i), $globalHolidaysList))) { 
        	$nbOpenDaysInPeriod++; 
        }
      }
      
      $nbDaysOf = array_sum($this->getDaysOfInPeriod($startT, $endT));
      $prodDaysForecast = $nbOpenDaysInPeriod - $nbDaysOf;
      
      #echo "user $this->id timestamp = ".date('Y-m-d', $startT)." to ".date('Y-m-d', $endT)." =>  ($nbOpenDaysInPeriod - $nbDaysOf) = $prodDaysForecast <br/>";
      
      return $prodDaysForecast;
   }
   
   
   
   
   // --------------------
   // returns the teams i'm leader of.
   public function getLeadedTeamList() {
      
      $teamList = array();
      
      $query = "SELECT DISTINCT id, name FROM `codev_team_table` WHERE leader_id = $this->id  ORDER BY name";
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         $teamList[$row->id] = $row->name;
         #echo "getLeadedTeamList FOUND $row->id - $row->name<br/>";
      }
      
      return $teamList;
   }

   // --------------------
   // returns the teams i'm member of.
   public function getDevTeamList() {
      global $accessLevel_dev;
      return $this->getTeamList($accessLevel_dev);
   }

   // --------------------
   // returns the teams i'm observer of.
   public function getObservedTeamList() {
      global $accessLevel_observer;
      return $this->getTeamList($accessLevel_observer);
   }

   // --------------------
   // returns the teams i'm Manager of.
   public function getManagedTeamList() {
      global $accessLevel_manager;
      return $this->getTeamList($accessLevel_manager);
   }
   
   // --------------------
   // returns the teams
   public function getTeamList($accessLevel = NULL) {
      global $accessLevel_dev;
      global $access_level_names;
      
   	if (NULL == $accessLevel) { $accessLevel = $accessLevel_dev; }
   	
      $teamList = array();
      
      $query = "SELECT codev_team_table.id, codev_team_table.name ".
               "FROM `codev_team_user_table`, `codev_team_table` ".
               "WHERE codev_team_user_table.user_id = $this->id ".
               "AND   codev_team_user_table.team_id = codev_team_table.id ".
               "AND   codev_team_user_table.access_level = $accessLevel ".
      "ORDER BY codev_team_table.name";
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         $teamList[$row->id] = $row->name;
         #echo "getTeamList(".$access_level_names[$accessLevel].") FOUND $row->id - $row->name<br/>";
      }
      
      return $teamList;
   }
   
   
   // --------------------
   public function getProjectList($teamList = NULL) {
      
      $projList = array();
   	
      if (NULL == $teamList) {
      	// if not specified, get projects from the teams I'm member of.
         $teamList = $this->getTeamList();
      }
      if (0 != count($teamList)) {
	      $formatedTeamList = valuedListToSQLFormatedString($teamList);
	      
	      $query = "SELECT DISTINCT codev_team_project_table.project_id, mantis_project_table.name ".
	               "FROM `codev_team_project_table`, `mantis_project_table`".
	               "WHERE codev_team_project_table.team_id IN ($formatedTeamList) ".
	               "AND codev_team_project_table.project_id = mantis_project_table.id ".
	               "ORDER BY mantis_project_table.name";
	      
	      $result = mysql_query($query) or die("Query failed: $query");
	      while($row = mysql_fetch_object($result)) {
	      	$projList[$row->project_id] = $row->name;
	      }
      } else {
      	// this happens if User is not a Developper (Manager or Observer)
         //echo "<div style='color:red'>ERROR: User is not member of any team !</div><br>";
      }
      return $projList;
   }
   
   // --------------------
   // returns the tasks I can work on.
   // depending on: the projects associated to this user in mantis_project_user_list_table.
   // this list is displayed in timeTracking.php
   public function getPossibleWorkingTasksList($projList = NULL) {
   	
   	$issueList = array();
   	if (NULL == $projList) {
   	  $projList = $this->getProjectList();
   	}
   	
   	if (0 == count($projList)) {
   		// this happens if User is not a Developper (Manager or Observer)
   		//echo "<div style='color:red'>ERROR: no project associated to this team !</div><br>";
   		return array();
   	}
   	
      $formatedProjList = valuedListToSQLFormatedString($projList);
	   
   	
      $query = "SELECT DISTINCT id FROM `mantis_bug_table` WHERE project_id IN ($formatedProjList) ORDER BY id DESC";
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result)) {
         	$issueList[] = $row->id;
      }
   	
   	return $issueList;
   }
   
   // --------------------
   /**
    * sum the RAE of all the opened Issues assigned to me.
    */
   public function getWorkload($projList = NULL) {

      global $status_resolved;
      global $status_delivered;
      global $status_closed;
   	
      $totalRemaining = 0;

      if (NULL == $projList) {
        $projList = $this->getProjectList();
      }
      
      if (0 == count($projList)) {
         // this happens if User is not a Developper (Manager or Observer)
         //echo "<div style='color:red'>ERROR: no project associated to this team !</div><br>";
         return $totalRemaining;
      }
      
      $formatedProjList = valuedListToSQLFormatedString($projList);
      
   	// find all issues i'm working on
      $query = "SELECT DISTINCT id FROM `mantis_bug_table` ".
               "WHERE project_id IN ($formatedProjList) ".
               "AND handler_id = $this->id ".
               "AND status NOT IN ($status_resolved, $status_delivered, $status_closed) ".
               "ORDER BY id DESC";
      
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result)) {
            $issue = new Issue($row->id);
            if (NULL != $issue->remaining) {
            	$totalRemaining += $issue->remaining;
            }
      }
   	
   	
   	return $totalRemaining;
   }
}




?>