<?php

namespace Stanford\HeartMRNMatcher;

require_once "src/emLoggerTrait.php";

use ExternalModules\ExternalModules;
use REDCap;
use DateTime;




class HeartMRNMatcher extends \ExternalModules\AbstractExternalModule {
    use emLoggerTrait;

    /**
     *No longer used. Using JSON field in EM config window
     * @var array N
     */
    private $map = array(
'mrn'=>0,
'last_name'=>1,
'first_name'=>2,
'patient_phenotype'=>3,
'hla_recip_hla_a1'=>4,
'hla_recip_hla_a2'=>5,
'hla_recip_hla_b1'=>6,
'hla_recip_hla_b2'=>7,
'hla_recip_hla_bw1'=>8,
'hla_recip_hla_bw2'=>9,
'hla_recip_hla_cw1'=>10,
'hla_recip_hla_cw2'=>11,
'hla_recip_hla_dr1'=>12,
'hla_recip_hla_dr2'=>13,
'hla_recip_hla_drp1'=>14,
'hla_recip_hla_drp2'=>15,
'hla_recip_hla_dq1'=>16,
'hla_recip_hla_dq2'=>17,
'unos_id'=>18,
'donor_phenotype'=>19,
'hla_donor_hla_a1'=>20,
'hla_donor_hla_a2'=>21,
'hla_donor_hla_b1'=>22,
'hla_donor_hla_b2'=>23,
'hla_donor_hla_bw1'=>24,
'hla_donor_hla_bw2'=>25,
'hla_donor_hla_cw1'=>26,
'hla_donor_hla_cw2'=>27,
'hla_donor_hla_dr1'=>28,
'hla_donor_hla_dr2'=>29,
'hla_donor_hla_drp1'=>30,
'hla_donor_hla_drp2'=>31,
'hla_donor_hla_dq1'=>32,
'hla_donor_hla_dq2'=>33
    );



