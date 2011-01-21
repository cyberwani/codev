<?php if (!isset($_SESSION)) { session_start(); } ?>

<?php
if (!isset($_SESSION['userid'])) {
  echo ("Sorry, you need to <a href='../'\">login</a> to access this page.");
  exit;
} 
?>

<?php
   $_POST[page_name] = "Indicateurs de production"; 
   include '../header.inc.php'; 
?>

<?php include '../login.inc.php'; ?>
<?php include '../menu.inc.php'; ?>


<script language="JavaScript">
  function submitForm() {
    document.forms["form1"].teamid.value = document.getElementById('teamidSelector').value;
    document.forms["form1"].action.value = "timeTrackingReport";
    document.forms["form1"].submit();
 }

  function setProjectid() {
     document.forms["form1"].teamid.value = document.getElementById('teamidSelector').value;
     document.forms["form1"].projectid.value = document.getElementById('projectidSelector').value;
     document.forms["form1"].action.value="setProjectid";
     document.forms["form1"].submit();
  }
</script>

<div id="content">

<?php

include_once "../constants.php";
include_once "../tools.php";
include_once "period_stats.class.php";
include_once "project.class.php";

include_once "time_tracking.class.php";
require_once('tc_calendar.php');

// -----------------------------------------------
function setInfoForm($teamid, $defaultDate1, $defaultDate2, $defaultProjectid) {
  list($defaultYear, $defaultMonth, $defaultDay) = explode('-', $defaultDate1);
           
  $myCalendar1 = new tc_calendar("date1", true, false);
  $myCalendar1->setIcon("../calendar/images/iconCalendar.gif");
  $myCalendar1->setDate($defaultDay, $defaultMonth, $defaultYear);
  $myCalendar1->setPath("../calendar/");
  $myCalendar1->setYearInterval(2010, 2015);
  $myCalendar1->dateAllow('2010-01-01', '2015-12-31');
  $myCalendar1->setDateFormat('Y-m-d');
  $myCalendar1->startMonday(true);

  list($defaultYear, $defaultMonth, $defaultDay) = explode('-', $defaultDate2);
        
  $myCalendar2 = new tc_calendar("date2", true, false);
  $myCalendar2->setIcon("../calendar/images/iconCalendar.gif");
  $myCalendar2->setDate($defaultDay, $defaultMonth, $defaultYear);
  $myCalendar2->setPath("../calendar/");
  $myCalendar2->setYearInterval(2010, 2015);
  $myCalendar2->dateAllow('2010-01-01', '2015-12-31');
  $myCalendar2->setDateFormat('Y-m-d');
  $myCalendar2->startMonday(true);

  echo "<div class=center>";
  // Create form
  if (isset($_GET['debug'])) {
      echo "<form id='form1' name='form1' method='post' action='productivity_report.php?debug'>\n";
  } else {
  	   echo "<form id='form1' name='form1' method='post' action='productivity_report.php'>\n";
  }
  
  echo "Team: <select id='teamidSelector' name='teamidSelector'>\n";
  
  $session_user = new User($_SESSION['userid']);
  $mTeamList = $session_user->getTeamList();
  $lTeamList = $session_user->getLeadedTeamList();
  $oTeamList = $session_user->getObservedTeamList();
  $managedTeamList = $session_user->getManagedTeamList();
  $teamList = $mTeamList + $lTeamList + $oTeamList + $managedTeamList;

  foreach($teamList as $tid => $tname) {
    if ($tid == $teamid) {
      echo "<option selected value='".$tid."'>".$tname."</option>\n";
    } else {
      echo "<option value='".$tid."'>".$tname."</option>\n";
    }
  }
  echo "</select>\n";

  echo "&nbsp;Date d&eacute;but: "; $myCalendar1->writeScript();

  echo "&nbsp;Date fin (inclu): "; $myCalendar2->writeScript();

  echo "&nbsp;<input type=button value='Envoyer' onClick='javascript: submitForm()'>\n";

  echo "<input type=hidden name=teamid  value=$teamid>\n";
  echo "<input type=hidden name=projectid value=$defaultProjectid>\n";
  
  echo "<input type=hidden name=currentAction value=setInfoForm>\n";
  echo "<input type=hidden name=nextAction    value=timeTrackingReport>\n";

  echo "</form>\n";
  echo "</div>";
}




