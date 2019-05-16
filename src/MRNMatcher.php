<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 2019-04-03
 * Time: 13:21
 */

namespace Stanford\HeartMRNMatcher;
/*** @var \Stanford\HeartMRNMatcher\HeartMRNMatcher $module */

require_once "emLoggerTrait.php";

use ExternalModules\ExternalModules;
use REDCap;
use Message;
use emLoggerTrait;

$module->emDebug("Starting Heart TX MRN Matcher");

//read in the candidate file

$module->emDebug("current working dir is ". basename(__DIR__) );
$module->emDebug("current working dir is ". getcwd());

$file = $module->getProjectSetting('hla-file');

$module->emDebug("FILE IS ".$file);
$candidate = $module->getEdoc($file);

$header = array_shift($candidate);
//$module->emDebug($header, "HEADER");
//$module->emDebug($candidate, "CANDIDATE"); exit;


//list($header, $candidate) = $module->loadCandidateFile($file);
//$module->emDebug("EXAMPLE ROWS TO LOAD", $header, $candidate[0], $candidate[1]);

//do a getData from the database and get the
$existing = $module->getProjectData();
//$module->emDebug("EXISTING",  $existing[0], $existing[1]);
//$module->emDebug($existing);

// TRY 1: compare MRN and Names : FAIL
//$matches = $module->compareNamesMRN($candidate, $existing);

// TRY 2: compare UNOS ID, then MRN, then last name : FAIL (some MRNs are different. i.e. Kaiser MRN
//$matches = $module->compareNamesMRN($candidate, $existing);

// TRY 3: search on UNOS ID, string compare last name and present comparison percentage for visual verification
//list($header, $matched, $unmatched_header,  $unmatched) = $module->compareUnosOnly($candidate, $existing);

// TRY 4; search on config selected fields
list($header, $matched, $unmatched_header,  $unmatched) = $module->compareSelectedFields($candidate, $existing);
//$module->emDebug($matches);

?>

<!DOCTYPE html>
<html>
<head>
    <title>MRN Matcher</title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>


    <!-- Favicon -->
    <link rel="icon" type="image/png"
          href="<?php print $module->getUrl("favicon/stanford_favicon.ico", false, true) ?>">

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jq-3.3.1/dt-1.10.18/b-1.5.6/b-flash-1.5.6/b-html5-1.5.6/datatables.min.css"/>

<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jq-3.3.1/dt-1.10.18/b-1.5.6/b-flash-1.5.6/b-html5-1.5.6/datatables.min.js"></script>

<div class="container">
    <h2>Match Report</h2>

    <h3>If the matches look correct, download the CSV to import into REDCap</h3>
    <h3>The incoming file had <?php print sizeof($candidate)?> rows.</h3>

    <?php print $module->renderTable("matched", $header, $matched) ?>
</div>

<div class="container">
    <h2>No match Report</h2>
    <h3>These rows did not match. You can download the CSV to attempt again later.</h3>
    <?php print $module->renderTable("unmatched", $unmatched_header, $unmatched) ?>
</div>



<script type = "text/javascript">

    $(document).ready(function(){

        $('#matched').DataTable( {
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf'
            ],
            scrollY:        "600px",
            scrollX:        true,
            scrollCollapse: true,
            paging:         false,
        } );

        $('#unmatched').DataTable( {
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf'
            ],
            scrollY:        "600px",
            scrollX:        true,
            scrollCollapse: true,
            paging:         false,
        } );

    });


</script>