    public function compareSelectedFields($candidate, $existing) {
        $record_id = REDCap::getRecordIdField();
        $j_map = $this->getProjectSetting('mapping-json');
        $map = json_decode($j_map, true);
        //$this->emDebug($map);  exit;

        $matched = array();
        $unmatched = array();
        $header = array();

        $match_field = $this->getProjectSetting('match-field');
        $header[] = "Target " . $match_field;
        $header[] = "RECID: ".$record_id;

        $compare_fields = $this->getProjectSetting('compare-field');
        if (isset($compare_fields)) {
            foreach ($compare_fields as $c_field) {
                $header[] = "Target " . $c_field;
                $header[] = "RC " . $c_field;
                $header[] = "Compare " . $c_field;
            }
        }

        $date_fields = $this->getProjectSetting('date-field');
        if (isset($date_fields)) {

            foreach ($date_fields as $d_field) {
                $header[] = "Target " . $d_field;
                $header[] = "RC " . $d_field;
                $header[] = "diff " . $d_field;
            }
        }



        foreach ($candidate as $k => $row) {
            $candidate = array();
            //no can't explode on ',' because there are larger strings
            //$v = explode(",", $row);
            $v = str_getcsv($row, ",", '"');
            //$this->emDebug($v);

            $match_value = $v[$map[$match_field]];
            //$this->emDebug("MATCH FIELD VALUED: ",$match_field,$map[$match_field],$match_value); exit;

            //store the selected fields to report

            $candidate["Check " . $match_field] = $match_value;

            //search current REDCap database for existing $match_field
            //get column of $match_field from redcap data
            $match_ids = array_column($existing, $match_field);
            //$this->emDebug("COLUMN OF UNOS REDCAP", $match_ids);
            //get keys of all matches
            $found = array_keys(array_map('strtoupper', $match_ids), trim($match_value));
            //$this->emDebug("FOUND:", $found); exit;

            //if found, do string comparison for each of the $compare_fields and show percentage, $date_fields and show diff_date
            foreach ($found as $ik => $iv) {
                //record the record_id

                $candidate["RECID:".$record_id] = $existing[$iv][$record_id];

                //store the redcap fields to report
                if (isset($compare_fields)) {
                    foreach ($compare_fields as $c_field) {
                        $candidate["Check " . $c_field] = $v[$map[$c_field]];

                    $candidate["RC_".$c_field] = $existing[$iv][$c_field];

                    //compare the strings
                    similar_text(strtoupper($candidate["RC_".$c_field]),strtoupper($candidate["Check " . $c_field]), $percent_compare);

                    $candidate['compare_'.$c_field] .= sprintf('%0.2f',$percent_compare);
                    }
                }

                $date_fields = $this->getProjectSetting('date-field');
                if (isset($date_fields)) {

                    foreach ($date_fields as $d_field) {
                        $candidate["Check " . $d_field] = $v[$map[$d_field]];

                        $candidate["RC_" . $d_field] = $existing[$iv][$d_field];

                        $date_rc = new DateTime($candidate["RC_" . $d_field]);
                        //$this->emDebug($candidate["RC_".$d_field], $date_rc, $date_rc_1);

                        $date_candidate = new DateTime($candidate["Check " . $d_field]);
                        $dDiff = $date_rc->diff($date_candidate);

                        //$this->emDebug($candidate["Check " . $d_field], $date_candidate, $date_candidate_1);
                        $candidate['diff_' . $d_field] = $dDiff->format('%r%a');

                    }
                }
            }

            if ($found) {
                //$matched[$k] = $candidate;

                $matched[$k] = array_merge($candidate, $this->getRow($map, ($v)));

            } else {
                //$unmatched[$k] = $candidate;
                //$unmatched[$k] = $v;
                $unmatched[$k] =  $this->getRow($map, $v);
                //$this->getRow($map, $v);

                //$print_unmatched[$k] = $this->getRow($map, $v);
            }
            //$this->emDebug($candidate); exit;
            //$this->emDebug($matched, $unmatched); exit;
        }
        //$this->emDebug($header, array_keys($map), array_merge($header, array_keys($map))); //
        return array(array_merge($header, array_keys($map)), $matched, array_keys($map), $unmatched);
    }

