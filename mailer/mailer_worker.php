<?php

date_default_timezone_set('Etc/UTC');

define('CURRENT_DIR', dirname(__FILE__).'/');
include(CURRENT_DIR.'../base.php');

/*
 * 发送邮件
 *
 * */
class mailer_worker {

	const LOG_PATH = LOG_PATH;
	const DEBUG = false;

	const MAILER_HOST = MAILER_HOST;
	const MAILER_PORT = MAILER_PORT;
	const MAILER_USER = MAILER_USER;
	const MAILER_USER_NAME = MAILER_USER_NAME;
	const MAILER_PASSWD = MAILER_PASSWD;
	private $base_mailer;

	public function __construct() {
		$this->base_mailer_init();
	}

    /*
     * Do write log
     *
     * */
	private function do_log($log_type, $log_line='') {
		switch ($log_type) {
			case 'mailer':
				$currDay = date('Y-m-d', time());
				$log_file = self::LOG_PATH."mailer_{$currDay}.log";

				$time = date('Y-m-d H:i:s', time());
				$log = "[$time][$log_line]\n";
				file_put_contents($log_file, $log, FILE_APPEND);
				break;
		}
		return;
	}

	private function base_mailer_init() {
		$this->base_mailer = new PHPMailer;
		$this->base_mailer->isSMTP();

		//Enable SMTP debugging
		// 0 = off (for production use)
		// 1 = client messages
		// 2 = client and server messages
		$this->base_mailer->SMTPDebug = 0;
		$this->base_mailer->Debugoutput = 'html';

		$this->base_mailer->Host = self::MAILER_HOST;
		$this->base_mailer->Port = self::MAILER_PORT;

		$this->base_mailer->SMTPAuth = true;
		$this->base_mailer->Username = self::MAILER_USER;
		$this->base_mailer->Password = self::MAILER_PASSWD;
		$this->base_mailer->setFrom(self::MAILER_USER, self::MAILER_USER_NAME);
		$this->base_mailer->CharSet = "utf-8";

		return;
	}

	private function set_mailer($address) {
		$this->base_mailer->addAddress($address);
		return;
	}

	private function send_mail($data, $template='template_1.html') {
		$this->base_mailer->Subject = '验证码['.$data.']';
		$this->base_mailer->msgHTML(file_get_contents(CURRENT_DIR.$template));
		$this->base_mailer->Body = str_replace('[code]', $data, $this->base_mailer->Body);
		return $this->base_mailer->send();
	}

	public function run($job, &$log) {
		//Eg: 111111|test@gmail.com
		$log = $msg = $job->workload();
		$msg = explode('|', $msg);

		$this->set_mailer($msg[1]);

		if ($this->send_mail($msg[0])) {
			$send_result =  $log.' Message sent';
		} else {
			$send_result =  $log.' Mailer Error: '.$this->base_mailer->ErrorInfo;
		}
		$this->do_log('mailer', $send_result);
		return 'OK';
	}
}
