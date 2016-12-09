<?php


// CSV SPLITTER

//A very simple CLI script for splitting a CSV into multiple pieces preserving the
//same headers as the origin file



//-----------------------------------------------------------------------------
//                     FUNCTION DECLARE AREA
//-----------------------------------------------------------------------------


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
 * Generates a part number filename
 *
 * @param $file
 * @param null $partNumber
 * @return string
 */
function createNewSplitfile($file, $partNumber = null){

    $splitFileFilename = generateFileNumber($file, $partNumber);

    //New split file's File Handle
    $splitOutputFileFH = fopen($splitFileFilename, 'w');

    if($splitOutputFileFH === false){
        die("\r\nCould not create split file " . $splitFileFilename . " check if the script has the right write permissions.\r\n\r\n");
    }

    //After creating the file, make it readable/writeable to anyone
    chmod(generateFileNumber($file, $partNumber), octdec('777'));


    return $splitOutputFileFH;

}

/**
 * Actually splits the file
 *
 * @param $file
 * @param int $parts
 * @return int
 */

function splitFile($file, $parts = 1)
{

    //Generic default declarations

    $lineNumber = 0;
    $fileNumber = 0;
    $csvHeader = '';


    //Check if target files exists
    if(file_exists(__DIR__ . DIRECTORY_SEPARATOR . $file) && is_file(__DIR__ . DIRECTORY_SEPARATOR . $file)){
        $inputFileLines = (int)getLines($file) + 1;
    } else {
        echo "\r\nError: Origin file not found or not specified\r\n\r\n";
        exit(2);
    }


    //Amount of pieces calculation
    //-------------------------------------

    $linesPerSplitFile = intval($inputFileLines / $parts);
    $roundLineExcess = $inputFileLines % $parts;

    //This way we make sure the amount of split files comprehends all of the lines of the original file
    //by adding one line per single file
    if( ($roundLineExcess) > 0) {
        $linesPerSplitFile ++;
    }

    //GC
    $roundLineExcess = null;
    unset($roundLineExcess);

    //-------------------------------------



    //PATTERN FILE READING
    //--------------------------------------

    //Depending on the pattern written in the file we will generate the sequential part using the split file
    //numbered names

    //If the pattern file exists we might need to generate the secuencial command launch commands
    if(file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'pattern.txt')){

        //Get the file handle
        $tmpHandlePatternFile = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'pattern.txt', 'r');
        //Read the whole pattern
        $subString = fread($tmpHandlePatternFile, 10000);
        //The file is now useless
        fclose($tmpHandlePatternFile);

        //Create output file
        $tmpCmdFile = fopen(__DIR__ . DIRECTORY_SEPARATOR . 'cmd.txt', 'w');
        //GC
        $tmpHandlePatternFile = null;
        unset($tmpHandlePatternFile);

    }

    //Create original file's File Handle
    $originalFileFH = fopen($file, 'rb');

    if($originalFileFH === false){
        die("\r\nCould not read the origin file, check if the file is accesible.\r\n\r\n");
    }

    //Create first split file
    $splitOutputFileFH = createNewSplitfile($file, $fileNumber);

    //We'll save the first command in the command file by doing the respective substitution
    if(!is_null($tmpCmdFile)){
        fwrite($tmpCmdFile, preg_replace('/PLACEHOLDER/i', generateFileNumber($file,$fileNumber), $subString) . "\r\n");
    }


    //We go on through each line of the origin CSV
    while (!feof($originalFileFH)) {

        //Our actual line counter
        $lineNumber++;

        //We need to catch the header for saving it later on each split file
        if($lineNumber === 1)
        {
            //Read the header
            $csvHeader = fgets($originalFileFH);
            //And save it in the split file
            fwrite($splitOutputFileFH, $csvHeader);

        } else {

            //Other lines (not header)

            //Read a normal line
            $linea = fgets($originalFileFH);

            //Check if we have fulfilled the split file with the right amount of lines
            if(($lineNumber % $linesPerSplitFile) === 0){

                //Yes, we have enough for one file


                //We have completed a split file, move on to the next one
                $fileNumber++;

                //We'll save our command in the command file by doing the respective substitution
                if(!is_null($tmpCmdFile)){
                    fwrite($tmpCmdFile, preg_replace('/PLACEHOLDER/i', generateFileNumber($file,$fileNumber), $subString) . "\r\n");
                }




                // Now we need to:
                // - Close the file
                // - Create a new one with the next number
                // - Put the header
                // - Save the line


                //Close the split file
                fclose($splitOutputFileFH);

                //In order to avoid writing a new file upon being at the very last line, we make our last check
                if($lineNumber < $inputFileLines){

                    //Practically we're creating a new brand split file
                    //--------------------------------------------------

                    //Create new split file
                    $splitOutputFileFH = createNewSplitfile($file,$fileNumber);

                    //Save header and line
                    fwrite($splitOutputFileFH, $csvHeader);
                }


            }

            //Write a normal line
            fwrite($splitOutputFileFH, $linea);


        }

    }

    //Housekeeping
    fclose($originalFileFH);
    fclose($splitOutputFileFH);
    fclose($tmpCmdFile);
    $tmpCmdFile = null;

}
//--------------------------------- END OF FUNCTION DECLARE AREA --------------














//-----------------------------------------------------------------------------
//                              PROGRAM'S MAIN
//-----------------------------------------------------------------------------



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




//Split the file and exit gracefully
splitFile($originalFileName,$numPieces);

exit(0);




