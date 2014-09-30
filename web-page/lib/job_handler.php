<?php
/**
 * Created by PhpStorm.
 * User: Niko
 * Date: 26/09/14
 * Time: 19:23
 */

$QUEUE_PATH = '../api/queue/';
$PUBLIC_FILES_LINK = 'http://snap.stanford.edu/news-search/api/queue/';

/**
 * Set logging
 */
define("LIB_PATH1", dirname(__FILE__)."/../lib/");
define("LOG_PATH1", dirname(__FILE__)."/../log/");
require_once(LIB_PATH1."log.php");
date_default_timezone_set('America/Los_Angeles');
$log = new Log(LOG_PATH1 . date('Y-m-d') . '-' . basename(__FILE__));
$GLOBALS['log'] = $log;


class Job{
    public $id;
    private $path;

    public function __construct($id){
        global $QUEUE_PATH;

        $this->id = $id;
        $this->path = $QUEUE_PATH.$id.'/';
    }

    public function get_dependency_files(){
        global $PUBLIC_FILES_LINK;
        $possible_files = array('command', 'langWLF.txt', 'urlWLF.txt', 'keywordWLF.txt', 'titleWLF.txt',
            'contentWLF.txt', 'quoteWLF.txt', 'langBLF.txt', 'urlBLF.txt', 'keywordBLF.txt', 'titleBLF.txt',
            'contentBLF.txt', 'quoteBLF.txt');
        $present = array();
        foreach($possible_files as $f){
            if(is_file($this->path.$f)){
                array_push($present, $PUBLIC_FILES_LINK.$this->id.'/'.$f);
            }
        }
        return $present;
    }

    public function set_hadoop_link($url){
        $handle = fopen($this->path.'/hadoop-track', "w");
        if ($handle) {
            fwrite($handle, $url."\n");
        } else {
            $GLOBALS['log']->error('Can not open file for hadoop tracking link! JobID: '.$this->id);
        }
        fclose($handle);
    }
    public function get_hadoop_link(){
        $url = '';
        $handle = fopen($this->path.'/hadoop-track', "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $url = str_replace(array("\n"), '', $line);
            }
        } else {
            $GLOBALS['log']->error('Can not open file for hadoop tracking link! JobID: '.$this->id);
        }
        fclose($handle);
        return $url;
    }

    public function get_results_link(){
        return "http://ilhadoop1.stanford.edu:50070/explorer.html#/user/niko/".$this->id;
    }

    public function set_start_date($date){
        $handle = fopen($this->path.'/date', "w");
        if ($handle) {
            fwrite($handle, $date."\n");
        } else {
            $GLOBALS['log']->error('Can not open file for date! JobID: '.$this->id);
        }
        fclose($handle);
    }
    public function get_start_date(){
        $date = '';
        $handle = fopen($this->path.'/date', "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $line = str_replace(array("\n"), '', $line);
                $date = explode(' ', $line);
            }
        } else {
            $GLOBALS['log']->error('Can not open file for date! JobID: '.$this->id);
        }
        fclose($handle);
        return $date;
    }

    public function get_progress(){
        $progress = array('0%', '0%');
        if($this->is_running()){
            $handle = fopen($this->path.'/_RUNNING', "r");
            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $progress = explode(' ', str_replace(array("\n"), '', $line));
                }
            } else {
                $GLOBALS['log']->error('Can not open file _RUNNING! JobID: '.$this->id);
            }
            fclose($handle);
        }
        return $progress;
    }

    public function update_progress($prg){
        if($this->is_running()){
            file_put_contents($this->path.'_RUNNING', $prg);
        }else{
            $GLOBALS['log']->error('Can not open file _RUNNING for updating progress! JobID: '.$this->id);
        }
    }

    // status setters
    public function set_to_submitted(){
        $this->set_to_state('_SUBMITTED');
    }
    public function set_to_running(){
        $this->set_to_state('_RUNNING');
        $this->update_progress('0% 0%');
    }
    public function set_to_success(){
        $this->set_to_state('_SUCCESS');
    }
    public function set_to_fail(){
        $this->set_to_state('_FAIL');
    }

    // status getters
    public function is_new(){
        return $this->is_state('_NEW');
    }
    public function is_submitted(){
        return $this->is_state('_SUBMITTED');
    }
    public function is_running(){
        return $this->is_state('_RUNNING');
    }
    public function is_success(){
        return $this->is_state('_SUCCESS');
    }
    public function is_fail(){
        return $this->is_state('_FAIL');
    }

    //helper function for getting states
    private function is_state($state){
        if(is_file($this->path.$state)){
            return true;
        }else{
            return false;
        }
    }

    // helper function for setting states
    private function set_to_state($state){
        $f = glob($this->path."_*");
        $f = $f[0];
        if (rename($f, $this->path.$state)) {
        } else {
            $GLOBALS['log']->error('Can not rename status file to state '.$state.'! JobID: '.$this->id);
        }
    }
}

/**
 * Return the list of all running jobs
 */
function get_job_list(){
    global $QUEUE_PATH;

    $all_jobs = array();
    if ($dh = opendir($QUEUE_PATH)) {
        while (($folder = readdir($dh)) !== false) {
            if(is_dir($QUEUE_PATH.'/'.$folder) && strncmp($folder, 'job_', 4) == 0){
                $job = new Job($folder);
                array_push($all_jobs, $job);
            }
        }
    }else{
        $GLOBALS['log']->error('Can not QUEUE folder!');
    }
    rsort($all_jobs);
    return $all_jobs;
}

/**
 * Check if a job with a given ID exists.
 */
function job_exists($id){
    foreach (get_job_list() as $job) {
        if($job->id == $id){
            return true;
        }
    }
    return false;
}

?>