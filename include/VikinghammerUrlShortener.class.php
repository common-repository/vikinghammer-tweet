<?php

/**
Shorten a URL, using an external API.

This uses the Tiny VH shortener by default.
*/
class VikinghammerUrlShortener {
    var $apiUrl;

    function __construct() {
        $this->apiUrl = 'http://tinyvh.com/api.php?url=';
    }

    /**
    Shorten a URL so that it'll show up nicely in a tweet. (And so that our tweet isn't too long).
    */
    function shortenUrl($link) {
        $link = urlencode($link);
        $url = $this->apiUrl . $link;
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        $short = curl_exec($c);
        curl_close($c);
        return $short;
    }
}

?>