// ---------------------------------------------------------------
function setProjectSelectionForm($teamid, $defaultProjectid) {
   
   // Display form
   echo "<div style='text-align: left;'>";
  if (isset($_GET['debug'])) {
      echo "<form id='projectSelectionForm' name='projectSelectionForm' method='post' action='productivity_report.php?debug'>\n";
  } else {
      echo "<form id='projectSelectionForm' name='projectSelectionForm' method='post' action='productivity_report.php'>\n";
  }

  $project1 = new Project($defaultProjectid);
   
   // --- Project List
   $query  = "SELECT mantis_project_table.id, mantis_project_table.name ".
                 "FROM `codev_team_project_table`, `mantis_project_table` ".
                 "WHERE codev_team_project_table.team_id = $teamid ".
                 "AND codev_team_project_table.project_id = mantis_project_table.id ".
                 "ORDER BY mantis_project_table.name";
       $result = mysql_query($query) or die("Query failed: $query");
         if (0 != mysql_num_rows($result)) {
            while($row = mysql_fetch_object($result))
            {
               $projList[$row->id] = $row->name;
            }
       }
   echo "<span class='caption_font'>Detail Projet </span>\n";       
   echo "<select id='projectidSelector' name='projectidSelector' onchange='javascript: setProjectid()' title='Projet'>\n";
   echo "<option value='0'> </option>\n";
   foreach ($projList as $pid => $pname)
   {
      if ($pid == $defaultProjectid) {
         echo "<option selected value='".$pid."'>$pname</option>\n";
      } else {
         echo "<option value='".$pid."'>$pname</option>\n";
      }
   }
   echo "</select>\n";

   echo "<input type=hidden name=teamid     value=$teamid>\n";
   echo "<input type=hidden name=projectid value=$defaultProjectid>\n";
   echo "<input type=hidden name=action    value=noAction>\n";
   echo "</form>\n";
   
   echo "</div>\n";
}




