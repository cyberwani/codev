<?php /*
    This file is part of CoDev-Timetracking.

    CoDev-Timetracking is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Foobar is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
*/ ?>
<?php

include_once "project.class.php";
include_once "issue_cache.class.php";

// -- COMPUTE DURATIONS --
// Status & Issue classes

// ==============================================================
class Status {
   var $statusId; // new=10, ack=30, ...
   var $duration; // in sec since 1970 (unix timestamp)

   function Status($s, $d) {
      $this->statusId = $s;
      $this->duration = $d;
   }
}


// ==============================================================
class Issue {
   var $bugId;      // mantis id
   var $projectId;  // Capu, peterpan, etc.
   var $categoryId;
   var $eta;
   var $summary;
   var $dateSubmission;
   var $currentStatus;
   var $priority;
   var $handlerId;
   var $resolution;
   var $version;  // Product Version

   // -- CoDev custom fields
   var $tcId;         // TelelogicChange id
   var $remaining;    // RAE 
   var $effortEstim;  // BI
   var $effortAdd;    // BS
   var $deadLine;
   var $deliveryDate;
   var $deliveryId;   // TODO FDL (FDJ specific) 

   private $relationships; // array[relationshipType][bugId]
   
   // -- computed fields
   var $elapsed;    // total time spent on this issue
   var $statusList; // array of statusInfo elements

   // -- PRIVATE cached fields
   var $holidays;
      
   // ----------------------------------------------
   public function Issue ($id) {
      $this->bugId = $id;
      $this->initialize();
   }

   // ----------------------------------------------
   public function initialize() {
   	
   	global $tcCustomField;
   	global $estimEffortCustomField;
   	global $remainingCustomField;
   	global $addEffortCustomField;
   	global $deadLineCustomField;
   	global $deliveryDateCustomField;
      global $deliveryIdCustomField;
   	
      // Get issue info
      $query = "SELECT * ".
      "FROM `mantis_bug_table` ".
      "WHERE id = $this->bugId";
      $result = mysql_query($query) or die("Query failed: $query");
      $row = mysql_fetch_object($result);

      $this->summary         = $row->summary;
      $this->currentStatus   = $row->status;
      $this->dateSubmission  = $row->date_submitted;
      $this->projectId       = $row->project_id;
      $this->categoryId      = $row->category_id;
      $this->eta             = $row->eta;
      $this->priority        = $row->priority;
      $this->handlerId       = $row->handler_id;
      $this->resolution      = $row->resolution;
      $this->version         = $row->version;
      
      // Get custom fields
      $query2 = "SELECT field_id, value FROM `mantis_custom_field_string_table` WHERE bug_id=$this->bugId";
      $result2 = mysql_query($query2) or die("Query failed: $query2");
      while($row = mysql_fetch_object($result2))
      {
         switch ($row->field_id) {
            case $tcCustomField:          $this->tcId        = $row->value; break;
            case $estimEffortCustomField: $this->effortEstim = $row->value; break;
            case $remainingCustomField:   $this->remaining   = $row->value; break;
            case $addEffortCustomField:   $this->effortAdd   = $row->value; break;
            case $deadLineCustomField:    $this->deadLine    = $row->value; break;
            case $deliveryDateCustomField: $this->deliveryDate = $row->value; break;
            case $deliveryIdCustomField:  $this->deliveryId  = $row->value; break;
            
         }
      }

      $this->tcId    = $this->getTC();

      $this->elapsed = $this->getElapsed();

      // Prepare fields
      $this->statusList = array();
      $this->relationships = array();
      
   }

   // ----------------------------------------------
   // Ex: vacation or Incident tasks are not production issues.
   //     but tools and doc are production issues.
   public function isSideTaskIssue() {
      
      $project = ProjectCache::getInstance()->getProject($this->projectId);
      
      if (($project->isSideTasksProject()) && 
          ($project->getToolsCategoryId() != $this->categoryId) && 
          ($project->getDocCategoryId()   != $this->categoryId)) {
         
         //echo "DEBUG $this->bugId is a sideTask.   type=$type<br/>";
         return true;
      }
      return false;
   }

   // ----------------------------------------------
   public function isVacation() {

      $project = ProjectCache::getInstance()->getProject($this->projectId);
   	      
      if (($project->isSideTasksProject()) && 
          ($project->getInactivityCategoryId() == $this->categoryId)) { 
         
         //echo "DEBUG $this->bugId is a sideTask.<br/>";
         return true;
      }
      return false;
   }

