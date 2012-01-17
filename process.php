<?php

tidyFiles();

$returnData = array(
    "results" => processResults(array("Race No", "Name", "Division", "Time"), $entrants)
);
$returnData["filename"] = $returnData["results"]["filename"];
echo json_encode($returnData);

function outputErrors($errors) {
    if ($errors["alerts"]["status"] != "OK") {
        foreach ($errors as $item => $desc) {
            echo "<br />" . $item . ": " . $desc;
        }
    }
    echo "<br />";
}

function error($data, $status, $message) { 
    $data["alerts"]["status"]= $status;
    $data["alerts"]["message"] = $message;
    return $data;
}

function tidyFiles() {
    exec("rm -r pub/");
}

function processResults($columns, $entrants) {
    if (empty($_POST["gender"])) {
        return error($errors, "FAIL", "You must provide a gender for the results");
    } else {
        $gender = htmlspecialchars($_POST["gender"]);
    }

    $raceid = htmlspecialchars($_POST['raceid']);  
    $errors["alerts"]["status"] = "OK";

    // check file type
    if ($_FILES["results"]["type"] != "image/gif") {
        if ($_FILES["results"]["error"] > 0) {
            return error($errors, "FAIL", "You must provide a valid results CSV file");
        
        // valid file type
        } else {
            ini_set('auto_detect_line_endings',TRUE);
            $fh = fopen($_FILES["results"]["tmp_name"], "r");

            //$line = fgets($fh);
            $cells = fgetcsv($fh, 0);
            
            // extract header info
            for ($i = 0; $i < count($cells); $i++) {
                $cell = $cells[$i];

                if (isset($cell) && $cell != "") {
                    $header[trim($cell)] = $i;    // save the cell position
                }
            }

            // make sure there is a header row
            if (count($columns) < 1) {
                return error($errors, "FAIL", "Could not find the header row. The header row is the first line of the file that contains the names of the columns. This should match the example files.");
            }

            // check we have all of the header data
            for ($i = 0; $i < count($columns); $i++) {
                if (!in_array($columns[$i], array_keys($header))) {
                    return error($errors, "FAIL", "Could not find the required column: '"
                        . $columns[$i] . "' in the header line.");
                }
            }
            
            $errors["alerts"]["header"] = "Header successfully read";

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
        return error($errors, "FAIL", "A race id must be provided");
    }

    $filename = "pub/results-$raceid.csv";
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

?>
