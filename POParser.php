<?php
interface ParseHandler{
  public function write( $msg, $isHeader );
}

class DBHandler implements ParseHandler{
  public function init( $filename ){
    global $simplepo_config;
    $q = new Query();
    $qry = "Select * from ". $simplepo_config['table_prefix'] ."catalogues where description = ?";
    $arr = $q->sql($qry, $filename)->fetchAll();
    if (!$arr){
      $qry = "Insert into ". $simplepo_config['table_prefix'] ."catalogues (description) values (?)";
      $q->sql($qry, $filename)->execute();
      $this->catalogue_id = $q->insertId();
    } else {
      $this->catalogue_id = $arr[0]['id'];
    }
  }

  public function write( $msg, $isHeader ){
    global $simplepo_config;
    $q = new Query();
    if ( !$msg["msgid"] ) $msg["msgid"] = "";
    if ( !$msg["msgstr"] ) $msg["msgstr"] = "";
    if ( !$msg["translator-comments"] ) $msg["translator-comments"] = "";
    if ( !$msg["extracted-comments"] ) $msg["extracted-comments"] = "";
    if ( !$msg["reference"] ) $msg["reference"] = "";
    if ( !$msg["flag"] ) $msg["flag"] = "";
    if ( !$msg["obsolete-message"] ) $msg["obsolete-message"] = "";
    if ( !$msg["previous-untranslated-string"] ) $msg["previous-untranslated-string"] = "";
    if ( $msg["fuzzy"] == NULL ) $msg["fuzzy"] = false;

    $qry = "Insert into ". $simplepo_config['table_prefix'] ."messages (catalogue_id, msgid, msgstr, comments, extracted_comments,  reference, flag, obsolete, previous_untranslated_string, fuzzy)
                        values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $q->sql("Insert into simplepo_messages (catalogue_id, msgid, msgstr, comments, extracted_comments,  reference, flag, obsolete, previous_untranslated_string, fuzzy)
                        values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",$this->catalogue_id , $msg["msgid"], $msg["msgstr"], $msg["translator-comments"], $msg["extracted-comments"],
                        $msg["reference"], $msg["flag"], $msg["obsolete-message"], $msg["previous-untranslated-string"], $msg["fuzzy"])->execute();
  }

  public function read(){
    global $simplepo_config;
    $q = new Query();
    $qry = "Select * from " . $simplepo_config['table_prefix'] . "messages where catalogue_id = ?";
    $res = $q->sql($qry, $this->catalogue_id)->fetchAll();
    return  $res;
  }
}

class POParser{
  public $fileHandle;
  protected $context = array();
  public $parseHandler;

  public function __construct( $fH, $pH ){
    $this->fileHandle = $fH;
    if ($this->fileHandle === false){
      throw new Exception("Could not open file.");
    }
    $this->parseHandler = $pH;
  }

  public function parseLine( $line ){
    $retVal = array();
    if ( substr($line,0,2) == "# " ) {
      $retVal["type"] = "translator-comments";
      $retVal["value"] = substr($line,2,-1);
    } else if ( substr($line, 0, 3) == "#. " ) {
      $retVal["type"] = "extracted-comments";
      $retVal["value"] = substr($line, 3, -1);
    } else if( substr($line, 0, 3) == "#: " ){
      $retVal["type"] = "reference";
      $retVal["value"] = substr($line, 3, -1);
    } else if( substr($line, 0, 3) == "#, " ){
      if ( substr($line, 3, -1) != "fuzzy" ){
        $retVal["type"] = "flag";
        $retVal["value"] = substr($line, 3, -1);
      } else {
        $retVal["type"] = "fuzzy";
        $retVal["value"] = true;
      }
    } else if( substr($line, 0, 9) == "#| msgid " ){
      $retVal["type"] = "previous-untranslated-string";
      $retVal["value"] = substr($line, 9, -1);
    } else if( substr($line, 0, 6) == "msgid " ){
      $retVal["type"] = "msgid";
      $retVal["value"] = substr($line, 6, -1);
    } else if( substr($line, 0, 7) == "msgstr " ){
      $retVal["type"] = "msgstr";
      $retVal["value"] = substr($line, 7, -1);
    } else if( substr($line, 0, 1) == "\"" ){
      $retVal["type"] = "string";
      $retVal["value"] = substr($line, 0, -1);
    } else if( $line == "\n" ){
      $retVal["type"] = "empty";
      $retVal["value"] = $line;
    } else if( substr($line, 0, 3) == "#~ "){
      $retVal["type"] = "obsolete-message";
      $retVal["value"] = substr($line, 3, -1);
    } else if( substr($line, 0, 1) == "#"){
      $retVal["type"] = "translator-comments";
      $retVal["value"] = "";
    } else {
      throw new Exception("Cannot parse line $this->lineNumber: $line");
    }
    
    return $retVal;
  }

