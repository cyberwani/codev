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

   /*
    * The Variables in here are not expected to be changed in any way.
    * 
    * most of them are initialyzed from the 'codev_config_table'.
    * 
    */

   include_once "config.class.php"; 


   $codevVersion = "v1.0.1 (19 Oct  2011)";

   $codevVersionHistory = array("v0.99.0" => "(09 Sept 2010) - team management complete",
                                "v0.99.1" => "(28 Sept 2010) - jobs management",
                                "v0.99.2" => "(08 Dec  2010) - Project Management",
                                "v0.99.3" => "(03 Jan  2011) - fix new year problems",
                                "v0.99.4" => "(13 Jan  2011) - ConsistencyCheck",
                                "v0.99.5" => "(21 Jan  2011) - Update directory structure & Apache config",
                                "v0.99.6" => "(16 Feb  2011) - i18n (internationalization)",
                                "v0.99.7" => "(25 Feb  2011) - Graph & Statistics",
                                "v0.99.8" => "(25 Mar  2011) - Add Job and specificities for 'support' + createTeam enhancements",
                                "v0.99.9" => "(11 Apr  2011) - Planning + enhance global performances",
                                "v1.0.0" =>  "(29 Jul  2011) - Add indicators: ReopenedRate, DelayRate + noFilterTC + security + bugfixes",
                                "v1.0.1" =>  "(19 Oct  2011) - Gantt (beta)"
                                );

                                
  // ---
  // il peut y avoir plusieurs observer
  // il n'y a qu'un seul teamLeader
  // il peut y avoir plusieurs managers, mais ils ne peuvent imputer que sur des SideTasks 
  // un observer ne fait jamais partie de l'equipe, il n'a acces qu'a des donnees impersonnelles
  
  $accessLevel_dev      = 10;    // in table codev_team_user_table
  $accessLevel_observer = 20;    // in table codev_team_user_table
  //$accessLevel_teamleader = 25;    // REM: NOT USED FOR NOW !!
  $accessLevel_manager  = 30;    // in table codev_team_user_table 
  $access_level_names = array($accessLevel_dev      => "Developper", // can modify, can NOT view stats
                              $accessLevel_observer => "Observer",  // can NOT modify, can view stats  
                              //$accessLevel_teamleader => "TeamLeader",  // REM: NOT USED FOR NOW !! can modify, can view stats, can work on projects ? , included in stats ?   
                              $accessLevel_manager  => "Manager");  // can modify, can view stats, can only work on sideTasksProjects, resource NOT in statistics
                              
  
  // ---
  $workingProjectType   = 0;     // normal projects are type 0
  $sideTaskProjectType  = 1;     // SuiviOp must be type 1
  $noCommonProjectType  = 2;     // projects which jave only assignedJobs (no common jobs) REM: these projects are not considered as sideTaskProjects

  $projectType_names = array($workingProjectType => "Project",
                             $noCommonProjectType => "Project (no common jobs)",
                             $sideTaskProjectType => "SideTasks");
  
  // ---
  $commonJobType   = 0;     // jobs common to all projects are type 0
  $assignedJobType = 1;     // jobs specific to one or more projects are type 1
  $jobType_names = array($commonJobType => "Common",
                         $assignedJobType => "Assigned");


  // ================== 

  $admin_teamid = Config::getInstance()->getValue("adminTeamId"); // users allowed to do CoDev administration

  $job_support = Config::getInstance()->getValue("job_support"); // jobid in codev_job_table corresponding to the 'Support' job (used to compute drifts)                         
  
  // this is the custom field added to mantis issues for TimeTracking
  $tcCustomField           = Config::getInstance()->getValue("customField_TC");
  $estimEffortCustomField  = Config::getInstance()->getValue("customField_effortEstim"); //  BI
  $remainingCustomField    = Config::getInstance()->getValue("customField_remaining"); //  RAE
  $deadLineCustomField     = Config::getInstance()->getValue("customField_deadLine");
  $addEffortCustomField    = Config::getInstance()->getValue("customField_addEffort"); // BS
  $deliveryIdCustomField   = Config::getInstance()->getValue("customField_deliveryId"); // FDL (id of the associated Delivery Issue)
  $deliveryDateCustomField = Config::getInstance()->getValue("customField_deliveryDate");
  
  
  // ---
  // toughness indicator to compute "Productivity Rate ETA"
  $ETA_balance = Config::getInstance()->getValue("ETA_balance");
  $ETA_names   = Config::getInstance()->getValue("ETA_names");
  
  // ---
  $astreintesTaskList = Config::getInstance()->getValue("astreintesTaskList"); // fiches de SuiviOp:Inactivite qui sont des astreintes
                       
  
  // --- Mantis Values ---
  // Unfortunately the following values are not in Mantis database, you'll have to manualy copy those
  // definition if you customized them.
  
  //$statusNames     = Config::getInstance()->getValue("statusNames");
  
  $priorityNames   = Config::getInstance()->getValue("priorityNames");
  $resolutionNames = Config::getInstance()->getValue("resolutionNames");
  // ---
  
  // ---
  // the projects listed here will be excluded from PeriodStatsReport 
  $periodStatsExcludedProjectList = Config::getInstance()->getValue("periodStatsExcludedProjectList");
  
  $defaultSideTaskProject = Config::getInstance()->getValue("defaultSideTaskProject"); // "SuiviOp" in table mantis_project_table
  
  $codevReportsDir = Config::getInstance()->getValue("codevReportsDir");
  
                         
                         
?>