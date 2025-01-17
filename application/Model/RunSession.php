<?php

class RunSession {

    public $id;
    public $run_id;
    public $user_id;
    public $session = null;
    public $created;
    public $ended;
    public $last_access;
    public $position;
    public $current_unit_id;
    public $deactivated;
    public $no_mail;
    public $current_unit_type;
    public $run_name;
    public $run_owner_id;
    public $run;
    public $unit_session = false;
    private $cron = false;
    private $is_testing = false;
    private $test_run = false;

    /**
     * @var DB
     */
    private $dbh;

    public function __construct($fdb, $run_id, $user_id, $session, $run = NULL) {
        $this->dbh = $fdb;
        $this->session = $session;
        $this->run_id = $run_id;
        $this->run = $run;
        if ($user_id == 'cron') {
            $this->cron = true;
        } else {
            $this->user_id = $user_id;
        }

        if ($run_id == -1) {
            $this->test_run = true;
            $this->id = -1;
            $this->session = $session;
            $this->run_id = $run_id;
            $this->user_id = $user_id;
            $this->run_name = $run->name;
            $this->run_owner_id = $user_id;
            $this->is_testing = true;
            Site::getInstance()->setRunSession($this);
        } else if ($this->session && $this->run_id) {// called with null in constructor if they have no session yet
            $this->load();
        }
    }

