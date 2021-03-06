<?php

///// PHP internal stuff, no need to change this
// (Hooray for PHP totally working like a normal language :|)
date_default_timezone_set('America/New_York');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', true);
ini_set('error_log',"/home/bitnami/php_errors.log");
// Required for currency formatting
setlocale(LC_MONETARY, 'en_US.UTF-8');

$GLOBALS["characteristics"] = array(
    "race" => ["42% White", "74% White", "92% White"],
    "govt_partyaff" => ["Mostly Republican", "Mostly Democrat", "Mixed Republican and Democrat"],
    "comm_react" => ["A group of White supporters joined an hour later", "A group of mostly White counter-protesters gathered an hour later", "A group of mostly Black and Latino counter-protesters gathered an hour later", "There was no immediate community reaction to the event"],
    "pol_react" => ["Local government officials put out a press release encouraging residents to exercise their right to free speech", "Local government officials put out a press release condemning White supremacy but also acknowledging residents' right to exercise free speech", "Local government officials put out a press release condemning White supremacy", "Local government officials called an immediate press conference with local reporters to condemn White supremacy"],
    "bus_react" => ["Local business leaders declined to comment on the event", "Local business leaders put out a statement publicly condemning White supremacy", "Local business leaders put out a statement supporting people exercising their right to free speech"],
    "pd_react" => ["Police kept watch over the event to ensure those at the rally were protected", "Police kept watch over the event to ensure those at the rally did not commit violence against others"]
);
$num_chars = count($GLOBALS["characteristics"]);
$char_list = array_keys($GLOBALS["characteristics"]);

$GLOBALS["table_display"] = array(
    "race" => "Racial Composition of Residents",
    "govt_partyaff" => "Political Affiliation of Local Government",
    "comm_react" => "Community Reaction to Rally",
    "pol_react" => "Local Government's Reaction to Rally",
    "bus_react" => "Local Business Leaders' Reaction to Rally",
    "pd_react" => "Local Police Department's Reaction to Rally"
);

// This will be iteratively updated to contain the array we want to return to Qualtrics
$GLOBALS["r"] = array();

// 1 offer (as in, 1 conjoint comparison table)
$GLOBALS["r"]["num_offers"] = 1;
if (isset($_POST["num_offers"])) {
    $GLOBALS["r"]["num_offers"] = $_POST["num_offers"];
}

// 2 options per offer
$GLOBALS["r"]["num_options"] = 2;
if (isset($_POST["num_options"])) {
    $GLOBALS["r"]["num_options"] = $_POST["num_options"];
}

// Created as a "drop-in" so we can easily switch between randomFromAll
// and randomFromRemaining, hence the unused 2nd arg
/*
function randomFromAll($opt_list, $chosen_opt){
    $rand_opt_index = array_rand($opt_list);
    $rand_opt = $opt_list[$rand_opt_index];
    return($rand_opt);
}
*/

function randomCategorical($value_list) {
    $rand_choice = randomFromAll($value_list, null);
    return $rand_choice;
}

function randomFromAll($opt_list, $chosen_opt){
    $rand_opt_index = array_rand($opt_list);
    $rand_opt = $opt_list[$rand_opt_index];
    return($rand_opt);
}

// Now we loop through the characteristics array, generating values to return
foreach ($GLOBALS["characteristics"] as $ch_name => $ch_values) {
    //echo "<br>Characteristic name:";
    //echo $ch_name;
    // Also need to generate a value for this characteristic for each *column*
    for ($i = 1; $i <= $GLOBALS["r"]["num_options"]; $i++) {
        // Need a string where we prepend "generated_", for returning
        $gen_var_name = "generated_" . $ch_name . "_" . $i;
        //echo "<br>";
        //echo $gen_var_name;
        //echo "<br>Characteristic values:";
        //var_dump($ch_values);
        // Here's where we remove "Mostly Republican" from the options if race == "42% White"
        $filtered_values = $ch_values;
        if ($ch_name == "govt_partyaff") {
            // Need to check race
            $race_varname = "generated_race_" . $i;
            $race_val = $GLOBALS["r"][$race_varname];
            if ($race_val == "42% White") {
                // Need to remove the "Mostly Republican" option
                $mr_key = array_search("Mostly Republican", $filtered_values);
                unset($filtered_values[$mr_key]);
            }
        }
        $GLOBALS["r"][$gen_var_name] = randomCategorical($filtered_values);
        //echo "<br>";
        //echo $GLOBALS["r"][$gen_var_name];
        //echo "<br>";
    }
}

// Finally, before returning, we choose a random order for the characteristics
$shuffled_chars = $char_list;
shuffle($shuffled_chars);
for ($i = 0; $i < $num_chars; $i++) {
    // php arrays start at 0, but row numbers start at 1
    $row_num = $i + 1;
    $rn_padded = sprintf('%02d', $row_num);
    // The current row's characteristic name
    $name_var = "name_o1_r" . $rn_padded;
    $name_value = $shuffled_chars[$i];
    $GLOBALS["r"][$name_var] = $name_value;
    // The first generated value for the current row's characteristic
    $gen1_var_name = "generated_" . $name_value . "_1";
    $cur_var = "cur_o1_r" . $rn_padded;
    $cur_value = $GLOBALS["r"][$gen1_var_name];
    $GLOBALS["r"][$cur_var] = $cur_value;
    // The second generated value for the current row's characteristic
    $gen2_var_name = "generated_" . $name_value . "_2";
    $val_var = "val_o1_r" . $rn_padded;
    $val_value = $GLOBALS["r"][$gen2_var_name];
    $GLOBALS["r"][$val_var] = $val_value;
    // Finally, the display name for the current row's characteristic
    $disp_var = "disp_o1_r" . $rn_padded;
    $disp_val = $GLOBALS["table_display"][$name_value];
    $GLOBALS["r"][$disp_var] = $disp_val;
}

// For debugging
$debug = false;
if (isset($_POST["debug"])) {
    $debug = $_POST["debug"];
}
if ($debug) {
    $GLOBALS["r"]["debug"] = var_export($GLOBALS["r"], true);
}

// And now we encode everything as JSON and echo it back to Qualtrics
// Generate JSON-format response
$json_response = json_encode($GLOBALS["r"]);
// Save the response with a timestamp before returning (TODO, if needed)
echo $json_response;

/*
function randomFromRemaining($opt_list, $chosen_opt){
    // Just takes a list with *all* options and removes the already-chosen one, then
    // randomly selects from among the remaining options
    // Since the RAND study draws two values *without replacement*, here we pretend that $suprespect is the
    // already-chosen first value, so draw the second value uniformly from (sup_respect_opts)\(suprespect)
    // (\ = set difference)
    $chosen_array = [$chosen_opt];
    $remaining_opts = array_diff($opt_list, $chosen_array);
    $rand_opt_index = array_rand($remaining_opts);
    $rand_opt = $remaining_opts[$rand_opt_index];
    return($rand_opt);
}
*/

?>