// -----------------------------------------------
function displayRates ($timeTracking) {
         
  $prodDays                = $timeTracking->getProdDays();
  $sideProdDaysDevel       = $timeTracking->getProdDaysSideTasks(true);
  $sideProdDaysManagers    = $timeTracking->getProdDaysSideTasks(false) - $sideProdDaysDevel;
  $productivityRateETA     = $timeTracking->getProductivityRate("ETA");
  $productivityRateBI      = $timeTracking->getProductivityRate("EffortEstim");
  $efficiencyRate          = $timeTracking->getEfficiencyRate();
  $systemDisponibilityRate = $timeTracking->getSystemDisponibilityRate();
  $productionDaysForecast  = $timeTracking->getProductionDaysForecast();
        
  echo "<table>\n";
  echo "<caption>Indicateurs de productivit&eacute;</caption>\n";
  echo "<tr>\n";
  echo "<th>Indicateur</th>\n";
  echo "<th>Valeur</th>\n";
  echo "<th>Description</th>\n";
  echo "<th>Formule</th>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td>Production Days : Projects</td>\n";
  echo "<td>$prodDays</td>\n";
  echo "<td>nombre de jours pass&eacute;s en d&eacute;veloppement sur les projets</td>\n";
  echo "<td></td>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td>Production Days : SuiviOp Dev</td>\n";
  echo "<td>$sideProdDaysDevel</td>\n";
  echo "<td>nombre de jours pass&eacute;s sur les taches annexes (hors cong&eacute;s) par les Developers</td>\n";
  echo "<td></td>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td>Production Days : SuiviOp Managers</td>\n";
  echo "<td>$sideProdDaysManagers</td>\n";
  echo "<td>nombre de jours pass&eacute;s sur les taches annexes (hors cong&eacute;s) par les Managers</td>\n";
  echo "<td></td>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td>Production Days : total</td>\n";
  echo "<td>".($sideProdDaysDevel + $sideProdDaysManagers + $prodDays)."</td>\n";
  echo "<td>nombre de jours factur&eacute;s</td>\n";
  echo "<td></td>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td>Capacit&eacute; de production</td>\n";
  echo "<td>".$productionDaysForecast."</td>\n";
  echo "<td>pr&eacute;vision de capacit&eacute; (hors cong&eacute;s, d&eacute;veloppeurs seuls)</td>\n";
  echo "<td></td>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td>Efficiency Rate</td>\n";
  echo "<td>".number_format($efficiencyRate, 2)."%</td>\n";
  echo "<td>temps consomm&eacute; en d&eacute;veloppement (d&eacute;veloppeurs seuls)</td>\n";
  echo "<td>ProjProdDays / TotalProdDays * 100</td>\n";
  echo "</tr>\n";
  
  echo "<tr>\n";
  echo "<td>System Disponibility</td>\n";
  echo "<td>".number_format($systemDisponibilityRate, 3)."%</td>\n";
  echo "<td>disponibilit&eacute; de la plateforme de develomppement</td>\n";
  echo "<td>100 - (breakdownDays / prodDays)</td>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td>Productivity Rate ETA</td>\n";
  echo "<td>".number_format($productivityRateETA, 2)."</td>\n";
  echo "<td>Nombre moyen de fiches resolues par jour.<br/>".
            "- Si l'estimation est bonne ce nbre doit tendre vers 1.<br/>".
            "- Le temps pass� sur une fiche est pond�r� par un indicateur de difficult&eacute;: ".
            "ETA (temps estim&eacute; AVANT analyse)<br/>".
            "- Le calcul est fait sur les fiches Resolved/Closed dans la p&eacute;riode.<br/>".
            "- Les fiches r&eacute;ouvertes ne sont pas comptabilis&eacute;s</td>\n";
  echo "<td>sum(ETA) / sum(elapsed)</td>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td>Productivity Rate</td>\n";
  echo "<td>".number_format($productivityRateBI, 2)."</td>\n";
  echo "<td>Nombre moyen de fiches resolues par jour.<br/>".
            "- Si l'estimation est bonne ce nbre doit tendre vers 1.<br/>".
            "- Le temps pass� sur une fiche est pond&eacute;r&eacute; par un indicateur de difficult&eacute;: ".
            "EffortEstim (temps estim&eacute; APRES analyse)<br/>".
            "- Le calcul est fait sur les fiches Resolved/Closed dans la p&eacute;riode.<br/>".
            "- Les fiches r&eacute;ouvertes ne sont pas comptabilis&eacute;es</td>\n";
    echo "<td>sum(EffortEstim + BS) / sum(elapsed)</td>\n";
  echo "</tr>\n";

  echo "</table>\n";

  //echo "<br/>SideTasks<br/>";
  //echo "Nb Production Days  : $sideProdDays<br/>";
  //echo "ProductivityRate    : ".$sideProductivityRate."<br/>\n";
}