    private function load() {
        $sess_array = $this->dbh->select(' 
			`survey_run_sessions`.id, 
			`survey_run_sessions`.session, 
			`survey_run_sessions`.user_id, 
			`survey_run_sessions`.run_id, 
			`survey_run_sessions`.created, 
			`survey_run_sessions`.ended, 
			`survey_run_sessions`.position,
			`survey_run_sessions`.current_unit_session_id,
			`survey_run_sessions`.last_access,
			`survey_run_sessions`.no_email,
			`survey_run_sessions`.testing,
			`survey_runs`.name AS run_name,
			`survey_runs`.user_id AS run_owner_id')
                        ->from('survey_run_sessions')
                        ->leftJoin('survey_runs', 'survey_runs.id = survey_run_sessions.run_id')
                        ->where(array('run_id' => $this->run_id, 'session' => $this->session))
                        ->limit(1)->fetch();

        if ($sess_array) {
            $this->id = $sess_array['id'];
            $this->session = $sess_array['session'];
            $this->run_id = $sess_array['run_id'];
            $this->user_id = $sess_array['user_id'];
            $this->created = $sess_array['created'];
            $this->ended = $sess_array['ended'];
            $this->position = $sess_array['position'];
            $this->run_name = $sess_array['run_name'];
            $this->run_owner_id = $sess_array['run_owner_id'];
            $this->last_access = $sess_array['last_access'];
            $this->no_mail = $sess_array['no_email'];
            $this->is_testing = (bool) $sess_array['testing'];

            Site::getInstance()->setRunSession($this);
            return true;
        }

        return false;
    }

    public function getLastAccess() {
        return $this->dbh->findValue('survey_run_sessions', array('id' => $this->id), array('last_access'));
    }

    public function setLastAccess() {
        if (!$this->cron && (int) $this->id > 0) {
            $this->dbh->update('survey_run_sessions', array('last_access' => mysql_now()), array('id' => (int) $this->id));
        }
    }

    public function runAccessExpired() {
        if (!$this->run || !($last_access = $this->getLastAccess())) {
            return false;
        }

        if (($timestamp = strtotime($last_access)) && $this->run->expire_cookie) {
            return $timestamp + $this->run->expire_cookie < time();
        }

        return false;
    }

    public function create($session = NULL, $testing = 0) {
        if ($this->run_id === -1) {
            return false;
        }
        if ($session !== NULL) {
            if (strlen($session) != 64) {
                alert("<strong>Error.</strong> Session tokens need to be exactly 64 characters long.", 'alert-danger');
                return false;
            }
        } else {
            $session = crypto_token(48);
        }

        $this->dbh->insert_update('survey_run_sessions', array(
            'run_id' => $this->run_id,
            'user_id' => $this->user_id,
            'session' => $session,
            'created' => mysql_now(),
            'testing' => $testing
                ), array('user_id'));
        $this->session = $session;
        return $this->load();
    }


    /**
     * Loop over units in Run for a session until you get a unit with output
     *
     * @param UnitSession $referenceUnitSession
     * @param boolean $executeReferenceUnit If TRUE, the first unit with session matching the $referenceUnitSession will be executed
     * @return mixed
     */
    public function execute(UnitSession $referenceUnitSession = null, $executeReferenceUnit = false) {
        $i = 0;
        $done = array();
        $unit_factory = new RunUnitFactory();
        $user = Site::getCurrentUser();

        $last_unit = null;
        $output = false;
        while (!$output): // only when there is something to display, stop.
            $i++;
            if ($i > 20) {
                if (!empty($user) && ($user->isCron() || $user->isAdmin())) {
                    if (isset($unit)) alert(print_r($unit, true), 'alert-danger');
                }
                formr_log($this->run_name . " contains infinite loops.");
                alert('Nesting too deep. Could there be an infinite loop or maybe no landing page (Stop button)?', 'alert-danger');
                return array('body' => '');
            }

            $unit_info = $this->getCurrentUnit(); // get first unit in line
//            formr_log($unit_info);
            if (!$unit_info) {
                if (!$this->runToNextUnit()) {   // if there is nothing in line yet, add the next one in run order
                    return array('body' => ''); // if that fails because the run is wrongly configured, return nothing
                }
            } else {
                  // if there is one, spin that shit
                if($last_unit === $unit_info["unit_id"]) {
                    formr_log($this->run_name . " unit ". $last_unit ." would have been repeated.");
                    formr_log($unit_info);
                    alert('The same unit is being repeated. This is an error.', 'alert-danger');
                    return array('body' => '');
                }
                $last_unit = $unit_info["unit_id"];
            
                if ($this->cron) {
                    $unit_info['cron'] = true;
                }

                $unit = $unit_factory->make($this->dbh, $this->session, $unit_info, $this, $this->run);
                $this->current_unit_type = $unit->type;

                if ($referenceUnitSession && $this->unit_session && $referenceUnitSession->id != $this->unit_session->id) {
                    // dead queue item, remove from queue
                    formr_log("Dead queue item " . $referenceUnitSession->id, " mismatch " . $this->unit_session->id);
                    formr_log($referenceUnitSession);
                    formr_log($this->unit_session);
                    $referenceUnitSession = null;
                } else if ($referenceUnitSession && $this->unit_session && $referenceUnitSession->id == $this->unit_session->id && !$executeReferenceUnit) {
                    # if we are calling this from the queue and the session is still current
                    $this->endUnitSession($unit_info); # then just end the session, don't execute it
                    $referenceUnitSession = null;
                    continue;
                }

                try {
                    $output = $unit->exec();
                    //@TODO check whether output is set or NOT
                    $queue = $this->run && $this->run->cron_active && $this->unit_session->id && !$unit->ended && !$unit->expired;
                    if ($queue) {
                        $queued = UnitSessionQueue::addItem($this->unit_session, $unit, $output);
                    }

                    if (!$output && is_object($unit)) {
                        if (!isset($done[$unit->type])) {
                            $done[$unit->type] = 0;
                        }
                        $done[$unit->type] ++;
                    }
                } catch (Exception $e) {
                    formr_log_exception($e);
                    if ($this->cron) {
                        UnitSessionQueue::removeItem($referenceUnitSession->id);
                        break;
                    }
                }
            }
        endwhile;

        if ($this->cron) {
            return $done;
        }

        return $output;
    }

    public function getUnitIdAtPosition($position) {
        $unit_id = $this->dbh->findValue('survey_run_units', array('run_id' => $this->run_id, 'position' => $position), 'unit_id');
        if (!$unit_id) {
            return false;
        }
        return $unit_id;
    }

    public function endUnitSession($unit = null, $reason = null) {
        $unit = $unit !== null ? $unit : $this->getCurrentUnit(); // get first unit in line
        if ($unit) {
            $unit_factory = new RunUnitFactory();
            $unit = $unit_factory->make($this->dbh, null, $unit, $this, $this->run);
            if ($unit->type == "Survey" || $unit->type == "External") {
                if($unit->type == "Survey") {
                    $unit->session_result = "survey_expired_q";
                } else if($unit->type == "External") {
                    $unit->session_result = "external_expired_q";
                }
                $unit->expire($unit->session_result);
            } else {
                if ($reason !== null) {
                  $unit->session_result = $reason;
                } else if($unit->type == "Pause") {
                    $unit->session_result = "pause_ended";
                } else if($unit->type == "Wait") {
                    $unit->session_result = "wait_ended";
                } else if($unit->type == "Endpage") {
                    $unit->session_result = "ended_by_queue";
                } else {
                    $unit->session_result = "ended_other";
                }
                $unit->end();  // cancel it
            }
            return true;
        }
        return false;
    }

    public function forceTo($position) {
        // If there a unit for current position, then end the unit's session before moving
        if ($this->getUnitIdAtPosition($this->position)) {
            $this->endUnitSession(null, "manual_admin_push");
        }
        return $this->runTo($position);
    }

    public function runTo($position, $unit_id = null) {
        if ($unit_id === null) {
            $unit_id = $this->getUnitIdAtPosition($position);
        }

        if ($unit_id):
            $this->unit_session = new UnitSession($this->dbh, $this->id, $unit_id);
            if (!$this->unit_session->id) {
                $this->unit_session->create();
            }
            $_SESSION['session'] = $this->session;

            if ($this->unit_session->id):
                $updated = $this->dbh->update('survey_run_sessions', array('position' => $position), array('id' => $this->id));
                $this->position = (int) $position;
                return true;
            else:
                alert(__('<strong>Error.</strong> Could not create unit session for unit %s at pos. %s.', $unit_id, $position), 'alert-danger');
            endif;
        elseif ($unit_id !== null AND $position):
            alert(__('<strong>Error.</strong> The run position %s does not exist.', $position), 'alert-danger');
        else:
            alert('<strong>Error.</strong> You tried to jump to a non-existing run position or forgot to specify one entirely.', 'alert-danger');
        endif;
        return false;
    }

    public function getCurrentUnit() {
        $query = $this->dbh->select('
			`survey_unit_sessions`.unit_id,
			`survey_unit_sessions`.id AS session_id,
			`survey_unit_sessions`.created,
			`survey_units`.type')
                ->from('survey_unit_sessions')
                ->leftJoin('survey_units', 'survey_unit_sessions.unit_id = survey_units.id')
                ->where('survey_unit_sessions.run_session_id = :run_session_id')
                ->where('survey_unit_sessions.unit_id = :unit_id')
                ->where('survey_unit_sessions.ended IS NULL AND survey_unit_sessions.expired IS NULL') //so we know when to runToNextUnit
                ->bindParams(array('run_session_id' => $this->id, 'unit_id' => $this->getUnitIdAtPosition($this->position)))
                ->order('survey_unit_sessions`.id', 'desc')
                ->limit(1);

        $unit = $query->fetch();

        if ($unit) {
            // unit needs:
            # run_id
            # run_name
            # unit_id
            # session_id
            # run_session_id
            # type
            # session? 
            $unit['run_id'] = $this->run_id;
            $unit['run_name'] = $this->run_name;
            $unit['run_session_id'] = $this->id;
            $this->unit_session = new UnitSession($this->dbh, $this->id, $unit['unit_id'], $unit['session_id']);

            return $unit;
        } else {
            return false;
        }
    }

    public function getUnitSession() {
        if (!$this->unit_session) {
            $this->getCurrentUnit();
        }

        $this->unit_session;
    }

    public function runToNextUnit() {
        $select = $this->dbh->select('unit_id, position')
                ->from('survey_run_units')
                ->where('run_id = :run_id')
                ->where('position > :position')
                ->order('position', 'asc')
                ->limit(1);

        $position = -1000000;
        if ($this->position !== null) {
            $position = $this->position;
        }

        $select->bindParams(array('run_id' => $this->run_id, 'position' => $position));
        $next = $select->fetch();
        if (!$next) {
            alert('Run ' . $this->run_name . ': Oops, this study\'s creator forgot to give it a proper ending (a Stop button), user ' . h($this->session) . ' is dangling at the end.', 'alert-danger');
            return false;
        }
        return $this->runTo($next['position'], $next['unit_id']);
    }

    public function endLastExternal() {
        $query = "UPDATE `survey_unit_sessions`
			LEFT JOIN `survey_units` ON `survey_unit_sessions`.unit_id = `survey_units`.id
			SET `survey_unit_sessions`.`ended` = NOW()
			WHERE `survey_unit_sessions`.run_session_id = :id AND `survey_units`.type = 'External' AND  `survey_unit_sessions`.ended IS NULL AND `survey_unit_sessions`.expired IS NULL;";

        $updated = $this->dbh->exec($query, array('id' => $this->id));
        $success = $updated !== false;
        return $success;
    }

    public function end() {
        $query = "UPDATE `survey_run_sessions` SET `ended` = NOW() WHERE `id` = :id AND `ended` IS NULL";
        $updated = $this->dbh->exec($query, array('id' => $this->id));

        if ($updated === 1) {
            $this->ended = true;
            return true;
        }

        return false;
    }

    public function setTestingStatus($status = 0) {
        $this->dbh->update("survey_run_sessions", array('testing' => $status), array('id' => $this->id));
    }

    public function isTesting() {
        return $this->is_testing;
    }

    public function isCron() {
        return $this->cron;
    }

    /**
     * Check if current run session is a test
     *
     * @param User $user
     * @return boolean True if current user in run is testing. False otherwise
     */
    public function isTest(User $user) {
        return $this->run_owner_id == $user->id;
    }

    public function __sleep() {
        return array('id', 'session', 'run_id');
    }

    public function saveSettings($settings, $update = null) {
        if (!empty($update)) {
            $this->dbh->update('survey_run_sessions', $update, array('id' => $this->id));
        }

        $oldSettings = $this->getSettings();
        unset($oldSettings['code']);
        if ($oldSettings) {
            $settings = array_merge($oldSettings, $settings);
        }

        $this->dbh->insert_update('survey_run_settings', array(
            'run_session_id' => $this->id,
            'settings' => json_encode($settings),
        ));
    }

    public function getSettings() {
        $settings = array();
        $row = $this->dbh->findRow('survey_run_settings', array('run_session_id' => $this->id));
        if ($row) {
            $settings = (array) json_decode($row['settings']);
        }
        $settings['code'] = $this->session;
        return $settings;
    }

    public static function toggleTestingStatus($sessions) {
        $dbh = DB::getInstance();
        if (is_string($sessions)) {
            $sessions = array($sessions);
        }

        foreach ($sessions as $session) {
            $qs[] = $dbh->quote($session);
        }

        $query = 'UPDATE survey_run_sessions SET testing = 1 - testing WHERE session IN (' . implode(',', $qs) . ')';
        return $dbh->query($query)->rowCount();
    }

    public static function deleteSessions($sessions) {
        $dbh = DB::getInstance();
        if (is_string($sessions)) {
            $sessions = array($sessions);
        }

        foreach ($sessions as $session) {
            $qs[] = $dbh->quote($session);
        }

        $query = 'DELETE FROM survey_run_sessions WHERE session IN (' . implode(',', $qs) . ')';
        return $dbh->query($query)->rowCount();
    }

    public static function positionSessions(Run $run, $sessions, $position) {
        $dbh = DB::getInstance();
        if (is_string($sessions)) {
            $sessions = array($sessions);
        }

        $count = 0;
        foreach ($sessions as $session) {
            $runSession = new RunSession($dbh, $run->id, 'cron', $session, $run);
            if ($runSession->position != $position && $runSession->forceTo($position)) {
                $runSession->execute();
                $count++;
            }
        }
        return $count;
    }

    public static function getSentRemindersBySessionId($id) {
        $stmt = DB::getInstance()->prepare('
            SELECT survey_unit_sessions.id as unit_session_id, survey_run_special_units.id as unit_id FROM survey_unit_sessions 
			LEFT JOIN survey_units ON survey_unit_sessions.unit_id = survey_units.id
			LEFT JOIN survey_run_special_units ON survey_run_special_units.id = survey_units.id
			WHERE survey_unit_sessions.run_session_id = :run_session_id AND survey_run_special_units.type = "ReminderEmail"
		');
        $stmt->bindValue('run_session_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
