<?php
    include_once("utilities/connessione_mysql.php");
    include_once("../utilities/connessione_mysql.php");

    /// shared methods
    function toDateFormat(string $db_date, $format='dS M Y'){
        return DateTime::createFromFormat('Y-m-d', $db_date)->format($format);
    }

    function get_open_election()
    {
        global $mysqli;
        global $db_elections_table;
        // an election is open if winner is null, there can be only one open election.
        // return open election id or -1 if not possible
        $query = "select `electionIdx`,`date` from ".$db_elections_table." where `winner` is null order by `date` asc";
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
        return [intval($rows[$idx]['electionIdx']), $rows[$idx]['date']];
    }

    function get_latest_closed_election(){
        global $mysqli;
        global $db_elections_table;
        global $db_options_table;
        // returns date and winning option of latest election. If no option could be selected, returns -1 instead of the winner
        $query = "select e.date,e.winner,o.option,e.electionIdx from (select * from ".$db_elections_table." where `winner` is not null) as e left join ".$db_options_table." as o on e.winner=o.optionIdx order by e.date desc limit 1";
        $result = mysqli_query($mysqli, $query);
    	$row = mysqli_fetch_array($result);
        // let's assume at least one election was done
        return [$row['winner'] == -1 ? -1 : $row['option'], $row['date'], $row['winner'], $row['electionIdx']];
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

        function __construct(){
            $this->votes = array();
        }

        public function append(Vote $vote){
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

        public function getVetoedOptions(){
            $vetoes = array();
            foreach($this->votes as $v){
                if($v->rank == -1)
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
    }

    function get_all_ballots(int $electionIdx) // This could be done by getting the unique users that voted and calling the get_previous_vote method, but that would make a lot of calls to the DB
    {
        global $mysqli;
        global $db_elections_table;
        global $db_options_table;
        global $db_ranks_table;

        // collect votes
        $query = "select r.userIdx,r.optionIdx,r.`rank` from ".$db_ranks_table." as r right join ".$db_options_table." as o on r.optionIdx=o.optionIdx where r.electionIdx=".$electionIdx;
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $choices = array();
        foreach($rows as $vote){
            $user = $vote['userIdx'];
            $option = $vote['optionIdx'];
            $rank = $vote['rank'];
            if(!isset($choices[$user])){
                $choices[$user] = new Ballot();
            }
            $newVote = new Vote($rank, $option);
            $choices[$user]->append($newVote);
        }

        // convert into indexed array
        $election = array();
        foreach($choices as $ballot)
            $election[] = $ballot;
        return $election;
    }

    function get_all_candidates()
    {
        global $mysqli;
        global $db_options_table;

        $query = "select `optionIdx`,`option` from ".$db_options_table." where true";
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $allOptions = array();
        foreach($rows as $option){
            $allOptions[] = [intval($option["optionIdx"]), $option["option"]];
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

    /// admin methods
    function new_election($date, $theme)
    {
        global $mysqli;
        global $db_elections_table;

        // return new election id or -1 if another election is still running.
        [$openElection, $latestDate] = get_open_election();
        if($openElection == -1){
            $escapedDate = mysqli_real_escape_string($mysqli, $date);
            if(DateTime::createFromFormat('Y-m-d', $escapedDate) === false){
                echo "invalid date<br>";
                return -1;
            }
            $escapedTheme = $theme === null ? null : mysqli_real_escape_string($mysqli, $theme);
            $query = "insert into ".$db_elections_table." (`electionIdx`, `date`, `theme`, `winner`) values (NULL, '".$escapedDate."', ".($escapedTheme === null ? "NULL" : "'".$escapedTheme."'").", NULL)";
            $success = mysqli_query($mysqli, $query);
            [$openElection, $latestDate] = get_open_election();
            return $openElection;
        }
        return -1;
    }

    function close_election(int $electionIdx)
    {
        global $mysqli;
        global $db_elections_table;
        global $db_options_table;
        global $db_ranks_table;

        // collect votes, evaluate winner and store it
        $query = "select count(electionIdx) from ".$db_elections_table." where electionIdx=".$electionIdx." and winner is null";
        $result = mysqli_query($mysqli, $query);
    	$row = mysqli_fetch_array($result);
        if($row['count(electionIdx)'] != 1){
            echo "no election to close!<br>";
            return;
        }

        // collect votes
        $election = get_all_ballots($electionIdx);

        // collect candidates
        $optionIds = array();
        $candidates = get_all_candidates();
        foreach($candidates as $candidate)
            $optionIds[] = $candidate[0];

        // collect latest winner
        $lastWinner = get_latest_closed_election()[2];

        // find and store winner
        $winner = -1; // invalid winner by default
        if(count($election) != 0 && count($optionIds) != 0)
            $winner = evaluateWinner($election, $optionIds, $lastWinner, false);
        $query = "update ".$db_elections_table." set `winner`='".$winner."' where `electionIdx`=".$electionIdx;
        $result = mysqli_query($mysqli, $query);
        if($winner > 0)
            echo "Winner: ".$candidates[array_search($winner,$optionIds)][1]."<br>";
        else
            echo "No winner :(<br>";
    }

    function evaluateWinner($election, $allOptions, $previousWinner, $ignoreVetoes){
        /// $election: array of Ballots (each Ballot has an array of options and their ranks)
        /// $allOptions: array of option IDs (from the DB)
        /// $previousWinner: option ID of the latest winner
        /// $ignoreVetoes: whether to remove vetoed options or not
        if(count($election) == 0) // no one voted
            return -1;
        
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
                    $rankA = $ballot->getRank($allOptions[$aIdx]); // rank can be -1, null or a positive integer
                    $rankB = $ballot->getRank($allOptions[$bIdx]);
                    if($rankA === $rankB){ // tie for user means either both vetoed or both unselected
                        continue;
                    }
                    if($rankA === null || $rankB === null || $rankA <= 0 || $rankB <= 0){
                        if($rankA < 0)
                            $vetoed[$aIdx] = true;
                        if($rankB < 0)
                            $vetoed[$bIdx] = true;

                        if($rankA > 0){ // rankB is either null or negative -> A wins
                            $scoreA++;
                            continue;
                        }
                        if($rankB > 0){ // rankA is either null or negative -> B wins
                            $scoreB++;
                            continue;
                        }
                        // both rankA and rankB are null or negatives, but different
                        if($rankA === null){ // rankA is null and rankB is -1, null wins over -1
                            $scoreA++;
                            continue;
                        }
                        if($rankB === null){ // rankB is null and rankA is -1, null wins over -1
                            $scoreB++;
                            continue;
                        }
                    }
                    // both rankA and rankB are positive and different
                    if($rankA > $rankB)
                        $scoreB++;
                    else
                        $scoreA++;
                }

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
                return -1;
            else // try running the election again but ignoring vetoes
                return evaluateWinner($election, $allOptions, $previousWinner, true);
        }

        // Evaluate Copeland set
        $copelandSet = array();
        for($idx = 0; $idx < $nOptions; $idx++){
            if($match[$idx][$nOptions] == $maxCopeland)
                $copelandSet[$idx] = $idx; // yes, arrays in php are wonky, since the keys are effectively a set, I'm using them as such
        }

        // Select a winner from the Copeland set. On ties, select at random
        $nCopelandWinners = count($copelandSet);
        if($nCopelandWinners == 0)
            return -1;
        $randomSelection = random_int(0, $nCopelandWinners - 1); // inclusive for both ends
        foreach($copelandSet as $winnerIdx){
            if($randomSelection == 0)
                return $allOptions[$winnerIdx];
            else
                $randomSelection--;
        }
        return -1;
    }

    function evaluateSmithSet($match, $allOptions)
    {
        $nOptions = count($allOptions);
        $maxCopeland = -1;
        for($idx = 0; $idx < $nOptions; $idx++){
            if($match[$idx][$nOptions] > $maxCopeland)
                $maxCopeland = $match[$idx][$nOptions];
        }

        $smithSet = array();
        $maxCopeland = -1;
        for($idx = 0; $idx < $nOptions; $idx++){
            $currentCopeland = $match[$idx][$nOptions];
            if($currentCopeland > $maxCopeland){
                $smithSet = array();
                $maxCopeland = $currentCopeland;
            }
            if($currentCopeland == $maxCopeland)
                $smithSet[$idx] = $idx; // yes, arrays in php are wonky, since the keys are effectively a set, I'm using them as such
        }
        while(true){
            $toAdd = null;
            foreach($smithSet as $smithWinner){
                for($opponentIdx = 0; $opponentIdx < $nOptions; $opponentIdx++){
                    if($smithWinner == $opponentIdx)
                        continue;
                    if($match[$smithWinner][$opponentIdx] != 1){
                        $toAdd = $opponentIdx;
                    }
                    if(!isset($smithSet[$toAdd]))
                        break; // new candidate is to be added to the set
                    else
                        $toAdd = null;
                }
                if($toAdd != null)
                    break; // new candidate is to be added to the set
            }
            if($toAdd != null)
                $smithSet[$toAdd] = $toAdd;
            else
                break; // no more candidates to add
        }
        return $smithSet;
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
    function cast_vote(int $electionIdx, int $userIdx, Ballot $ballot)
    {
        global $mysqli;
        global $db_elections_table;
        global $db_options_table;
        global $db_ranks_table;

        clear_vote($electionIdx, $userIdx);
        $query = "insert into ".$db_ranks_table." (`userIdx`,`electionIdx`,`optionIdx`,`rank`) values ";
        for($idx = 0; $idx < count($ballot->votes); $idx++){
            $vote = $ballot->votes[$idx];
            if($idx != 0)
                $query = $query.", ";
            $query = $query."(".$userIdx.",".$electionIdx.",".($vote->optionIdx).",".($vote->rank).")";
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

        // returns a Ballot
        $ballot = new Ballot();
        $query = "select `optionIdx`,`rank` from ".$db_ranks_table." where electionIdx=".$electionIdx." and userIdx=".$userIdx;
        $result = mysqli_query($mysqli, $query);
    	$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
        foreach($rows as $vote){
            $option = $vote['optionIdx'];
            $rank = $vote['rank']; // for now, assume rank is always > 0
            $vote = new Vote($rank, $option);
            $ballot->append($vote);
        }
        return $ballot;
    }
?>
