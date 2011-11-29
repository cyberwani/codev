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

// ==============================================================


/**
 * Constants Singleton class
 * contains CoDev settings
 * @author lbayle
 *
 */

class ConfigItem {
   
   public $id;
   public $value;
   public $type;

	public function __construct($id, $value, $type) 
   {
   	$this->id    = $id;
      $this->type  = $type;
      
      switch ($type) {
      	case Config::configType_keyValue :
            $this->value = (NULL != $value) ? $this->value = doubleExplode(':', ',', $value) : NULL;      		
      		break;
         case Config::configType_array :
         	$this->value = (NULL != $value) ? $this->value = explode(',', $value) : NULL;
            break;
         default:
         	$this->value = $value;
      }
   }
	
}

class Config {
   
   const  configType_int      = 1;
   const  configType_string   = 2;
   const  configType_keyValue = 3;
   const  configType_array    = 4;

   // known Config ids
   const id_defaultSideTaskProject   = "defaultSideTaskProject";
   const id_jobSupport               = "job_support";
   const id_adminTeamId              = "adminTeamId";
   const id_statusNames              = "statusNames";
   const id_astreintesTaskList       = "astreintesTaskList";
   const id_codevReportsDir          = "codevReportsDir";
   const id_customField_TC           = "customField_TC";
   const id_customField_PrelEffortEstim = "customField_PrelEffortEstim";  // ex ETA
   const id_customField_effortEstim  = "customField_effortEstim"; //  BI
   const id_customField_remaining    = "customField_remaining"; //  RAE
   const id_customField_deadLine     = "customField_deadLine";
   const id_customField_addEffort    = "customField_addEffort"; // BS
   const id_customField_deliveryId   = "customField_deliveryId"; // FDL (id of the associated Delivery Issue)
   const id_customField_deliveryDate = "customField_deliveryDate";
   const id_prelEffortEstim_balance  = "prelEffortEstim_balance"; // ex ETA_balance
   const id_priorityNames            = "priorityNames";
   const id_resolutionNames          = "resolutionNames";
   const id_mantisFile_strings       = "mantisFile_strings";
   const id_mantisFile_custom_strings = "mantisFile_custom_strings";
   const id_mantisPath                = "mantisPath";
   const id_bugResolvedStatusThreshold     = "bug_resolved_status_threshold";

   const id_ClientTeamid             = "client_teamid"; // FDJ_teamid
   
   private static $instance;    // singleton instance
   private static $configItems;
    
   // --------------------------------------
   private function __construct() 
   {
      self::$configItems = array();
    	
      $query = "SELECT * FROM `codev_config_table`";
      $result = mysql_query($query) or die("Query failed: $query");
      while($row = mysql_fetch_object($result))
      {
      	#echo "DEBUG: Config:: $row->config_id<br/>";
      	self::$configItems["$row->config_id"] = new ConfigItem($row->config_id, $row->value, $row->type);
      }
    
        #echo "DEBUG: Config ready<br/>";
   }

   // --------------------------------------
   /**
    * get Singleton instance
    */
   public static function getInstance() 
   {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
        return self::$instance;
   }
   
   // --------------------------------------
   public static function getValue($id) {
    	
    	$value == NULL;
    	$item = self::$configItems[$id];
    	
    	if (NULL != $item) {	
         $value = $item->value;
    	} else {
    		echo "DEBUG: Config::getValue($id): item not found !<br/>";
    	}
    	return $value;
   }
    
   // --------------------------------------
   public static function getType($id) {
      
      $type == NULL;
      $item = self::$configItems[$id];
      
      if (NULL != $item) { 
         $type = $item->type; 
      } else {
         echo "DEBUG: Config::getType($id): item not found !<br/>";
      }
      return $value;
   }
   
   // --------------------------------------
   /**
    * Add or update an Item (in DB and Cache)
    * 
    * Note: update does not change the type.
    * 
    * @param unknown_type $id
    * @param unknown_type $value
    */
   public static function addValue($id, $value, $type, $desc=NULL) {

   	// add/update DB
      $query = "SELECT * FROM `codev_config_table` WHERE config_id='$id'";
      $result = mysql_query($query) or die("Query failed: $query");
      if (0 != mysql_num_rows($result)) {
         $query2 = "UPDATE `codev_config_table` SET value = '$value' WHERE config_id='$id'";
         echo "DEBUG UPDATE Config::addValue $id: $value (t=$type) $desc<br/>";
      } else {
         $query2 = "INSERT INTO `codev_config_table` (`config_id`, `value`, `type`, `desc`) VALUES ('$id', '$value', '$type', '$desc');";
         echo "DEBUG INSERT Config::addValue $id: $value (t=$type) $desc<br/>";
      }
      $result    = mysql_query($query2) or die("Query failed: $query2");

      // --- add/replace Cache
      self::$configItems["$id"] = new ConfigItem($id, $value, $type);
      
   }
   
} // class

?>