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
		$electionIdx = get_open_election()[0];
		if($electionIdx != -1)
			close_election($electionIdx);
	}
	if(!$debug)
		header("location: ".$location."index.php");
	else
		echo "<a href=\"".$location."index.php\">home</a>";
	?>
    </body>
</html>