    /**
     *
     * ATTEMPT 3: Search on matching UNOSID only, then do string comprasion of last name for visual verification
     * WRitten specific for HLA
     *
     * @param $candidate
     * @param $existing
     * @return array
     */
    public function compareUnosOnly($candidate, $existing) {
        $record_id = REDCap::getRecordIdField();
        $j_map = $this->getProjectSetting('mapping-json');
        $map = json_decode($j_map, true);
        //$this->emDebug($map);

        $matched = array();
        $unmatched = array();

        $header = array('HLA last_name', 'HLA first_name', 'HLA mrn', 'HLA unos_id', //'HLA mrn fixed',
            'RC last_name', 'RC first_name', 'RC mrn', 'RC unos_id', 'Transplant Number', 'Compare Last Name', 'MRN Matched?'
        );

        $unmatched_header = array('HLA last_name', 'HLA first_name', 'HLA mrn', 'HLA unos_id');


        foreach ($candidate as $k => $row) {
            $candidate = array();
            $v = explode(",", $row);
            //$this->emDebug($k, $v);
            $hla_last_name =$v[$map['last_name']];
            $hla_first_name = $v[$map['first_name']];
            $hla_mrn = $v[$map['mrn']];
            $hla_unos = $v[$map['unos_id']];

            //$this->emDebug("HLA FIRST:".$hla_first_name);
            //$this->emDebug("HLA LAST:".$hla_last_name);
            //$this->emDebug("HLA MRN:".$hla_mrn .  "  / HLA UNOS :".$hla_unos);

            //HLA has extraneous charaacters in their MRN, so strip it
            $re = '/(\w#)?(?<fixed>\d+)/m';
            preg_match_all($re, $hla_mrn,$regex_match, PREG_SET_ORDER, 0);
            $hla_mrn_fixed = current($regex_match)['fixed'];

            $candidate['hla_last_name'] = $hla_last_name;
            $candidate['hla_first_name'] = $hla_first_name;
            $candidate['hla_mrn'] = $hla_mrn;
            $candidate['hla_unos'] = $hla_unos;
            //$candidate['hla_mrn_fixed'] = $hla_mrn_fixed;

            //search current REDCap database for existing UNOS ID
            //get column of UNOSID from redcap data
            $unos_ids = array_column($existing, 'unos_id');
            //$this->emDebug("COLUMN OF UNOS REDCAP", $unos_ids);
            //get keys of all matches
            $found = array_keys(array_map('strtoupper', $unos_ids), $hla_unos);
            //$this->emDebug("FOUND:", $found); exit;

            //if found, do string comparison of last name and show percentage
            foreach ($found as $ik => $iv) {
                //get the REDCap set of data
                //$this->emDebug("FOUND IN RC", $existing[$iv]);
                $rc_rec_id     = $existing[$iv][$record_id];
                $rc_mrn        = $existing[$iv]['mrn_fix'];
                $rc_unos       = $existing[$iv]['unos_id'];
                $rc_last_name  = $existing[$iv]['last_name'];
                $rc_first_name = $existing[$iv]['first_name'];

                //check that the MRN matches with similar text
                similar_text($rc_mrn, $hla_mrn_fixed, $percent1);
                similar_text($hla_mrn_fixed, $rc_mrn, $percent2);

                //$this->emDebug("CANDIDATE: ", $rc_rec_id, $rc_mrn, $rc_last_name, $rc_first_name, $matches);
                $candidate['RC_last_name'] = $rc_last_name;
                $candidate['RC_first_name'] = $rc_first_name;
                $candidate['RC_mrn'] = $rc_mrn;
                $candidate['RC_unos_id'] = $rc_unos;
                $candidate['transplant_number'] .= $rc_rec_id;
                similar_text(strtoupper($rc_last_name),strtoupper($hla_last_name), $percent1);
                $candidate['compare_name'] .= sprintf('%0.2f',$percent1);

                if (($percent1 > 80) and ($percent2 > 80)) {
                    //found a match
                    $matches[$k]['matched'] .= $rc_rec_id . ",";
                    $candidate['matched'] .= $rc_rec_id . ",";

                } else {
                    $candidate['matched'] .=  "";
                }
                //$this->emDebug($rc_rec_id, $rc_mrn, $rc_last_name, $rc_first_name, "match1: ". $percent1, "match2: ". $percent2);

            }

            if ($found) {
                //$matched[$k] = $candidate;
                $matched[$k] = array_merge($candidate, $this->getRow($map, $v));
                $print_matched[$candidate['transplant_number']] = $row;
            } else {
                //$unmatched[$k] = $candidate;
                //$unmatched[$k] = $v;
                $unmatched[$k] =  $this->getRow($map, $v);
                //$this->getRow($map, $v);

                //$print_unmatched[$k] = $this->getRow($map, $v);
            }
        }
        $this->emDebug($matched);
        //$this->emDebug( $print_matched, $print_unmatched);
        //file_put_contents("foo.csv", $print_matched);
        return array(array_merge($header, array_keys($map)), $matched, array_keys($map), $unmatched);

    }


