<?php
/**
 * Renderer for LaTeX output
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author Harry Fuecks <hfuecks@gmail.com>
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Danjer <danjer@doudouke.org>
 *
 * Rolphin is a looser
 *
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');

if ( !defined('DOKU_LF') ) {
  // Some whitespace to help View > Source
  define ('DOKU_LF',"\n");
}

if ( !defined('DOKU_TAB') ) {
  // Some whitespace to help View > Source
  define ('DOKU_TAB',"\t");
}

require_once DOKU_INC . 'inc/parser/renderer.php';
require_once DOKU_INC . 'inc/html.php';
require_once(DOKU_PLUGIN.'dokutexit/class.texitimage.php');


class Doku_Renderer_latex extends Doku_Renderer {

  var $doc = '';


  var $acronyms = array();
  var $smileys = array();
  var $badwords = array();
  var $entities = array();
  var $interwiki = array();
  var $state = array();
  var $latexentities = array();
  var $headers = array();
  var $dokulinks = array();
  var $smileys_ps = array();
  var $interwiki_ps = array();
  var $_quote_level = 0;
  var $_section_level = 0;
  var $_footnotes = array();
  var $_footnote_index = 0;
  var $_num_titles = 0;
  var $_current_table_mode = NULL;
  var $_current_table_args = array();
  var $_current_table = array();
  var $_current_table_maxcols_size = array();
  var $_current_table_cols_size = 0;
  var $_acronyms_used = array();
  var $_tmphandle = NULL;
  var $_tmp_put = array();

  function label_document() { //For links
    if (isset($this->info['current_file_id'])) 
      $cleanid = $this->info['current_file_id'];
    else
      $cleanid = noNS(cleanID($this->info['current_id'], TRUE));
    $this->putcmd("label{" . md5($cleanid) . "}");
    if (isset($this->info['current_file_id'])) 
      $this->putnl("%%Start: " . $cleanid . ' => ' 
		   . $this->info['current_file_id']);
    else
      $this->putnl("%%Start: " . $cleanid . ' => ' . wikiFN($cleanid));
  }

  function getFormat() {
    return 'latex';
  }
  
  function document_start() {
    //    ob_start();
    $this->headers = array();
    //msg("Memory Usage doc start: ". memory_get_usage(), -1);
    //memory managment
    //$this->_tmphandle = tmpfile();
    
  }

  function document_end() {
    //    $this->doc .= ob_get_contents();
    //ob_end_clean();
    //    msg("Memory Usage doc close: ". memory_get_usage(), -1);
    $this->info['dokulinks'] = $this->dokulinks;
    if (is_null($this->_tmphandle))
      return $this->doc;
    //    if (function_exists('stream_get_contents')) {
    //      $this->doc = stream_get_contents($this->_tmphandle, -1, 0);
    //} else {
    //    msg("tmpfile mode");
    rewind($this->_tmphandle);
    while (!feof($this->_tmphandle))
      $this->doc .= fgets($this->_tmphandle);
    //}
    fclose($this->_tmphandle);
  }

  function header($text, $level) {
    global $conf;
    $levels = array(
		    1=>'dokutitlelevelone',
		    2=>'dokutitleleveltwo',
		    3=>'dokutitleleveltree',
		    4=>'dokutitlelevelfour',
		    5=>'dokutitlelevelfive',
		    );

    if ( isset($levels[$level]) ) {
      $token = $levels[$level];
    } else {
      $token = $levels[1];
    }
    $text = trim($text);
    $this->nlputcmdnl("$token{" . $this->_latexEntities($text) . "}");
    $cleanid = noNS(cleanID($text, TRUE));
    if ($conf['maxtoclevel'] >= $level) {   // Don't label too high
      $this->putcmd("label{" . md5($cleanid) . "}"); //label for links on headers
      $this->putnl("%% " . $cleanid);
    }
    if ($this->_num_titles == 0) { //label for links on document/page
      $this->label_document();
    }
    $this->_num_titles++;
    if ($level == 1) {//Reset footnotes used each chapter
      $this->_acronyms_used = array();
      $this->_footnote_index = 0;
    }
  }

  function section_open($level) {
    $this->_section_level = $level;
  }
  
  function section_close() {
  
  }

  function cdata($text) {
    $this->putent($text);
  }

  function p_open() {

  }

  function p_close() {
    $this->put(DOKU_LF.DOKU_LF);
  }

  function linebreak() {
    $this->put('\\\\ ');
  }

  function hr() {
    $this->nlputcmdnl("dokuhline");
    $this->putnl();
  }

  function strong_open() {
    $this->putcmd("dokubold{");
  }

  function strong_close() {
    $this->put('}');
  }

  function emphasis_open() {
    $this->putcmd("dokuitalic{");
  }

  function emphasis_close() {
    $this->put('}');
  }

  function underline_open() {
    $this->putcmd("dokuunderline{");
    $this->state['format'] = 1;
  }

  function underline_close() {
    $this->state['format'] = 0;
    $this->put('}');
  }

  function monospace_open() {
    $this->putcmd("dokumonospace{");
    $this->state['format'] = 1;
  }

  function monospace_close() {
    $this->state['format'] = 0;
    $this->put('}');
  }

  function subscript_open() {
    $this->putcmd("dokusubscript{");
  }

  function subscript_close() {
    $this->put('}');
  }

  function superscript_open() {
    $this->putcmd("dokusupscript{");
  }

  function superscript_close() {
    $this->put('}');
  }

  function deleted_open() {
    $this->putcmd("dokuoverline{");
  }

  function deleted_close() {
    $this->put('}');
  }

  function footnote_open() {
    $this->_footnote_index++;
    $this->putcmd("dokufootnote{");
  }

  function footnote_close() {
    $this->put('}');
  }

  function footnotemark_open() {
    $this->putcmd("dokufootmark{");
  }

  function footnotemark_close() {
    $this->put('}');
  }

  /**
   * @TODO Problem here with nested lists
   */
  function listu_open() {
    $this->nlputcmdnl("begin{itemize}");  //need to overload that 
  }

  function listu_close() {
    $this->putcmdnl("end{itemize}");
  }

  function listo_open() {
    $this->nlputcmd("begin{enumerate}");  //need to overload that 
  }

  function listo_close() {
    $this->putcmdnl("end{enumerate}");
  }

  function listitem_open($level) {
    $this->putcmd("dokuitem "); 
  }

  function listitem_close() {
    $this->putnl(); 
  }

  function listcontent_open() {

  }
  
  function listcontent_close() {

  }
  
  function unformatted($text) {
    $this->cdata($text);
  }

  function php($text) {
    $this->code("\n<php>\n$text\n</php>\n", 'php'); //Need to do smth ?!?
  }

  function html($text) {
    $this->code("\n<html>\n$text\n</html>\n", 'html'); //Any Ideas ?
  }

  /**
   * Indent?
   */
  function preformatted($text) {
    $this->nlputcmdnl("small");
    $this->putcmdnl("begin{verbatimtab}");		//need overload
    $this->put(wordwrap(str_replace('verbatimtab', 'verbatim', 
				    $text), $this->info['wrapcodelength'], "\n"));
    $this->nlputcmdnl("end{verbatimtab}");		//need overload
    $this->putcmdnl("normalsize");
  }

  function file($text) {
    $this->preformatted($text);
  }

  /**
   * Problem here with nested quotes
   */
  function quote_open() {
    $this->_quote_level++;
    $this->putcmd_protect("dokuquoting");
  }

  function quote_close() {
    $this->_quote_level--;
    if ($this->_quote_level == 0) {
      $this->putnl();
      $this->putnl();
    }
  }

  function code($text, $lang = NULL) {
    if ( !$lang ) {
	$this->preformatted($text);
    } else {
      switch ($lang) { //Latex syntax hightlight is quite old...
      case "shell":
	$lang = "sh";
      case "bash":
	$lang = "sh";
      case "latex":
	$lang = "TeX";
      }
      $this->nlputcmdnl("lstset{language=$lang}");	//need overload
      $this->putcmd("begin{lstlisting}");		//need overload
      $this->put(wordwrap($text, $this->info['wrapcodelength'], DOKU_LF));
      $this->nlputcmdnl("end{lstlisting}");		//need overload
    }
  }

  function acronym($acronym) {
    $this->put($acronym);
    if (!isset($this->_acronyms_used[$this->acronyms[$acronym]])) {
      $this->footnote_open();
      $this->putent($this->acronyms[$acronym], 1);
      $this->footnote_close();
      $this->_acronyms_used[$this->acronyms[$acronym]] = $this->_footnote_index;
    } else {
      $this->footnotemark_open();
      $this->put($this->_acronyms_used[$this->acronyms[$acronym]]);
      $this->footnotemark_close();
    }
  }

  function smiley($smiley) {
    if ( array_key_exists($smiley, $this->smileys) ) {
      if (!(isset($this->smileys_ps[$smiley]) 
	    && @file_exists($this->smileys_ps[$smiley]))) {
	$img = new TexItImage(DOKU_INC . 'lib/images/smileys/'. $this->smileys[$smiley]);
	if ($img->is_error) {
	  $this->unformatted('img error'.DOKU_INC.'lib/images/smileys/'. $this->smileys[$smiley]);    
	  return ;
	} 
	$filename = $img->get_output_filename();
	$this->smileys_ps[$smiley] = $filename;
      }
      if ($this->smileys_ps[$smiley] == '') 
	{
	  $this->putent($smiley);
	  return;
	}
      $this->putcmd("includegraphics[height=1em]{"); // need config for that
      $this->put($this->smileys_ps[$smiley]);
      $this->put("}");
    } else {
      $this->put($smiley);
    }
  }

  function wordblock($word) {
    $this->put($word);
  }

  function entity($entity) {
    $this->put($this->latexentities[$entity], 1);
  }

  // 640x480 ($x=640, $y=480)
  function multiplyentity($x, $y) {
    if ($this->_current_table_mode == 'table_analyse') { //try think something better
      $this->cdata($x . "x" . $y);
      return;
    }
    $this->put($x);
    $this->put("{\\texttimes}", 1); //Need Overload
    $this->put($y);
  }

  function singlequoteopening() {
    $this->put("'");
  }

  function singlequoteclosing() {
    $this->put("'");
  }

  function apostrophe() {
    $this->singlequoteopening();
  }

  function doublequoteopening() {
    $this->put('"');
  }

  function doublequoteclosing() {
    $this->put('"');
  }

  // $link like 'SomePage'
  function camelcaselink($link) {
    $this->put($link);
  }

  function locallink($hash, $name = NULL) {
    global $ID;
    list($page, $section) = split('#', $hash, 2);
    //    $md5 = md5(cleanID($section, TRUE));
    $cleanid = noNS(cleanID($hash, TRUE));
    $md5 = md5($cleanid);
    $name  = $this->_getLinkTitle($name, $hash, $isImage);
    $hash  = $this->_headerToLink($hash);
    array_push($this->dokulinks, array('id' => $hash, 'name' => $name, 'type' => 'local', ));
    $this->putcmd('hyperref[' . $md5 . ']{');
    $this->putent($name);
    $this->put('}');
    //    $this->putnl('%% '. $cleanid);
  }
  

  function internallink($id, $name = NULL, $search=NULL, $returnonly=false) {
    global $ID;
    // default name is based on $id as given
    $default = $this->_simpleTitle($id);
    // now first resolve and clean up the $id
    $orgname = $name;
    resolve_pageid(getNS($ID),$id,$exists);
    $name = $this->_getLinkTitle($name, $default, $isImage, $id);
    list($page, $section) = split('#', $id, 2);
    if (isset($section))
      $cleanid = noNS(cleanID($section, TRUE));
    else
      $cleanid = noNS(cleanID($id, TRUE));

    $md5 = md5($cleanid);
    array_push($this->dokulinks, array('id' => $id, 'name' => $name, 
				       'type' => 'internal',
				       'hash' => $md5));
    $hash = NULL;
    $this->putcmd('hyperref[');
    //    if (is_null($hash)) 
    //      $this->put(str_replace('_', ' ', $id));
    //    else
    //      $this->put(str_replace('_', ' ', $hash));
    $this->put($md5);
    $this->put(']{');
    $this->putent($name);
    $this->put('}');
    //    $this->putnl('%% '. $cleanid);
  }
  

  // $link like 'wiki:syntax', $title could be an array (media)
  function internallink_old($link, $title = NULL) {
    $this->putent('[['.$link.'|'.$title.']]');
  }

  function internallink_xhtml($id, $name = NULL, $search=NULL, $returnonly=false) {
    global $conf;
    global $ID;
    // default name is based on $id as given
    $default = $this->_simpleTitle($id);
    // now first resolve and clean up the $id
    resolve_pageid(getNS($ID),$id,$exists);
    $name = $this->_getLinkTitle($name, $default, $isImage, $id);
    if ( !$isImage ) {
      if ( $exists ) {
	$class='wikilink1';
	// do some recurse
      } else {
	$class='wikilink2';
      }
    } else {
      $class='media';
    }

    //keep hash anchor
    list($id,$hash) = split('#',$id,2);
    if (isset($hash)) {
      $this->putcmd('href{' . $id . '#' . $hash . '}{' . $name . '}');
    } else {
      $this->putcmd('hyperlink{' . $id . '}{' . $name . '}');
    }
  }

  // $link is full URL with scheme, $title could be an array (media)
  function externallink($link, $title = NULL) {
    $title_org = $title;
    $title = $this->_getLinkTitle($title, $link, $isImage);
    if ($isImage) {
      //      $this->putnl('%% Title: ' . $title);
      //      $this->putcmd('href{' . $link . '}{');
      //      $this->putcmd('hyperimage{' . $title . '}{');
      $this->put($title);
//       if (isset($title_org['title']))
// 	$this->put($title_org['title']);
//       else
// 	$this->put($title_org['src']);
//      $this->put('}}');
    } else {
      if ( $title ) {
	$this->put($this->_formatLink(array('url' => $link, 
					    'title' => $title)));
	
//	$this->putcmd('href{'.$link.'}{');
//	$this->putent($title);
//	$this->put('}');
      } else {
	$this->putcmd('url{'.$link.'}');
      }
    } 
  }

  // $link is the original link - probably not much use
  // $wikiName is an indentifier for the wiki
  // $wikiUri is the URL fragment to append to some known URL
  function interwikilink($link, $title = NULL, $wikiName, $wikiUri) {
    if (is_array($title)) {
      $url = '';
      if ( isset($this->interwiki[$wikiName]) ) {
	$url = $this->interwiki[$wikiName];
      }
      $title['caption'] = $url.$wikiUri;
    }
    $linkname = $this->_getLinkTitle($title, $wikiUri, $isImage);
    if ( !$isImage ) {
      $class = preg_replace('/[^_\-a-z0-9]+/i','_',$wikiName);
      $imagefile = DOKU_INC . 'lib/images/interwiki/' . $class . '.gif';
      if (!(isset($this->interwiki_ps[$class]) 
	    && @file_exists($this->interwiki_ps[$class]))
	  && @file_exists($imagefile)) {
	$img = new TexItImage($imagefile);
	if ($img->is_error) {
	  msg('img error: '. $imagefile, -1);
	  $this->unformatted('img error: '. $imagefile);
	  return ;
	} 
	$filename = $img->get_output_filename();
	$this->interwiki_ps[$class] = $filename;
      }
      if (isset($this->interwiki_ps[$class])) {
	$this->putcmd("includegraphics[height=1em]{"); // need config for that
	$this->put($this->interwiki_ps[$class], 1);
	$this->put("}", 1);
      }
    } else {
      $this->put($linkname); //link is an image
##      $this->putcmd('breakup');
      return ;
    }
    if ( isset($this->interwiki[$wikiName]) ) {
      $url = $this->interwiki[$wikiName];
      $this->put($this->_formatLink(array('url' => $url, 
				'title' => $linkname)));
       
//       $this->putcmd('href{');
//       $this->put(str_replace('#', '\#', $url.$wikiUri), 1);
//       $this->put('}{', 1);
//       $this->put($linkname);
//        if ($title) {
// 	 $this->putent($title);
//        } else {
// 	 list($baselink, $name) = split('#', $link, 2);
// 	 if ($name) {
// 	   $this->putent($name);
// 	 } else {
// 	   list($baselink, $name) = split('>', $link, 2);
// 	   if ($name) 
// 	     $this->putent($name);
// 	   else 
// 	     $this->putent($link);
// 	 }
//       }
//       $this->put('}', 1);
    } else {
      $this->putcmd('url{');
      $this->put($link);
      $this->put('}', 1);
    }
    
  }

  // Link to file on users OS, $title could be an array (media)
  function filelink($link, $title = NULL) {
    array_push($this->dokulinks, array('id' => $link, 'name' => $title, 'type' => 'filelink'));
    $this->unformatted('[['.$link.'|'.$title.']]');
  }

  // Link to a Windows share, , $title could be an array (media)
  function windowssharelink($link, $title = NULL) {
    $this->unformatted('[['.$link.'|'.$title.']]');
  }

  function emaillink($address, $title = NULL) {
    $this->putent($address);
  }

  // @TODO
  function internalmedialink (
			      $src,$title=NULL,$align=NULL,$width=NULL,$height=NULL,$cache=NULL
			      ) {
    
  }

  // @TODO
  function externalmedialink(
			     $src,$title=NULL,$align=NULL,$width=NULL,$height=NULL,$cache=NULL
			     ) {
    if ( $title ) {
      $this->doc .= '{{'.$src.'|'.$title.'}}';
    } else {
      $this->doc .= '{{'.$src.'}}';
    }
  }

  // Need analyse table before choose a table mode

    /**
     * Renders an RSS feed
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function rss ($url,$params){
        global $lang;
        global $conf;

        require_once(DOKU_INC.'inc/FeedParser.php');
        $feed = new FeedParser();
        $feed->feed_url($url);

        //disable warning while fetching
        if (!defined('DOKU_E_LEVEL')) { $elvl = error_reporting(E_ERROR); }
        $rc = $feed->init();
        if (!defined('DOKU_E_LEVEL')) { error_reporting($elvl); }

        //decide on start and end
        if($params['reverse']){
            $mod = -1;
            $start = $feed->get_item_quantity()-1;
            $end   = $start - ($params['max']);
            $end   = ($end < -1) ? -1 : $end;
        }else{
            $mod   = 1;
            $start = 0;
            $end   = $feed->get_item_quantity();
            $end   = ($end > $params['max']) ? $params['max'] : $end;;
        }

	//$this->doc .= '<ul class="rss">';
	$this->listu_open();
        if($rc){
            for ($x = $start; $x != $end; $x += $mod) {
                $item = $feed->get_item($x);
                //$this->doc .= '<li><div class="li">';
		$this->listitem_open(1);
                $this->externallink($item->get_permalink(),
                                    $item->get_title());
                if($params['author']){
                    $author = $item->get_author(0);
                    if($author){
                        $name = $author->get_name();
                        if(!$name) $name = $author->get_email();
                        if($name) 
			  $this->cdata(' '.$lang['by'].' '.$name);
			//$this->doc .= ' '.$lang['by'].' '.$name;
                    }
                }
                if($params['date']){
		  $this->cdata(' ('.$item->get_date($conf['dformat']).')');
		  //$this->doc .= ' ('.$item->get_date($conf['dformat']).')';
                }
                if($params['details']){
		  //$this->doc .= '<div class="detail">';
                    if($htmlok){
                        $this->cdata($item->get_description());
                    }else{
                        $this->cdata(strip_tags($item->get_description()));
                    }
                    //$this->doc .= '</div>';
                }

                //$this->doc .= '</div></li>';
		$this->listitem_close();
            }
        }else{
	  //$this->doc .= '<li><div class="li">';
	    $this->listitem_open(1);
	    $this->emphasis_open();
            //$this->doc .= '<em>'.$lang['rssfailed'].'</em>';
            $this->cdata($lang['rssfailed']);
	    $this->emphasis_close();
            $this->externallink($url);
//             if($conf['allowdebug']){
//                 $this->doc .= '<!--'.hsc($feed->error).'-->';
//             }
	    //  $this->doc .= '</div></li>';
	    $this->listitem_close();
        }
	//        $this->doc .= '</ul>';
	$this->listu_close();
    }

  function table_open($maxcols = NULL, $numrows = NULL) {
    $this->_current_table_mode = 'table_analyse';
    $table_mode = $this->_current_table_mode . '_open';
    $this->$table_mode($maxcols, $numrows);
  }

  function table_close() {
    $table_mode = $this->_current_table_mode . '_close';
    $this->$table_mode();
  }

  function tablerow_open() {
    $table_mode = $this->_current_table_mode . 'row_open';
    $this->$table_mode();

  }
  
  function tablerow_close() {
    $table_mode = $this->_current_table_mode . 'row_close';
    $this->$table_mode();
  }
  
  function tableheader_open($colspan = 1, $align = NULL) {
    $table_mode = $this->_current_table_mode . 'header_open';
    $this->$table_mode($colspan, $align);
  }

  function tableheader_close() {
    $table_mode = $this->_current_table_mode . 'header_close';
    $this->$table_mode();
  }

  function tablecell_open($colspan = 1, $align = NULL) {
    $table_mode = $this->_current_table_mode . 'cell_open';
    $this->$table_mode($colspan, $align);
  }

  function tablecell_close() {
    $table_mode = $this->_current_table_mode . 'cell_close';
    $this->$table_mode();
  }
  
  function table_analyse_open($maxcols = NULL, $numrows = NULL) {
    $this->_current_table_args = array($maxcols, $numrows);
    array_push($this->_current_table, 
	       array('type' => '_open', 
		     'args' => array($maxcols, $numrows)));
  }

  function table_analyse_size() { //calculate max aprox size of each cell 
    $longest_row = 0; 	 //and find the longest row
    $row_len = 0;
    $cell_len = 0;
    $cell_word = 0;
    $cell_text = '';
    $i = 0;
    $j = 0;
    $maxcols_len = NULL;
    $maxcols_text = NULL;
    $maxcols_word = NULL;
    //    msg("Memory Usage Table open: ". memory_get_usage(), -1);
    foreach ( $this->_current_table as $action ) {
      if ($action['type'] == 'put') {
	$row_len += $action['size'];
	$cell_len += $action['size'];
	$cell_text .= $action['text'];
	$cell_word = $action['word_size'];
      }
      if ($action['type'] == 'row_open') {
	$row_len = 0;
	$i++;
      }
      if ($action['type'] == 'row_close') {
	if ($longest_row < $row_len) 
	  $longest_row = $row_len;
	$row_len = 0;
	$j = 0;
      }
      if ($action['type'] == 'cell_open' || $action['type'] == 'header_open') {
	$j++;
      }
      if ($action['type'] == 'cell_close' || $action['type'] == 'header_close') {
	if ($maxcols_len[$j] < $cell_len) {
	  $maxcols_len[$j] = $cell_len;
	  $maxcols_word[$j] = $this->biggest_word_size($cell_text);
	  $maxcols_text[$j] = trim($cell_text);
	}
	$cell_len = 0;
	$cell_word = 0;
	$cell_text = '';
      }
    }
    //    msg("longest row: " . $longest_row);
    list($maxcols, $numrows) = $this->_current_table_args;
    $max_i = 0;
    $max = 0;
    for ($i = 1; $i <= $maxcols; $i++) { // Make some adjustment
      if ($max < $maxcols_len[$i]) {
	$max = $maxcols_len[$i];
	$max_i = $i;
      }
    }
    $maxcols_word[$max_i] = NULL;
    $this->_current_table_maxcols_size = $maxcols_word;
    return $longest_row;
  }

  function table_analyse_close() {
    // choose mode
    $i = 0;
    $j = 0;
    list($maxcols, $numrows) = $this->_current_table_args;
    $longest_row = $this->table_analyse_size();
    $this->_current_table_mode = 'tabular';
    //    msg("Memory Usage Table close: ". memory_get_usage(), -1);
    if ($numrows > $this->info['tablemaxrows']) { // need config
      if ($longest_row < $this->info['tablerowlength'])  //need config 
	$this->_current_table_mode = 'supertabular';
      else 
	$this->_current_table_mode = 'supertabular_landscape';
    } else {
      if ($longest_row < $this->info['tablerowlength']) { //need config 
	$this->_current_table_mode = 'tabular';
      } else {
	$this->_current_table_mode = 'tabularx';
      }
    }
    $this->putnl("%% Table analyse:");
    $this->putnl("%%    Numrows: " . $numrows);
    $this->putnl("%%    Longest row size: " . $longest_row);
    $this->putnl("%%    Choose table mode: " . $this->_current_table_mode);

    foreach ( $this->_current_table as $action ) { //Ouput analysed table
      if ($action['type'] == 'row_open') 
	$i++;
      if ($action['type'] == 'cell_open' || $action['type'] == 'header_open') {
	$j++;
	$this->_current_table_cols_size = $this->_current_table_maxcols_size[$j];
      }
      if ($action['type'] == 'row_close') 
	$j = 0;
      if ($action['type'] == 'put') {
	$this->put($action['text']);
	unset($action['text']);
      } else {
	$table_mode = $this->_current_table_mode . $action['type'];
	list($arg1, $arg2) = $action['args'];
	$this->$table_mode($arg1, $arg2);
      }
    }
    //msg("Memory Usage B: ". memory_get_usage(), -1);
    $table_mode = $this->_current_table_mode . '_close';
    $this->$table_mode($arg1, $arg2);
    $this->_current_table_mode = NULL;
    $this->_current_table_args = array();
    $this->_current_table = array();
    $this->_current_table_maxcols_size = array();
    $this->_current_table_cols_size = 0;
  }

  function table_analyserow_open() {
    array_push($this->_current_table, 
	       array('type' => 'row_open', 
		     'args' => array()));
  }
  
  function table_analyserow_close() {
    array_push($this->_current_table, 
	       array('type' => 'row_close', 
		     'args' => array()));
  }
  
  function table_analyseheader_open($colspan = 1, $align = NULL) {
    array_push($this->_current_table, 
	       array('type' => 'header_open', 
		     'args' => array($colspan, $align)));
  }

  function table_analyseheader_close() {
    array_push($this->_current_table, 
	       array('type' => 'header_close', 
		     'args' => array()));
  }

  function table_analysecell_open($colspan = 1, $align = NULL) {
    array_push($this->_current_table, 
	       array('type' => 'cell_open', 
		     'args' => array($colspan, $align)));
  }

  function table_analysecell_close() {
    array_push($this->_current_table, 
	       array('type' => 'cell_close', 
		     'args' => array()));
  }

  // Table tabular way
  function tabular_open($maxcols = NULL, $numrows = NULL) {
    $this->_current_tab_cols = 0;
    if ($this->info['usetablefigure'] == "on")
      $this->putcmdnl("begin{figure}[h]");
    else 
      $this->putcmdnl("vspace{0.8em}");
    $this->putcmd("begin{tabular}");
    $this->put("{");
    for ($i = 0; $i < $maxcols; $i++) {
      $this->put("l");
    }
    $this->putnl("}");
  }

  function tabular_close() {
    $this->putcmdnl("hline");
    $this->putcmdnl("end{tabular}");
    if ($this->info['usetablefigure'] == "on")
      $this->putcmdnl("end{figure}");
    else
      $this->putcmdnl("vspace{0.8em}");
    $this->putnl(); //Prevent Break 
  }

  function tabularrow_open() {
    $this->putcmdnl("hline");
    $this->_current_tab_cols = 0;
  }
  
  function tabularrow_close() {
    $this->linebreak();
    $this->putnl();
  }
  
  function tabularheader_open($colspan = 1, $align = NULL) {
    $this->tablecell_open($colspan, $align);
    $this->putcmd("dokuheadingstyle{");
    $this->putcmd("color{dokuheadingcolor}");
  }

  function tabularheader_close() {
    $this->putcmd("normalcolor");
    $this->put("}");
    $this->tablecell_close();
  }

  function tabularcell_open($colspan = 1, $align = NULL) {
    if ($this->_current_tab_cols)
      $this->put("&");
    if ($colspan > 0) {
      $this->_current_tab_colspan = 1;
      $this->putcmd("multicolumn{". $colspan . "}");
      $this->put("{");
      if ($this->_current_tab_cols == 0)
	$this->put("|");
      switch ($align) {
      case "right" :
	$this->put("r");
	break;
      case "left" :
	$this->put("l");
	break;
      case "center" :
	$this->put("c");
	break;
      default:
	$this->put("l");
      }
      $this->put("|}");
      $this->put("{");
    }
    $this->_current_tab_cols++;
  }

  function tabularcell_close() {
    if ($this->_current_tab_colspan = 1) {
      $this->_current_tab_colspan = 0;
      $this->put("}");
    }
  }

  // Table tabularx way

  function tabularx_open($maxcols = NULL, $numrows = NULL) {
    $this->_current_tab_cols = 0;
    if ($this->info['usetablefigure'] == "on")
      $this->putcmdnl("begin{figure}[h]");
    else
      $this->putcmdnl("vspace{0.8em}");
    $this->putcmd("begin{tabularx}{");
    $this->putcmd("dokutabularwidth");
    $this->put("}{|");
    for ($i = 1; $i <= $maxcols; $i++) {
      //      $this->put("X|");
      if (is_null($this->_current_table_maxcols_size[$i])
	  || $this->_current_table_maxcols_size[$i] == 0 
	  || $this->_current_table_maxcols_size[$i] > $this->info['biggesttableword']) 
	$this->put("X|");
      else
	$this->put("p{" . ($this->_current_table_maxcols_size[$i] * 0.80). "em}|");
    }
    $this->put("}");
    $this->put("%% ");
    for ($i = 1; $i <= $maxcols; $i++) {
      if (is_null($this->_current_table_maxcols_size[$i]))
	$this->put("X");
      else
	$this->put($this->_current_table_maxcols_size[$i]);
      $this->put(" | ");
    }
    $this->putnl();
  }

  function tabularx_close() {
    $this->putcmdnl("hline");
    $this->putcmdnl("end{tabularx}");
    if ($this->info['usetablefigure'] == "on")
      $this->putcmdnl("end{figure}");
    else
      $this->putcmdnl("vspace{0.8em}");
    $this->putnl(); //Prevent Break 
  }

  function tabularxrow_open() {
    $this->putcmdnl("hline");
    $this->_current_tab_cols = 0;
  }
  
  function tabularxrow_close() {
    $this->linebreak();
    $this->putnl();
  }
  
  function tabularxheader_open($colspan = 1, $align = NULL) {
    $this->tablecell_open($colspan, $align);
    $this->putcmd("dokuheadingstyle{");
    $this->putcmd("color{dokuheadingcolor}");
  }

  function tabularxheader_close() {
    $this->putcmd("normalcolor");
    $this->put("}");
    $this->tablecell_close();
  }

  function tabularxcell_open($colspan = 1, $align = NULL) {
    if ($this->_current_tab_cols)
      $this->put("&");
    if ($colspan > 1) {
      $this->_current_tab_colspan = 1;
      $this->putcmd("multicolumn{". $colspan . "}");
      $this->put("{");
      if ($this->_current_tab_cols == 0)
	$this->put("|");
      switch ($align) {
      case "right" :
	$this->put("r");
	break;
      case "left" :
	$this->put("l");
	break;
      case "center" :
	$this->put("c");
	break;
      default:
	$this->put("l");
      }
      $this->put("|}");
      $this->put("{");
    } else {
      $this->put("{");
    }
    $this->_current_tab_cols++;
  }

  function tabularxcell_close() {
    if ($this->_current_tab_colspan = 1) {
      $this->_current_tab_colspan = 0;
      $this->put("}");
    }
  }

  // Table supertabular way

  function supertabular_open($maxcols = NULL, $numrows = NULL) {
    $this->_current_tab_cols = 0;
    $this->putcmdnl('par');
    $this->putcmd('tablefirsthead{');
    $this->putcmdnl('hline}');
    $this->putcmd('tablehead{');
    $this->putcmd('hline');
    $this->putcmd('multicolumn{'.($maxcols).'}{|l|}{');
    $this->putcmd('dokusupertabularheadbreak{}');
    $this->put('}');
    $this->linebreak();
    $this->nlputcmd('hline');
    $this->putnl('}');
    $this->putcmd('tabletail{');
    $this->putcmd('hline');
    $this->putcmd('multicolumn{'.($maxcols).'}{|r|}{');
    $this->putcmd('dokusupertabulartailbreak{}');
    $this->put('}');
    $this->linebreak();
    $this->nlputcmd('hline');
    $this->putnl('}');
    $this->putcmdnl('tablelasttail{\hline}');
    $this->putcmdnl('par');
    //    $this->putcmd('begin{supertabular}{');
    //    for ($i = 0; $i < $maxcols; $i++) {
    //      $this->put('|p{'.((int)(14 / $maxcols * 1000)).'mm}');
    //    } // 14 c'est pour une feuille a4 avec les marges... il faut trouver mieux ca sux
    //    $this->putnl('|}'); 
    $this->putcmd("begin{supertabular}");
    $this->put("{");
    for ($i = 0; $i < $maxcols; $i++) {
      $this->put("l");
    }
    $this->putnl("}");
  }

  function supertabular_close() {
    $this->putcmdnl("hline");
    $this->putcmdnl("end{supertabular}");
    $this->putcmdnl('par');
    $this->putnl(); //Prevent Break 
  }

  function supertabularrow_open() {
    $this->putcmdnl("hline");
    $this->_current_tab_cols = 0;
  }
  
  function supertabularrow_close() {
    $this->linebreak();
    $this->putnl();
  }
  
  function supertabularheader_open($colspan = 1, $align = NULL) {
    $this->tablecell_open($colspan, $align);
    $this->putcmd("dokuheadingstyle{");
    $this->putcmd("color{dokuheadingcolor}");
  }

  function supertabularheader_close() {
    $this->putcmd("normalcolor");
    $this->put("}");
    $this->tablecell_close();
  }

  function supertabularcell_open($colspan = 1, $align = NULL) {
    if ($this->_current_tab_cols)
      $this->put("&");
    if ($colspan > 0) {
      $this->_current_tab_colspan = 1;
      $this->putcmd("multicolumn{". $colspan . "}");
      $this->put("{");
      if ($this->_current_tab_cols == 0)
	$this->put("|");
      switch ($align) {
      case "right" :
	$this->put("r");
	break;
      case "left" :
	$this->put("l");
	break;
      case "center" :
	$this->put("c");
	break;
      default:
	$this->put("l");
      }
      $this->put("|}");
      $this->put("{");
    }
    $this->_current_tab_cols++;
  }

  function supertabularcell_close() {
    if ($this->_current_tab_colspan = 1) {
      $this->_current_tab_colspan = 0;
      $this->put("}");
    }
  }


  // Table supertabular_landscape way

  function supertabular_landscape_open($maxcols = NULL, $numrows = NULL) {
    $this->putcmdnl("begin{landscape}");
    $this->supertabular_open($maxcols, $numrows);
  }

  function supertabular_landscape_close() {
    $this->putcmdnl("hline");
    $this->putcmdnl("end{supertabular}");
    $this->putcmdnl("end{landscape}");
  }

  function supertabular_landscaperow_open() {
    $this->supertabularrow_open();
  }
  
  function supertabular_landscaperow_close() {
    $this->supertabularrow_close();
  }
  
  function supertabular_landscapeheader_open($colspan = 1, $align = NULL) {
    $this->supertabularheader_open($colspan, $align);
  }

  function supertabular_landscapeheader_close() {
    $this->supertabularheader_close();
  }

  function supertabular_landscapecell_open($colspan = 1, $align = NULL) {
    $this->supertabularcell_open($colspan, $align);
  }

  function supertabular_landscapecell_close() {
    $this->supertabularcell_close();
  }

  function mediatops($filename, $title=NULL, $align=NULL, $width=NULL,
		     $height=NULL, $cache=NULL, $linking=NULL) {
    //    if ((is_null($align) || $align == 'center') && is_null($title)) {
    if ((is_null($align) || $align == 'center') || !is_null($title)) {
      return $this->mediatops_old($filename, $title, $align, $width, $height,
			   $cache, $linking);
    }
    $img = new TexItImage($filename);
    if ($img->is_error) {
      $this->unformatted('img '. $filename . '('. $mime . '=>' . $ext . ')'); 
      return ;
    }
    $ps_filename = $img->get_output_filename();
    if ($ps_filename == '') {
      $this->unformatted('img '. $filename);
      return;
    } else {
      $this->putcmd("begin{wrapfigure}{", -1);
      if ($align == "left")
	$align = 'l';
      else if ($align == "right")
	$align = 'r';
      else if ($align == "center")
	$align = 'c';
      else 
	$align = 'l';
      $this->putnl($align . "}{0pt}", -1);
      $this->putcmd("includegraphics", -1); // need config for that
      if ($width || $height) {
	$this->put("[", -1);
	if ($height) 
	  $this->put("height=" . $height. "pt", -1);
	if ($width && $height)
	  $this->put(",", -1);
	if ($width) 
	  $this->put("width=" . $width . "pt", -1);
	$this->put("]", -1);
      }
      $this->put("{", -1);
      $this->put($img->get_output_filename(), -1);
      $this->put("}\n", -1);
      if (isset($title)) {
	$this->putcmd("caption{", -1);
	if (substr($title, 0, 5) == 'http:') 
	  $this->_formatLink(array('url' => $title, 
				   'title' => $title,
				   'noflush' => 1));
	else 
	  $this->putent($title, -1);
	$this->putnl("}", -1);
      }
      $this->putcmdnl("end{wrapfigure}", -1);
      return $this->put_flush();
    }
  }

  function mediatops_old($filename, $title=NULL, $align=NULL, $width=NULL,
		     $height=NULL, $cache=NULL, $linking=NULL) {
    $img = new TexItImage($filename);
    if ($img->is_error) {
      $this->unformatted('img '. $filename . '('. $mime . '=>' . $ext . ')'); 
      return '';
    }
    $ps_filename = $img->get_output_filename();
    if ($ps_filename == '') {
      $this->unformatted('img '. $filename);
      return '';
    } else {
      if (!is_null($align) || !is_null($title)) {
	$this->putcmdnl("begin{figure*}[h]", -1);
	if ($align == "left") {
	  $align = 'flushleft';
	  $this->putcmdnl("raggedright", -1);
	}
	if ($align == "right") {
	  $align = 'flushright';
	  $this->putcmdnl("raggedleft", -1);
	}
	if ($align == "center")
	  $this->putcmdnl("centering", -1);
	else {
	  //	  $this->putcmdnl("begin{" . $align . "}", -1);
// 	  $this->putcmdnl("hfill", -1);
	}
      }
      $this->putcmd("includegraphics", -1); // need config for that
      if ($width || $height) {
	$this->put("[", -1);
	if ($height) 
	  $this->put("height=" . $height. "pt", -1);
	if ($width && $height)
	  $this->put(",", -1);
	if ($width) 
	  $this->put("width=" . $width . "pt", -1);
	$this->put("]", -1);
      }
      $this->put("{", -1);
      $this->put($img->get_output_filename(), -1);
      $this->put("}\n", -1);
      if (isset($title)) {
	$this->putcmd("caption{", -1);
	if (substr($title, 0, 5) == 'http:') 
	  $this->_formatLink(array('url' => $title, 
				   'title' => $title,
				   'noflush' => 1));
	else 
	  $this->putent($title, -1);
	$this->putnl("}", -1);
      }
      if (!is_null($align) || !is_null($title)) {
	// if ($align != 'center') {
// 	  $this->putcmdnl("end{" . $align. "}", -1);
// 	  $this->putcmdnl("hfill", -1);
// 	}
	$this->putcmdnl("end{figure*}", -1);
      }
      return $this->put_flush();
    }
  }

  function internalmedia ($src, $title=NULL, $align=NULL, $width=NULL,
			  $height=NULL, $cache=NULL, $linking=NULL) {
    global $conf;
    global $ID;
    $filename = mediaFN($src);
    resolve_mediaid(getNS($ID),$src, $exists);
    list($ext,$mime) = mimetype($src);
    if(substr($mime,0,5) == 'image') {
      $img = $this->mediatops($filename, $title, $align, $width,
		       $height, $cache, $linking);
      $this->put($img);
      return;
    }
    if ($title == NULL) 
      $title = basename($filename);
    array_push($this->dokulinks, array('id' => $filename , 'name' => $title, 'type' => 'file'));
    $this->putcmd('hyperref[');
    $this->put(md5($filename));
    $this->put(']{');
    $this->putent($title);
    $this->put('}');
    
    //    $this->unformatted('unkown '. $filename . '('. $mime . '=>' . $ext . ')');    
  }

  /**
   * Returns the wanted cachetime in seconds
   *
   * Resolves named constants
   *
   * @author  Andreas Gohr <andi@splitbrain.org>
   */
  function calc_cache($cache) {
    global $conf;
    
    if(strtolower($cache) == 'nocache') return 0; //never cache
    if(strtolower($cache) == 'recache') return $conf['cachetime']; //use standard cache
    return -1; //cache endless
  }
  
  /**
   * Download a remote file and return local filename
   *
   * returns false if download fails. Uses cached file if available and
   * wanted
   *
   * @author  Andreas Gohr <andi@splitbrain.org>
   * @author  Pavel Vitis <Pavel.Vitis@seznam.cz>
   */
  function get_from_URL($url,$ext,$cache) {
    global $conf;

    $local = getCacheName(strtolower($url),".media.$ext");
    $mtime = @filemtime($local); // 0 if not exists
    
    //decide if download needed:
    if( $cache == 0 ||                             // never cache
	($mtime != 0 && $cache != -1) ||           // exists but no endless cache
	($mtime == 0) ||                           // not exists
	($cache != -1 && $mtime < time()-$cache)   // expired
	) {
      if(io_download($url,$local)) {
        return $local;
      }else{
        return false;
      }
    }
    
    //if cache exists use it else
    if($mtime) return $local;
    
    //else return false
    return false;
  }


  /**
   * @todo don't add link for flash
   */
  function externalmedia ($src, $title=NULL, $align=NULL, $width=NULL,
			  $height=NULL, $cache=NULL, $linking=NULL) {
    global $conf;
    
    list($ext,$mime) = mimetype($src);
    if(substr($mime,0,5) == 'image') {
      $filename = $this->get_from_URL($src,$ext,$this->calc_cache($cache));
      if(!$filename) {
	//download failed - redirect to original URL
	//make default images.
	$this->unformatted('externalmedia dnl error: ' . $src . ' ext: ' . $ext. ' cache: '. $cache);
      } else {
	$img = $this->mediatops($filename, $title, $align, $width,
				$height, $cache, $linking);
	$this->put($img);
      }
      return;
    }else{
      // add file icons
      $this->unformatted('externalmedia ' . $src);
    }
      
    //output formatted
    if ($linking == 'nolink' || $noLink) $this->doc .= $link['name'];
    else $this->doc .= $this->_formatLink($link);
  }
  
  
  /**
   * Construct a title and handle images in titles
   *
   * @author Harry Fuecks <hfuecks@gmail.com>
   */

  function _getLinkTitle($title, $default, & $isImage, $id=NULL) {
    global $conf;
    
    $isImage = false;
    if ( is_null($title) ) {
      if ($conf['useheading'] && $id) {
	$heading = p_get_first_heading($id);
	if ($heading) {
	  return $this->_latexEntities($heading);
	}
      }
      return $this->_latexEntities($default);
    } else if ( is_string($title) ) {
      return $this->_latexEntities($title);
    } else if ( is_array($title) ) {
      $isImage = true;
      if (isset($title['caption'])) {
	$title['title'] = $title['caption'];
      } else {
	$title['title'] = $default;
      }
      $title['align'] = 'center';
      return $this->_imageTitle($title);
    }
  }

  function _xmlEntities($string) {
    return htmlspecialchars($string);
  }

  function _latexEntities($string,$ent=NULL) {
    static $doku_ent = NULL;
    static $latex_ent = NULL;
    if (is_null($ent)) {
      $ent = $this->latexentities;
    }
    if ($doku_ent == NULL && $latex_ent == NULL && is_array($ent)) {
      $doku_ent = array_keys($ent);
      $latex_ent = array_values($ent);
    }
    return str_replace($doku_ent, $latex_ent, $string);
  }    



  /**
   * Creates a linkid from a headline
   *
   * @param string  $title   The headline title
   * @param boolean $create  Create a new unique ID?
   * @author Andreas Gohr <andi@splitbrain.org>
   */
  function _headerToLink($title,$create=false) {
    $title = str_replace(':','',cleanID($title,true)); //force ASCII
    $title = ltrim($title,'0123456789._-');
    if(empty($title)) $title='section';

    if($create) {
      // make sure tiles are unique
      $num = '';
      while(in_array($title.$num,$this->headers)) {
	($num) ? $num++ : $num = 1;
      }
      $title = $title.$num;
      $this->headers[] = $title;
    }

    return $title;
  }


  /**
   * Renders internal and external media
   *
   * @author Andreas Gohr <andi@splitbrain.org>
   */
  function _media_test ($src, $title=NULL, $align=NULL, $width=NULL,
			$height=NULL, $cache=NULL) {

    $ret = '';
    list($ext,$mime) = mimetype($src);
    if(substr($mime,0,5) == 'image') {
      //add image tag
      $ret .= ml($src,array('w'=>$width,'h'=>$height,'cache'=>$cache));
    } elseif(!is_null($title)) {
      // well at least we have a title to display
      $ret .= $this->_xmlEntities($title);
    } else {
      // just show the sourcename
      $ret .= $this->_xmlEntities(noNS($src));
    }
    return $ret;
  }

  function _media($src, $title=NULL, $align=NULL, $width=NULL,
		   $height=NULL, $cache=NULL) {

    $ret = '';
    list($ext,$mime) = mimetype($src);
    if(substr($mime,0,5) == 'image') {
      $filename = mediaFN($src);
      if (!file_exists($filename)) {
	$filename = $this->get_from_URL($src,$ext,$this->calc_cache($cache));
	if(!$filename) {
	  $this->unformatted('externalmedia dnl error: ' . $src . ' ext: ' . $ext. ' cache: '. $cache);
	}
      }
      $ret .= $this->mediatops_old($filename, $title, $align, $width,
				   $height, $cache, $linking);
    
      //$ret .= '\\hyperimage{';
      //$ret .= ml($src,array('w'=>$width,'h'=>$height,'cache'=>$cache), true, '&', true);
      //$ret .= '}';

    }elseif($mime == 'application/x-shockwave-flash') {
      $ret .= '';

    }elseif(!is_null($title)) {
      // well at least we have a title to display
      $ret .= $this->_latexEntities($title);
    }else{
      // just show the sourcename
      $ret .= $this->latexEntities(noNS($src));
    }

    return $ret;
  }


  function _media_xhtml ($src, $title=NULL, $align=NULL, $width=NULL,
		   $height=NULL, $cache=NULL) {

    $ret = '';

    list($ext,$mime) = mimetype($src);
    if(substr($mime,0,5) == 'image') {
      //add image tag
      $ret .= '<img src="'.ml($src,array('w'=>$width,'h'=>$height,'cache'=>$cache)).'"';
      $ret .= ' class="media'.$align.'"';

      if (!is_null($title)) {
	$ret .= ' title="'.$this->_xmlEntities($title).'"';
	$ret .= ' alt="'.$this->_xmlEntities($title).'"';
      }elseif($ext == 'jpg' || $ext == 'jpeg') {
	//try to use the caption from IPTC/EXIF
	require_once(DOKU_INC.'inc/JpegMeta.php');
	$jpeg =& new JpegMeta(mediaFN($src));
	if($jpeg !== false) $cap = $jpeg->getTitle();
	if($cap) {
	  $ret .= ' title="'.$this->_xmlEntities($cap).'"';
	  $ret .= ' alt="'.$this->_xmlEntities($cap).'"';
	}
      }else{
	$ret .= ' alt=""';
      }

      if ( !is_null($width) )
	$ret .= ' width="'.$this->_xmlEntities($width).'"';

      if ( !is_null($height) )
	$ret .= ' height="'.$this->_xmlEntities($height).'"';

      $ret .= ' />';

    }elseif($mime == 'application/x-shockwave-flash') {
      $ret .= '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"'.
	' codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,40,0"';
      if ( !is_null($width) ) $ret .= ' width="'.$this->_xmlEntities($width).'"';
      if ( !is_null($height) ) $ret .= ' height="'.$this->_xmlEntities($height).'"';
      $ret .= '>'.DOKU_LF;
      $ret .= '<param name="movie" value="'.ml($src).'" />'.DOKU_LF;
      $ret .= '<param name="quality" value="high" />'.DOKU_LF;
      $ret .= '<embed src="'.ml($src).'"'.
	' quality="high"';
      if ( !is_null($width) ) $ret .= ' width="'.$this->_xmlEntities($width).'"';
      if ( !is_null($height) ) $ret .= ' height="'.$this->_xmlEntities($height).'"';
      $ret .= ' type="application/x-shockwave-flash"'.
	' pluginspage="http://www.macromedia.com/go/getflashplayer"></embed>'.DOKU_LF;
      $ret .= '</object>'.DOKU_LF;

    }elseif(!is_null($title)) {
      // well at least we have a title to display
      $ret .= $this->_xmlEntities($title);
    }else{
      // just show the sourcename
      $ret .= $this->_xmlEntities(noNS($src));
    }

    return $ret;
  }


  /**
   * Build a link
   *
   * Assembles all parts defined in $link returns HTML for the link
   *
   * @author Andreas Gohr <andi@splitbrain.org>
   */
  function _formatLink_xhtml($link) {
    //make sure the url is XHTML compliant (skip mailto)
    if(substr($link['url'],0,7) != 'mailto:') {
      $link['url'] = str_replace('&','&amp;',$link['url']);
      $link['url'] = str_replace('&amp;amp;','&amp;',$link['url']);
    }
    //remove double encodings in titles
    $link['title'] = str_replace('&amp;amp;','&amp;',$link['title']);

    // be sure there are no bad chars in url or title
    // (we can't do this for name because it can contain an img tag)
    $link['url']   = strtr($link['url'],array('>'=>'%3E','<'=>'%3C','"'=>'%22'));
    $link['title'] = strtr($link['title'],array('>'=>'&gt;','<'=>'&lt;','"'=>'&quot;'));

    $ret  = '';
    $ret .= $link['pre'];
    $ret .= '<a href="'.$link['url'].'"';
    if($link['class'])  $ret .= ' class="'.$link['class'].'"';
    if($link['target']) $ret .= ' target="'.$link['target'].'"';
    if($link['title'])  $ret .= ' title="'.$link['title'].'"';
    if($link['style'])  $ret .= ' style="'.$link['style'].'"';
    if($link['more'])   $ret .= ' '.$link['more'];
    $ret .= '>';
    $ret .= $link['name'];
    $ret .= '</a>';
    $ret .= $link['suf'];
    return $ret;
  }

  function _formatLink($link) {
    //make sure the url is XHTML compliant (skip mailto)
    if(substr($link['url'],0,7) != 'mailto:') {
      
    }

    $link['url'] = str_replace('#','\#',$link['url']);
    
    $this->putcmd('href{'.$link['url'].'}', -1);
    if (isset($link['title'])) {
      $this->put('{', -1);
      $this->putent($link['title'], -1);
      $this->put('}', -1);
    }
    
    if (isset($link['noflush']) && $link['noflush'] == 1)
      return;
    return $this->put_flush();
  }


  /**
   * Returns an HTML code for images used in link titles
   *
   * @todo Resolve namespace on internal images
   * @author Andreas Gohr <andi@splitbrain.org>
   */
  function _imageTitle($img) {
    return $this->_media($img['src'],
			 $img['title'],
			 $img['align'],
			 $img['width'],
			 $img['height'],
			 $img['cache']);
  }

  function _simpleTitle($name) {
    global $conf;

    if($conf['useslash']) {
      $nssep = '[:;/]';
    }else{
      $nssep = '[:;]';
    }
    $name = preg_replace('!.*'.$nssep.'!','',$name);
    //if there is a hash we use the ancor name only
    $name = preg_replace('!.*#!','',$name);
    return $name;
  }


  //latex utils

  function biggest_word_size($str) {
    $m = strlen($str) / 2;
    $a = 1;
    while ($a < $m) {
      $str = str_replace("  "," ",$str);
      $a++;
    }
    $b = explode(" ", $str);
    $max = 0;
    foreach ($b as $w) {
      $len = strlen($w);
      if ($max < $len) 
	$max = $len;
    }
    return $max;
  }
  
  function put_flush() {
    $ret = join('', $this->_tmp_put);
    $this->_tmp_put = array();
    return $ret;
  }

  function put($text, $mode=0) { 
    if ($mode == -1) {
      array_push($this->_tmp_put, $text);
      return ;
    }
    if ($this->_current_table_mode == 'table_analyse') {
      array_push($this->_current_table, 
		 array('type' => 'put', 
		       'text' => $text,
		       'size' => $mode == 0 ? strlen($text) : $mode
		       ));
      return;
    } 
    if (is_null($this->_tmphandle))
      $this->doc .= $text; 
    else 
      fwrite($this->_tmphandle, $text);
  }
  function putent($text, $mode=0) { 
    if ($mode != -1) 
      $mode = strlen($text);
    return $this->put($this->_latexEntities($text), $mode);
  }
  function putcmd($cmd, $mode=1) { 
    return $this->put('\\' . $cmd, $mode);
  }
  function putcmd_protect($cmd, $mode=1) { 
    return $this->put('{\\' . $cmd . '}', $mode);
  }
  function putcmdnl($cmd, $mode=1) { 
    $this->putcmd($cmd . DOKU_LF, $mode);
  }
  function nlputcmdnl($cmd) { 
    $this->putnl();
    $this->putcmd($cmd . DOKU_LF);
  }
  function nlputcmd($cmd) { 
    $this->putnl();
    $this->putcmd($cmd);
  }
  function putnl($text = NULL, $mode = 0) { 
    if (!is_null($text)) {
      $this->put($text, $mode);
    }
    $this->put(DOKU_LF, $mode);
  }
  function putmathcmd($cmd) { 
    $this->put('$'. $cmd . '$');
  }
  function putverb($text) { 
    if ($this->state['format'] == 0) 
      $this->putcmd("begin{verbatim}");
    $this->put($text);
    if ($this->state['format'] == 0) 
      $this->putcmd("end{verbatim}");
  } 

  function add_command($text) {
    $this->info['command_hook'] .= $text;
  }

  function add_footer($text) {
    $this->info['footer_hook'] .= $text;
  }

  function get_info() {
    return $this->info;
  }
  
  function get_clevel() { // for include plugins.
    return $this->_section_level;
  }

}


