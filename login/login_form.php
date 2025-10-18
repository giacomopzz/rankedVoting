<?php
require("../utilities/useful.php");
if(is_logged()){
	header("location: ".$location."index.php");
}
/*
 * this page is only accessible to unlogged users
 * so it's safe to set the login cookies
 */
setcookie('logged','false',0,"/");
setcookie('user','unknown',0,"/");
setcookie('user_idx',0,0,"/");
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title> Login </title>
		<link rel="shortcut icon" href="../<?php echo $icon ?>"/>
    </head>
	<body>
	<?php
	@$user=$_POST['user'];
	@$password=$_POST['password'];
	@$remember=$_POST['remember'];
	@$sent=$_POST['sent'];
	if($sent!="true" or $user==null or $password==null){
		echo "Please fill your username and password<br>";
		if($sent=="true"){
			echo "the form is incomplete<br>";
		}
		echo "<form action=\"#\" method=\"post\">";
		echo "<input type=\"hidden\" name=\"sent\" value=\"true\">";
		echo "<input type=\"text\" name=\"user\" value=\"".$user."\"> username<br>";
		echo "<input type=\"password\" name=\"password\"> password<br>";
		echo "<input type=\"checkbox\" name=\"remember\" value=\"true\"";
		if($remember=="true"){
			echo " checked";
		}
		echo "> Remember me<br>";
		echo "<input type=\"submit\" value=\"login\">";
		echo "</form><br>";
	}
	else{	
		require("login_code.php");
		$password=sha1($password);
		[$user_idx, $user_role]=login($user,$password);
		if($user_idx != -1){
			$user_admin = $user_role == "ADMIN" ? "true" : "false";
			$user_voter = $user_role == "VOTER" ? "true" : "false";
			$_SESSION['user']=$user;
			$_SESSION['user_idx']=$user_idx;
			$_SESSION['user_admin']=$user_admin;
			$_SESSION['user_voter']=$user_voter;
			$_SESSION['logged']=true;
			if($remember=="true"){
				setcookie("logged","true",time()+(365*24*60*60),"/");
				setcookie("user",$user,time()+(365*24*60*60),"/");
				setcookie("user_idx",$user_idx,time()+(365*24*60*60),"/");
				setcookie("user_admin",$user_admin,time()+(365*24*60*60),"/");
				setcookie("user_voter",$user_voter,time()+(365*24*60*60),"/");
			}
			else{
				setcookie("user","",time(),"/");
				setcookie("user_idx","",time(),"/");
				setcookie("user_admin","false",time(),"/");
				setcookie("user_voter","false",time(),"/");
			}
			header("location: ".$location."index.php");
		}
		else{
			echo "login failed.<br>";
			$_SESSION['logged']=false;
			setcookie("user","",time(),"/");
			setcookie("user_idx","",time(),"/");
			setcookie("user_admin","false",time(),"/");
			setcookie("user_voter","false",time(),"/");
			echo "<a href=\"".$location."login/login_form.php\">try again</a>";
		}
	}
	echo "\t<a href=\"".$location."index.php\">home</a>";
	?>
    </body>
</html>