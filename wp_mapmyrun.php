<?php
/*
 * Plugin Name: WordPress Under Armour - MapMyRun
 * Plugin URI: http://www.davebongaerts.nl
 * Description: Een WordPress plugin die je Under Armour - MapmyRun resultaten toont
 * Version: 1.0
 * Author: Dave Bongaerts
 * Author URI: http://www.davebongaerts.nl/
 * License: GPL2
 * */
?>
<?php
require_once("api.class.php");

function getResults($action) {
	
	$API = new API();

    $tokens = $API->getTokens();
    $convert = new Convert();
	
	switch($action) {
	case "user_stats":			
	$fields = array(
      "aggregate_by_period" => "month"	
	);	
	
	$data = $API->getUserStats($fields);
		
	$user_stats = $data["_embedded"][stats];
			
	print(json_encode($user_stats));
	print('=======');
			
	$flatten = $convert->flatten($user_stats);
    print(json_encode($flatten));
	//print($flatten);
			
	break;
    case "current":
    //print "Where's waldo: current speed and location.<br />";
    //print "Get latest workout, then get time series of workout by ID.<br />";

    // get data for all workouts since DATE.
    $tripStart = new DateTime('2016-06-27');
    $fields = array(
      "aggregate_by_period" => "month"
    );

    // Make the API call to /v7.1/workouts
    $data = $API->getWorkouts($fields);

    // get the workouts from the returned data
    $workouts = $data["_embedded"]["workouts"];
    $id = $workouts[0]["_links"]["self"][0]["id"];


    //print "Now, do an API call to get the workout by id:" . $id . "<br />";
    $latest = $API->getWorkout($id);

    // All we really care about is today's route (lat/lon), and stats like today's speed, distance, etc.
    $json = array(
      "position"=>$latest["time_series"]["position"],
      "aggregates"=>convertAggregates($convert,$latest["aggregates"])
    );

    print json_encode($json);
  break;
  case "tripstats":
    // get data for all workouts since DATE.
    $tripStart = new DateTime('2016-06-27');
    $tripEnd = new DateTime('2018-02-01');
    $fields = array(
      "user" => $tokens["user_href"],
      "order_by"=>"-start_datetime", // the last shall be first. :)
      "started_after"=>$tripStart->format('c'),
      "started_before"=>$tripEnd->format('c')
    );

    // Make the API call to /v7.1/workouts
    $data = $API->getWorkouts($fields);

    // get the workouts from the returned data
    $workouts = $data["_embedded"]["workouts"];

    $totals = totalAggregates($workouts);

    return json_encode(convertAggregates($convert,$totals));

    break;
  case "test":
    print "Testing the unit conversion.<br />";
    print "========== Meters Per Second to Miles Per Hour ==========<br />";
    print "1 meters per second is " . $convert->mpsToMph(1) . " miles per hour!<br />";
    print "5 meters per second is " . $convert->mpsToMph(5) . " miles per hour!<br />";
	print "========== Meters Per Second to Kilometers Per Hour ==========<br />";
    print "1 meters per second is " . $convert->mpsToKph(1) . " kilometer per hour!<br />";
    print "5 meters per second is " . $convert->mpsToKph(5) . " kilometer per hour!<br />";
    print "========== Joules to Calories ========<br />";
    print "1 joules is " . $convert->joulesToKCals(1) . " k calories!<br />";
    print "500 joules is " . $convert->joulesToKCals(500) . " k calories!<br />";

    print "========== Meters to Miles ==========<br />";
    print "1000 meters is " . $convert->metersToMiles(1000) . " miles!<br />";
    print "9999 meters is " . $convert->metersToMiles(9999) . " miles!<br />";

    print "========== Seconds to time interval ==========<br />";
    print "1000 seconds is " . $convert->secondsToTimeString(1000) . " hh:mm:ss<br />";
    print "1 seconds is " . $convert->secondsToTimeString(1) . " hh:mm:ss<br />";

  break;
  default:

    print '{"error":"Bad URL action."}';
  break;
  }
}

