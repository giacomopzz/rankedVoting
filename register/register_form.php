<?php
require("../utilities/useful.php");
if(is_logged()){
	header("location: ".$location."index.php");
}
/*
 * this page is only accessible to unlogged users
 * so it's safe to set the login cookies
 */
$_SESSION['logged']=false;
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title> Register account </title>
		<link rel="shortcut icon" href="../<?php echo $icon ?>"/>
    </head>
    <body>
		<?php
		@$user=$_POST['user'];
		@$password=$_POST['password'];
		if($user==null or $password==null){
			echo "Please choose a username and password<br>";
			if($condizioni!=null){
				echo "the form is incomplete<br>";
			}
			echo "<form action=\"#\" method=\"post\">";
			echo "<input type=\"text\" name='user' value=\"".$user."\"> Username<br>";
			echo "<input type=\"password\" name='password'> Password<br>";
			echo "<input type=\"submit\" value=\"Go!\">";
			echo "</form>";
		}
		else{
            require("register_code.php");
            $password=sha1($password);
            [$user_idx, $user_role]=register($user,$password);
            if($user_idx >= 0){
			$user_admin = $user_role == "ADMIN" ? "true" : "false";
			$user_voter = $user_role == "VOTER" ? "true" : "false";
			$_SESSION['user']=$user;
			$_SESSION['user_idx']=$user_idx;
			$_SESSION['user_admin']=$user_admin;
			$_SESSION['user_voter']=$user_voter;
			$_SESSION['logged']=true;
				header("location: ".$location."index.php");
			}
			else{
				echo "sorry, it wasn't possible to sign you up<br>";
				$_SESSION['logged']=false;
				unset($_POST['user']);
				unset($_POST['password']);
				echo "<a href=\"".$location."register/register_form.php\">try again</a>";
			}
		}
		echo "<a href=\"".$location."index.php\">home</a>";
		?>
    </body>
</html>