   // ----------------------------------------------
   public function isIncident() {

      $project = ProjectCache::getInstance()->getProject($this->projectId);
   	      
      if (($project->isSideTasksProject()) && 
          ($project->getIncidentCategoryId() == $this->categoryId)) { 
         
         //echo "DEBUG $this->bugId is a Incident.<br/>";
         return true;
      }
      return false;
   }
   
   // ----------------------------------------------
   public function isAstreinte() {

   	global $astreintesTaskList;
   	
      if (in_array($this->bugId, $astreintesTaskList)) { 
         
         #echo "DEBUG $this->bugId is an Astreinte.<br/>";
         return true;
      }
      return false;
   }

   
   // ----------------------------------------------
   public function getTC() {
      $query  = "SELECT value FROM `mantis_custom_field_string_table` WHERE field_id='1' AND bug_id=$this->bugId";
      $result = mysql_query($query) or die("Query failed: $query");

      $tcId    = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : "";

      return $tcId;
   }

   // ----------------------------------------------
   public function getProjectName() {
   	
   	$project = ProjectCache::getInstance()->getProject($this->projectId);
   	return $project->name;
   	
   	/*
      $query = "SELECT name FROM `mantis_project_table` WHERE id= $this->projectId";
      $result = mysql_query($query) or die("Query failed: $query");
      $projectName = mysql_result($result, 0);
      
      return $projectName;
      */
   }

   // ----------------------------------------------
   public function getCategoryName() {
      $query = "SELECT name FROM `mantis_category_table` WHERE id= $this->categoryId";
      $result = mysql_query($query) or die("Query failed: $query");
      $categoryName = mysql_result($result, 0);

      return $categoryName;
   }

   // ----------------------------------------------
   public function getCurrentStatusName() {
      global $statusNames;

      return $statusNames[$this->currentStatus];
   }

   // ----------------------------------------------
   public function getEtaName() {
      global $ETA_names;

      return $ETA_names[$this->eta];
   }

   // ----------------------------------------------
   public function getPriorityName() {
      global $priorityNames;

      return $priorityNames[$this->priority];
   }

   // ----------------------------------------------
   public function getResolutionName() {
      global $resolutionNames;

      return $resolutionNames[$this->resolution];
   }

   
   // ----------------------------------------------
   /**
    * Get elapsed from TimeTracking
    * @param unknown_type $job_id   if no category specified, then all category.
    */
   public function getElapsed($job_id = NULL) {  // TODO $doRefresh = false
   	
      $elapsed = 0;

      $query     = "SELECT duration FROM `codev_timetracking_table` WHERE bugid=$this->bugId";
      
      if (isset($job_id)) {
         $query .= " AND jobid = $job_id";
      }
            
      $result    = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         $elapsed += $row->duration;
      }

      return $elapsed;
   }

   /**
    * Returns the nb of days needed to finish the issue.
    * if the 'remaining' (RAF) field is not defined, return effortEstim or prelEffortEstim
    */
   public function getRemaining() {
      // determinate issue duration (Remaining, BI, PrelEffortEstim)
      if       (NULL != $this->remaining)   { $issueDuration = $this->remaining; }
     elseif   (NULL != $this->effortEstim) { $issueDuration = $this->effortEstim; }
      else                                   { $issueDuration = $this->eta; }
      return $issueDuration;
   }
   
   /**
    * TODO: NOT FINISHED, ADAPT TO ALL RELATIONSHIP TYPES
    *
    * get list of Relationships
    *
    * @param type = 2500 or 2501
    * @return array(issue_id);
    */
   public function getRelationships($type) {

      // TODO
      $complementaryType = (2500 == $type) ? 2501 : 2500;

        if (NULL == $this->relationships[$type]) {
         $this->relationships[$type] = array();

           // normal
         $query = "SELECT * FROM `mantis_bug_relationship_table` ".
                  "WHERE source_bug_id=$this->bugId ".
                  "AND relationship_type = $type";
         $result    = mysql_query($query) or die("Query failed: $query");
         while($row = mysql_fetch_object($result))
         {
            #echo "DEBUG relationships: [$type] $this->bugId -> $row->destination_bug_id </br>\n";
            $this->relationships[$type][] = $row->destination_bug_id;
         }
         // complementary
         $query = "SELECT * FROM `mantis_bug_relationship_table` ".
                  "WHERE destination_bug_id=$this->bugId ".
                  "AND relationship_type = ".$complementaryType;
         $result    = mysql_query($query) or die("Query failed: $query");
         while($row = mysql_fetch_object($result))
         {
            #echo "DEBUG relationshipsC: [$type] $this->bugId -> $row->source_bug_id </br>\n";
            $this->relationships[$type][] = $row->source_bug_id;
         }
        }

      return $this->relationships[$type];
   }
   
   // ----------------------------------------------
   /**
    * returns the timestamp of the first TimeTrack
    */
   public function startDate() {
   	
   	$query = "SELECT MIN(date) FROM `codev_timetracking_table` WHERE bugid=$this->bugId ";
      $result = mysql_query($query) or die("Query failed: $query");
      $startDate = mysql_result($result, 0);
   	
   	return $startDate;
   }
   
   // ----------------------------------------------
   /**
    * returns the timestamp of the latest TimeTrack
    */
   public function endDate() {
      
   	$query = "SELECT MAX(date) FROM `codev_timetracking_table` WHERE bugid=$this->bugId ";
      $result = mysql_query($query) or die("Query failed: $query");
      $endDate = mysql_result($result, 0);
   	return $endDate;
   }
   
   // ----------------------------------------------
   // Returns how many time has already been spent on this task
   // REM: if no category specified, then all category.