// -----------------------------------------------
// display Drifts for Issues that have been marked as 'Resolved' durung the timestamp
function displayResolvedDriftStats ($timeTracking) {
  global $status_resolved;
  global $status_closed;
         
  $driftStats_new = $timeTracking->getResolvedDriftStats();
  //$driftStats_new = $timeTracking->getDriftStats(); // TODO
  
  
  echo "<table>\n";
  echo "<caption>D&eacute;rives - Resolved dans la p&eacute;riode</caption>\n";
  echo "<tr>\n";
  echo "<th></th>\n";
  echo "<th width='100' title='AVANT analyse'>ETA</th>\n";
  echo "<th width='100' title='APRES analyse'>EffortEstim <br/>(BI + BS)</th>\n";
  echo "<th>Description</th>\n";
  echo "<th>Formule</th>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td title='si n&eacute;gatif, avance sur le planing'>D&eacute;rive</td>\n";
  echo "<td title='elapsed - ETA'>".number_format($driftStats_new["totalDriftETA"], 2)."</td>\n";
  echo "<td title='elapsed - EffortEstim'>".number_format($driftStats_new["totalDrift"], 2)."</td>\n";
  echo "<td>Nb jours de d&eacute;passement<br/>".
            "- Le calcul est fait sur les fiches Resolved/Closed dans la p&eacute;riode.<br/>".
            "- Les fiches r&eacute;ouvertes ne sont pas comptabilis&eacute;es.<br/>\n".
            "- Si n�gatif, avance sur le planing.</td>\n";
  echo "<td>elapsed - EffortEstim</td>\n";
  echo "</tr>\n";
  
  echo "<tr>\n";
  echo "<td>nbre Fiches en D&eacute;rive</td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsPosETA"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftPosETA"]).")</span></td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsPos"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftPos"]).")</span></td>\n";
  echo "<td title='Liste des Fiches pour EffortEstim'>".$driftStats_new["formatedBugidPosList"]."</td>\n";
  echo "<td>derive > 1</td>\n";
  echo "</tr>\n";
  
  echo "<tr>\n";
  echo "<td>nbre Fiches &agrave; l'&eacute;quilibre</td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsEqualETA"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftEqualETA"] + $driftStatsClosed["driftEqualETA"]).")</span></td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsEqual"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftEqual"] + $driftStatsClosed["driftEqual"]).")</span></td>\n";
  if (isset($_GET['debug'])) {
   echo "<td title='Liste des fiches pour EffortEstim'>".$driftStats_new["formatedBugidEqualList"]."</td>\n";
  } else {
   echo "<td>Fiches livres dans les temps.</td>\n";
  }
  echo "<td> -1 <= derive <= 1</td>\n";
  echo "</tr>\n";
  
  echo "<tr>\n";
  echo "<td>nbre Fiches en avance</td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsNegETA"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftNegETA"]).")</span></td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsNeg"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftNeg"]).")</span></td>\n";
  echo "<td title='Liste des fiches pour EffortEstim'>".$driftStats_new["formatedBugidNegList"]."</td>\n";
  echo "<td>derive < -1</td>\n";
  echo "</tr>\n";
  echo "</table>\n";
}



