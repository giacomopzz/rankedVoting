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
        <title> Change password </title>
		<link rel="shortcut icon" href="../<?php echo $icon ?>"/>
    </head>
    <body>
		<?php
		@$answer=$_POST['answer'];
		@$user=$_POST['user'];
		@$password=$_POST['password'];
		@$new_password=$_POST['new_password'];
		@$confirm_password=$_POST['confirm_password'];
		@$sent=$_POST['sent'];
		if($user==null or $password==null or $new_password==null or $confirm_password==null or $sent!="true"){
			echo "<script language=\"JavaScript\" type=\"text/javascript\">\n";
			echo "function evaluatePasswordConfirmation(){\n";
			echo "let pw = document.getElementById(\"pw\").value;\n";
			echo "let confirmPw = document.getElementById(\"confirmPw\").value;\n";
			echo "let warning = document.getElementById(\"matchingWarning\");\n";
			echo "let submitBtn = document.getElementById(\"submitBtn\");\n";
			echo "let confirmOk = pw == confirmPw;\n";
			echo "warning.style.visibility = confirmOk ? \"hidden\" : \"visible\";\n";
			echo "submitBtn.disabled = !confirmOk;\n";
			echo "}\n";
			echo "</script>\n";

			if($sent=="true"){
				echo "not all fields are complete<br>";
			}

			echo "<form method=\"post\" action=\"#\">";
			echo "Please fill your current password and the new password<br>";
			echo "<input type=\"hidden\" value=\"true\" name=\"sent\">";
			echo "<input type=\"text\" name=\"user\" value=\"".$user."\"> Username<br>";
			echo "<input type=\"password\" name=\"password\" value=\"\"> Current password<br>";
			echo "<input type=\"password\" name=\"new_password\" id=\"pw\" value=\"\" oninput=\"evaluatePasswordConfirmation()\"> New password<br>";
			echo "<input type=\"password\" name=\"confirm_password\" id=\"confirmPw\" value=\"\" oninput=\"evaluatePasswordConfirmation()\"> Confirm new password <span id=\"matchingWarning\">(does not match the new password)</span><br>";
			echo "<input type=\"submit\" value=\"update\" id=\"submitBtn\"><br>";
			echo "</form>";
		}
		else{
			require('register_code.php');
			$password=sha1($password);
			$new_password=sha1($new_password);
			$success=change_password($user,$password,$new_password);
			if($success){
				echo "".$user."'s password has been updated<br>";
				echo "<script language=\"JavaScript\" type=\"text/javascript\">\n";
				echo "<!--\n";
				echo "function redirect(page){\n";
				echo "var loca=\"".$location."\";\n";
				echo "location.href=loca+page;\n";
				echo "}\n";
				echo "window.setTimeout(\"redirect('index.php')\",5000);\n";
				echo "//-->\n";
				echo "</script>";
			}
			else{
				echo "there has been an error while deleting the account<br>";
				unset($_POST['user']);
				unset($_POST['password']);
				unset($_POST['new_password']);
				unset($_POST['confirm_password']);
				unset($_POST['answer']);
				unset($_POST['send']);
				echo "<a href=\"".$location."register/change_password_form.php\">try again</a>";
			}
		}
		echo "\t<a href=\"".$location."index.php\">home</a>";
		?>
    </body>
</html>
