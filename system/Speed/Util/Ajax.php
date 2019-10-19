<?php

namespace Speed\Util;

class Ajax
{
	const STATUS_CODE_SUCCESS = 'OK';
	const STATUS_CODE_FAILURE = 'FAIL';

	const STATUS_MESSAGE_SUCCESS = 'Successful';
	const STATUS_MESSAGE_FAILURE = 'Failed';

	public static function Initialize ()
	{
		route('/(.+)\.(post|get)\.(json)/', function () {
			// self::SendJsonData(self::CallProvider());
			self::CallProvider('json');
		});

		route('/(.+)\.(post|get)\.(xml)/', function () {
			// self::SendXmlData(self::CallProvider());
			self::CallProvider('xml');
		});

		route('/(.+)\.(post|get)\.(html)/', function () {
			// self::SendHtmlData(self::CallProvider());
			self::CallProvider('html');
		});
	}

	public static function SendJsonData ($data, $status_code = self::STATUS_CODE_SUCCESS, $status_message = self::STATUS_MESSAGE_SUCCESS)
	{
		header('Content-Type: application/json');

		echo json_encode((object)[
	    	'status' => [
	    		'code' => $status_code,
	    		'message' => $status_message
	    	],
	    	'data' => $data
	    ]);

	    exit;
	}

	public static function SendHtmlData ($data)
	{
		header('Content-Type: text/html');
		// echo $data;
		echo str_replace('&amp;', '&', $data);

	    exit;
	}

	public static function SendXmlData ($data)
	{
		header('Content-Type: text/xml');
		echo $data;

	    exit;
	}

	private static function CallProvider ($type)
	{
		if (request('request.method') !== server('request.method')) {
			self::SendJsonData(null, self::STATUS_CODE_FAILURE, 'Invalid request method');
		}

		$call = call_request(false, config('app.remote_call_prefix'), true);

		if ($call) {
			$code = self::STATUS_CODE_SUCCESS;
			$message = self::STATUS_MESSAGE_SUCCESS;

			if (isset($call->code)) {
				$code = $call->code;
				unset($call->code);
			}

			if (isset($call->message)) {
				$message = $call->message;
				unset($call->message);
			}

			if ('json' == $type) self::SendJsonData($call, $code, $message);
			if ('xml' == $type) self::SendXmlData($call, $code, $message);
			if ('html' == $type) self::SendHtmlData($call, $code, $message);
		}

		return self::SendJsonData(false, self::STATUS_CODE_FAILURE, self::STATUS_MESSAGE_FAILURE);
	}
}