<?php
/**
 * TeXit-Plugin: Parses TeXit-blocks in xhtml mode
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Danjer <danjer@doudouke.org>
 * @date       2007-02-11
 */
 

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
require_once(DOKU_PLUGIN.'dokutexit/texitrender.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_dokutexit extends DokuWiki_Syntax_Plugin {

  /**
   * return some info
   */
  function getInfo(){
    $ld = '$LastChangedDate: 2007-12-04 15:41:33 +0100 (Tue, 04 Dec 2007) $';
    $date = substr($ld, 18, 10);
    return array(
		 'author' => 'Danjer',
		 'email'  => 'Danjer@doudouke.org',
		 'date'   => $date,
		 'name'   => 'Doku TeXit Plugin',
		 'desc'   => 'Generate Latex/PDF Document',
		 'url'    => 'http://danjer.doudouke.org/tech/dokutexit'
		 );
  }

  /**
   * What kind of syntax are we?
   */
  function getType(){
    return 'protected';
  }

  /**
   * Where to sort in?
   */
  function getSort(){
    return 100;
  }

  /**
   * Connect pattern to lexer
   */
  function connectTo($mode) {
    $this->Lexer->addEntryPattern('<texit(?=.*\x3C/texit\x3E)', $mode,
				  'plugin_dokutexit');
  }

  function postConnect() {
    $this->Lexer->addExitPattern('</texit>','plugin_dokutexit');
  }

  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    //print_r(array('match' => $match, 'state' => $state, "pos" => $pos, "handler" => $handler));
    //print "<br>";
    if ($state == DOKU_LEXER_UNMATCHED) {
      
      $matches = preg_split('/>/u',$match,2);
      $matches[0] = trim($matches[0]);
      if ( trim($matches[0]) == '' ) {
	$matches[0] = NULL;
      }
      return array($state,$matches[0], $matches[1],$pos);
    }
    
    return array($state,'',$match,$pos);
  }

  /**
   * Create output
   */
  function render($mode, &$renderer, $data) {
    global $ID;
    list($state, $substate, $match, $pos) = $data;
    if (!isset($this->_texit)) {
      if (!$this->configloaded) { 
	$this->loadConfig(); 
      }
      $this->_texit = new texitrender_plugin_dokutexit($ID);          
    }
    if($mode == 'xhtml'){
      $renderer->info['cache'] = $this->_texit->docache();
      if ($state == DOKU_LEXER_EXIT) {
	return TRUE;
      }
      if ($state != DOKU_LEXER_UNMATCHED) {
	return FALSE;
      }
      switch ($substate) {
      case 'info':
	if ($this->_texit->add_data($substate, $match)) {
	  $renderer->doc = $this->_texit->render() . '<p>';
	}
	break;
      case 'footer':
      case 'begin':
      case 'document':
      case 'command':
      default:
	break;
      }
      return TRUE;
    }
    if($mode == 'latex'){
      if ($state == DOKU_LEXER_EXIT) {
	return TRUE;
      }
      if ($state != DOKU_LEXER_UNMATCHED) {
	return FALSE;
      }
      if (!isset($substate)) {
	$renderer->put($match);
      }
      return TRUE;
    }
    return FALSE;
  } 
}
?>
