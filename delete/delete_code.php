<?php
require ("../utilities/connessione_mysql.php");
function delete($user,$password){
	global $db_user_table;
    global $mysqli;
    $escapedUser = mysqli_real_escape_string($mysqli, $user);
    if($escapedUser != $user){
		echo "<br>invalid username.<br>";
		return false;
    }
	$query="select password,userIdx from ".$db_user_table." where username='".$user."'";
	$result=mysqli_query($mysqli, $query);
	$row=mysqli_fetch_array($result);
	if($row['password']==$password){
		$idx=$row['userIdx'];
		$query="delete from ".$db_user_table." where userIdx=".$idx;
		$result=mysqli_query($mysqli, $query);
        // remember to remove the account from the other tables too.
	}
	else{
		echo "<br>wrong password.<br>";
		return false;
	}
    return true;
}
?>
