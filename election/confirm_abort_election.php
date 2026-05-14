<?php
require_once("election_code.php");
require_once("../utilities/logged_code.php");
require_once("../utilities/useful.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Confirm</title>
		<link rel="shortcut icon" href="<?php echo $icon ?>"/>
    </head>
    <body>
	<?php
	if(is_logged() && $_SESSION['user_admin']){
        echo "Are you sure you want to cancel the election?";
        echo "<br><a href=\"".$location."election/abort_election.php\">Confirm</a>";
        echo "<br>";
        echo "<br><a href=\"".$location."index.php\">Home</a>";
	}
    else{
        if(!$debug)
            header("location: ".$location."index.php");
        else
            echo "<a href=\"".$location."index.php\">Home</a>";
    }
	?>
    </body>
</html>
