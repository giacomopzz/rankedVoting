<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Welcome</title>
		<link rel="shortcut icon" href="<?php echo $icon ?>"/>
    </head>
    <body>
		<?php
        // useful.php is already included in index
		echo "<a href=\"".$location."register/register_form.php\">register</a>";
		echo "<br>";
		echo "<a href=\"".$location."login/login_form.php\">login</a>";
		?>
    </body>
</html>
