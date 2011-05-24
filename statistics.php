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
//  Created by: dave olsen, university relations - web at west virginia university
//  v1 released on: october 28, 2010
//  v2 released on: may 24, 2011
//
//  To use:
//    - go to https://foursquare.com/oauth/ & login w/ your foursquare username & password
//    - register a new consumer (big, green button). you DO NOT need to supply a valid URL or callback URL.
//    - enter in the generated client id & client secret below in the AUTHENTICATION CREDENTIALS SECTION
//    - create a venues.txt file that contains a list of every venue ID you want to check
//    - run this script from the console by typing: php statistics.php
//    - check the reports directory for the file you just created
//
//  WARNING:
//    - if you have more than 200 venues to check then this script will fail on venue #201
//      foursquare has instituted rate limiting & this query can only be run 200 times an hour
//
//  v1 features:
//    - support for foursquare API v1
//    - basic reporting for venues
//
//  v2 features:
//    - support for foursquare API v2
//    - required support for OAuth2
//    - lists top five locations with tips
//    - lists total tips per location
//    - lists top five locations with photos
//    - lists total photos per location
//    - hopefully it's slightly easier to read
//

# AUTHENTICATION CREDENTIALS
$client_id     = 'QQUU1K21CLRD2GCUORJBPIXZOIWHNKKU0MR5XMGVHZEL144P';
$client_secret = '3MBPG1FIYESUXMRLM4X3XIC4PU4FSOPHMDVZPSDPCXGJHSWK';

# require json lib for processing
require_once('lib/Services_JSON-1.0.2/JSON.php');

# quick function to provide spacing
function extra_space($venue,$longest_name) {
	$extra_space = '';
	$spaces = strlen($longest_name) - strlen($venue);
	$s = 0;
	while ($s < $spaces) {
		$extra_space .= " ";
		$s++;
	}
	return $extra_space;
}

# zero out the checkin, tip, and photo totals & set-up arrays
$checkin_total      = 0;
$tip_total          = 0;
$photo_total        = 0;
$checkinzeros_total = 0;
$venues_by_checkin  = array();
$venues_by_tip      = array();
$venues_by_photo    = array();
$mayors             = array();
$longest_name       = '';

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
	
	# set-up URL and fetch data, need to "authenticate"
	$url = 'https://api.foursquare.com/v2/venues/'.$line.'?client_id='.$client_id.'&client_secret='.$client_secret;
	$response = file_get_contents($url);
	
	# format json data
	$json = new Services_JSON();
	$checkin_data = $json->decode($response);

	# data
	$venue    = $checkin_data->response->venue->name;
	$checkins = $checkin_data->response->venue->stats->checkinsCount;
	$tips     = $checkin_data->response->venue->tips->count;
	$photos   = $checkin_data->response->venue->photos->count;
	
	# add up checkins, tips, and photos for totals list
	$checkin_total = $checkin_total + $checkins;
	$tip_total     = $tip_total + $tips;
	$photo_total   = $photo_total + $photos;
	
	# add venue & checkins to our venue array for later reporting
	$venues_by_checkin[$venue] = $checkins;
	$venues_by_tip[$venue]     = $tips;
	$venues_by_photo[$venue]   = $photos;
	
	# if this venue has zero check-ins note that
	if ($checkins == 0) {
		$checkinzeros_total++;
	}
	
	# for some reason the user details can be undefined...
	if (isset($checkin_data->response->venue->mayor->user)) {
		$mayor = $checkin_data->response->venue->mayor->user->id;
	} else {
		$mayor = '';
	}
	
	# if the mayor hasn't been listed before add 'em for a count later
	if (!in_array($mayor,$mayors)) {
		$mayors[] = $mayor;
	}
	
	# for spacing purposes figure out which venue name is longest
	if (strlen($venue) > strlen($longest_name)) {
		$longest_name = $venue;
	}
}

# create the look & feel for the report
$report = "foursquare report for ".date("M. j, Y @ g:ia")."

total check-ins: ".$checkin_total."
total venues: ".$venue_count."
total venues w/out check-ins: ".$checkinzeros_total."
total different mayors: ".count($mayors)."
total tips: ".$tip_total."
total photos: ".$photo_total."

top five venues by checkins:
";

$i = 0;
arsort($venues_by_checkin);
foreach ($venues_by_checkin as $venue => $checkins) {
	if ($i < 5) {
		$report .= $venue.": ".$checkins."
";
	}
	$i++;
}

$report .= "
top five venues by tips:
";

$i = 0;
arsort($venues_by_tip);
foreach ($venues_by_tip as $venue => $tips) {
	if ($i < 5) {
		$report .= $venue.": ".$tips."
";
	}
	$i++;
}

$report .= "
top five venues by photos:
";

$i = 0;
arsort($venues_by_photo);
foreach ($venues_by_photo as $venue => $photos) {
	if ($i < 5) {
		$report .= $venue.": ".$photos."
";
	}
	$i++;
}

$report .= "
all venues (sorted by name): ".extra_space("all venues (sorted by name)",$longest_name)."ci / t / p
";

ksort($venues_by_checkin);
foreach ($venues_by_checkin as $venue => $checkins) {
	$extra_space = extra_space($venue,$longest_name);
	$report .= $venue.": ".$extra_space.$checkins." / ".$venues_by_tip[$venue]." / ".$venues_by_photo[$venue]."
";
}

$report .= "
all venues (sorted by check-ins): ".extra_space("all venues (sorted by check-ins)",$longest_name)."ci / t / p
";

arsort($venues_by_checkin);
foreach ($venues_by_checkin as $venue => $checkins) {
	$extra_space = extra_space($venue,$longest_name);
	$report .= $venue.": ".$extra_space.$checkins." / ".$venues_by_tip[$venue]." / ".$venues_by_photo[$venue]."
";
}

# write out the report
$timestamp = date("YmdHis");
$fp = fopen('reports/4sq_report_'.$timestamp.'.txt', 'w');
fwrite($fp, $report);
fclose($fp);

echo("report compiled and saved...\n");

?>