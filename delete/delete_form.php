<?php
require("../utilities/useful.php");
if(!is_logged()){
	header("location: ".$location."index.php");
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title> Delete account </title>
		<link rel="shortcut icon" href="../<?php echo $icon ?>"/>
    </head>
    <body>
		<?php
		@$answer=$_POST['answer'];
		@$user=$_POST['user'];
		@$password=$_POST['password'];
		@$sent=$_POST['sent'];
		if($answer==null){
			if($user==null or $password==null or $sent!="true"){
				if($sent=="true"){
					echo "not all fields are complete<br>";
				}
				echo "<form method=\"post\" action=\"#\">";
				echo "Please fill username and password of the account to delete<br>";
				echo "<input type=\"hidden\" value=\"true\" name=\"sent\">";
				echo "<input type=\"text\" name=\"user\" value=\"".$user."\"> Username<br>";
				echo "<input type=\"password\" name=\"password\" value=\"\"> Password<br>";
				echo "<input type=\"submit\" value=\"delete the account\"><br>";
				echo "</form>";
			}
			else{
				$password=sha1($password);
				header("location: ".$location."delete/question_form.php?user=".$user."&pw=".$password);
			}
		}
		else if($answer=="yes"){
			require('delete_code.php');
			$user_idx=delete($user,$password);
			if($user_idx){
				if($user_idx==@$_SESSION['user_idx']){
					$_SESSION['logged']=false;
					setcookie("logged","false",0,"/");
					setcookie("user","",time(),"/");
					setcookie("user_idx","",time(),"/");
				}
				echo "user \"".$user."\" has been deleted<br>";
				// javascript delayed redirect
				echo "<script language=\"JavaScript\" type=\"text/javascript\">";
				echo "<!--";
				echo "function redirect(page){";
				echo "var loca=\"".$location."\";";
				echo "location.href=loca+page;";
				echo "}";
				echo "window.setTimeout(\"redirect('index.php')\",5000);";
				echo "-->";
				echo "</script>";
			}
			else{
				echo "there has been an error while deleting the account<br>";
				unset($_POST['user']);
				unset($_POST['password']);
				unset($_POST['answer']);
				unset($_POST['send']);
				echo "<a href=\"".$location."delete/delete_form.php\">try again</a>";
			}
		}
		else{#answer=="no"
			header("location: ".$location."index.php");
		}
		echo "\t<a href=\"".$location."index.php\">home</a>";
		?>
    </body>
</html>