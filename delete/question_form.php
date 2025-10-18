<?php
require("../utilities/useful.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title> Conferma </title>
		<link rel="shortcut icon" href="../<?php echo $icon ?>"/>
    </head>
    <body>
        <?php
        $user=$_GET['user'];
        $password=$_GET['pw'];
        ?>
        <table>
            Are you sure you want to delete this accont?<br>
            <tr>
                <td>
                    <form method="post" action="delete_form.php">
                        <input type="hidden" name="user" value="<?php echo $user; ?>">
                        <input type="hidden" name="password" value="<?php echo $password; ?>">
                        <input type="hidden" name="answer" value="yes">
                        <input type="submit" value="Yes">
                    </form>
                </td>
                <td>
                    <form method="post" action="delete_form.php">
                        <input type="hidden" name="answer" value="no">
                        <input type="submit" value="No">
                    </form>
                </td>
            </tr>
        </table>
    </body>
</html>
