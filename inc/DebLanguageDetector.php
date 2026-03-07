<?php

class DebLanguageDetector
{

    private $error;
    private $warning;
    private $texts;
    private $apiKey = 'TEST';
    private $userAgent = 'DebLanguageDetector';
    private $url = 'http://bld.debelop.com/detect';

    public function __construct($apiKey = null, $url = null)
    {
        $this->texts = array();
        if ($apiKey) {
            $this->apiKey = $apiKey;
        }
        if ($url) {
            $this->url = $url;
        }
    }

    public function setApiKey($key)
    {
        $this->apiKey = $key;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function clear()
    {
        $this->texts = array();
    }

    public function addText($text, $id)
    {
        $this->texts[$id] = $text;
    }

    public function encodeParams($texts)
    {
        $a = array_map(function ($e) {
            return 'texts[]=' . urlencode($e);
        }, $texts);
        $a[] = 'key=' . urlencode($this->apiKey);
        return implode('&', $a);
    }

    private function apiCall($texts)
    {
        $this->error = null;
        $this->warning = null;

        $ch = curl_init();

        $options = array(
            CURLOPT_URL => $this->url,
            CURLOPT_POSTFIELDS => $this->encodeParams($texts),
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_RETURNTRANSFER => true,
        );

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if ($result === false) {

            $code = curl_errno($ch);
            $msg = curl_error($ch);
            curl_close($ch);

            $this->error = "Unable to connect ($code): $msg";
            return false;
        }

        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($result, true);
        if (null === $response) {
            $this->error = 'Invalid response: ' . $result;
            return false;
        } elseif ($responseCode != 200) {
            $msg = $response['error'];
            $this->error = "$msg ($responseCode)";
            return false;
        }
        if (!empty($response['warning'])) {
            $this->warning = $response['warning'];
        }
        return $response['detected'];
    }

    public function detect()
    {

        $keys = array_keys($this->texts);
        $texts = array_values($this->texts);

        $detected = $this->apiCall($texts);

        if (is_array($detected)) {
            return $this->combineResults($keys, $detected);
        }

        return false;
    }

    private function combineResults($keys, $detected)
    {
        $k = count($keys);
        $n = count($detected);
        if ($k == $n) {
            return array_combine($keys, $detected);
        }
        $size = min($k, $n);
        $a = $size < $k ? array_slice($keys, 0, $size) : $keys;
        $b = $size < $n ? array_slice($detected, 0, $size) : $detected;
        return array_combine($a, $b);
    }

    public function getError()
    {
        return $this->error;
    }

    public function getWarning()
    {
        return $this->warning;
    }

    public function hasWarning()
    {
        return isset($this->warning);
    }

}
