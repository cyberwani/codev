<?php
include_once('../include/session.inc.php');

include_once '../path.inc.php';

include_once 'i18n.inc.php';

$page_name = T_("Tools: serialize");
require_once 'header.inc.php';

include_once 'install.class.php';

/**
 * local test func
 */
function addCustomMenuItem($serialized, $name, $url) {

    $pos = '10'; // invariant

    if ((NULL != $serialized) && ("" != $serialized)) {
		$menuItems = unserialize($serialized);
    } else {
    	$menuItems = array();
    }
	$menuItems[] = array($name, $pos, $url);

    $newStr = serialize($menuItems);

	return $newStr;
}



#==== MAIN =====

$main_menu_custom_options = 'a:1:{i:0;a:3:{i:0;s:5:"CoDev";i:1;i:10;i:2;s:18:"../codev/index.php";}}';



$serialized = addCustomMenuItem(NULL, 'toto', 'http://toto.fr');
echo "serialized=$serialized<br/>\n";
$serialized = addCustomMenuItem($serialized, 'titi', 'http://titi.fr');
echo "serialized=$serialized<br/>\n";
#$serialized = addCustomMenuItem($serialized, 'CodevTT', '../codev/index.php');
#echo "serialized=$serialized<br/>\n";


$tok = strtok($_SERVER["SCRIPT_NAME"], "/");
echo "../".$tok."/index.php<br/>\n";
$serialized = addCustomMenuItem($serialized, 'CodevTT', '../'.$tok.'/index.php');
echo "serialized=$serialized<br/>\n";


?>