// -----------------------------------------------
// display Drifts for Issues that are CURRENTLY OPENED 
function displayCurrentDriftStats ($timeTracking) {

   global $status_resolved;
   global $status_delivered;
   global $status_closed;
	  
    // ---- get Issues that are not Resolved/Closed   
    $formatedProdProjectList = simpleListToSQLFormatedString($timeTracking->prodProjectList);
    $issueList = array();
    
    $query = "SELECT DISTINCT id ".
               "FROM `mantis_bug_table` ".
               "WHERE status NOT IN ($status_resolved,$status_delivered,$status_closed) ".
               "AND project_id IN ($formatedProdProjectList)".
               "ORDER BY id DESC";
    $result = mysql_query($query) or die("Query failed: $query");
    while($row = mysql_fetch_object($result)) {
            $issue = new Issue($row->id);
            $user = new User($issue->handlerId);
            
            $issueList[] = $issue;
            
    }

    $driftStats_new = $timeTracking->getIssuesDriftStats($issueList);
  
  
  echo "<table>\n";
  echo "<caption>D&eacute;rives - En cours &agrave; ce jour</caption>\n";
  echo "<tr>\n";
  echo "<th></th>\n";
  echo "<th width='100' title='AVANT analyse'>ETA</th>\n";
  echo "<th width='100' title='APRES analyse'>EffortEstim <br/>(BI + BS)</th>\n";
  echo "<th>Description</th>\n";
  echo "<th>Formule</th>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  echo "<td title='si n&eacute;gatif, avance sur le planing'>D&eacute;rive</td>\n";
  echo "<td title='elapsed - ETA'>".number_format($driftStats_new["totalDriftETA"], 2)."</td>\n";
  echo "<td title='elapsed - EffortEstim'>".number_format($driftStats_new["totalDrift"], 2)."</td>\n";
  echo "<td>Nb jours de d&eacute;passement<br/>".
            "- Le calcul est fait sur les fiches NON Resolved/Closed au ".date("Y-m-d").".<br/>";
  echo "<td>elapsed - EffortEstim</td>\n";
  echo "</tr>\n";
  
  echo "<tr>\n";
  echo "<td>nbre Fiches en D&eacute;rive</td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsPosETA"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftPosETA"]).")</span></td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsPos"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftPos"]).")</span></td>\n";
  echo "<td title='Liste des Fiches pour EffortEstim'>".$driftStats_new["formatedBugidPosList"]."</td>\n";
  echo "<td>derive > 1</td>\n";
  echo "</tr>\n";
  
  echo "<tr>\n";
  echo "<td>nbre Fiches &agrave; l'&eacute;quilibre</td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsEqualETA"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftEqualETA"] + $driftStatsClosed["driftEqualETA"]).")</span></td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsEqual"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftEqual"] + $driftStatsClosed["driftEqual"]).")</span></td>\n";
  if (isset($_GET['debug'])) {
   echo "<td title='Liste des Fiches pour EffortEstim'>".$driftStats_new["formatedBugidEqualList"]."</td>\n";
  } else {
   echo "<td>Fiches livres dans les temps.</td>\n";
  }
  echo "<td> -1 <= derive <= 1</td>\n";
  echo "</tr>\n";
  
  echo "<tr>\n";
  echo "<td>nbre Fiches en avance</td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsNegETA"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftNegETA"]).")</span></td>\n";
  echo "<td title='nb fiches'>".($driftStats_new["nbDriftsNeg"])."<span title='nb jours' class='floatr'>(".($driftStats_new["driftNeg"]).")</span></td>\n";
  echo "<td title='Liste des Fiches pour EffortEstim'>".$driftStats_new["formatedBugidNegList"]."</td>\n";
  echo "<td>derive < -1</td>\n";
  echo "</tr>\n";
  echo "</table>\n";
}


