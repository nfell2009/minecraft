<?php

/**
 * @class: Minecraft
 * @description: Intergrate Minecraft within your own projects.
 * @author: Nathaniel Blackburn
 * @version: 2.1
 * @license: http://creativecommons.org/licenses/by/3.0/legalcode
 * @support: support@nblackburn.co.uk
 * @website: http://www.nblackburn.co.uk
 */
class Minecraft
{

    public $account = array();

    /**
     * @description Sends a request and it's parameters (if applicable) to a website and returns it's response.
     * @param $website
     * @param array $parameters
     * @return mixed|null
     */
    private function request($website, array $parameters)
    {
        $request = curl_init();
        curl_setopt($request, CURLOPT_HEADER, 0);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($request, CURLOPT_FOLLOWLOCATION, 1);
        if ($parameters != null) {
            curl_setopt($request, CURLOPT_URL, $website . '?' . http_build_query($parameters, null, '&'));
        } else {
            curl_setopt($request, CURLOPT_URL, $website);
        }
        $response = curl_exec($request);
        $details = curl_getinfo($request);
        curl_close($request);
        return ($details['http_code'] == 200) ? $response : null;
    }


    /**
     * @description Parses the mime type from the pages headers
     * @param $content
     * @return mixed
     */
    private function getMimeType($content)
    {
        preg_match_all('/content-type:\s([a-z\/?]+)/i', $content, $matches);
        return $matches[1];
    }

    /**
     * @description Signs a user in to their Minecraft account and returns their account details.
     * @param $username
     * @param $password
     * @param int $version
     * @return array|bool
     */
    public function signin($username, $password, $version = 12)
    {
        $request = $this->request('https://login.minecraft.net/', array('user' => $username, 'password' => $password, 'version' => $version));
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
            return $this->account;
        }
        return false;
    }

    /**
     * @description Checks whether or not the user specified as purchased Minecraft.
     * @param $username
     * @return mixed|null
     */
    public function isPremium($username)
    {
        return $this->request('https://www.minecraft.net/haspaid.jsp', array('user' => $username));
    }

    /**
     * @description Returns the URL to the users custom skin (if appicable).
     * @param $username
     * @return bool|string
     */
    public function getSkin($username)
    {
        $skinurl = 'https://s3.amazonaws.com/MinecraftSkins/char.png';
        if ($this->isPremium($username)) {
            $headers = get_headers('http://s3.amazonaws.com/MinecraftSkins/' . $username . '.png');
            if ($this->getMimeType($headers[7]) == in_array('application/octet-stream', 'image/png')) {
                $skinurl = 'https://s3.amazonaws.com/MinecraftSkins/' . $username . '.png';
            }
        }
        return $skinurl;
    }

    /**
     * @description Keeps the specified users active session alive.
     * @param $username
     * @param $session
     * @return null
     */
    public function keepAlive($username, $session)
    {
        return $this->request('https://login.minecraft.net/session', array('name' => $username, 'session' => $session));
    }

    /**
     * @description Adds the specified user to the server.
     * @param $username
     * @param $session
     * @param $server
     * @return bool
     */
    public function joinServer($username, $session, $server)
    {
        $request = false;
        if ($this->checkServer($username, $session, $server)) {
            $request = $this->request('https://session.minecraft.net/game/joinserver.jsp', array('user' => $username, 'sessionId' => $session, 'serverId' => $server));
        }
        return ($request != 'Bad Login' || 'Failed to verify username!') ? true : false;
    }

    /**
     * @description Checks if the specified user is able to join the server.
     * @param $username
     * @param $server
     * @return bool
     */
    public function checkServer($username, $server)
    {
        $request = $this->request('https://session.minecraft.net/game/checkserver.jsp', array('user' => $username, 'serverId' => $server));
        return ($request == 'YES') ? true : false;
    }

    /**
     * @description Renders the specified users skin.
     * @param $username
     * @param string $render_type
     * @param int $size
     * @return bool
     */
    public function renderSkin($username, $render_type = 'body', $size = 100)
    {
        if (in_array($render_type, array('head', 'body'))) {
            if ($render_type == 'head') {
                header('Content-Type: image/png');
                $canvas = imagecreatetruecolor($size, $size);
                $image = imagecreatefrompng($this->getSkin($username));
                imagecopyresampled($canvas, $image, 0, 0, 8, 8, $size, $size, 8, 8);
                return imagepng($canvas);
            } else if ($render_type == 'body') {
                header('Content-Type: image/png');
                $scale = $size / 16;
                $canvas = imagecreatetruecolor(16 * $scale, 32 * $scale);
                $image = imagecreatefrompng($this->getSkin($username));
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);
                $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
                imagefilledrectangle($canvas, 0, 0, 16 * $scale, 32 * $scale, $transparent);
                imagecopyresized($canvas, $image, 4 * $scale, 0 * $scale, 8, 8, 8 * $scale, 8 * $scale, 8, 8);
                imagecopyresized($canvas, $image, 4 * $scale, 8 * $scale, 20, 20, 8 * $scale, 12 * $scale, 8, 12);
                imagecopyresized($canvas, $image, 0 * $scale, 8 * $scale, 44, 20, 4 * $scale, 12 * $scale, 4, 12);
                imagecopyresampled($canvas, $image, 12 * $scale, 8 * $scale, 47, 20, 4 * $scale, 12 * $scale, -4, 12);
                imagecopyresized($canvas, $image, 4 * $scale, 20 * $scale, 4, 20, 4 * $scale, 12 * $scale, 4, 12);
                imagecopyresampled($canvas, $image, 8 * $scale, 20 * $scale, 7, 20, 4 * $scale, 12 * $scale, -4, 12);
                return imagepng($canvas);
            }
        }
        return false;
    }

}