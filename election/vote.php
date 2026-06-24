<?php
require_once("election_code.php");
require_once("../utilities/logged_code.php");
require_once("../utilities/useful.php");
?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Vote</title>
		<link rel="shortcut icon" href="<?php echo $icon ?>"/>
    </head>
    <body>
	<?php

	function get_ballot($postData, $userIdx)
	{
		// contains an array of "op<candidateIdx>_<rank>" => "on" (basically, if isset is true, it is checked)
		// rank is always from 1 to count($candidates)
		// (candidateIdx - 1) is the index of the option, not optionIdx. $optionIdx = $candidates[$candidateIdx - 1][0]
		$candidates = get_all_candidates();
		$ballot = new Ballot($userIdx, 0.0);
        if(isset($postData["op_no_vote"]))
            $ballot->setNotComing();
        else{
            for($cIdx = 0; $cIdx < count($candidates); $cIdx++){
                $optionIdx = $candidates[$cIdx][0];
                for($rIdx = 0; $rIdx < count($candidates); $rIdx++){
                    $key = "op".($cIdx+1)."_".($rIdx+1);
                    if(isset($postData[$key]) && $postData[$key]=="on"){
                        $vote = new Vote($rIdx+1, $optionIdx);
                        $ballot->append($vote); // append already takes care of double options or double ranks
                    }
                }
                $vetoKey = "op".($cIdx+1)."_veto";
                if(isset($postData[$vetoKey]) && $postData[$vetoKey]=="on"){
                    $vote = new Vote(-1, $optionIdx);
                    $ballot->append($vote); // append already takes care of double options or double ranks
                }
            }
        }
		return $ballot;
	}

	if(is_logged() && $_SESSION['user_voter'] === "true" && isset($_POST["election_idx"])){
		$ballot = get_ballot($_POST, $_SESSION["user_idx"]);
		cast_vote($_POST["election_idx"], $ballot);
	}
	if(!$debug)
		header("location: ".$location."index.php");
	else
		echo "<a href=\"".$location."index.php\">home</a>";
	?>
    </body>
</html>