  public function decodeStringFormat( $str ){
    if ( substr($str, 0, 1) == "\"" && substr($str, -1,1)=="\"" ){
      $str = substr($str, 1, -1);
      $trans = array("\\\\"=>"\\", "\\n"=>"\n");
      $str = strtr($str, $trans);
    } else {
      throw new Exception("Not a PO String");
    }
    return $str;
  }

  public function saveEntry( $entry, $entry_count ){
    $isHeader = ($entry_count == 0) ? true : false;
    $this->parseHandler->write($entry, $isHeader );
  }
  public function reduceLines( $entry_lines ){
    $entry = array();
    $context = "";

    foreach ( $entry_lines as $line ){
      if($line['type'] == "string"){
        if($context == "msgid" || $context == "msgstr"){
          $entry[ $context ][] = $this->decodeStringFormat( $line['value'] );
        } else{
          throw new Exception("String in invalid position: " . $line["value"]);
        }
      } else {
        $context = $line["type"];
        $entry[ $line["type"] ] [] = ( $context == "msgid" || $context == "msgstr" ) ? $this->decodeStringFormat( $line["value"] ) : $line["value"];
      }
    }

    foreach($entry as $k=>&$v){
      if( in_array($k,array('msgid',"msgstr")) ){
        $v  = implode('',$v);
      } else {
        $v = implode("\n",$v);
      }
    }
    return $entry;
  }

  public function parse(){
    $this->context[] = "header";
    $this->lineNumber =0;
    $entry_count = 0;
    $entry_lines = array();

    while( ($line = fgets($this->fileHandle)) !== false ){
      $this->lineNumber++;
      $line = $this->parseLine($line);
      if ( $line["type"] != "empty" ){
        $entry_lines[] = $line;
      }
      else {
        $entry = $this->reduceLines($entry_lines);
        $this->saveEntry( $entry, $entry_count++ );
        $entry_lines = array();
      }
    }
  }

  public function encodeStringFormat( $str ){
    $width = 70;
    $retVal = "";
    $str = explode("\n", $str);
    if ( count($str) == 1 ) {
      if ( strlen( $str[0] ) > $width ){
        $retVal = "\"\"\n\"" . wordwrap($str[0], $width, "\"\n\"") . "\"\n";
      } else{
        $retVal = "\"" . $str[0] . "\"\n";
      }
      return $retVal;
    }

    $retVal .= "\"\"\n";
    for ($i = 0; $i < count($str) - 1; $i++ ){
      if (strlen($str[$i]) > $width) {
        $retVal .= "\"" . wordwrap($str[$i], $width, "\"\n\"") . "\\n\"\n";
      }else{
        $retVal .= "\"" . $str[$i] . "\\n\"\n";
      }
    }
    return $retVal;
  }

  public function addMessage( $entry ){
    //comment, extr comments, Reference, flag/fuzzy, prev-untr-str, obsolete, msgid, msgstr
    $prefix = array("comments"=>"# ", "extracted_comments"=>"#. ", "reference"=>"#: ", "flag"=>"#, ", "previous_untranslated_string"=>"#| ", "obsolete"=>"#~ ");
    $msg = "";
    foreach ( $entry as $k=>&$v ){
      if ( in_array( $k, array("comments", "extracted_comments", "reference", "obsolete") ) && $entry[$k] ){
        $msg .= $prefix[$k] . str_replace("\n", "\n" . $prefix[$k], $v) . "\n";
      }
      $msg .= ( $entry['fuzzy'] ) ? $prefix["flag"] . "fuzzy\n" : ""; $entry["fuzzy"] = 0;
      if (in_array( $k, array("flag", "previous_untranslated_string") ) && $entry[$k]){
        $msg .= $prefix[$k] . $v . "\n";
      }
    }

    $msg .= ($entry['msgid']) ? "msgid " . $this->encodeStringFormat($entry['msgid']) : "";
    $msg .= ($entry['msgid']) ? "msgstr " . $this->encodeStringFormat($entry['msgstr']) : "";
    $msg .= "\n";
    fwrite($this->fileHandle, $msg);
  }

  public function create(){
    $res = $this->parseHandler->read();
    for ( $i = 0; $i < count($res); $i++){
      $this->addMessage($res[$i]);
    }
  }
}