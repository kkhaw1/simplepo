<?php


(@include_once('config.php')) || die("You must create a config.php file\nsee config-sample.php\n");
require_once('DB.php');
require_once('POParser.php');

class SimplePO{
  public $force;
  public $infile;
  public $outfile;
  public $catalogue_name;
  public $fileHandle;
  public $POParser;
  public $pHandler;

function main($argc, $argv){
  $this->parseArguments($argc, $argv);
  $this->pHandler = new DBHandler();

  if ( $this->catalogue_name ){
    $this->pHandler->init( $this->catalogue_name );
  }
  if ( $this->infile ){
    if ( !$this->catalogue_name ) die("Please provide a catalogue name\n");
    $this->fileHandle = fopen( $this->infile, 'r');
    $this->POParser = new POParser( $this->fileHandle, $this->pHandler);
    $this->POParser->parse();
    echo "$this->infile successfully parsed\n";
    return;
  }
  if ( $this->outfile ){
    if ( !$this->catalogue_name ) die("Please provide a catalogue name\n");
    $this->fileHandle = fopen( $this->outfile, 'w');
    $this->POParser = new POParser( $this->fileHandle, $this->pHandler);
    $this->POParser->create();
    echo "$this->outfile successfully written\n";
    return;
  }
  $this->usage();
}

function parseArguments($argc, $argv){
  $flags = array(
                   "version" => array("-v","--version"),
                   "install" =>array("--install"),
                   "force" => array("-f", "--force")
  );

  $options = array(
                    "inputfile" => array("-i","--inputfile"),
                    "outputfile" => array("-o","--outputfile"),
                    "catalogue_name" => array("-n","--name")
  );

  $installCmd = false;
  for($i=1; $i < count($argv); $i++) {
      $a = $argv[$i];
      if(in_array($a,$flags['version'])) {
        $this->usage();
        exit(0);
      }
      if( in_array($a, $flags['install']) ){
        $installCmd = true;
      }
      if ( in_array($a, $flags['force']) ){
        $this->force = true;
      }
      if ( in_array($a, $options['inputfile']) ){
        $this->infile = ( ($i+1) < count($argv) ) ? ($argv[$i+1]) : die("   Please provide input filename.\n");
      }
      if ( in_array($a, $options['outputfile']) ){
        $this->outfile = ( ($i+1) < count($argv) ) ? ($argv[$i+1]) : die("   Please provide input filename.\n");
      }
      if ( in_array($a, $options['catalogue_name']) ){
        $this->catalogue_name = ( ($i+1) < count($argv) ) ? ($argv[$i+1]) : die("   Please provide input filename.\n");
      }
    }
    if ($installCmd){
      $this->install($this->force);
      exit(0);
    }
}

function usage() {
  echo "   ________________________________________\n";
  echo "\n\t\tSimplePO\n";
  echo "   ________________________________________\n";
  echo "   Flags:\n";
  echo "   \tversion:\t-v\t--version\n";
  echo "   \tversion:\t \t--install\n";
  echo "   \tforce:\t-f\t--force\n";
  echo "   Options:\n";
  echo "  \t-i\tinputfilename\n";
  echo "  \t-o\toutputfilename\n";
  echo "  \t-n\tcataloguename\n\n";
  echo "   This is how you use this program:\n";
  echo "     To install:\n";
  echo "  \tphp simplepo.php --install\n";
  echo "  \tphp simplepo.php --force --install\n";
  echo "  \tphp simplepo.php -f --install\n\n";
  echo "     To read in a PO file:\n";
  echo "  \tphp simplepo.php -n CatalogueName -i inputfilename\n";
  echo "     To write to a PO file:\n";
  echo "  \tphp simplepo.php -n CatalogueName -o outputfilename\n\n";
}

function install( $force ){
  global $simplepo_config;
  $create_message =<<<CM
    CREATE TABLE IF NOT EXISTS `{$simplepo_config['table_prefix']}messages` (
      `id` int(11) NOT NULL auto_increment,
      `catalogue_id` int(11) NOT NULL,
      `msgid` text NOT NULL,
      `msgstr` text NOT NULL,
      `comments` text NOT NULL,
      `extracted_comments` text NOT NULL,
      `reference` text NOT NULL,
      `flag` text NOT NULL,
      `obsolete` text NOT NULL,
      `previous_untranslated_string` text NOT NULL,
      `fuzzy` tinyint(1) NOT NULL,
      `updated_at` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
      PRIMARY KEY  (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1
CM;

  $create_catalogue = <<<CM
    CREATE TABLE IF NOT EXISTS `{$simplepo_config['table_prefix']}catalogues` (
      `id` int(11) NOT NULL auto_increment,
      `description` varchar(100) NOT NULL,
      PRIMARY KEY  (`id`),
      UNIQUE KEY `description` (`description`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
CM;

  $q = new Query();
  if ($this->force){
    echo "\tForced Installation taking place...\n";
    $q->sql("DROP TABLE IF EXISTS " . $simplepo_config['table_prefix'] . "catalogues, " . $simplepo_config['table_prefix'] . "messages")->execute();
  }
  $q->sql($create_catalogue)->execute();
  $q->sql($create_message)->execute();
  echo "\tInstallation complete!\n\n";
}

}

$s = new SimplePO();
$s->main($argc, $argv);