    /**
     * ATTEMPT 2: compare MRN and UNOS
     * 1. Grep for UNOSID first in REDCap data
     * 2. Check that the  MRN for HLA matches MRN existing in  REDCap
     * 3. Do string compare of last name and use threshold of 80 to confirm match
     *
     * THIS WILL NOT WORK. HLA submits MRN that are not the same (ex. Kaiser caseshas different MRNs submittted)
     *
     * @param $candidate
     * @param $existing
     * @return array
     */
    public function compareMrnUnos($candidate, $existing) {
        $record_id = REDCap::getRecordIdField();

        $matches = array();

        foreach ($candidate as $k => $row) {
            $v = explode(",", $row);
            //$this->emDebug($k, $v);
            $hla_last_name =$v[$this->map['last_name']];
            $hla_first_name = $v[$this->map['first_name']];
            $hla_mrn = $v[$this->map['mrn']];
            $hla_unos = $v[$this->map['unos_id']];

            //$this->emDebug("HLA FIRST:".$hla_first_name);
            //$this->emDebug("HLA LAST:".$hla_last_name);
            //$this->emDebug("HLA MRN:".$hla_mrn .  "  / HLA UNOS :".$hla_unos);

            //HLA has extraneous charaacters in their MRN, so strip it
            $re = '/(\w#)?(?<fixed>\d+)/m';
            preg_match_all($re, $hla_mrn,$regex_match, PREG_SET_ORDER, 0);
            $hla_mrn_fixed = current($regex_match)['fixed'];

            $matches[$k]['hla_last_name'] = $hla_last_name;
            $matches[$k]['hla_first_name'] = $hla_first_name;
            $matches[$k]['hla_mrn'] = $hla_mrn;
            $matches[$k]['hla_unos'] = $hla_unos;
            $matches[$k]['hla_mrn_fixed'] = $hla_mrn_fixed;

            //search current REDCap database for existing UNOS ID
            //get column of UNOSID from redcap data
            $unos_ids = array_column($existing, 'unos_id');
            //$this->emDebug("COLUMN OF UNOS REDCAP", $unos_ids);
            //get keys of all matches
            $found = array_keys(array_map('strtoupper', $unos_ids), $hla_unos);
            //$this->emDebug("FOUND:", $found); exit;

            //if found, make sure only one was found
            foreach ($found as $ik => $iv) {
                //get the REDCap set of data
                //$this->emDebug("FOUND IN RC", $existing[$iv]);
                $rc_rec_id =$existing[$iv][$record_id];
                $rc_mrn    = $existing[$iv]['mrn_fix'];
                $rc_unos    = $existing[$iv]['unos_id'];
                $rc_last_name    = $existing[$iv]['last_name'];
                $rc_first_name    = $existing[$iv]['first_name'];

                //check that the MRN matches with similar text
                similar_text($rc_mrn, $hla_mrn_fixed, $percent1);
                similar_text($hla_mrn_fixed, $rc_mrn, $percent2);

                $matches[$k]['candidates'] .= $rc_rec_id ."(".sprintf('%0.2f',$percent1). "/" . sprintf('%0.2f',$percent2) . ")". ",";

                //$this->emDebug("CANDIDATE: ", $rc_rec_id, $rc_mrn, $rc_last_name, $rc_first_name, $matches);
                $matches[$k]['RC_last_name'] = $rc_last_name;
                $matches[$k]['RC_first_name'] = $rc_first_name;
                $matches[$k]['RC_mrn'] = $rc_mrn;
                $matches[$k]['RC_unos_id'] = $rc_unos;

                if (($percent1 > 80) and ($percent2 > 80)) {
                    //found a match
                    $matches[$k]['matched'] .= $rc_rec_id . ",";

                }
                //$this->emDebug($rc_rec_id, $rc_mrn, $rc_last_name, $rc_first_name, "match1: ". $percent1, "match2: ". $percent2);

            }
        }
        $this->emDebug($matches);
        return $matches;

    }


    public function getRow($map, $row) {
        //$this->emDebug($map, $row);
        $temp = array();
        foreach ($map as $key => $col) {
            $temp[$key] = (trim($row[$col]) == 'NULL') ? null :  $row[$col];
        }
        //$this->emDebug($temp);  exit;
        return $temp;
    }


    public static function multiSearch(array $array, array $pairs)
    {
        $found = array();
        foreach ($array as $aKey => $aVal) {
            $coincidences = 0;
            foreach ($pairs as $pKey => $pVal) {
                if (array_key_exists($pKey, $aVal) && $aVal[$pKey] == $pVal) {
                    $coincidences++;
                }
            }
            if ($coincidences == count($pairs)) {
                $found[$aKey] = $aVal;
            }
        }

        return $found;
    }


