<?php


//include_once "constants.php";
//include_once "tools.php";
include_once "../reports/issue.class.php";
include_once "../auth/user.class.php";

class ConsistencyError {
	
	var $bugId;
	var $userId;
	var $teamId;
	var $desc;
   var $timestamp;
   var $status;
   
	public function ConsistencyError($bugId, $userId, $status, $timestamp, $desc) {
		$this->bugId     = $bugId;
      $this->userId    = $userId;
      $this->status = $status;
      $this->timestamp = $timestamp;
      $this->desc      = $desc;
	}
}


class ConsistencyCheck {
  
   // ----------------------------------------------
   public function ConsistencyCheck() {
   }
  
   // ----------------------------------------------
   /**
    * perform all consistency checks
    */
   public function check() {
      
      $cerrList1 = $this->checkAnalyzed();
      $cerrList2 = $this->checkResolved();
      $cerrList3 = $this->checkDeliveryDate();

      $cerrList = array_merge($cerrList1, $cerrList2, $cerrList3);
      return $cerrList;
   }
   
   
   // ----------------------------------------------
   /**
    * if $deliveryIssueCustomField is specified, then $deliveryDateCustomField should also be specified.
    */
   public function checkDeliveryDate() {
      global $status_resolved;
      global $status_delivered;
      global $status_closed;
   	
      global $deliveryIdCustomField; // in mantis_custom_field_table 'FDL'
      global $deliveryDateCustomField; // in mantis_custom_field_table  'Liv. Date'
      
      $cerrList = array();
      
      // select all issues which current status is 'analyzed'
      $query = "SELECT id AS bug_id, status, handler_id, last_updated ".
        "FROM `mantis_bug_table` ".
        "WHERE status in ($status_resolved, $status_delivered, $status_closed) ".
        "ORDER BY bug_id DESC";
      
      $result    = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         $issue = new Issue($row->bug_id);
         
         if ((NULL != $issue->deliveryId) &&  
         	 (NULL == $issue->deliveryDate)) {
               $cerrList[] = new ConsistencyError($row->bug_id, 
                                              $row->handler_id, 
                                              $row->status,
                                              $row->last_updated, 
                                              "Date de livraison non renseign&eacute;: si une fiche de livraison est sp&eacute;cifi&eacute;e, alors une date doit y &ecirc;tre associ&eacute;e");
         }
      }
      return $cerrList;
      
   }
   
   // ----------------------------------------------
   // fiches analyzed dont BI non renseignes
   // fiches analyzed dont RAE non renseignes
   public function checkAnalyzed() {
   	
   	global $status_analyzed;
      global $status_accepted;
      global $status_openned;
      global $status_deferred;
      global $status_resolved;
      global $status_delivered;
      global $status_closed;
   	global $FDJ_teamid;
   	
      $cerrList = array();

      // select all issues which current status is 'analyzed'
      $query = "SELECT id AS bug_id, status, handler_id, last_updated ".
        "FROM `mantis_bug_table` ".
        "WHERE status in ($status_analyzed, $status_accepted, $status_openned, $status_deferred) ".
        "ORDER BY bug_id DESC";
      
      $result    = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
      	$issue = new Issue($row->bug_id);
      	
         if (NULL == $issue->effortEstim) {
           $cerrList[] = new ConsistencyError($row->bug_id, 
                                              $row->handler_id, 
                                              $row->status,
                                              $row->last_updated, 
                                              "BI non renseign&eacute;: il doit &ecirc;tre &eacute;gal &agrave; temps(Analyse + Dev + Tests)");
         }
      	if (NULL == $issue->remaining) {
           $cerrList[] = new ConsistencyError($row->bug_id, 
                                              $row->handler_id,
                                              $row->status,
                                              $row->last_updated, 
                                              "RAE non renseign&eacute;: il doit &ecirc;tre &eacute;gal &agrave; temps(BI - Analyse)");
         }
         if ($status_analyzed == $row->status) {
             $user = new User($row->handler_id);
             if (! $user->isTeamMember($FDJ_teamid)) {
              $cerrList[] = new ConsistencyError($row->bug_id, 
                                                 $row->handler_id,
                                                 $row->status,
                                                 $row->last_updated, 
                                                 "Apr&egrave;s l'analyse, une fiche doit &ecirc;tre assign&eacute;e &agrave; 'FDJ' pour validation");
             }
         	
         }
      }
      
      
      // check if fields correctly set
      
      return $cerrList;
   }
   
   // ----------------------------------------------
   // fiches resolved dont le RAE != 0
   public function checkResolved() {
      
   	global $statusNames;
   	global $status_resolved;
      global $status_delivered;
      global $status_closed;
      
      
      $cerrList = array();

      // select all issues which current status is 'analyzed'
      $query = "SELECT id AS bug_id, status, handler_id, last_updated ".
        "FROM `mantis_bug_table` ".
        "WHERE status in ($status_resolved, $status_delivered, $status_closed) ".
        "ORDER BY last_updated DESC, bug_id DESC";
      
      $result    = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
         // check if fields correctly set
      	$issue = new Issue($row->bug_id);
         
         if (0 != $issue->remaining) {
           $cerrList[] = new ConsistencyError($row->bug_id, 
                                              $row->handler_id, 
                                              $row->status, 
                                              $row->last_updated, 
                                              "Le RAE devrait &ecirc;tre = 0  (et non ".$issue->remaining.")");
         }
      }
      
      
      
      return $cerrList;
  	}
  
}


?>