<?php
    include_once("utilities/connessione_mysql.php");
    include_once("../utilities/connessione_mysql.php");

    /// shared methods
    function toDateFormat(string $db_date, $format='D dS M Y'){
        return DateTime::createFromFormat('Y-m-d', $db_date)->format($format);
    }

    function get_open_election()
    {
        global $mysqli;
        global $db_elections_table;
        global $db_series_table;
        // an election is open if winner is null, there can be only one open election.
        // return open election id or -1 if not possible
        $query = "select e.`electionIdx`,e.`date`,e.`seriesIdx`,s.`seriesName` from ".$db_elections_table." as e join ".$db_series_table." as s on e.`seriesIdx`=s.`seriesIdx` where e.`winner` is null order by e.`date` asc";
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $numRows = count($rows);
        if($numRows == 0){
            // no open elections
            return [-1, null];
        }
        for($idx = 0; $idx < $numRows-1; $idx++){
            // close all the earlier elections
            close_election(intval($rows[idx]['electionIdx']));
        }
        $idx = $numRows-1;
        // exactly one open election left
        return [intval($rows[$idx]['electionIdx']), $rows[$idx]['date'], intval($rows[$idx]['seriesIdx']), $rows[$idx]['seriesName']];
    }

    function get_latest_closed_election(int $seriesIdx = null ){
        global $mysqli;
        global $db_elections_table;
        global $db_options_table;
        global $db_results_table;
        // returns date and winning option of latest election. If no option could be selected, returns -1 instead of the winner
        $scores = array();
        $query = "select e.date,e.winner,o.option,e.electionIdx from (select * from ".$db_elections_table." where `winner` is not null".($seriesIdx != null && is_int($seriesIdx) ? " and `seriesIdx`=".$seriesIdx : "").") as e left join ".$db_options_table." as o on e.winner=o.optionIdx order by e.date desc limit 1";
        $result = mysqli_query($mysqli, $query);
    	$lastElectionRow = mysqli_fetch_array($result);
        if($lastElectionRow == null)
            return [-1, null, -1, -1, $scores];
        $lastElectionIdx = intval($lastElectionRow['electionIdx']);
        $query = "select o.option,r.score from (select * from ".$db_results_table." where `electionIdx`=".$lastElectionIdx.") as r left join ".$db_options_table." as o on r.optionIdx=o.optionIdx order by r.score desc";
        $result = mysqli_query($mysqli, $query);
        $scoreRows = mysqli_fetch_all($result, MYSQLI_NUM);
        for($idx = 0; $idx < count($scoreRows); $idx++){
            $scores[] = ["option" => $scoreRows[$idx][0], "score" => $scoreRows[$idx][1]];
        }
        return [intval($lastElectionRow['winner']) == -1 ? -1 : $lastElectionRow['option'], $lastElectionRow['date'], intval($lastElectionRow['winner']), $lastElectionIdx, $scores];
    }

    class Vote {
        public $rank; // int
        public $optionIdx; // int

        function __construct(int $rank, int $optionIdx){
            $this->rank = $rank;
            $this->optionIdx = $optionIdx;
        }
    }

    class Ballot {
        public $votes; // array of Vote with unique optionIdx and rank, sorted by ascending rank
        private $notComing;
        private $userIdx;
        private $pityScore;

        function __construct(int $userIdx, float $pityScore){
            $this->votes = array();
            $this->notComing = false;
            $this->userIdx = $userIdx;
            $this->pityScore = $pityScore;
        }

        public function append(Vote $vote){
            if($this->notComing)
                return;
            // make sure that rank and optionIdx are unique
            if($vote->rank <= 0 && $vote->rank != -1) // only -1 is allowed as non-positive value
                return;
            foreach($this->votes as $v){
                if($vote->rank > 0 && $v->rank === $vote->rank) // multiple ranks of -1 are allowed for vetoing purposes
                    return;
                if($v->optionIdx === $vote->optionIdx)
                    return;
            }
            $this->votes[] = $vote;
            usort($this->votes, function($a, $b) {
                return $a->rank - $b->rank;
            }); // sort according to ascending rank
        }

        public function getRank(int $optionIdx){
            foreach($this->votes as $v){
                if($v->optionIdx == $optionIdx)
                    return $v->rank;
            }
            return null;
        }

        public function getEffectiveRank(int $optionIdx){
            // returns ranks ignoring empty spaces, vetoes are -1
            $effectiveRank = 0;
            foreach($this->votes as $v){
                if($v->rank > 0)
                    $effectiveRank++;
                if($v->optionIdx == $optionIdx)
                    return $v->rank < 0 ? -1 : $effectiveRank;
            }
            return $effectiveRank + 1;
        }

        public function getVetoedOptions(){
            $vetoes = array();
            foreach($this->votes as $v){
                if($v->rank < 0)
                    $vetoes[] = $v->optionIdx;
            }
            return $vetoes;
        }

        public function getRankedVotes(){
            $ranked = array();
            foreach($this->votes as $v){
                if($v->rank > 0)
                    $ranked[] = $v;
            }
            return $ranked;
        }

        public function setNotComing(){
            $this->notComing = true;
            $this->votes = [];
        }

        public function isNotComing(){
            return $this->notComing;
        }

        public function getPityScore(){
            return $this->pityScore;
        }

        public function getUserIdx(){
            return $this->userIdx;
        }
    }

    function get_all_ballots(int $electionIdx) // This could be done by getting the unique users that voted and calling the get_previous_vote method, but that would make a lot of calls to the DB
    {
        global $mysqli;
        global $db_elections_table;
        global $db_options_table;
        global $db_ranks_table;
        global $db_user_table;

        // get users' pity scores
        $query = "select `userIdx`,`pityScore` from ".$db_user_table." where `roleIdx`=2";
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $pityScores = array();
        foreach($rows as $userPity){
            $pityScores[intval($userPity["userIdx"])] = floatval($userPity["pityScore"]);
        }

        // collect votes
        $query = "select r.userIdx,r.optionIdx,r.`rank`,o.`option` from ".$db_ranks_table." as r left join ".$db_options_table." as o on r.optionIdx=o.optionIdx where r.electionIdx=".$electionIdx;
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $choices = array();
        foreach($rows as $vote){
            $user = $vote['userIdx'];
            $option = $vote['optionIdx'];
            $rank = $vote['rank'];
            if(!isset($choices[$user])){
                if(!isset($pityScores[$user]))
                    $pityScores[$user] = 0.0;
                $choices[$user] = new Ballot($user, $pityScores[$user]);
            }
            if($vote['option'] == null)
                $choices[$user]->setNotComing();
            $newVote = new Vote($rank, $option);
            $choices[$user]->append($newVote);
        }

        // convert into indexed array
        $election = array();
        foreach($choices as $ballot)
            $election[] = $ballot;
        return $election;
    }

    function get_all_candidates(int $electionIdx = -1)
    {
        global $mysqli;
        global $db_options_table;
        global $db_aliases_table;

        $query = "select o.`optionIdx`,o.`option`,a.`alias` from ".$db_options_table." as o left join (select `optionIdx`,`alias` from ".$db_aliases_table." where `electionIdx`=".$electionIdx.") as a on o.`optionIdx`=a.`optionIdx` where true";
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $allOptions = array();
        foreach($rows as $option){
            if($option["alias"] == null)
                $option["alias"] = $option["option"];
            $allOptions[] = [intval($option["optionIdx"]), $option["option"], $option["alias"]];
        }
        return $allOptions;
    }

    function get_election_theme(){
        global $mysqli;
        global $db_elections_table;

        $electionIdx = get_open_election()[0];
        if($electionIdx == -1)
            return null;
        $query = "select `theme` from ".$db_elections_table." where electionIdx=".$electionIdx;
        $result = mysqli_query($mysqli, $query);
    	$row = mysqli_fetch_array($result);
        if($row["theme"] == null)
            return null;
        return $row["theme"];
    }

    function get_all_series(){
        global $mysqli;
        global $db_series_table;

        $query = "select `seriesIdx`,`seriesName` from ".$db_series_table." where true";
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $allSeries = array();
        foreach($rows as $series){
            $allSeries[] = [intval($series["seriesIdx"]), $series["seriesName"]];
        }
        return $allSeries;
    }

    /// admin methods
    function new_election($date, $series, $theme)
    {
        global $mysqli;
        global $db_elections_table;

        // return new election id or -1 if another election is still running.
        $openElection = get_open_election()[0];
        if($openElection == -1){
            $escapedDate = mysqli_real_escape_string($mysqli, $date);
            if(DateTime::createFromFormat('Y-m-d', $escapedDate) === false){
                echo "invalid date<br>";
                return -1;
            }
            $escapedTheme = $theme === null ? null : mysqli_real_escape_string($mysqli, $theme);
            $query = "insert into ".$db_elections_table." (`electionIdx`, `date`, `seriesIdx`, `theme`, `winner`) values (NULL, '".$escapedDate."', ".$series.", ".($escapedTheme === null ? "NULL" : "'".$escapedTheme."'").", NULL)";
            $success = mysqli_query($mysqli, $query);
            $openElection = get_open_election()[0];
            return $openElection;
        }
        return -1;
    }

    function close_election(int $electionIdx)
    {
        global $mysqli;
        global $db_elections_table;
        global $db_results_table;
        global $db_options_table;
        global $db_ranks_table;
        global $db_user_table;

        // collect votes, evaluate winner and store it
        $query = "select count(electionIdx) from ".$db_elections_table." where electionIdx=".$electionIdx." and winner is null";
        $result = mysqli_query($mysqli, $query);
    	$row = mysqli_fetch_array($result);
        if($row['count(electionIdx)'] != 1){
            echo "no election to close!<br>";
            return;
        }

        // find series of election to close
        $query = "select seriesIdx from ".$db_elections_table." where electionIdx=".$electionIdx;
        $result = mysqli_query($mysqli, $query);
    	$row = mysqli_fetch_array($result);
        $seriesIdx = $row["seriesIdx"];
        
        // collect votes
        $election = get_all_ballots($electionIdx);

        // collect candidates
        $optionIds = array();
        $candidates = get_all_candidates();
        foreach($candidates as $candidate)
            $optionIds[] = $candidate[0];

        // collect latest winner
        $lastWinner = get_latest_closed_election($seriesIdx)[2];

        // find and store winner
        $winner = -1; // invalid winner by default
        $scores = array();
        if(count($election) != 0 && count($optionIds) != 0)
            $scores = evaluateScores($election, $optionIds, $lastWinner, false);
        $winner = evaluateWinner($scores);

        // save winner in the database
        $query = "update ".$db_elections_table." set `winner`='".$winner."' where `electionIdx`=".$electionIdx;
        $result = mysqli_query($mysqli, $query);
        if($winner > 0)
            echo "Winner: ".$candidates[array_search($winner,$optionIds)][1]."<br>";
        else
            echo "No winner :(<br>";

        // save scores in the database
        if(count($scores) == 0)
            echo "No scores available<br>";
        else{
            echo "Scores:<br>";
            {
                // remove previous scores for same electionIdx
                $query = "delete from `".$db_results_table."` where `electionIdx` = ".$electionIdx;
                $result = mysqli_query($mysqli, $query);
            }
            $query = "insert into ".$db_results_table." (`electionIdx`, `optionIdx`, `score`) values ";
            foreach($scores as $idx => $score){
                $optionIdx = $score['optionIdx'];
                $scoreValue = $score['score'];

                // update query
                $query = $query.($idx != 0 ? ", " : "")."(".$electionIdx.",".$optionIdx.",".$scoreValue.")";

                // print results
                foreach($candidates as $candidate){
                    if($candidate[0] == $optionIdx){
                        echo $candidate[2]." -> ".$scoreValue."<br>";
                        break;
                    }
                }
            }
            $result = mysqli_query($mysqli, $query);
        }

        // reset pity scores of users that had a deciding vote
        $decidingUsers = getDecidingUsers($election, $optionIds, $lastWinner, $winner);
        echo "deciding users:<br>";
        var_dump($decidingUsers);
        echo "<br>";

        // update pity scores
        $newPityScores = array();
        foreach($election as $ballot){
            $newScore = 0.0;
            if(!in_array($ballot->getUserIdx(), $decidingUsers))
                $newScore = evaluatePityScore($winner, $ballot, count($candidates));
            $newPityScores[] = ["userIdx" => $ballot->getUserIdx(), "newScore" => $newScore];
            echo "User ".$ballot->getUserIdx()."'s pity score from ".$ballot->getPityScore()." to ".$newScore."<br>";
            $query = "update ".$db_user_table." set `pityScore`='".$newScore."' where `userIdx`=".$ballot->getUserIdx();
            $result = mysqli_query($mysqli, $query);
        }
    }

    function evaluateWinner($scores){
        /// $scores: array of ["optionIdx" => optionIdx, "score" => score] sorted on decreasing score
        $copelandSet = getCopelandSet($scores);

        // Select a winner from the Copeland set.
        $nCopelandWinners = count($copelandSet);
        if($nCopelandWinners == 1)
            return $copelandSet[0]; // Only one possible winner
        if($nCopelandWinners == 0)
            return -1; // Quite impossible, but no winner just in case

        // Select at random between multiple options
        $randomSelection = random_int(0, $nCopelandWinners - 1); // inclusive for both ends
        return $copelandSet[$randomSelection];
    }

    function getCopelandSet($scores){
        /// $scores: array of ["optionIdx" => optionIdx, "score" => score] sorted on decreasing score
        /// returns an array of optionIdx
        $nOptions = count($scores);
        if($nOptions == 0)
            return -1;

        // Evaluate Copeland set (the set of all the options with the highest Copeland score)
        $maxCopeland = $scores[0]["score"];
        $copelandSet = array();
        for($idx = 0; $idx < $nOptions; $idx++){
            if($scores[$idx]["score"] == $maxCopeland)
                $copelandSet[] = $scores[$idx]["optionIdx"];
        }
        return $copelandSet;
    }

    function evaluateScores($election, $allOptions, $previousWinner, $ignoreVetoes){
        /// returns an array of ["optionIdx" => optionIdx, "score" => score] sorted on decreasing score
        /// $election: array of Ballots (each Ballot has an array of options and their ranks)
        /// $allOptions: array of option IDs (from the DB)
        /// $previousWinner: option ID of the latest winner
        /// $ignoreVetoes: whether to remove vetoed options or not
        global $applyPityScore;

        if(count($election) == 0) // no one voted
            return array();
        
        // Generate result matrix
        $nOptions = count($allOptions);
        $match = array();
        for($aIdx = 0; $aIdx < $nOptions; $aIdx++){
            $match[$aIdx] = array();
            for($bIdx = 0; $bIdx < $nOptions; $bIdx++){
                $match[$aIdx][$bIdx] = 0;
            }
            $match[$aIdx][$nOptions] = 0; // column for the Copeland score
        }

        // Fill result matrix: $match[candidateA][candidateB] is 1 if majority prefers A over B, 1/2 if equal preferences, 0 otherwise
        $anyoneVoted = false;
        $maxCopeland = -1;
        $vetoed = array();
        for($aIdx = 0; $aIdx < $nOptions; $aIdx++){
            $copelandScore = 0;
            for($bIdx = 0; $bIdx < $nOptions; $bIdx++){
                if($bIdx <= $aIdx){
                    $copelandScore += $match[$aIdx][$bIdx];
                    continue;
                }
                $scoreA = 0;
                $scoreB = 0;
                foreach($election as $ballot){
                    if($ballot->isNotComing())
                        continue;
                    $anyoneVoted |= true;
                    $rankA = $ballot->getRank($allOptions[$aIdx]); // rank can be -1, null or a positive integer
                    $rankB = $ballot->getRank($allOptions[$bIdx]);
                    if($rankA === $rankB){ // tie for user means either both vetoed or both unselected
                        continue;
                    }
                    $userWeight = 1 + ($applyPityScore ? $ballot->getPityScore() : 0);
                    if($rankA === null || $rankB === null || $rankA <= 0 || $rankB <= 0){
                        if($rankA < 0)
                            $vetoed[$aIdx] = true;
                        if($rankB < 0)
                            $vetoed[$bIdx] = true;

                        if($rankA > 0){ // rankB is either null or negative -> A wins
                            $scoreA += $userWeight;
                            continue;
                        }
                        if($rankB > 0){ // rankA is either null or negative -> B wins
                            $scoreB += $userWeight;
                            continue;
                        }
                        // both rankA and rankB are null or negatives, but different
                        if($rankA === null){ // rankA is null and rankB is -1, null wins over -1
                            $scoreA += $userWeight;
                            continue;
                        }
                        if($rankB === null){ // rankB is null and rankA is -1, null wins over -1
                            $scoreB += $userWeight;
                            continue;
                        }
                    }
                    // both rankA and rankB are positive and different
                    if($rankA > $rankB)
                        $scoreB += $userWeight;
                    else
                        $scoreA += $userWeight;
                }

                if(!$anyoneVoted) // all voters selected the "not coming" option
                    return array();

                // Apply winner penalty, it counts as one extra voter that ranked every option above the last winner
                if($allOptions[$aIdx] == $previousWinner)
                    $scoreA--;
                if($allOptions[$bIdx] == $previousWinner)
                    $scoreB--;

                // Evaluate head-to-head winner
                if($scoreA == $scoreB){
                    $match[$aIdx][$bIdx] = 0.5;
                    $match[$bIdx][$aIdx] = 0.5;
                }
                else if($scoreA > $scoreB){
                    $match[$aIdx][$bIdx] = 1;
                    $match[$bIdx][$aIdx] = 0;
                }
                else{
                    $match[$aIdx][$bIdx] = 0;
                    $match[$bIdx][$aIdx] = 1;
                }
                $copelandScore += $match[$aIdx][$bIdx];
            }
            if($vetoed[$aIdx] && !$ignoreVetoes)
                $copelandScore = -1; // a vetoed option has negative score
            $match[$aIdx][$nOptions] = $copelandScore;
            if($copelandScore > $maxCopeland)
                $maxCopeland = $copelandScore;
        }

        if($maxCopeland < 0){ // this can only happen if all options were vetoed
            if($ignoreVetoes)
                return array();
            else // try running the election again but ignoring vetoes
                return evaluateScores($election, $allOptions, $previousWinner, true);
        }

        // Convert matches matrix into score array and sort by score
        $scores = array();
        for($idx = 0; $idx < $nOptions; $idx++){
            $scores[] = ["optionIdx" => $allOptions[$idx], "score" => $match[$idx][$nOptions]];
        }
        usort($scores, function($a, $b){
            return $b["score"] - $a["score"]; // decreasing order of score
        });
        
        return $scores;
    }

    function getDecidingUsers($election, $allOptions, int $previousWinner, int $newWinner){
        /// $election: array of Ballot
        /// $allOptions: array of option IDs (from the DB)
        /// $previousWinner: option ID of the latest winner
        /// $newWinner: option ID of the current winner
        /// returns an array of user IDs
        /// A user is "deciding" if removing their vote (but keeping their vetos) results in a copeland set that does not contain the winning option.

        $scores = evaluateScores($election, $allOptions, $previousWinner, false);
        $originalCopelandSet = getCopelandSet($scores);
        $decidingUsers = array();
        foreach($election as $originalBallot){
            if($originalBallot->isNotComing())
                continue; // For sure a user that didn't vote was not deciding
            $userIdx = $originalBallot->getUserIdx();

            // check for tie if option that won is worse than others for user
            $winnerRank = $originalBallot->getEffectiveRank($previousWinner);
            foreach($originalCopelandSet as $alternative){
                if($alternative != $previousWinner){
                    $alternativeRank = $originalBallot->getEffectiveRank($alternative);
                    if($alternativeRank > 0 && $alternativeRank < $winnerRank )
                        continue;
                }
            }

            // evaluate if removing the vote affects the result
            $virtualElection = array();
            foreach($election as $virtualBallot){
                if($virtualBallot->getUserIdx() != $userIdx){
                    $virtualElection[] = $virtualBallot;
                    continue;
                }
                $emptyBallot = new Ballot($userIdx, $originalBallot->getPityScore());
                $userVetos = $originalBallot->getVetoedOptions();
                foreach($userVetos as $vetoIdx){
                    $emptyBallot->append(new Vote(-1, $vetoIdx));
                }
                $virtualElection[] = $emptyBallot;
            }
            $scores = evaluateScores($virtualElection, $allOptions, $previousWinner, false);
            $copelandSet = getCopelandSet($scores);
            $winnerWasRemoved = true;
            foreach($copelandSet as $potentialWinner){
                if($potentialWinner == $newWinner){
                    $winnerWasRemoved = false;
                    break;
                }
            }
            if($winnerWasRemoved)
                $decidingUsers[] = $userIdx;
        }

        return $decidingUsers;
    }

    function evaluatePityScore(int $winnerIdx, Ballot $ballot, int $numOptions){
        global $applyPityScore;
        $minRank = 3; // highest rank that gets no score
        $pityWeight = $applyPityScore ? 2.0 / 3 : 0.0; // weight given to the worst case scenario (the last voted option winning)
        if($ballot->isNotComing())
            return $ballot->getPityScore();
        $effectiveRank = $ballot->getEffectiveRank($winnerIdx);
        if($effectiveRank > 0 && $effectiveRank <= $minRank) // top ranking, no additional score
            return $ballot->getPityScore();
        if($effectiveRank < 0)
            $effectiveRank = $numOptions * 1.5; // a vetoed option is below all others by a lot
        $score = ($effectiveRank * $effectiveRank - $minRank * $minRank) / ($numOptions * $numOptions - $minRank * $minRank);
        return $ballot->getPityScore() + $pityWeight * $score;
    }

    function get_already_voted($electionIdx)
    {
        global $mysqli;
        global $db_ranks_table;
        global $db_user_table;

        $query = "select distinct u.username from ".$db_ranks_table." as r join ".$db_user_table." as u on r.userIdx=u.userIdx where r.electionIdx=".$electionIdx;
        $result = mysqli_query($mysqli, $query);
        $list = array();
        while(true){
        	$row = mysqli_fetch_row($result);
            if($row == null)
                break;
            $list[] = $row[0];
        }
        return $list;
    }

    /// voter methods
    function cast_vote(int $electionIdx, Ballot $ballot)
    {
        global $mysqli;
        global $db_elections_table;
        global $db_options_table;
        global $db_ranks_table;

        $userIdx = $ballot->getUserIdx();
        clear_vote($electionIdx, $userIdx);
        $query = "insert into ".$db_ranks_table." (`userIdx`,`electionIdx`,`optionIdx`,`rank`) values ";
        if($ballot->isNotComing())
            $query = $query."(".$userIdx.",".$electionIdx.",-1,-1)";
        else{
            for($idx = 0; $idx < count($ballot->votes); $idx++){
                $vote = $ballot->votes[$idx];
                if($idx != 0)
                    $query = $query.", ";
                $query = $query."(".$userIdx.",".$electionIdx.",".($vote->optionIdx).",".($vote->rank).")";
            }
        }
        $result = mysqli_query($mysqli, $query);
    }

    function clear_vote(int $electionIdx, int $userIdx)
    {
        global $mysqli;
        global $db_ranks_table;

        $query = "delete from ".$db_ranks_table." where electionIdx=".$electionIdx." and userIdx=".$userIdx;
        $result = mysqli_query($mysqli, $query);
    }

    function get_previous_vote(int $electionIdx, int $userIdx)
    {
        global $mysqli;
        global $db_ranks_table;
        global $db_user_table;

        // find user's pity score
        $query = "select `pityScore` from ".$db_user_table." where userIdx=".$userIdx;
        $result = mysqli_query($mysqli, $query);
        $pityRow = mysqli_fetch_row($result);

        // returns a Ballot
        $ballot = new Ballot($userIdx, floatval($pityRow[0]));
        $query = "select `optionIdx`,`rank` from ".$db_ranks_table." where electionIdx=".$electionIdx." and userIdx=".$userIdx;
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        foreach($rows as $vote){
            $option = $vote['optionIdx'];
            if($option == -1){
                $ballot->setNotComing();
                break;
            }
            $rank = $vote['rank']; // for now, assume rank is always > 0
            $vote = new Vote($rank, $option);
            $ballot->append($vote);
        }
        return $ballot;
    }
?>
