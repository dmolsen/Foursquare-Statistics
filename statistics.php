<?php

/**
 * Copyright (c) 2010 West Virginia University
 * 
 * Licensed under the MIT License
 * Redistributions of files must retain the above copyright notice.
 * 
 */

//  === foursquare Venue Statistics ===
//  Generates some general statistics related to foursquare and the supplied venue IDs
//
//  Created by: dave olsen, university relations/web at west virginia university
//  Created on: october 28, 2010
//
//  To use:
//    - create a venues.txt file that contains a list of every venue ID you want to check
//    - run this script from the console by typing: php statistics.php
//    - check the reports directory for the file you just created
//
//  WARNING:
//    - if you have more than 200 venues to check then this script will fail on venue #201
//      foursquare has instituted rate limiting & this query can only be run 200 times an hour
//

# require json lib for processing
require_once('lib/Services_JSON-1.0.2/JSON.php');

# zero out the checkin total & set-up arrays
$checkin_total = 0;
$checkinzeros_total = 0;
$venues = array();
$mayors = array();

# open the file and read in the data to process
$filename = "venues.txt";
$fd = fopen ($filename, "r");
$contents = fread ($fd,filesize ($filename));
fclose ($fd); 

# separate out the data
$lines = explode("\r", $contents);
$venue_count = count($lines);

echo("processing ".$venue_count." venues...\n");

# fetch foursquare info based on each venue ID to build up report data
foreach ($lines as $line) {
	
	# set-up URL and fetch data
	$url = 'http://api.foursquare.com/v1/venue.json?vid='.$line;
	$response = file_get_contents($url);
	
	# format json data
	$json = new Services_JSON();
	$checkin_data = $json->decode($response);
	
	# data
	$venue = $checkin_data->venue->name;
	$checkins = $checkin_data->venue->stats->checkins;
	$mayor = $checkin_data->venue->stats->mayor->user->id;
	
	# add up checkins
	$checkin_total = $checkin_total + $checkins;
	
	# if this venue has zero check-ins note that
	if ($checkins == 0) {
		$checkinzeros_total++;
	}
	
	# if the mayor hasn't been listed before add 'em for a count later
	if (!in_array($mayor,$mayors)) {
		$mayors[] = $mayor;
	}
	
	# add venue & checkins to our venue array for later reporting
	$venues[$venue] = $checkins;
}

# create the look & feel for the report
$report = "foursquare report for ".date("M. j, Y @ H:i:s")."

total check-ins: ".$checkin_total."
total venues: ".$venue_count."
total venues w/out check-ins: ".$checkinzeros_total."
total different mayors: ".count($mayors)."

top five venues:
";

$i = 0;
arsort($venues);
foreach ($venues as $venue => $checkins) {
	if ($i < 5) {
		$report .= $venue.": ".$checkins."
";
	}
	$i++;
}

$report .= "
all venues (sorted by name):
";

ksort($venues);
foreach ($venues as $venue => $checkins) {
	$report .= $venue.": ".$checkins."
";
}

$report .= "
all venues (sorted by check-ins):
";

arsort($venues);
foreach ($venues as $venue => $checkins) {
	$report .= $venue.": ".$checkins."
";
}

# write out the report
$timestamp = date("Ymdhis");
$fp = fopen('reports/4sq_report_'.$timestamp.'.txt', 'w');
fwrite($fp, $report);
fclose($fp);

echo("report compiled and saved...\n");

?>