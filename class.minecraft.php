<?php

	/*
	 * @product: Minecraft Class
	 * @description: Intergrate Minecraft within your own projects.
	 * @author: Nathaniel Blackburn
	 * @version: 2.0
	 * @license: http://creativecommons.org/licenses/by/3.0/legalcode
	 * @support: support@nblackburn.co.uk
	 * @website: http://www.nblackburn.co.uk
	*/

class Minecraft
{

	public $account;

	private function request($website, array $parameters)
	{
		$request = curl_init();
		curl_setopt($request, CURLOPT_HEADER, 0);
		curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($request, CURLOPT_FOLLOWLOCATION, 1);
		if ($parameters != null) {
			curl_setopt($request, CURLOPT_URL, $website.'?'.http_build_query($parameters, null, '&'));
		} else {
			curl_setopt($request, CURLOPT_URL, $website);
		}
		$response = curl_exec($request);
        $details = curl_getinfo($request);
        curl_close($request);
        return ($details['http_code'] == 200) ? $response : null;
	}

	public function signin($username, $password, $version = 12)
	{
		$parameters = array('user' => $username, 'password' => $password, 'version' => $version);
		$request = $this->request('https://login.minecraft.net/', $parameters);
		$response = explode(':', $request);
		if (count($response) >= 0) {
			$this->account = array(
				'current_version' => $response[0],
				'correct_username' => $response[2],
				'session_token' => $response[3],
				'premium_account' => $this->isPremium($response[2]),
				'player_skin' => $this->getSkin($response[2]),
				'request_timestamp' => date("dmYhms", mktime(date('h'), date('m'), date('s'), date('m'), date('d'), date('y')))
			);
			return true;
		}
		return false;
	}

	public function isPremium($username)
	{
		$parameters = array('user' => $username);
		return $this->request('https://www.minecraft.net/haspaid.jsp', $parameters);
	}

	public function getSkin($username)
	{
		if ($this->isPremium($username)) {
			$headers = get_headers('http://s3.amazonaws.com/MinecraftSkins/'.$username.'.png');
			if ($headers[7] == 'Content-Type: image/png' || $headers[7] == 'Content-Type: application/octet-stream') {
				return 'https://s3.amazonaws.com/MinecraftSkins/'.$username.'.png';
			} else {
				return 'https://s3.amazonaws.com/MinecraftSkins/char.png';
			}
		}
		return false;
	}

	public function keepAlive($username, $session)
	{
		$parameters = array('name' => $username, 'session' => $session);
		$this->request('https://login.minecraft.net/session', $parameters);
		return null;
	}

	public function joinServer($username, $session, $server)
	{
		$parameters = array('user' => $username, 'sessionId' => $session, 'serverId' => $server);
		$request = $this->request('http://session.minecraft.net/game/joinserver.jsp', $parameters);
		return ($request != 'Bad Login') ? true : false;
	}

	public function checkServer($username, $server)
	{
		$parameters = array('user' => $username, 'serverId' => $server);
		$request = $this->request('http://session.minecraft.net/game/checkserver.jsp', $parameters);
		return ($request == 'YES') ? true : false;
	}

	public function renderSkin($username, $render_type = 'body', $size = 100)
	{
		if (in_array($render_type, array('head', 'body'))) {
			if ($render_type == 'head') {
				header('Content-Type: image/png');
				$canvas = imagecreatetruecolor($size, $size);
				$image = imagecreatefrompng($this->getSkin($username));
				imagecopyresampled($canvas, $image, 0, 0, 8, 8, $size, $size, 8, 8);
				return imagepng($canvas);
			} else if($render_type == 'body') {
				header('Content-Type: image/png');
				$scale = $size / 16;
				$canvas = imagecreatetruecolor(16*$scale, 32*$scale);
				$image = imagecreatefrompng($this->getSkin($username));
				imagealphablending($canvas, false);
				imagesavealpha($canvas,true);
				$transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
				imagefilledrectangle($canvas, 0, 0, 16*$scale, 32*$scale, $transparent);
				imagecopyresized  ($canvas, $image, 4*$scale,  0*$scale,  8,   8,   8*$scale,  8*$scale,  8,  8);
				imagecopyresized  ($canvas, $image, 4*$scale,  8*$scale,  20,  20,  8*$scale,  12*$scale, 8,  12);
				imagecopyresized  ($canvas, $image, 0*$scale,  8*$scale,  44,  20,  4*$scale,  12*$scale, 4,  12);
				imagecopyresampled($canvas, $image, 12*$scale, 8*$scale,  47,  20,  4*$scale,  12*$scale, -4,  12);
				imagecopyresized  ($canvas, $image, 4*$scale,  20*$scale, 4,   20,  4*$scale,  12*$scale, 4,  12);
				imagecopyresampled($canvas, $image, 8*$scale,  20*$scale, 7,   20,  4*$scale,  12*$scale, -4,  12);
				return imagepng($canvas);
			}
		}
		return false;
	}

}