function allTimeSeries($workouts) {
  $dat = array();

  foreach($workouts as $workout) {
    print "Time series:";
    var_dump($workout);
    array_push($dat,$workout["time_series"]);
  }
  return $dat;
}

function totalAggregates($workouts) {
  $sumAgg = array(
    "distance_total" => 0,
    "speed_avg" => 0,
    "speed_max" => 0,
    "speed_min" => 0,
    "active_time_total" => 0,
    "metabolic_energy_total" => 0
  );

  // loop through each workout and get the sum stats
  foreach($workouts as $workout) {
    $stats = $workout["aggregates"];
    $sumAgg["distance_total"] += $stats["distance_total"];
    $sumAgg["speed_avg"] += $stats["speed_avg"];
    $sumAgg["active_time_total"] += $stats["active_time_total"];
    $sumAgg["metabolic_energy_total"] += $stats["metabolic_energy_total"];

    // set speed_max IF greater than previous speed_max
    if ($stats["speed_max"] > $sumAgg["speed_max"]) {
      $sumAgg["speed_max"] = $stats["speed_max"];
    }
  }

  $sumAgg["speed_avg"] = $sumAgg["speed_avg"] / count($workouts);

  return $sumAgg;
}

function convertAggregates($convert,$arr) {
  // For each item, convert to a more friendly unit.
  return array(
    "distance_total"=>$convert->metersToKilometers($arr["distance_total"]),
    "speed_avg"=>$convert->mpsToKph($arr["speed_avg"]),
    "speed_max"=>$convert->mpsToKph($arr["speed_max"]),
    "active_time_total"=>$convert->secondsToTimeString($arr["active_time_total"]),
    "kcalories_burned_total"=>$convert->joulesToKCals($arr["metabolic_energy_total"])
  );
}



function UnderArmour_func( $atts ) {
	$atts = shortcode_atts( array(
		'action' => 'test'
	), $atts, 'UnderArmourTag' );

	$json = getResults($atts['action']);
	$result = json_decode($json);
	return "<table><tr><td>Afstand totaal:</td><td>{$result->distance_total}</td><td>Gemiddelde snelheid:</td>
	        <td>{$result->speed_avg}</td></tr><tr><td>Maximale snelheid:</td><td>{$result->speed_max}</td><td>Totale tijd actief geweest:</td><td>{$result->active_time_total}</td></tr><tr><td>Totaal verbrande calorieën</td><td>{$result->kcalories_burned_total}</td></tr></table>" ;
}
add_shortcode( 'UnderArmourTag', 'UnderArmour_func' );

function prettyPrint( $json ){

$result = '';
$level = 0;
$in_quotes = false;
$in_escape = false;
$ends_line_level = NULL;
$json_length = strlen( $json );

for( $i = 0; $i < $json_length; $i++ ) {
    $char = $json[$i];
    $new_line_level = NULL;
    $post = "";
    if( $ends_line_level !== NULL ) {
        $new_line_level = $ends_line_level;
        $ends_line_level = NULL;
    }
    if ( $in_escape ) {
        $in_escape = false;
    } else if( $char === '"' ) {
        $in_quotes = !$in_quotes;
    } else if( ! $in_quotes ) {
        switch( $char ) {
            case '}': case ']':
                $level--;
                $ends_line_level = NULL;
                $new_line_level = $level;
                $char.="<br>";
                for($index=0;$index<$level-1;$index++){$char.="-----";}
                break;

            case '{': case '[':
                $level++;
                $char.="<br>";
                for($index=0;$index<$level;$index++){$char.="-----";}
                break;
            case ',':
                $ends_line_level = $level;
                $char.="<br>";
                for($index=0;$index<$level;$index++){$char.="-----";}
                break;

            case ':':
                $post = " ";
                break;

            case "\t": case "\n": case "\r":
                $char = "";
                $ends_line_level = $new_line_level;
                $new_line_level = NULL;
                break;
        }
    } else if ( $char === '\\' ) {
        $in_escape = true;
    }
    if( $new_line_level !== NULL ) {
        $result .= "\n".str_repeat( "\t", $new_line_level );
    }
    $result .= $char.$post;
}

echo "RESULTS ARE: <br><br>$result";
return $result;
}


?>

