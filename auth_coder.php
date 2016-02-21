<?php

define('CURRENT_DIR', dirname(__FILE__).'/');
include(CURRENT_DIR.'base.php');

class auth_coder {
	const LOG_PATH = LOG_PATH;
	const MAX_RETRY = 50;

	private $_THRESHOD;
	private $gearman;
	private $code_generator;

    public function __construct() {
        $this->gearman = new GearmanClient();
		$this->code_generator = new PHPGangsta_GoogleAuthenticator();
    }

    /*
     * Do write log
     *
     * */
	private function do_log($log_type, $log_line='') {
		switch ($log_type) {
			case 'auth_code':
				$currDay = date('Y-m-d', time());
				$log_file = self::LOG_PATH."auth_code_{$currDay}.log";

				$time = date('Y-m-d H:i:s', time());
				$log = "[$time][$log_line]\n";
				file_put_contents($log_file, $log, FILE_APPEND);
				break;
		}
		return;
	}

    /*
     * Add service worker
     *
     * */
	private function addServer($retry=0) {
		try {
        	$this->gearman->addServer(GEARMAN_HOST, GEARMAN_PORT);
		} catch(GearmanException $e) {
			$retry++;
			if ($retry > self::MAX_RETRY) {
				$log = "Maximum retry times of '".self::MAX_RETRY."' reached, exit the retry process!";
				$this->do_log('auth_code', $log);
				return false;
			}
			sleep(6);
			$log = "Could not add the service server, retry ".$retry;
			$this->do_log('auth_code', $log);
			return $this->addServer($retry);
		}
		return true;
	}

    /*
     * submit mission
     *
     * */
	private function submit_mission($retry=0) {
		$ping = @$this->gearman->ping('test');
		$addServer = FALSE;
		if ($ping == FALSE) {
			$addServer = $this->addServer();
		}
		if ($ping || (!$ping && $addServer)) {
			$run = $this->gearman->runTasks();
			return $run;
		} else {
			return FALSE;
		}
	}

    /*
     * Gearman Auth Code
     *
     * */
	private function generate_code() {
		$secret = $this->code_generator->createSecret();
		$code = $this->code_generator->getCode($secret);
		return array('secret'=>$secret, 'code'=>$code);
	}

	public function generate() {
		$addServer = $this->addServer();
		if (!$addServer) {
			$log = "Can't add mailer server, Exit the process!";
			$this->do_log('auth_code', $log);
			exit();
		}

		$code_info = $this->generate_code();
		$mail = 'test@gmail.com';
		$msg = $code_info['code'].'|'.$mail;
		$this->gearman->addTaskBackground('mailer_worker', $msg, 'mailer_worker');
		$submit = $this->submit_mission();
		if (!$submit) {
			$log = "Submit error, exit the process!";
			$this->do_log('auth_code', $log);
			exit();
		} else {
			$log = "Submit job";
			$this->do_log('auth_code', $log);
		}
	}
}

$auth_coder = new auth_coder();
$auth_coder->generate();
