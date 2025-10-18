<!DOCTYPE html>
<?php
	require_once('home_code.php');
	require_once('election/election_code.php');
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Home</title>
		<link rel="shortcut icon" href="<?php echo $icon ?>"/>
		<?php
		include_once("utilities/javascript_useful.php");
		?>
		<script language="JavaScript" type="text/javascript" src="home/home_code.js">
		</script>
    </head>
	<?php
	[$electionIdx, $electionDate] = get_open_election();
	$options = get_all_candidates();
    echo "<body onload=\"initPage(".count($options).",".($electionIdx == -1 && $_SESSION['user_voter'] === "true" ? "'previous_election'": "'current_election'").");\">";
	?>
		<?php
		echo "Hello ".$_SESSION['user']."!<br>";
        echo "<button class=\"tab_button\" id=\"current_election_button\" onclick=\"showControl('current_election')\">Current election</button>";
        echo "<button class=\"tab_button\" id=\"previous_election_button\" onclick=\"showControl('previous_election')\">Previous election</button>";
        echo "<button class=\"tab_button\" id=\"account_actions_button\" onclick=\"showControl('account_actions')\">Account</button>";
        echo "<button class=\"tab_button\" id=\"game_resources_button\" onclick=\"showControl('game_resources')\">Game resources</button>";
        echo "<br>";

		echo "<div id=\"previous_election\" class=\"hiddable_control\">";
        echo "<h2>Previous election</h2>";
		[$lastResult, $lastDate, $lastResultIdx, $lastElectionIdx] = get_latest_closed_election();

        // Show last winner
		echo "Last winner (on ".toDateFormat($lastDate)."): ";
		echo ($lastResult == -1 ? "nothing" : $lastResult)."<br>";

        // Show election stats
        $lastElection = get_all_ballots($lastElectionIdx);
        $vetoedNumber = 0;
        $assignedOrder = array();
        $maxOrder = -1;
        foreach($lastElection as $ballot){
            $currentRank = 0;
            foreach($ballot->votes as $vote){
                if($vote->rank != -1)
                    $currentRank++;
                if($vote->optionIdx == $lastResultIdx){
                    if($vote->rank == -1){
                        $vetoedNumber++;
                        $currentRank = -1;
                    }
                    break;
                }
            }
            if($currentRank == -1)
                continue;
            if($maxOrder < $currentRank)
                $maxOrder = $currentRank;
            if(!isset($assignedOrder[$currentRank]))
                $assignedOrder[$currentRank] = 0;
            $assignedOrder[$currentRank]++;
        }
        echo "<br>Winner was ranked:<br>";
        for($rankIdx = 1; $rankIdx <= $maxOrder; $rankIdx++){
            $numVotersOfRank = isset($assignedOrder[$rankIdx]) ? $assignedOrder[$rankIdx] : 0;
            echo $rankIdx.ordinalString($rankIdx)." by ".$numVotersOfRank." ".($numVotersOfRank == 1 ? "person" : "people")."<br>";
        }
        echo "vetoed by ".$vetoedNumber." ".($vetoedNumber == 1 ? "person" : "people")."<br>";

        // Show user's previous ballot
        if($_SESSION['user_voter'] === "true")
        {
            $previousBallot = get_previous_vote($lastElectionIdx, intval($_SESSION['user_idx']));
            echo "<br><div>";
            echo count($previousBallot->votes) == 0 ? "You didn't vote." : "Your ballot was:";
            printBallotSummary($previousBallot, $options, $lastResultIdx);
            echo "</div>";
        }
		echo "</div>";

        echo "<div id=\"current_election\" class=\"hiddable_control\">";
        echo "<h2>Current election</h2>";
		if($_SESSION['user_admin'] === "true")
		{
			if($electionIdx != -1){
				$alreadyVoted = get_already_voted($electionIdx);
				$nAlreadyVoted = count($alreadyVoted);
				$alreadyVotedText = "";
				for($idx = 0; $idx < $nAlreadyVoted; $idx++){
					$alreadyVotedText = $alreadyVotedText.$alreadyVoted[$idx];
					if($idx < $nAlreadyVoted - 2)
						$alreadyVotedText = $alreadyVotedText.", ";
					else if($idx == $nAlreadyVoted - 2)
						$alreadyVotedText = $alreadyVotedText." and ";
				}
				echo "Users can vote for the election of ".toDateFormat($electionDate)."<br>";
				echo ($nAlreadyVoted > 0 ? $alreadyVotedText : "Nobody has")." already voted<br>";
				echo "<br><a href=\"".$location."election/close_election.php\">Close election</a>";
			}
			else{
				echo "<form action=\"election/start_election.php\" method=\"post\">";
				echo "<input type=\"date\" name=\"date\"><br>";
                echo "<label>Theme:</label><input type=\"text\" name=\"theme\"><br>";
				echo "<input type=\"submit\" value=\"Start election\">";
				echo "</form>";
			}
		}
		else if($_SESSION['user_voter'] === "true")
		{
			if($electionIdx != -1){
				echo "You can vote for the election of ".toDateFormat($electionDate)."<br>";
				$ballot = get_previous_vote($electionIdx, intval($_SESSION['user_idx']));

				echo "<form action=\"election/vote.php\" method=\"post\">";

                // Print ballot control
				echo "<table>";
				echo "<tr><th>Rank</th>";
				for($idx = 0; $idx < count($options); $idx++)
					echo "<th>".($idx+1)."</th>";
				echo "<th>Veto</th>";
				echo "</tr>";
				for($oIdx = 0; $oIdx < count($options); $oIdx++){
					echo "<tr><th>".$options[$oIdx][1]."</th>";
					for($ridx = 0; $ridx < count($options); $ridx++){
						$boxId = "op".($oIdx+1)."_".($ridx+1);
						$isChecked = $ballot->getRank($options[$oIdx][0]) == $ridx + 1;
						echo "<td><input type=\"checkbox\" id=\"".$boxId."\" name=\"".$boxId."\"".($isChecked ? " checked" : "")." oninput=\"controlBallot(".count($options).");\"></td>";
					}
					$boxId = "op".($oIdx+1)."_veto";
					$isChecked = $ballot->getRank($options[$oIdx][0]) == -1;
					echo "<td><input type=\"checkbox\" id=\"".$boxId."\" name=\"".$boxId."\"".($isChecked ? " checked" : "")." oninput=\"controlBallot(".count($options).");\"></td>";
					echo "</tr>";
				}
                echo "</table>";

				// Vote button
				echo "<input type=\"hidden\" value=\"".$electionIdx."\" name=\"election_idx\">\n<input type=\"submit\" value=\"Vote\">";
				echo "</form><br><br>";

                // Print ballot summary
                echo "<div>";
                echo count($ballot->votes) == 0 ? "You haven't voted yet." : "Your current ballot is:";
                printBallotSummary($ballot, $options);
                echo "</div>";
			}
		}
		else
		{
			echo "You have not been approved to vote yet";
		}
        echo "</div>";

        // Account control
        echo "<div id=\"account_actions\" class=\"hiddable_control\">";
        echo "<h2>Account</h2>";
		echo "<a href=\"".$location."login/logout.php\">logout</a><br>";
		echo "<a href=\"".$location."register/change_password_form.php\">change password</a><br>";
		echo "<a href=\"".$location."delete/delete_form.php\">delete account</a><br>";
        echo "</div>";

        // Game resources
        echo "<div id=\"game_resources\" class=\"hiddable_control\">";
        echo "<h2>Game resources</h2>";
		echo "<a href=\"".$location."homebrew/Equipment40K.html\">Equipment</a><br>";
		echo "<a href=\"".$location."homebrew/Languages40K.html\">Languages</a><br>";
		echo "<a href=\"".$location."homebrew/Trades40K.html\">Trades</a><br>";
		?>
    </body>
</html>
