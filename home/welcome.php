<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Welcome</title>
		<link rel="shortcut icon" href="<?php echo $icon ?>"/>
		<script language="JavaScript" type="text/javascript" src="home/home_code.js">
		</script>
    </head>
    <body>
		<?php
        // useful.php is already included in index
		echo "<button type=\"button\" onclick=\"redirect('".$location."login/login_form.php')\" style=\"font-size: 40px;\">login</button>";
		echo "<br>";
		echo "<br>";
		echo "<a href=\"".$location."register/register_form.php\">register</a>";
		?>
    </body>
</html>
