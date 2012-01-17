<?php
$returnData = array(
    //"entrants" => processEntrants(&$entrants),
    "results" => processResults(array("Place", "Race No", "Name", "Division", "Time"), $entrants)
);
$returnData["filename"] = $returnData["results"]["filename"];
echo "<textarea>" . json_encode($returnData) . "</textarea>";

function outputErrors($errors) {
    if ($errors["alerts"]["status"] != "OK") {
        foreach ($errors as $item => $desc) {
            echo "<br />" . $item . ": " . $desc;
        }
    }
    echo "<br />";
}

function processResults($columns, $entrants) {
    if (empty($_POST["gender"])) {
        $errors["alerts"]["status"] = "FAIL";
        $errors["alerts"]["message"] = "You must provide a gender for the results";
        return $errors;
    } else {
        $gender = htmlspecialchars($_POST["gender"]);
    }

    $raceid = htmlspecialchars($_POST['raceid']);  
    $errors["alerts"]["status"] = "OK";

    // check file type
    if ($_FILES["results"]["type"] != "image/gif") {
        if ($_FILES["results"]["error"] > 0) {
            $errors["alerts"]["status"] = "FAIL";
            $errors["alerts"]["message"] = "You must provide a valid results CSV file";
            return $errors;
        
        // valid file type
        } else {
            $fh = fopen($_FILES["results"]["tmp_name"], "r");

            // find the header line
            while (!feof($fh)) {
                $line = fgets($fh);
                $cells = explode(",", $line);
                
                if ($cells[0] != $columns[0]) continue;               
                
                $errors["alerts"]["header"] = "Header successfully read";
                break;
            }
            
            // did we find a header line
            if (!isset($errors["alerts"]["header"])) {
                $errors["alerts"]["header"] = "Could not find the header line in the file.";
                $errors["alerts"]["status"] = "FAIL";
                return $errors;
            }

            // extract header info
            for ($i = 0; $i < count($cells); $i++) {
                $cell = $cells[$i];

                if (isset($cell) && $cell != "") {
                    $header[trim($cell)] = $i;    // save the cell position
                }
            }

            // check we have all of the header data
            for ($i = 0; $i < count($columns); $i++) {
                if (!in_array($columns[$i], array_keys($header))) {
                    $errors["alerts"]["header"] = "Could not find the required column: '"
                        . $columns[$i] . "' in the header line.";
                    $errors["alerts"]["status"] = "FAIL";
                    return $errors;
                }
            }

            $rowNum = 0;
            while (!feof($fh)) {
                $line = fgets($fh);
                $cells = explode(",", $line);
                
                // are there any cells?
                if (count($cells) < 2) break;
                
                for ($i = 1; $i < count($columns); $i++) {
                    $column = $columns[$i];
                    $cell = $cells[$header[$column]];

                    // format names
                    if ($column == "Name") {
                        $name = explode(" ", $cell, 2);
                        $data[$rowNum]["LastName"] = trim($name[1]); 
                        $data[$rowNum]["FirstName"] = trim($name[0]);

                        $g = $cells[$header["Gender"]]; 
                        if (!empty($g)) {
                            $data[$rowNum]["Gender"] = $cells[$header["Gender"]];
                        } else {
                            $data[$rowNum]["Gender"] = $gender;
                        }
                    } else if ($column == "Division") {
                        $division = explode(" ", $cell);
                        $data[$rowNum]["Division"] = trim($division[0]);
                    } else if ($column == "Race No") {
                        $data[$rowNum]["Race No"] = trim($_POST["bibid"]) . str_pad(trim($cell), 3, "0", STR_PAD_LEFT);
                    } else {
                        $data[$rowNum][$column] = trim($cell); 
                    }
                }
                $rowNum++;
            }
//echo "<pre>";
//print_r($data);
//echo "</pre>";
            fclose($fh);

            return outputFile($header, $data, $entrants, $errors);
        }
    } else {
        echo "Invalid file";
    }
}

function outputFile($header, $data, $entrants, $errors) {
    $raceid = htmlspecialchars($_POST['raceid']);
    $i = 0;
    
    if (empty($raceid) || !isset($raceid)) {
        $errors["alerts"]["status"] = "FAIL";
        $errors["alerts"]["raceid"] = "A race id must be provided";
        return $errors;
    }

    $filename = "pub/results-eventdirector-" . time() . ".csv";
    $f = fopen($filename, "w"); 

    fwrite($f, "RACE_ID,GENDER,DIVISION,BIB,FORENAME,SURNAME,TIME_FINISH\n");

    foreach ($data as $key => $val) {
        $line = $raceid 
            . "," . $val["Gender"] 
            . "," . $val["Division"] 
            . "," . $val["Race No"] 
            . "," . $val["FirstName"] 
            . "," . $val["LastName"] 
            . "," . $val["Time"] . "\n";
        fwrite($f, $line);
        $i++;
    }

    fclose($f);
    $errors["filename"] = $filename;
    $errors["missing"] = $missing;
    $errors["count"] = $i;
    return $errors;
}

function getTotalDuration ($startTime, $extraTime) {
    $total = 0;
    $duration = explode(':', $startTime);
    $extraTime = explode(':', $extraTime);

    if (count($duration) < 2) {
        $duration = array_merge(array(0, 0), $startTime);
    } else if (count($duration) < 3) {
        $duration = array_merge(array(0), $startTime);
    }
    
    if (count($extraTime) < 2) {
        $extraTime = array_merge(array(0, 0), $extraTime);
    } else if (count($extraTime) < 3) {
        $extraTime = array_merge(array("0" => 0), $extraTime);
    }
    
    $total += intval($duration[0]) * 60 * 60 + intval($extraTime[0]) * 60 * 60;     // hours to seconds
    $total += intval($duration[1]) * 60 + intval($extraTime[1]) * 60;               // mins to seconds
    $total += intval($duration[2]) + intval($extraTime[2]);                         // seconds
    
    $hours = floor($total / 60 / 60);
    $mins = floor($total / 60) - $hours * 60;
    $secs = $total % 60;
    
    return $hours . ':' . $mins . ':' . $secs;
}
?>
