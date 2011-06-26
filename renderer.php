<?php
/**
 * Renderer for Dokutexit output
 *
 * @author Harry Fuecks <hfuecks@gmail.com>
 * @author Andreas Gohr <andi@splitbrain.org>
 */
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

// we inherit from the XHTML renderer instead directly of the base renderer
//require_once DOKU_INC.'inc/parser/xhtml.php';
//require_once DOKU_INC.'inc/parser/renderer.php';
require_once DOKU_INC.'lib/plugins/dokutexit/latex.php';
require_once DOKU_INC.'lib/plugins/dokutexit/texitrender.php';
/**
 * The Renderer
 */
class renderer_plugin_dokutexit extends Doku_Renderer {
    var $info = array(
        'cache' => false, // may the rendered result cached?
        'toc'   => false, // render the TOC?
    );

    /**
     * return some info
     */
  function getInfo(){
    //        return confToHash(dirname(__FILE__).'/info.txt');
    // Change this !
    return array(
		 'author' => 'Danjer',
		 'email'  => 'Danjer@doudouke.org',
		 'date'   => 'soon',
		 'name'   => 'Doku TeXit Plugin',
		 'desc'   => 'Generate Latex/PDF Document',
		 'url'    => 'http://danjer.doudouke.org/tech/dokutexit'
		 );
  }

    /**
     * the format we produce
     */
    function getFormat(){

        // this should be 'dokutexit' usally, but we inherit from the xhtml renderer
        // and produce XHTML as well, so we can gain magically compatibility
        // by saying we're the 'xhtml' renderer here.
        return 'dokutexit';
    }


    /**
     * Initialize the rendering
     */
    function document_start() {
      global $ID;
      
      $this->id  = $ID;
      if (!isset($this->_texit)) {
	if (!$this->configloaded) { 
	  $this->loadConfig(); 
	}
	$this->_texit = new texitrender_plugin_dokutexit($this->id);
	$info = array();
	if (preg_match("/<texit info>(.*?)<\/texit>/", 
		       str_replace("\n", '\n', rawWiki($this->id)), 
		       $info, PREG_OFFSET_CAPTURE)) {
	  $this->_texit->add_data('info', 
				  str_replace('\n', "\n", $info[0][0]));
	} else {
	  echo "error preg_match";
	}
 	if ($_REQUEST['dokutexit_type'] == 'zip')
 	  $this->_texit->_dokutexit_conf['zipsources'] = true;
	if ($this->_texit->generate('pdf')) {
	  $filename = null;
	  switch ($_REQUEST['dokutexit_type']) {
	  case 'zip':
	    if (is_readable($this->_texit->zip['file'])) {
	      $filename = $this->_texit->zip['file'];
	      header('Content-Type: application/zip');
	    }
	    break;
	  case 'pdf':
	  default:
	    if (is_readable($this->_texit->pdf['file'])) {
	      $filename = $this->_texit->pdf['file'];
	      header('Content-Type: application/pdf');
	    }
	    break;
	  }
	  $hdr = "Content-Disposition: attachment;";
	  $hdr .= "filename=".basename($filename).";";
	  header($hdr);
	  header("Content-Transfer-Encoding: binary");
	  header("Content-Length: ".filesize($filename));
	  readfile("$filename"); 
	  die;
	}
      }
    }

}

//Setup VIM: ex: et ts=4 enc=utf-8 :