    public function findNameFromID($array, $ID) {
        $results = array_column($array, 'name', 'id');
        return (isset($results[$ID])) ? $results[$ID] : FALSE;
    }

    public function getProjectData() {
        //get the list of target fields from the config settings
        $match_field = $this->getProjectSetting('match-field');
        //$this->emDebug("Match field: ", $match_field);

        $compare_fields = $this->getProjectSetting('compare-field');
        //$this->emDebug("String compare field: ", $compare_fields);

        $date_fields  = $this->getProjectSetting('date-field');
        //$this->emDebug("Date compare fields:", $date_fields);


        $record_id = REDCap::getRecordIdField();
        //$get_data = array_merge();

        //filter those with  unos iD
        $get_fields = array_merge(array($record_id,$match_field), $compare_fields, $date_fields);
        //$this->emDebug($get_fields);

        //?? no single quotes around field name!!
        //$filter = "[unos_id] <> ''";
        $filter = "[". $match_field. "] <> ''";
        //$filter2 = "[" . $filter_event . "][" . $filter_field . "] = '$filter_value'";

        //$this->emDebug($get_fields, $filter);

        $params = array(
            'return_format'    => 'json',
//            'events'           => $filter_event,
            'fields'           => $get_fields,
            'filterLogic'      => $filter
        );

        $q = REDCap::getData($params);
        $existing = json_decode($q, true);

        //$this->emDebug($existing, $get_fields, $filter, $filter2); exit;

        //remove the inner array so we can use the array_column search on it
        /**
        $by_id = array();
        foreach ($q as $key => $event) {
            //$this->emDebug($key, current($event)); exit;
            $by_id[$key] = current($event);
        }
        */

        //$this->emDebug(array_keys($this->map), $by_id); exit;

        return $existing;

    }

    function renderTable($id, $header = array(), $data) {
    // Render table
        $grid = '<table id="' . $id . '" class="table table-striped table-bordered table-condensed" cellspacing="0" width="95%">';
        $grid .= $this->renderHeaderRow($header, 'thead');
        $grid .= $this->renderTableRows($data, $date_window);
        $grid .= '</table>';

        return $grid;
    }

    private function renderHeaderRow($header) {
        $row = '<thead><tr>';

        foreach ($header as $col_key => $this_col) {
            $row .= '<th class="th-sm" scope="col">' . $this_col;
            $row .= '<i class="fa float-right" aria-hidden="true"></i>';
            $row .= '</th>';
        }

        $row .= '</tr></thead>';

        return $row;
    }

    private function renderTableRows($data) {
        $rows = '<tbody>';

        foreach ($data as $row_key => $this_row) {
            $rows .= '<tr>';

            foreach($this_row as $rowKey => $rowValue) {
                $rows .= '<td>' . $rowValue . '</td>';
            }

            // End row
            $rows .= '</tr>';
        }

        $rows .= '</tbody>';

        return $rows;
    }


    public function getEdoc($edoc_id) {
        // Get edoc
        //$edoc_id = $this->getProjectSetting('hla-file');
        if (is_numeric($edoc_id)) {
                $path = \Files::copyEdocToTemp($edoc_id);
            } else {
                $this->errors[] = "Unable to find a valid json source file for $field in $key";
            }

            if ($path) {
                $file = $this->loadFile($path);

                // $setting['json'] = substr($this->loadJsonFile($path), 0, 50);

                //unset the temp dir file
                if(unlink($path)) {
                    $this->emDebug("File ".$path.  " has been DELETED.");
                }

            return $file;
        }
    }

    private function loadFile($path) {
        // Verify file exists
        if (file_exists($path)) {
            $contents = file($path);

            //$this->emDebug($contents, get_class($contents));

            return $contents;

        } else {
            $this->errors[] = "Unable to locate file $path";
        }
        return false;
    }

    public function dumpResource($name) {
        $file =  $this->getModulePath() . $name;
        if (file_exists($file)) {
            $contents = file_get_contents($file);
            echo $contents;
        } else {
            $this->emError("Unable to find $file");
        }
    }

}