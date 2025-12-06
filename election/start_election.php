<?php
require_once("election_code.php");
require_once("../utilities/logged_code.php");
require_once("../utilities/useful.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title></title>
		<link rel="shortcut icon" href="<?php echo $icon ?>"/>
    </head>
    <body>
	<?php

	if(is_logged() && $_SESSION['user_admin']){
		$date;
		if(!isset($_POST["date"]) || DateTime::createFromFormat('Y-m-d', $_POST["date"]) === false)
			$date = date("Y-m-d");
		else
			$date = $_POST["date"];
        $series = isset($_POST["series"]) ? intval($_POST["series"]) : -1;
        $series = is_int($series) ? $series : -1;
        $theme = isset($_POST["theme"]) ? $_POST["theme"] : null;
		$electionIdx = new_election($date, $series, $theme);
	}
	if(!$debug)
		header("location: ".$location."index.php");
	else
		echo "<a href=\"".$location."index.php\">home</a>";
	?>
    </body>
</html>
