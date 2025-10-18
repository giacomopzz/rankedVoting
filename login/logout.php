<?php
require("../utilities/useful.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title></title>
    </head>
    <body>
	<?php
	if(is_logged()){
		$_SESSION['logged']=false;
		setcookie("logged","false",0,"/");
		setcookie("user","",time(),"/");
		setcookie("user_idx","",time(),"/");
		header("location: ".$location."index.php");
	}
	else{
		echo "<p align=\"center\">LOGOUT FAILED<br>";
		echo "'you need to be logged in to log out. Please log in to log out'<br>";
		echo "cit. Medal of Honor<br>";
		echo "<p align=\"left\"><a href=\"".$location."index.php\">home</a>";
	}
	?>
    </body>
</html>