// --------------------------------
function displayWorkingDaysPerJob($timeTracking) {
  echo "<table width='300'>\n";
  echo "<caption>Charge par poste</caption>\n";
  echo "<tr>\n";
  echo "<th>Poste</th>\n";
  echo "<th>Nb jours</th>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  $query     = "SELECT id, name FROM `codev_job_table`";
  $result    = mysql_query($query) or die("Query failed: $query");
  while($row = mysql_fetch_object($result))
  {
    echo "<tr>\n";
    echo "<td>$row->name</td>\n";
    echo "<td>".$timeTracking->getWorkingDaysPerJob($row->id)."</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
}

// -----------------------------------------------
function displayWorkingDaysPerProject($timeTracking) {
  echo "<table width='300'>\n";
  echo "<caption>Charge par projet</caption>\n";
  echo "<tr>\n";
  echo "<th>Projet</th>\n";
  echo "<th>Nb jours</th>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  $query     = "SELECT mantis_project_table.id, mantis_project_table.name ".
               "FROM `mantis_project_table`, `codev_team_project_table` ".
               "WHERE codev_team_project_table.project_id = mantis_project_table.id ".
               "AND codev_team_project_table.team_id = $timeTracking->team_id ".
               " ORDER BY name";
  $result    = mysql_query($query) or die("Query failed: $query");
  while($row = mysql_fetch_object($result))
  {
    echo "<tr>\n";
    echo "<td>";
    echo "$row->name\n";
    if (isset($_GET['debug'])) { echo " (".$row->id.")"; }
    echo "</td>\n";
    echo "<td>".$timeTracking->getWorkingDaysPerProject($row->id)."</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
}

// -----------------------------------------------
function displaySideTalksProjectDetails($timeTracking) {
  global $sideTaskProjectType;
   
  $durationPerCategory = array();
  $formatedBugsPerCategory = array();
  $stProjList = "";
  
  // find all sideTasksProjects (type = 1)
  $query     = "SELECT project_id ".
               "FROM `codev_team_project_table` ".
               "WHERE team_id = $timeTracking->team_id ".
               "AND type = $sideTaskProjectType";
  $result = mysql_query($query) or die("Query failed: $query");
  while($row = mysql_fetch_object($result))
  {
     $durPerCat = $timeTracking->getProjectDetails($row->project_id);
     foreach ($durPerCat as $catName => $bugList)
     {
     	   foreach ($bugList as $bugid => $duration) {
     	   	$durationPerCategory[$catName] += $duration;
     	   	
     	   	if ($formatedBugsPerCategory[$catName] != "") { $formatedBugsPerCategory[$catName] .= ', '; }
     	   	$issue = new Issue($bugid);
            $formatedBugsPerCategory[$catName] .= mantisIssueURL($bugid, $issue->summary);
     	   }
     }
     
     $proj = new Project($row->project_id);
     $stProjList[] = $proj->name;
     
  }
  $formatedProjList = simpleListToSQLFormatedString($stProjList);
  
  $formatedBugList = "";
  
  echo "<table width='300'>\n";
  echo "<caption title='Projets: $formatedProjList'>Detail Gestion de Projet</caption>\n";
  echo "<tr>\n";
  echo "<th>Categorie</th>\n";
  echo "<th>Nb jours</th>\n";
  echo "<th>Fiches</th>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  foreach ($durationPerCategory as $catName => $duration)
  {
    echo "<tr bgcolor='white'>\n";
    echo "<td>$catName</td>\n";
    echo "<td>$duration</td>\n";
    echo "<td>".$formatedBugsPerCategory[$catName]."</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
}

// -----------------------------------------------
function displayProjectDetails($timeTracking, $projectId) {
   
  $durationPerCategory = array();
  $formatedBugsPerCategory = array();
  
  $durPerCat = $timeTracking->getProjectDetails($projectId);
  foreach ($durPerCat as $catName => $bugList)
  {
      foreach ($bugList as $bugid => $duration) {
         $durationPerCategory[$catName] += $duration;
         
         if ($formatedBugsPerCategory[$catName] != "") { $formatedBugsPerCategory[$catName] .= ', '; }
         $issue = new Issue($bugid);
         $formatedBugsPerCategory[$catName] .= mantisIssueURL($bugid, $issue->summary);
      }
  }
     
  $proj = new Project($projectId);
  echo "<table width='300'>\n";
  //echo "<caption>Detail Projet ".$proj->name."</caption>\n";
  echo "<tr>\n";
  echo "<th>Categorie</th>\n";
  echo "<th>Nb jours</th>\n";
  echo "<th>Fiches</th>\n";
  echo "</tr>\n";

  echo "<tr>\n";
  foreach ($durationPerCategory as $catName => $duration)
  {
    echo "<tr bgcolor='white'>\n";
    echo "<td>$catName</td>\n";
    echo "<td>$duration</td>\n";
    echo "<td>".$formatedBugsPerCategory[$catName]."</td>\n";
    echo "</tr>\n";
  }
  echo "</table>\n";
}

// -----------------------------------------------
function displayCheckWarnings($timeTracking) {
  $query = "SELECT codev_team_user_table.user_id, mantis_user_table.username ".
    "FROM  `codev_team_user_table`, `mantis_user_table` ".
    "WHERE  codev_team_user_table.team_id = $timeTracking->team_id ".
    "AND    codev_team_user_table.user_id = mantis_user_table.id ".
    "ORDER BY mantis_user_table.username";   
   
  $result = mysql_query($query) or die("Query failed: $query");
   
  echo "<p style='color:red'>\n";
   
  while($row = mysql_fetch_object($result))
  {
    $incompleteDays = $timeTracking->checkCompleteDays($row->user_id, TRUE);
    foreach ($incompleteDays as $date => $value) {
      $formatedDate = date("Y-m-d", $date);
      if ($value < 1) {
        echo "<br/>$row->username: $formatedDate incomplet (manque ".(1-$value)." jour).\n";
      } else {
        echo "<br/>$row->username: $formatedDate incoh&eacute;rent (".($value)." jour).\n";
      }
    }
                   
    $missingDays = $timeTracking->checkMissingDays($row->user_id);
    foreach ($missingDays as $date) {
      $formatedDate = date("Y-m-d", $date);
      echo "<br/>$row->username: $formatedDate non d&eacute;finie.\n";
    }
  }
  echo "</p>\n";
}

// =========== MAIN ==========
$year = date('Y');

$defaultTeam = isset($_SESSION[teamid]) ? $_SESSION[teamid] : 0;
$teamid = isset($_POST[teamid]) ? $_POST[teamid] : $defaultTeam;
$_SESSION[teamid] = $teamid;


// Connect DB
$link = mysql_connect($db_mantis_host, $db_mantis_user, $db_mantis_pass) 
  or die("Impossible de se connecter");
mysql_select_db($db_mantis_database) or die("Could not select database");

$weekDates      = week_dates(date('W'),$year);

$action           = $_POST[action];
$defaultProjectid = isset($_POST[projectid]) ? $_POST[projectid] : 0;

$date1  = isset($_REQUEST["date1"]) ? $_REQUEST["date1"] : date("Y-m-d", $weekDates[1]);
$date2  = isset($_REQUEST["date2"]) ? $_REQUEST["date2"] : date("Y-m-d", $weekDates[5]);

$startTimestamp = date2timestamp($date1);
$endTimestamp = date2timestamp($date2);

$endTimestamp += 24 * 60 * 60 -1; // + 1 day -1 sec.

//echo "DEBUG startTimestamp $startTimestamp  ".date("Y-m-d H:i:s", $startTimestamp)."<br/>";
//echo "DEBUG endTimestamp $endTimestamp  ".date("Y-m-d H:i:s", $endTimestamp)."<br/>";

$timeTracking = new TimeTracking($startTimestamp, $endTimestamp, $teamid);
        
setInfoForm($teamid, $date1, $date2, $defaultProjectid);
echo "<br/><br/>\n";


if (0 != $teamid) {

	echo "<br/>\n";
	echo "du ".date("Y-m-d  (H:i)", $startTimestamp)."&nbsp;<br/>";
	echo "au ".date("Y-m-d  (H:i)", $endTimestamp)."<br/><br/>\n";
	
	// Display on 3 columns
	echo "<div class=\"float\">\n";
	displayWorkingDaysPerJob($timeTracking);
	echo "</div>\n";
	
	echo "<div class=\"float\">\n";
	displayWorkingDaysPerProject($timeTracking);
	echo "</div>\n";
	
	echo "<div class=\"float\">\n";
	displaySideTalksProjectDetails($timeTracking);
	echo "</div>\n";

   echo "<div class=\"float\">\n";
   setProjectSelectionForm($teamid, $defaultProjectid);
   $defaultProjectid  = $_POST[projectid];
   if (0 != $defaultProjectid) {
      displayProjectDetails($timeTracking, $defaultProjectid);
   }
   echo "</div>\n";
	
	echo "<div class=\"spacer\"> </div>\n";
	
	echo "<br/><br/>\n";
	displayRates($timeTracking);
	        
	echo "<br/><br/>\n";
	displayResolvedDriftStats($timeTracking);
	
   echo "<br/><br/>\n";
   displayCurrentDriftStats($timeTracking);
   
	echo "<br/><br/>\n";
	displayCheckWarnings($timeTracking);
}

?>

</div>

<?php include '../footer.inc.php'; ?>