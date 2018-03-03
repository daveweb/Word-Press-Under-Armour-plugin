<?php

define( 'WPMAPMYRUN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
/** This file (class?) accesses data from MapMyRide.com. */
class API {
  private $debug = FALSE;
  private $tokens = array();
  private $tokenFilename = WPMAPMYRUN_PLUGIN_DIR."tokens.json";

  /** The address of the MapMyRide API server which we do HTTP requests to. */
  private $domain = "https://api.ua.com/";
//  private $domain = "https://oauth2-api.mapmyapi.com/";


  /** The MapMyRide API version string which is used in the URL of the HTTP request. */
  private $apiVersion = "v7.1";

	/** Our developer keys which identify this unique application. */
	private $keys = array(
		"clientid"=>"wkow6njvxysra7ei7f6pkl3iaifpzc4k",
		"clientsecret"=>"g2bypfrybm35xqec6mq3vyl3geyhj3w4j3ed2kxkug55lppizrlxq33od6gru4di"
	);

  function __construct() {
    $this->tokens = $this->readTokens();
    $this->log("Tokens:");
    $this->log($this->tokens);
  }

  private function readTokens() {
    $fstring = file_get_contents($this->tokenFilename);
    return json_decode($fstring,TRUE);
  }

  public function getTokens() {
    return $this->tokens;
  }

  private function getAPIUrl($endpoint) {
    return $this->domain . $this->apiVersion . $endpoint;
  }

  public function getWorkout($workoutid) {
    if (!$workoutid) { die("Workoutid is required."); }
    $endpoint = "/workout/{$workoutid}/";

    $this->log("endpoint");
    $this->log($endpoint);

    $fields = array(
      "id"=>$workoutid,
      "field_set"=>"time_series"
    );

    $workout = $this->GET($this->getAPIUrl($endpoint),$fields);
    $this->log("workout");
	$this->log($workout);
    return $workout;
  }

  public function getWorkouts($fields) {
    $endpoint = '/workout/'; 
    //finally, do the GET request.
    $this->log($this->getAPIUrl($endpoint));
    $workout = $this->GET($this->getAPIUrl($endpoint),$fields);
    $this->log($workout);
    return $workout;
  }
	
  public function getUserStats($fields) {
	$userid = $this->tokens["user_id"];
	$endpoint = "/user_stats/{$userid}/"; 
    $this->log($this->getAPIUrl($endpoint));
    $userStats = $this->GET($this->getAPIUrl($endpoint),$fields);
    $this->log($userStats);
    return $userStats;
  }

	private function GET($url,$fields) {
    $fields_string = '?';
		//url-ify the data for the POST
		foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
		rtrim($fields_string, '&');

    $this->log("fields:");
    $this->log($fields_string);

		//open connection
     $ch = curl_init();

    // debugging!
    if ($this->debug === TRUE) {
      ///curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
  //    curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
    }

		//set the url, number of vars, data
    $toURL = $url . $fields_string;
    $this->log("toURL");
	$this->log($toURL);

    // set request headers
    $headers = array(
      'Authorization: Bearer ' . $this->tokens["access_token"],
      'Api-Key: ' . $this->keys["clientid"],
    );
    $this->log("Sending GET request to: {$toURL}");

		curl_setopt($ch, CURLOPT_URL, $toURL);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		//execute GET request
    $result = curl_exec($ch);

    if (curl_errno($ch)) {
      $this->log("Curl error:");
      die(curl_error($ch));
      curl_close($ch);
    } else {

      switch(curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
        case 200: 
          $this->log("200 Success!");

          // if JSON content, decode!
          if (curl_getinfo($ch, CURLINFO_CONTENT_TYPE) == "application/json") {
            $this->log("it's JSON!");
            $result = json_decode($result,TRUE);
          }

          break;
        case 403: //permission denied?!
          $this->log("Error 403: Permission Denied!");

          break;
        case 404: //endpoint not found!
          $this->log("Error 404: Endpoint (or API URL)");
          
          break;
        case 400: //Bad request
          $this->log("Error 400: Bad Request");

          break;
        default: //unidentified error
          $this->log("Error:");
          
          break;
      }

      //get header info
      $this->log(curl_getinfo($ch));
      $this->log($result);
    }
 

    curl_close($ch);
		return $result;
	}

  private function log($data) {
    if ($this->debug === TRUE) {
      print"<br />debug info<br/>";
      var_dump($data);
    }
    return;
  }

}

Class Convert {
  private $metersPerKilometer = 1000;
  private $kilometersPerMile = 1.609344;
  private $mphPerMps = 2.23693629;
  private $kphPerMps = 3.6;
	
  private $joulesPerKcal = 4184;

  public function mpsToMph($val) {
    // convert meters per second to miles per hour
    return $val * $this->mphPerMps;
  }

  public function mpsToKph($val) {
    // convert meters per second to miles per hour
    return $val * $this->kphPerMps;
  }	

  public function metersToMiles($val) {
    // convert distance from meters to kilometers to miles
    return ($val / $this->metersPerKilometer) / $this->kilometersPerMile;
  }
	
  public function metersToKilometers($val) {
    // convert distance from meters to kilometers to miles
    return ($val / $this->metersPerKilometer);
  }

  public function joulesToKCals($val) {
    // convert joules to kcal (calories burned during workout)
    return $val / $this->joulesPerKcal;
  }

  public function secondsToTimeString($seconds) {
    // convert seconds (time) to hh:mm:ss
		// adapted from: http://codeaid.net/php/convert-seconds-to-hours-minutes-and-seconds-(php)
		// extract hours
    $hours = floor($seconds / (60 * 60));
 
    // extract minutes
    $divisor_for_minutes = $seconds % (60 * 60);
    $minutes = floor($divisor_for_minutes / 60);
 
    // extract the remaining seconds
    $divisor_for_seconds = $divisor_for_minutes % 60;
    $seconds = ceil($divisor_for_seconds);

    return $hours . ":" . $minutes . ":" . $seconds;
  }

  public function flatten(array $array) {
    $branch = [];

    foreach ($array as $item) {
        $time_in_heart_rate_zones = [];
        if (isset($item['time_in_heart_rate_zones']) && is_array($item['time_in_heart_rate_zones'])) {
            $time_in_heart_rate_zones = $this->flatten($item['time_in_heart_rate_zones']);
            unset($item['time_in_heart_rate_zones']);
        }
        $branch = array_merge($branch, [$item], $time_in_heart_rate_zones);
    }

    return $branch;
}

}
?>
