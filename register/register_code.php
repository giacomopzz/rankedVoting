<?php
require ("../utilities/connessione_mysql.php");
function register($user,$password){
	global $db_user_table;
	global $db_user_roles_table;
    global $mysqli;
    $escapedUser = mysqli_real_escape_string($mysqli, $user);
    if($escapedUser != $user){
		echo "<br>invalid username, please choose another one.<br>";
		return [-1, ""];
    }
	$query="select count(username) from ".$db_user_table." where user='".$user."'";
	$result_user=mysqli_query($mysqli, $query);
	$row_user=mysqli_fetch_array($result_user);
	if($row_user['count(username)']!=0){
		echo "username already in use, please choose another one.<br>";
		return [-1, ""];
	}
	else{
		$query="insert into ".$db_user_table." (username,password) values('".$user."','".$password."')";
		$result_add=mysqli_query($mysqli, $query);
		if($result_add){
			$query="select u.userIdx, r.role from ".$db_user_table." as u inner join ".$db_user_roles_table." as r on u.roleIdx=r.roleIdx where username='".$user."'";
			$result_idx=mysqli_query($mysqli, $query);
			$row_idx=mysqli_fetch_array($result_idx);
			return [$row['userIdx'], $row['role']];
		}
		else{
			return [-1, ""];
		}
	}
}

function change_password($user,$password,$new_password){
	global $db_user_table;
    global $mysqli;
    $escapedUser = mysqli_real_escape_string($mysqli, $user);
    if($escapedUser != $user){
		echo "<br>invalid username.<br>";
		return false;
    }
	if($new_password === $password)
		return true;
	$query = "select userIdx from ".$db_user_table." where username='".$user."' and password='".$password."'";
	$result_user=mysqli_query($mysqli, $query);
	$row_user=mysqli_fetch_array($result_user);
	if($result_user->num_rows == 0){
		echo "the user \"".$user."\" doesn't exist or the password used was incorrect.<br>";
		return false;
	}
	if($result_user->num_rows != 1){
		echo "unexpected number of users with the given username and password.<br>";
		return false;
	}
	$query = "update ".$db_user_table." set password='".$new_password."' where userIdx=".$row_user['userIdx'];
	$result_update=mysqli_query($mysqli, $query);
	return $result_update;
}
?>