/*   
   public function getElapsed2($startTimestamp, $endTimestamp, $job_id = NULL) {
      $elapsed = 0;

      $query     = "SELECT duration FROM `codev_timetracking_table` WHERE bugid=$this->bugId ".
      "AND  date >= $this->startTimestamp AND date < $this->endTimestamp ";

      if (isset($job_id)) {
         $query .= " AND jobid = $job_id";
      }
      $result    = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         $elapsed += $row->duration;
      }

      return $elapsed;
   }
*/
   // ----------------------------------------------
   // returns an HTML color string "#ff6a6e;" depending on pos/neg drift and current status.
   // returns NULL if $drift=0
   // REM: if $drift is not specified, then $this->drift is used ()
   public function getDriftColor($drift = NULL) {
    global $status_delivered;
    global $status_resolved;
    global $status_closed;
   	
     if (!isset($drift)) {
     	   $drift = $this->getDrift(false);
     }
    
        if (0 < $drift) {
            if (($status_resolved  != $this->currentStatus) && 
                ($status_delivered != $this->currentStatus) &&
                ($status_closed    != $this->currentStatus)) {
              $color = "#ff6a6e;";
            } else {
              $color = "#fcbdbd;";
            }
        } elseif (0 > $drift) {
          if (($status_resolved != $this->currentStatus) && 
              ($status_delivered != $this->currentStatus) &&
              ($status_closed   != $this->currentStatus)) {
              $color = "#61ed66;";
              } else {
              $color = "#bdfcbd;";
              }
        } else {
          $color = NULL;
        }
     
   	return $color;
   }
   
   
   // ----------------------------------
   // if NEG, then we saved time
   // if 0, then just in time
   // if POS, then there is a drift !

   // elapsed - (effortEstim - remaining)
   // if bug is Resolved/Closed, then remaining is not used.

   // REM if EffortEstim = 0 then Drift = 0
   public function getDrift($withSupport = true) {
      global $status_resolved;
      global $status_closed;
      global $job_support;

      $totalEstim = $this->effortEstim + $this->effortAdd;
      
      if ($withSupport) {
      	$myElapsed = $this->elapsed;
      } else {
      	$myElapsed = $this->elapsed - $this->getElapsed($job_support);
      }
      
      if (0 == $totalEstim) { return 0; }

      if (($status_resolved == $this->currentStatus) || ($status_closed == $this->currentStatus)) {
         $derive = $myElapsed - $totalEstim;
      } else {
         $derive = $myElapsed - ($totalEstim - $this->remaining);
      }

      if (isset($_GET['debug'])) {echo "issue->getDrift(): bugid ".$this->bugId." ".$this->getCurrentStatusName()." derive=$derive (elapsed $this->elapsed - estim $totalEstim)<br/>";}
      return $derive;
   }

   // ----------------------------------
   // if NEG, then we saved time
   // if 0, then just in time
   // if POS, then there is a drift !

   // elapsed - (ETA - remaining)
   // if bug is Resolved/Closed, then remaining is not used.
   
   // REM if ETA = 0 then Drift = 0
   public function getDriftETA($withSupport = true) {
      global $status_resolved;
      global $status_closed;
      global $ETA_balance;
      global $job_support;
      
      #if (0 == $this->eta) { return 0; }

      if ($withSupport) {
         $myElapsed = $this->elapsed;
      } else {
         $myElapsed = $this->elapsed - $this->getElapsed($job_support);
      }
      
      
      if (($status_resolved == $this->currentStatus) || ($status_closed == $this->currentStatus)) {
         $derive = $myElapsed - $ETA_balance[$this->eta];
      } else {
         $derive = $myElapsed - ($ETA_balance[$this->eta] - $this->remaining);
      }

      if (isset($_GET['debug'])) {echo "issue->getDriftETA(): bugid ".$this->bugId." ".$this->getCurrentStatusName()." derive=$derive (elapsed $this->elapsed - estim ".$ETA_balance[$this->eta].")<br/>";}
      return $derive;
   }

   
   // ----------------------------------------------
   /**
    * check if the Issue has been delivered in time (before the  DeadLine)
    * formula: (DeadLine - DeliveryDate)
    *
    * 
    * @return int nb days drift (except holidays)
    *         if <= 0, Issue delivered in time
    *         if  > 0, Issue NOT delivered in time !
    *         OR "Error" string if could not be determinated. REM: check with is_string($timeDrift)
    */
   public function getTimeDrift() {
      
   	if ((NULL != $this->deadLine) && (NULL != $this->deliveryDate)) {
   		$timeDrift = $this->deliveryDate - $this->deadLine;
   		
   		// convert seconds to days (24 * 60 * 60) = 86400
   		$timeDrift /=  86400 ;
         
   		// remove weekends & holidays
   		$holidays = Holidays::getInstance();
   		if ($this->deliveryDate < $this->deadLine) {
   		    $nbHolidays = $holidays->getNbHolidays($this->deliveryDate, $this->deadLine);
   		} else {
             $nbHolidays = $holidays->getNbHolidays($this->deadLine, $this->deliveryDate);
   		}
         #echo "DEBUG: TimeDrift for issue $this->bugId = ($this->deliveryDate - $this->deadLine) / 86400 = $timeDrift (- $nbHolidays holidays)<br/>";

         if ($timeDrift > 0) {
            $timeDrift -= $nbHolidays;
         } else {
            $timeDrift += $nbHolidays;
         }
   		
   	} else {
         $timeDrift = "Error";
   		#echo "WARNING: could not determinate TimeDrift for issue $this->bugId.<br/>";
   	}
   	return  $timeDrift;
   }
   
   /**
    * returns the number of days (holiday excluded) from the submission date
    * to the deadLine.
    * this represents the nb of days available to fix the bug
    * 
    * Note: The submission day is not included (bug may have been posted at 18:00)
    * 
    * formula: deadLine - dateSubmission - nb holidays in that period
    * 
    * @return nb of days or NULL if no deadLine specified.
    */
   public function getAvalableWorkload() {
   	
   	if (!isset($this->deadLine)) {
   		echo "ERROR getAvalableWorkload: issue $this->bugId has no deadline $this->deadLine !<br/>";
   		return NULL;
   	}

   	// Mantis stores submission date AND time. we want the date at midnight.
   	$submissionDate = date2timestamp(date("Y-m-d", $this->dateSubmission));
   	
   	$h = Holidays::getInstance();
   	$nbHolidays = $h->getNbHolidays($submissionDate, $this->deadLine);

   	$avalableWorkload  = ($this->deadLine - $submissionDate) / (60*60*24);
   	$avalableWorkload -= $nbHolidays; // remove weekEnds and other fixed holidays
   	
   	if (isset($_GET['debug'])) { echo "getAvalableWorkload: issue $this->bugId: period=".($this->deadLine - $submissionDate) / (60*60*24)." - $nbHolidays = $avalableWorkload<br/>";}
   	
   	return $avalableWorkload;
   }
   
   // ----------------------------------------------
   public function getTimeTracks($user_id = NULL) {
      $timeTracks = array();

      $query     = "SELECT id, date FROM `codev_timetracking_table` ".
      "WHERE bugid=$this->bugId ";

      if (isset($user_id)) {
         $query .= "AND userid = $user_id";
      }
      $query .= " ORDER BY date";

      $result    = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         $timeTracks[$row->id] = $row->date;
      }

      return $timeTracks;
   }

   // ----------------------------------------------
   /**
    * returns the date of the oldest TimeTrack
    * @param unknown_type $user_id
    */
   public function getStartTimestamp($user_id = NULL) {
      $timeTracks = array();

      $query     = "SELECT id, date FROM `codev_timetracking_table` ".
      "WHERE bugid=$this->bugId ";

      if (isset($user_id)) {
         $query .= "AND userid = $user_id";
      }
      $query .= " ORDER BY date";

      $result    = mysql_query($query) or die("Query failed: $query");
      $row = mysql_fetch_object($result);

      return $row->date;
   }
   
   // ----------------------------------------------
   public function getInvolvedUsers($team_id = NULL) {
      $userList = array();

      $query = "SELECT mantis_user_table.id, mantis_user_table.username ".
      "FROM  `mantis_user_table`, `codev_timetracking_table`, `codev_team_user_table`  ".
      "WHERE  codev_timetracking_table.userid = mantis_user_table.id ".
      "AND    codev_timetracking_table.bugid  = $this->bugId ";

      if (isset($team_id)) {
         $query .= "AND codev_team_user_table.team_id = $team_id ".
        "AND codev_team_user_table.user_id = mantis_user_table.id ";
      }

      $query .= " ORDER BY mantis_user_table.username";
         
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         $userList[$row->id] = $row->username;
      }

      return $userList;
   }

   // ----------------------------------------------
   // returns statusId at date < $timestamp or current status if $timestamp = NULL
   public function getStatus($timestamp = NULL) {

      if (NULL == $timestamp) {
         $query = "SELECT status FROM `mantis_bug_table` WHERE id = $this->bugId";
         $result = mysql_query($query) or die("Query failed: $query");
         $row = mysql_fetch_object($result);
         $this->currentStatus   = $row->status;

         if (isset($_GET['debug'])) { echo "issue->getStatus(NULL) : bugId=$this->bugId, status=$this->currentStatus<br/>"; }
         return $this->currentStatus;
      }
         
      // if a timestamp is specified, find the latest status change (strictly) before this date
      $query = "SELECT new_value, old_value, date_modified ".
                "FROM `mantis_bug_history_table` ".
                "WHERE bug_id = $this->bugId ".
                "AND field_name='status' ".
                "AND date_modified < $timestamp ".
                "ORDER BY date_modified DESC";

      // get latest result
      $result = mysql_query($query) or die("Query failed: $query");
      if (0 != mysql_num_rows($result)) {
         $row = mysql_fetch_object($result);

         if (isset($_GET['debug'])) { echo "issue->getStatus(".date("d F Y", $timestamp).") : bugId=$this->bugId, old_value=$row->old_value, new_value=$row->new_value, date_modified=".date("d F Y", $row->date_modified)."<br/>";}

         return $row->new_value;
      } else {
         if (isset($_GET['debug'])) { echo "issue->getStatus(".date("d F Y", $timestamp).") : bugId=$this->bugId not found !<br/>"; }
         return -1;
      }
   }


   // ----------------------------------------------
   // updates DB with new value
   public function setRemaining($remaining) {
   	
   	global $remainingCustomField;
   	
      $old_remaining = $this->remaining;

      //echo "DEBUG setRemaining old_value=$old_remaining   new_value=$remaining<br/>";

      // TODO should be done only once... in Constants singleton ? 
      $query  = "SELECT name FROM `mantis_custom_field_table` WHERE id='$remainingCustomField'";
      $result = mysql_query($query) or die("Query failed: $query");
      $field_name    = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : "Remaining (RAE)";
      

      $query = "SELECT * FROM `mantis_custom_field_string_table` WHERE bug_id=$this->bugId AND field_id = $remainingCustomField";
      $result = mysql_query($query) or die("Query failed: $query");
      if (0 != mysql_num_rows($result)) {

         $query2 = "UPDATE `mantis_custom_field_string_table` SET value = '$remaining' WHERE bug_id=$this->bugId AND field_id = $remainingCustomField";
      } else {
         $query2 = "INSERT INTO `mantis_custom_field_string_table` (`field_id`, `bug_id`, `value`) VALUES ('$remainingCustomField', '$this->bugId', '$remaining');";
      }
      $result    = mysql_query($query2) or die("Query failed: $query2");
      $this->remaining = $remaining;

      // Add to history
      $query = "INSERT INTO `mantis_bug_history_table`  (`user_id`, `bug_id`, `field_name`, `old_value`, `new_value`, `type`, `date_modified`) ".
      "VALUES ('".$_SESSION['userid']."','$this->bugId','$field_name', '$old_remaining', '$remaining', '0', '".time()."');";
      mysql_query($query) or die("Query failed: $query");
   }

   // ----------------------------------------------
   // Computes the lifeCycle of the issue (time spent on each status)
   public function computeDurations () {
   	global $status_new;
   	
      $statusNames = Config::getInstance()->getValue("statusNames");
      ksort($statusNames);
  
      foreach ($statusNames as $s => $sname) {
         if ($status_new == $s) {
            $this->statusList[$s] = new Status($s, $this->getDuration_new());
         } else {
            $this->statusList[$s] = new Status($s, $this->getDuration_other($s));
         }
      }
   }

   // ----------------------------------------------
   protected function getDuration_new ()
   {
      $time = 0;

      global $status_new;

      $current_date = time();

      // If status = 'new',
      // -- the start_date is the bug creation date
      // -- the end_date   is transition where old_value = status or current_date if status unchanged.

      $query = "SELECT status, date_submitted FROM `mantis_bug_table` WHERE id=$this->bugId";
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      { // REM: only one line in result, while should be optimized
         $current_status = $row->status;
         $date_submitted = $row->date_submitted;
         //echo "&nbsp;&nbsp; start_date = $date_submitted (date_submitted)<br/>";
      }

      // If status has not changed, then end_date is now.
      if ($status_new == $current_status) {
         //echo "bug still in 'new' state<br/>";
         $time = $current_date - $date_submitted;
      } else {
         // Bug has changed, search history for status changed
         $query = "SELECT date_modified FROM `mantis_bug_history_table` WHERE bug_id=$this->bugId AND field_name = 'status' AND old_value='$status_new'";
         $result = mysql_query($query) or die("Query failed: $query");
         while($row = mysql_fetch_object($result))
         { // REM: only one line in result, while should be optimized
            $date_modified = $row->date_modified;
            //echo "&nbsp;&nbsp; end_date = $date_modified <br/>";
         }

         $time= $date_modified - $date_submitted;
      }

      //echo "duration new $time<br/>";
      return $time;
   }

   // ----------------------------------------------
   protected function getDuration_other ($status)
   {
      $time = 0;

      $current_date = time();

      // Status is not 'new' and not 'feedback'
      //  -- the start_date is transition where new_value = status
      //  -- the end_date   is transition where old_value = status, or current date if no transition found.

      // Find start_date
      $query = "SELECT id, date_modified, old_value, new_value ".
               "FROM `mantis_bug_history_table` ".
               "WHERE bug_id=$this->bugId ".
               "AND field_name = 'status' ".
               "AND (new_value=$status OR old_value=$status) ORDER BY id";
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         //echo "&nbsp;&nbsp; id=$row->id &nbsp;&nbsp;date = $row->date_modified  &nbsp;&nbsp;old_value = $row->old_value  &nbsp;&nbsp;new_value = $row->new_value<br/>";
         $start_date = $row->date_modified;

         // Next line is end_date. if NULL then end_date = current_date
         if ($row = mysql_fetch_object($result)) {
            $end_date = $row->date_modified;
            //echo "&nbsp;&nbsp; id=$row->id &nbsp;&nbsp;date = $row->date_modified  &nbsp;&nbsp;old_value = $row->old_value  &nbsp;&nbsp;new_value = $row->new_value<br/>";
         } else {
            $end_date = $current_date;
            //echo "end_date = current date = $end_date<br/>";
         }
         $intervale =  $end_date - $start_date;
         //echo "&nbsp;&nbsp; intervale = $intervale<br/>";
         $time = $time + ($end_date - $start_date);
      }

      //echo "duration other $time<br/>";
      return $time;
   } // getDuration_other

   
   // ----------------------------------------------
   /**
    * QuickSort compare method.
    * returns true if $this has higher priority than $issueB
    * 
    * @param Issue $issueB the object to compare to
    */
   function compareTo($issueB) {
      
      global $status_openned;

      // if IssueB constrains IssueA, then IssueB is higher priority
      $AconstrainsList = $this->getRelationships( BUG_CUSTOM_RELATIONSHIP_CONSTRAINS );
      $BconstrainsList = $issueB->getRelationships( BUG_CUSTOM_RELATIONSHIP_CONSTRAINS );
      if (in_array($this->bugId, $BconstrainsList)) {
         // B constrains A
         #echo "DEBUG isHigherPriority $this->bugId < $issueB->bugId (B constrains A)<br/>\n";
         return false;
      }
      if (in_array($issueB->bugId, $AconstrainsList)) {
         // A constrains B
         #echo "DEBUG isHigherPriority $this->bugId > $issueB->bugId (A constrains B)<br/>\n";
         return true;
      }
      
      
      // Tasks currently open are higher priority
      if (($this->currentStatus == $status_openned) && ($issueB->currentStatus != $status_openned)) {
            #echo "DEBUG isHigherPriority $this->bugId > $issueB->bugId (status_openned)<br/>\n";
            return  true; 
      }
      if (($issueB->currentStatus == $status_openned) && ($this->currentStatus != $status_openned)) {
            #echo "DEBUG isHigherPriority $this->bugId < $issueB->bugId (status_openned)<br/>\n";
            return  false; 
      }
      
      // the one that has NO deadLine is lower priority
      if ((NULL != $this->deadLine) && (NULL == $issueB->deadLine)) { 
         #echo "DEBUG isHigherPriority $this->bugId > $issueB->bugId (B no deadline)<br/>\n";
         return  true; 
      }
      if ((NULL == $this->deadLine) && (NULL != $issueB->deadLine)) { 
         #echo "DEBUG isHigherPriority $this->bugId < $issueB->bugId (A no deadline)<br/>\n";
         return  false; 
      }

      // the soonest deadLine has priority
      if ($this->deadLine < $issueB->deadLine) { 
         #echo "DEBUG isHigherPriority $this->bugId > $issueB->bugId (deadline)<br/>\n";
         return  true; 
      }
      if ($this->deadLine > $issueB->deadLine) { 
         #echo "DEBUG isHigherPriority $this->bugId < $issueB->bugId (deadline)<br/>\n";
         return  false; 
      }
      
      // if same deadLine, check priority attribute
      if ($this->priority > $issueB->priority) {
         #echo "DEBUG isHigherPriority $this->bugId > $issueB->bugId (priority attr)<br/>\n";
         return  true;
      }
      if ($this->priority < $issueB->priority) {
         #echo "DEBUG isHigherPriority $this->bugId < $issueB->bugId (priority attr)<br/>\n";
         return  false;
      }


      // if IssueA constrains nobody, and IssueB constrains IssueX, then IssueB is higher priority
      if (count($AconstrainsList) > count($BconstrainsList)) {
         // A constrains more people, so A is higher priority
         #echo "DEBUG isHigherPriority $this->bugId > $issueB->bugId (A constrains more people)<br/>\n";
         return true;
      }

      #echo "DEBUG isHigherPriority $this->bugId <= $issueB->bugId (B constrains more people)<br/>\n";
      return false;
   }    

   /**
    * Returns the Estimated Date of Arrival, depending on user's holidays and other timetracks
    *
    * @param $beginTimestamp              the start day
    * @param $availTimeOnBeginTimestamp   On the start day, part of the day may already have
    *                                     been spent on other issues. this param defines how much
    *                                     time is left for this issue.
    *                                     if NULL, use user->getAvailableTime($beginTimestamp)
    * @param $userid                      if NULL, use assignedTo user
    *
    * @return array(endTimestamp, $availTimeOnEndTimestamp)
    *          $availTimeOnEndTimestamp can be re-injected in the next call to this function
    */
   public function computeEstimatedDateOfArrival($beginTimestamp, $availTimeOnBeginTimestamp=NULL, $userid=NULL) {

      // find user in charge of this issue
      if (NULL != $userid) {
         $user = UserCache::getInstance()->getUser($userid);
      } else {
         if (NULL != $this->handlerId) {
            $user = UserCache::getInstance()->getUser($this->handlerId);
         } else {
            // issue not assigned to anybody
            $user = NULL;
         }
      }

      // we need to be absolutely sure that time is 00:00:00
      $timestamp = mktime(0, 0, 0, date("m", $beginTimestamp), date("d", $beginTimestamp), date("Y", $beginTimestamp));

      $tmpDuration = $this->getRemaining();

      //echo "DEBUG user=".$user->getName()." tmpDuration = $tmpDuration begindate=".date('Y-m-d', $timestamp)."<br/>";

      // first day depends only on $availTimeOnBeginTimestamp
      if (NULL == $availTimeOnBeginTimestamp) {
         $availTime = $user->getAvailableTime($timestamp);
      } else {
         $availTime = $availTimeOnBeginTimestamp;
      }
      $tmpDuration -= $availTime;
      //echo "DEBUG 1st ".date('Y-m-d', $timestamp)." tmpDuration (-$availTime) = $tmpDuration<br/>";

      // --- next days
      while ($tmpDuration > 0) {
         $timestamp = strtotime("+1 day",$timestamp);

         if (NULL != $user) {
            $availTime = $user->getAvailableTime($timestamp);
            $tmpDuration -= $availTime;
            //echo "DEBUG ".date('Y-m-d', $timestamp)." tmpDuration = $tmpDuration<br/>";
         } else {
            // if not assigned, just check for global holidays
            if (NULL == Holidays::getInstance()->isHoliday($timestamp)) {
                 $tmpDuration -= 1; // it's not a holiday, so complete day available.
              }
         }
      }
      $endTimestamp = $timestamp;

      // if $tmpDuration < 0 this means that this issue will be finished before
      // the end of the day. So the remaining time must be reported to be available
      // fot the next issue to be worked on.
      $availTimeOnEndTimestamp = abs($tmpDuration);

      //echo "DEBUG $this->bugId.computeEstimatedEndTimestamp(".date('Y-m-d', $beginTimestamp).", $availTimeOnBeginTimestamp, $userid) = [".date('Y-m-d', $endTimestamp).",$availTimeOnEndTimestamp]<br/>\n";
      return array($endTimestamp, $availTimeOnEndTimestamp);
   }
   
   
   /**
    * returns the timestamp of the first time that
    * the issue switched to status 'status'
    *
    * @return timestamp or NULL if not found
    */
   public function getFirstStatusOccurrence($status) {

      global $status_new;

      if ($status_new == $status) {
         return $this->dateSubmission;
      }

      $query = "SELECT date_modified ".
               "FROM `mantis_bug_history_table` ".
               "WHERE bug_id=$this->bugId ".
               "AND field_name = 'status' ".
               "AND new_value=$status ORDER BY id";
      $result = mysql_query($query) or die("<span style='color:red'>Query FAILED: $query <br/>".mysql_error()."</span>");
      $timestamp  = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : NULL;
/*
      if (NULL == $timestamp) {
         echo "DEBUG issue $this->bugId: getFirstStatusOccurrence($status)  NOT FOUND ! <br/>\n";
      } else {
         echo "DEBUG issue $this->bugId: getFirstStatusOccurrence($status) = ".date('Y-m-d', $timestamp)."<br/>\n";
      }
*/
      return $timestamp;
   }

   /**
    * returns the timestamp of the latest time that
    * the issue switched to status 'status'
    *
    * @return timestamp or NULL if not found
    */
   public function getLatestStatusOccurrence($status) {

      global $status_new;

      if ($status_new == $status) {
         return $this->dateSubmission;
      }

      $query = "SELECT date_modified ".
               "FROM `mantis_bug_history_table` ".
               "WHERE bug_id=$this->bugId ".
               "AND field_name = 'status' ".
               "AND new_value=$status ORDER BY id DESC";
      $result = mysql_query($query) or die("<span style='color:red'>Query FAILED: $query <br/>".mysql_error()."</span>");
      $timestamp  = (0 != mysql_num_rows($result)) ? mysql_result($result, 0) : NULL;
/*
      if (NULL == $timestamp) {
         echo "DEBUG issue $this->bugId: getLatestStatusOccurrence($status)  NOT FOUND ! <br/>\n";
      } else {
         echo "DEBUG issue $this->bugId: getLatestStatusOccurrence($status) = ".date('Y-m-d', $timestamp)."<br/>\n";
      }
*/
      return $timestamp;
   }

   /**
    * returns a progress rate (depending on Remaining)
    * formula: (BI+BS - RAF) / (BI+BS)
    *
    * 1 = 100% finished
    * 0.5 = 50% done
    * 0 = 0% done
    */
   public function getProgress() {

      $bug_resolved_status_threshold = Config::getInstance()->getValue(Config::id_bugResolvedStatusThreshold);


      if ($this->currentStatus >= $bug_resolved_status_threshold) {
         return 1; // issue is finished
      }

      // totalEffort is (BI+BS) or if not exist, PEE
      if (NULL != $this->effortEstim) {
         $totalEffort = $this->effortEstim + $this->effortAdd;
      } else {
         $totalEffort = $this->eta;
      }

      // if no Remaining set, 0% done
      if (NULL == $this->remaining) {
         return 0;
      }

      // nominal case
      if ($this->remaining <= $totalEffort) {
         $progress = ($totalEffort - $this->remaining) / $totalEffort;   // (T-R)/T
      } else {
         $progress = $totalEffort / ($totalEffort - $this->remaining);   // T/(T+R)
      }
      return $progress;
   }
   
   
} // class issue

?>