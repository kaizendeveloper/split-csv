<?php


// CSV SPLITTER

//A very simple CLI script for splitting a CSV into multiple pieces preserving the
//same headers as the origin file


//CLI ARGUMENT EXTRACTION
//-------------------------------------------------------------------

//By default we assume we don't have a file to work with
$originalFileName = null;
//By default we won't slice the file
$numPieces = 1;


//Short option definitions
$shortopts = "";
$shortopts .= "f:";  // Original filename (required)
$shortopts .= "p:";  // Number of pieces (required)
$shortopts .= "h::"; // Help (optional)

//Long option definitions
$longopts = array( 'file:' , 'pieces:', 'help::' );

$parsedOptions = getopt($shortopts, $longopts);


//--------------------
//Show help page
//--------------------
if(isset($parsedOptions['h']) || isset($parsedOptions['help']) || count($parsedOptions) === 0)
{
    echo "\r\n---------------\r\n";
    echo " CSV SPLITTER\r\n";
    echo "---------------\r\n\r\n";
    echo "Synopsis: Splits a CSV file by the number defined by the user. \r\n";
    echo "If a \"pattern.txt\" file is located at the same location as this script\r\n";
    echo "it will generate a \"cmd.txt\" file which is useful for launching other\r\n";
    echo "scripts from the command shell related to the split files for instance.\r\n\r\n";

    $thisFileInfo = pathinfo(__FILE__);
    echo "Usage: php " . $thisFileInfo['basename'] . " [OPTIONS]\r\n\r\n";

    echo "[OPTIONS LIST]\r\n--------------\r\n";
    echo "-forigin-filename\r\n--file=\"origin-filename\"\tSpecifies the original CSV file\r\n\r\n";
    echo "-pNUMBER --pieces=NUMBER\tNumber of pieces\r\n\r\n";
    echo "-h  --help\t This help\r\n\r\n";

    echo "Examples:\r\n---------\r\nphp " . $thisFileInfo['basename'] . " -ftest.csv -p50\r\n";
    echo "php " . $thisFileInfo['basename'] . " -file=\"test.csv\" --pieces=50\r\n\r\n";

    exit(0);


}


//CLI Parameter value extraction
//-----------------------------------

//File
//-------------
if(isset($parsedOptions['f']))
{
    $originalFileName = $parsedOptions['f'];
}
if(isset($parsedOptions['file']))
{
    $originalFileName = $parsedOptions['file'];
}


//Pieces
//--------------
if(isset($parsedOptions['p']))
{
    $numPieces = (int)$parsedOptions['p'];
}
if(isset($parsedOptions['pieces']))
{
    $numPieces = (int)$parsedOptions['pieces'];
}





/**
 * Retrieves the number of lines a text file has
 *
 * @param $file
 * @return int
 */
function getLines($file)
{

    //Open the file as read only and as binary
    $f = fopen($file, 'rb');
    $lines = 0;

    //Read using chunks of 8192 bytes until END OF FILE
    while (!feof($f)) {
        $lines += substr_count(fread($f, 8192), "\n");
    }

    fclose($f);

    return $lines;

}

/**
 * Generates a part number filename
 *
 * @param $file
 * @param null $partNumber
 * @return string
 */
function generateFileNumber($file, $partNumber = null){

    $filePathInfo = pathinfo($file);

    return $filePathInfo['filename'] . '-part-' . sprintf('%03d', (int)$partNumber) . '.' . $filePathInfo['extension'];

}

/**
 * Actually splits the file
 *
 * @param $file
 * @param int $parts
 * @return int
 */

function splitFile($file, $parts = 1){


    //Check if target files exists
    if(file_exists(__DIR__ . DIRECTORY_SEPARATOR . $file) && is_file(__DIR__ . DIRECTORY_SEPARATOR . $file)){
        $linee = (int)getLines($file) + 1;
    } else {
        echo "\r\nError: File not found or not specified\r\n\r\n";
        exit(2);
    }


    //Calculate amount of pieces
    $div = (int)($linee / $parts);

    $numFile = 0;

    //If the pattern file exists we might need to generate the secuencial command launch commands
    if(file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'pattern.txt')){

        //Get the file handle
        $tmpPatternFile = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'pattern.txt', 'r');
        //Read the whole pattern
        $subString = fread($tmpPatternFile, 10000);
        //The file is now useless
        fclose($tmpPatternFile);

        //Create output file
        $tmpCmdFile = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'cmd.txt', 'w');
        //GC
        $tmpPatternFile = null;

    }

    //Open
    $fileToSplitHandle = fopen($file, 'rb');

    //Create the empty file
    $splitOutputFile = fopen(generateFileNumber($file,$numFile), 'w');
    //Make it readable/writeable to anyone
    chmod(generateFileNumber($file,$numFile), octdec('777'));

    $numLinea = 0;

    //We read the input file line by line until depletion
    while (!feof($fileToSplitHandle)) {

        //Our actual line counter
        $numLinea++;

        //For saving the header on each split file
        if($numLinea === 1)
        {
            //Read the header
            $csvHeader = fgets($fileToSplitHandle);
            //And save it in the split file
            fwrite($splitOutputFile, $csvHeader);

        } else {

            //Read a normal line
            $linea = fgets($fileToSplitHandle);

            //Check if we have fulfilled the split file with the right amount of lines
            if(($numLinea % $div) === 0){

                //We'll save our command in the command file by doing the respective substitution
                if(!is_null($tmpCmdFile)){
                    fwrite($tmpCmdFile, preg_replace('/PLACEHOLDER/i', generateFileNumber($file,$numFile),$subString) . "\r\n");
                }


                $numFile++;

                //Check if we're working on the last file
                if($numFile === $parts) {

                    //No, we are still working on the previuos file. Just put the content in
                    fwrite($splitOutputFile, $linea);

                } else {

                    //No, we need to close the file
                    // - Create a new one with the next number
                    // - Put the header
                    // - Save the line

                    //But first we'll save our command in the command file by doing the respective substitution (after file number change)
                    /*if(!is_null($tmpCmdFile)){
                        fwrite($tmpCmdFile, preg_replace('/PLACEHOLDER/i', generateFileNumber($file,$numFile),$subString) . "\r\n");
                    }*/

                    //Close the split file
                    fclose($splitOutputFile);

                    //Create new split file
                    $splitOutputFile = fopen(generateFileNumber($file,$numFile), 'w');
                    chmod(generateFileNumber($file,$numFile) , octdec('777'));

                    //Save header and line
                    fwrite($splitOutputFile, $csvHeader);
                    fwrite($splitOutputFile, $linea);
                }
            } else {
                //No, we haven't finished yet, so we save and keep going on
                fwrite($splitOutputFile, $linea);
            }

        }

    }
    //Housekeeping
    fclose($fileToSplitHandle);
    fclose($splitOutputFile);
    fclose($tmpCmdFile);
    $tmpCmdFile = null;

}

//Split the file and exit gracefully
splitFile($originalFileName,$numPieces);

exit(0);




