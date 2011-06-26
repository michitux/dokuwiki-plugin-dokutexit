<?php
/**
 * texit Rendering Class
 * Copyright (C) 2006   Danjer <danjer@doudouke.org>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * --------------------------------------------------------------------
 * @author Danjer <danjer@doudouke.org>
 * @version v0.2
 * @package TeXitrender
 *
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'dokutexit/class.texitconfig.php');
require_once(DOKU_PLUGIN.'dokutexit/latex.php');

class texitrender_plugin_dokutexit {
  // =======================================================================
  // Variable Definitions
  // =======================================================================
  var $_data = array(
		     'begin' => NULL,
		     'command' => NULL,
		     'document' => NULL,
		     'footer' => NULL
		     );
  var $_inputs = NULL;  
  var $_dokutexit_conf = array(
			       'recurse' => 'off',
			       'recurse_file' => 'off',
			       'usetablefigure' => 'off',
			       'tablerowlength' => 80,
			       'tablemaxrows' => 30,
			       'wrapcodelength' => 100,
			       'biggesttableword' => 15,
			       'mode' => 'latex',
			       'zipsources' => false,
			       'dnl_button' => true,
			       'force_clean_up' => false
			       );

  var $_namespaces = array();
  var $_p_get_count = 0;
  var $_p_get_parsermodes = null;
  var $_Parser = null;
  // =======================================================================
  // constructor
  // =======================================================================
  
  /**
   * Initializes the class
   *
   * @param $pageid
   */
  function texitrender_plugin_dokutexit($pageid = NULL) {
    global $conf;
    global $_dokutexit_conf;
    if ($pageid != NULL) {
      $this->_pageid = $pageid;
      $this->_doku_file = wikiFN($this->_pageid);
      $this->pdf = $this->buildfilelink('pdf');
      $this->zip = $this->buildfilelink('zip');
      $this->latex = $this->buildfilelink('tex', 'texit:tmp:');
      $this->latexlog = $this->buildfilelink('log', 'texit:tmp:');
    }
    if (isset($conf['plugin']['dokutexit'])) {
      if (isset($conf['plugin']['dokutexit']['zipsources']))
	$this->_dokutexit_conf['zipsources'] = 
	  $conf['plugin']['dokutexit']['zipsources'];
      if (isset($conf['plugin']['dokutexit']['dnl_button']))
	$this->_dokutexit_conf['dnl_button'] = 
	  $conf['plugin']['dokutexit']['dnl_button'];
      if (isset($conf['plugin']['dokutexit']['force_clean_up']))
	$this->_dokutexit_conf['force_clean_up'] = 
	  $conf['plugin']['dokutexit']['force_clean_up'];
      if (isset($conf['plugin']['dokutexit']['latex_mode']))
	$this->_dokutexit_conf['mode'] = 
	  $conf['plugin']['dokutexit']['latex_mode'];
      if (isset($conf['plugin']['dokutexit']['latex_path']))
	$this->_dokutexit_conf['path'] = 
	  $conf['plugin']['dokutexit']['latex_path'];
    }
    $_dokutexit_conf = $this->_dokutexit_conf;
  }

  // ========================================================================
  // public functions
  // ========================================================================

  function add_inputs($data) {
    if (is_null($this->_inputs)) {
      $this->_inputs = $data;
    } else {
      $this->_inputs .= $data;
    }
  } 

  function render_inputs() {
    if (is_null($this->_inputs))
      return '';
    return $this->_inputs;
  }

  /**
   * wraps a minimalistic texit document and returns a string
   * containing the whole document as string.
   *
   * @returns minimalistic texit document containing the form
   */
  function render_cleanup($force=0) {
    $string = '<form class="button" method="post" action="';
    $string .= wl($this->_pageid) . '"';
    $string .= '><div class="no">';
    $string .= $this->render_inputs();
    $string .= '<input type="submit" value="';
    if ($force) 
      $string .= 'Force clean up';
    else 
      $string .= 'Clean up';
    $string .= '" class="button" />';
    if ($force) 
      $string .= '<input type="hidden" name="cleanup" value="force" />';
    else
      $string .= '<input type="hidden" name="cleanup" value="pdf" />';
    $string .= '</div></form>';
    return ($string);
  }


  function render_xhtml_button($file, $button_text) {
    $filename = $file['file'];
    $linkname = $file['link'];
    $string = '';
    if (is_readable($filename)) {
      $url = $linkname;
      $string .= '<form class="button" method="post" action="'. $url ;
      $string .= '"><div class="no"><input type="submit" value="';
      $string .= $button_text;
      $string .= '" class="button" />';
      $string .= $this->render_inputs();
      $string .= '</div></form>';
    }
    return $string;
  }
  
  function render($type = 'pdf') {
    $url = $this->pdf['link'];
    if (isset($_REQUEST['ebook_namespaces'])) {
      $this->_namespaces = split("\n", trim($_REQUEST['ebook_namespaces']));
    }
    if (isset($_REQUEST['cleanup'])) {
      if ($_REQUEST['cleanup'] == 'force')
	$this->remove_outfile(1);
      else 
	$this->remove_outfile();
      $this->clean_up(1);
    }
    if ($this->generate($_REQUEST['generate'])) {
      if (isset($this->_error_log) && $this->_error_log != '') {
	$string .=  '<pre class="file">'. $this->_error_log . '</pre>';
	$string .= $this->render_cleanup();
	$string .= $this->render_xhtml_button($this->latexlog, 
					      'View LaTeX log');
	$string .= $this->render_xhtml_button($this->latex, 
					      'View LaTeX sources');
	return $string;
      }
    }
    if (is_readable($this->pdf['file']) ) {
      if ($this->_dokutexit_conf['dnl_button']) {
	$string .= '<form class="button" method="post" action="'. $url ;
	$string .= '"><div class="no"><input type="submit" value="';
	$string .= 'Download as PDF';
	$string .= '" class="button" /></div></form>';
      }
      if ($this->_dokutexit_conf['force_clean_up']) {
	$string .= $this->render_cleanup(1);
      }
    } else {
      $string .= '<form class="button" method="post"';// action="'. $url ;
      $string .= ' action="'. wl($this->_pageid) .'"><div class="no">';
      $string .= '<input type="submit" value="';
      $string .= 'Generate PDF';
      $string .= '" class="button" />';
      $string .= '<input type="hidden" name="generate" value="'. $type .'" />';
      $string .= $this->render_inputs();
      $string .= '</div></form>';
    }
    if (is_readable($this->zip['file']) 
	&& $this->_dokutexit_conf['zipsources']) {
	$string .= $this->render_xhtml_button($this->zip, 
					      'Download LaTeX sources as ZIP');
    } 
    return $string;
  }

  function remove_outfile($force=0) {
    if ( is_readable($this->pdf['file']) ) {
      if (filemtime($this->pdf['file']) > filemtime($this->_doku_file) && !$force)
	return false;
      unlink($this->pdf['file']);    
      @unlink($this->zip['file']);
      return true;
    }
    return false;
  }

  function add_data($state, $data) {
    $array = preg_split("/\r?\n/", trim($data));
    $this->remove_outfile();
    if (!is_array($this->_data[$state])) {
      $this->_data[$state] = $array;
      return true;
    }
    array_push($this->_data[$state], $array);
    return false;
  }

  function docache() {
    if ($this->_dokutexit_conf['force_clean_up'])
      return false;
    if (isset($_REQUEST['generate'])) {
      return false;
    }
    if (isset($_REQUEST['cleanup'])) {
      return false;
    }
    return $this->pdf_exist();
  }

  function pdf_exist() {
    if (is_readable($this->pdf['file'])) {
      return true;
    }
    return false;
  }
  // =========================================================================
  // private method
  // =========================================================================

  function generate($type) {
    $this->_Parser = null;
    if (!isset($type))
      return false;
    if ($type == 'pdf') {
      //       msg("Memory Usage: ". memory_get_usage(), -1);
      $this->generate_pdf();
      return true;
    } 
    if ($type == 'ebook') {
      //msg("Generate Ebook");
      $this->generate_ebook();
      return true;
    }
    return true;
  }

  function clean_up($lvl=0) {
    //$log = exec("rm $tmp_dir/texit*.ps", $output, $ret);
    if ($lvl >= 1) {
      @unlink($this->latex['file']);
    }
    @unlink($this->latexlog['file']);
    @unlink(mediaFN('texit:tmp:'. $this->_pageid . '.out'));
    @unlink(mediaFN('texit:tmp:'. $this->_pageid . '.aux'));
    @unlink(mediaFN('texit:tmp:'. $this->_pageid . '.toc'));
    @unlink(mediaFN('texit:tmp:'. $this->_pageid . '.dvi'));
    @unlink(mediaFN('texit:tmp:'. $this->_pageid . '.pdf'));
    //Cleaning plugins files
    $TeXitImage_glob['plugin_list'] = array();
    $images = new TexItImage();
    $images->clean_up();
  }


  function zipsources() {
    global $TeXitImage_glob;
    include_once("createzipfile.inc.php");
    $zip = new createZip;  
    $dir = $this->_pageid . "/";
    $zip->addDirectory($dir);
    $locale = array();
    $portable = array();
    $zip->addFile(rawWiki($this->_pageid), $dir . $this->_pageid . '.txt');
    if (isset($TeXitImage_glob['list'])) {
      foreach ($TeXitImage_glob['list'] as $file) {
	$zip->addFile(io_readFile($file), 
		      $dir . basename($file));
	array_push($locale, $file);
	array_push($portable, basename($file));
      }
    }
    if (isset($TeXitImage_glob['plugin_list'])) {
      foreach ($TeXitImage_glob['plugin_list'] as $file) {
	$zip->addFile(io_readFile($file), 
		      $dir . basename($file));
	array_push($locale, $file);
	array_push($portable, basename($file));
      }
    }
    $zip->addFile(str_replace($locale,$portable,
			      io_readFile($this->latex['file'])), 
		  $dir . basename($this->latex['file']));
    io_saveFile($this->zip['file'], $zip->getZippedfile());
  }


  function pdffromdvi() {
    $tmp_dir = $this->_tmp_dir;
    if (eregi('WIN',PHP_OS)) {
      if ($this->_dokutexit_conf['path']) {
	$cmdline = $this->_dokutexit_conf['path'] . DIRECTORY_SEPARATOR;
      } else {
	$cmdline = '';
      }
      $cmdline .= "dvipdfm ";
      $cmdline .= "-o " . $this->pdf['file'] . " ";
      $cmdline .= mediaFN('texit:tmp:'. $this->_pageid . '.dvi');
    } else {
      if ($this->_dokutexit_conf['path']) {
	$cmdline = $this->_dokutexit_conf['path'] . DIRECTORY_SEPARATOR;
      } else {
	$cmdline = '';
      }
      $cmdline .= "dvipdf ";
      $cmdline .= mediaFN('texit:tmp:'. $this->_pageid . '.dvi') . " ";
      $cmdline .= $this->pdf['file'] . " ";
    }
      $log = @exec($cmdline, $output, $ret);
    if ($ret) {
      $this->_error_log = "cmdline: " . $cmdline . "\n";
      $this->_error_log .= "ret: " . $ret . "\n";
      $this->_error_log .= implode("\n", $output);
    }
    if (!filesize($this->pdf['file'])) {
      $this->_error_log .= "\nError while exec dvipdf\n";
    }
  }

  function copypdftomedia() {
    $tmp_dir = $this->_tmp_dir;
    copy(mediaFN('texit:tmp:'. $this->_pageid . '.pdf'),
	 $this->pdf['file']);
    if (!filesize($this->pdf['file'])) {
      $this->_error_log .= "\nError while exec dvipdf\n";
    }
  }

  function generate_latex_info() {
    global $_dokutexit_conf;
    $latex = & new Doku_Renderer_latex;
    $latex->latexentities = $this->getLatexEntities(); 
    $info = "\n";
    foreach ( $this->_data['info'] as $line ) {
      list($key,$data) = split('=',$line,2);
      $hash[$key] = $data;
    } 
    $info .= "\\hypersetup{\n";
    foreach ( array('title', 'author', 'subject', 'keywords') as $section) {
      if (isset($hash[$section])) {
	$info .= 'pdf' . $section . ' = {' . $hash[$section] . "},\n";
      }
    }
    $info .= "pdfcreator = {DokuTeXit},\n";
    $info .= "pdfproducer = {dokuwiki + TeXit + ";
    if ($this->_dokutexit_conf['mode'] == "pdflatex") 
      $info .= $this->_dokutexit_conf['mode'] . "}\n";
      else
	$info .= $this->_dokutexit_conf['mode'] . " + dvipdf}\n";
    $info .= "}\n";
    if (isset($hash['title'])) {
      $info .= '\\title{' . $latex->_latexEntities($hash['title']) . "}\n";
    } else {
      $info .= '\\title{' . "title}\n";
    }
    if (isset($hash['author'])) {
      $info .= '\\author{' . $latex->_latexEntities($hash['author']) . "}\n";
    } 
    if (isset($hash['date'])) {
      $info .= '\\date{' . $latex->_latexEntities($hash['date']) . "}\n";
    } 
    if (isset($hash['backgroundtext'])) {
      if (strlen($hash['backgroundtext']) && $hash['backgroundtext'] != 'off') {
	$info .= '\\dokubackground{';
	$info .= $latex->_latexEntities($hash['backgroundtext']) . "}\n";
      }
    } else {
      $info .= '\\dokubackground{'. $latex->_latexEntities(DOKU_URL) . "}\n";
    } 
    foreach ( array_keys($this->_dokutexit_conf) as $val ) {
      if (isset($hash[$val])) {
	$this->_dokutexit_conf[$val] = $hash[$val];
      } 
    }
    $_dokutexit_conf = $this->_dokutexit_conf;
    return $info;
  }

  function generate_tex() {
    $tex_doc = array();
    $error = 0;
    $begin = new texitConfig('begin');
    $cmd = new texitConfig('command');
    $doc = new texitConfig('document');
    $foot = new texitConfig('footer');
    $begin_doc = $begin->read();
    if ($begin->is_error())
      $error = 1;
    $cmd_doc .= $cmd->read();
    if ($cmd->is_error())
      $error = 1;
    if (!$error) {
      $info_doc .= $this->generate_latex_info();
    }
    $tex_doc[] = $doc->read();
    if ($doc->is_error())
      $error = 1;
    if (!$error) {
      $latex = $this->p_locale_latex();
      foreach ($latex as $part) {
	array_push($tex_doc, $part);
      }      
    }
    if (isset($this->_dokutexit_conf['command_hook']))
      $cmd_doc .= $this->_dokutexit_conf['command_hook'];
    if (isset($this->_dokutexit_conf['foot_hook']))
      $tex_doc[] = $this->_dokutexit_conf['foot_hook'];
    $tex_doc[] = $foot->read();
    if ($foot->is_error())
      $error = 1;
    if (!$error) {
      array_unshift($tex_doc, $begin_doc, $cmd_doc, $info_doc);
      return $tex_doc;
    }
    return NULL;
  }

  function run_latex($latexdocument = NULL) {
    //     msg("Memory Usage A: ". memory_get_usage(), -1);
    if (is_null($latexdocument))
      return false;
    @unlink($this->latex['file']);
    io_makeFileDir($this->latex['file']);
    $ret = 0;
    $output = array();
    if ($latexdocument == NULL) {
      msg("DokuTeXit configuration is not saved", -1);
      $this->_error_log = "DokuTeXit configuration is not saved\n";
      return false;
    }
    foreach ($latexdocument as $subpart) {
      io_saveFile($this->latex['file'], $subpart, true);
    }
    unset($latexdocument);
    //     msg("Memory Usage B: ". memory_get_usage(), -1);
    chdir(dirname($this->latex['file']));
    if (isset($this->_dokutexit_conf['path']) 
	&& trim($this->_dokutexit_conf['path']) != "") {
      $cmdline = $this->_dokutexit_conf['path'] . '/';
    } else {
      $cmdline = '';
    }
    $cmdline .=  $this->_dokutexit_conf['mode'] . ' ';
    $cmdline .= $this->latex['file'] . ' 2>&1 ';
    $log = @exec($cmdline, $output, $ret);
    if ($ret) {
      $this->_error_log = "latex pass 1:\n";
      $this->_error_log .= "cmdline: " . $cmdline . "\n";
      $this->_error_log .= "ret: " . $ret . "\n";
      $this->_error_log .= implode("\n", $output);
      return false;
    }
    $log = @exec($cmdline, $output, $ret); //twice for toc
    if ($ret) {
      $this->_error_log = "latex pass 2:\n";
      $this->_error_log .= "cmdline: " . $cmdline . "\n";
      $this->_error_log .= "ret: " . $ret . "\n";
      $this->_error_log .= implode("\n", $output);
      return false;
    }
    io_makeFileDir($this->pdf['file']);
    //    msg("Memory Usage C: ". memory_get_usage(), -1);
    if ($this->_dokutexit_conf['mode'] != 'pdflatex')
      $this->pdffromdvi();
    else
      $this->copypdftomedia();
    if (strlen($this->_error_log)) {
      return false;
    }
    if ($this->_dokutexit_conf['zipsources'])
      $this->zipsources();
    $this->clean_up();
    return true;
  }
  
  function generate_pdf() {
    if (is_readable($this->pdf['file']))
      return true;
    //    $timing = microtime(true);
    $latexdocument = $this->generate_tex();
    //     $timing = microtime(true) - $timing;
    //     msg("Timing: ". $timing , 2);
    return $this->run_latex($latexdocument);
   }

  function generate_ebook_tex() {
    $tex_doc = array();
    $error = 0;
    $begin = new texitConfig('begin');
    $cmd = new texitConfig('command');
    $doc = new texitConfig('document');
    $foot = new texitConfig('footer');
    $begin_doc = $begin->read();
    if ($begin->is_error())
      $error = 1;
    $cmd_doc .= $cmd->read();
    if ($cmd->is_error())
      $error = 1;
    if (!$error) {
      $info_doc .= $this->generate_latex_info();
    }
    $tex_doc[] = $doc->read();
    if ($doc->is_error())
      $error = 1;
    if (!$error) {
      foreach ($this->_namespaces as $id) { 
	msg("Render ebook: " . $id, -1);
	$tex_doc[] = $this->p_locale_latex($id);
      }
    }
    if (isset($this->_dokutexit_conf['command_hook']))
      $cmd_doc .= $this->_dokutexit_conf['command_hook'];
    if (isset($this->_dokutexit_conf['footer_hook']))
      $tex_doc[] = $this->_dokutexit_conf['footer_hook'];
    $tex_doc[] = $foot->read();
    if ($foot->is_error())
      $error = 1;
    if (!$error)
      return join('', array($begin_doc, $cmd_doc, $info_doc), $tex_doc);
    return NULL;
  }

  function generate_ebook() {
    $latexdocument = $this->generate_ebook_tex();
    return $this->run_latex($latexdocument);
  }


  function p_get_instructions(&$text){
    // Dokuwiki Get instruction
    // Original parser use
    //    return p_get_instructions($text); 

    // Dokutexit Get instruction with low memory usage
    // Use only one parser object and little bit faster
    return $this->p_get_instructions_dokutexit($text); 
  }


/**
 * turns a page into a list of instructions
 *
 * @author Harry Fuecks <hfuecks@gmail.com>
 * @author Andreas Gohr <andi@splitbrain.org>
 */
  function p_get_instructions_dokutexit(&$text){
    
//     msg("Memory Usage Parser Start: ". memory_get_usage(), -1);
//     msg("Text: ". md5($text), 1);


    if (is_null($this->_p_get_parsermodes)) {
      $this->_p_get_parsermodes = p_get_parsermodes();
      //add modes to parser
    }

    if (is_null($this->_Parser)) {
      $this->_Parser = & new Doku_Parser();
    } 

    if (is_null($this->_Parser->Handler)) {
      $this->_Parser->Handler = & new Doku_Handler();
    }
  
    if (count($this->_Parser->modes) == 0) {
      foreach($this->_p_get_parsermodes as $mode){
	//	msg("Add Parser Mode: ". $mode['mode'], -1);
	$this->_Parser->addMode($mode['mode'],$mode['obj']);
      }
    }
    
    // Do the parsing
    //trigger_event('PARSER_WIKITEXT_PREPROCESS', $text);
    $p = $this->_Parser->parse($text);
    //  dbg($p);
    //$this->_p_get_parsermodes = null;
    //$this->_Parser->modes = array();
    $this->_Parser->Handler->calls = array();
    //$this->_Parser->Handler = null;
    //    $this->_Parser->Lexer = null;
    //$this->_Parser = null;
//     if ($p == false) {
//       msg("Parser error", -1);
//     }
//     echo "<pre>";
//     print_r($p);
//    echo "</pre>";
//     msg("p count: ". count($p), 2);
//     msg("Memory Usage Parser End: ". memory_get_usage(), -1);
    return $p;
  }
  
  function p_render_latex_text(& $text, & $info){
//     error_log("p_get_instructions[start]:" . $this->_p_get_count);
    $ins = $this->p_get_instructions($text);
    unset($text);
    $parsed = $this->p_render('latex', $ins, $info);
//     error_log("p_get_instructions[end]:" . $this->_p_get_count++);
    $ins = null;
    return $parsed;
  }

  function p_render_latex($id, & $info){
    $info['current_id'] = $id;
    $filename = wikiFN($id);
    if (!file_exists($filename)) {
      msg("$filename: Not exists", -1);
    }
    if (!is_readable($filename)) {
      msg("$filename: Can't read", -1);
    }
    $text = rawWiki($id);
    $parsed = $this->p_render_latex_text($text, $info);
    return $parsed;
  }

  function p_locale_latex($id=NULL){
    $latex = array();
    $do_recurse = 0;
    $do_recurse_file = 0;
    if (is_null($id)) {
      $id = $this->_pageid;
    }
    //fetch parsed locale
    $info = $this->_dokutexit_conf;
    $latex[] = $this->p_render_latex($id, $info);
    //    msg("Memory Sub Usage First: ". memory_get_usage(), -1);
    $this->_dokutexit_conf['command_hook'] = $info['command_hook'];
    $this->_dokutexit_conf['footer_hook'] = $info['footer_hook'];
    if ($this->_dokutexit_conf['recurse'] == "on"
	|| $this->_dokutexit_conf['recurse'] == "appendix"
	|| $this->_dokutexit_conf['recurse'] == "chapter") 
      $do_recurse = 1;
    if ($this->_dokutexit_conf['recurse_file'] == "on") 
      $do_recurse_file = 1;
    if ($do_recurse || $do_recurse_file) {
      if ($this->_dokutexit_conf['recurse'] != "chapter")
	$latex[] = "\n\\appendix\n";
      if (is_array($info['dokulinks']) ) {
	$hash = NULL;
// 	msg('dokulinks: ' . count($info['dokulinks']), 0);
	foreach ( $info['dokulinks'] as $link ) {
//	  msg("Memory Sub Usage: ". memory_get_usage(), -1);
// 	  msg('dokulink id: ' . $link['id'], 0);
// 	  msg('latex: ' . count($latex), 1);
	  if (!isset($hash[$link['id']]) && $link['id'] != $id) {
	    if ($do_recurse 
		&& ($link['type'] == 'local' || $link['type'] == 'internal')
		&& @file_exists(wikiFN($link['id']))) {
	      $subinfo = $this->_dokutexit_conf;
	      error_log("render_link " . $link['id']);
	      $latex[] = $this->p_render_latex($link['id'], $subinfo);
	      error_log("render_end " . $link['id']);
	    }
	    if ($do_recurse_file && $link['type'] == 'file' 
		&& @file_exists($link['id'])) {
	      $subinfo = $this->_dokutexit_conf;
	      $subinfo['current_file_id'] = $link['id'];
	      $text = '====== ' . $link['name'] . "======\n";
	      $text .= "<file>\n";
	      $text .= io_readFile($link['id']);
	      $text .= "</file>\n";
	      $latex[] = $this->p_render_latex_text($text, $subinfo);
	    }
	    $hash[$link['id']] = 1;
	  }
	}
      }
    }
//     error_log("latex_cnt:" . count($latex));
//     error_log("latex_cnt:" . count($latex));
//     msg("latex_cnt:" . count($latex), 2);
//     $i = 0;
//     foreach ($latex as $part) {
//       msg("latex[".$i."]_len:" . strlen($part), 2);
//       $i++;
//     }
    return $latex;
  }

  function p_render($mode,$instructions,& $info){
    if(is_null($instructions)) return '';
    //    msg("Memory Usage p_render start: ". memory_get_usage(), -1);
    //    require_once DOKU_INC."inc/parser/$mode.php";
    $rclass = "Doku_Renderer_$mode";
    if ( !class_exists($rclass) ) {
      trigger_error("Unable to resolve render class $rclass",E_USER_ERROR);
    }
    $Renderer = & new $rclass(); #FIXME any way to check for class existance?
								     
								     
    $Renderer->smileys = getSmileys();
    $Renderer->entities = getEntities();
    $Renderer->latexentities = $this->getLatexEntities();
    $Renderer->acronyms = getAcronyms();
    $Renderer->interwiki = getInterwiki();
    $Renderer->info = $info;

    // Loop through the instructions
    foreach ( $instructions as $instruction ) {
      // Execute the callback against the Renderer
      call_user_func_array(array(&$Renderer, $instruction[0]),$instruction[1]);
    }
    //set info array
    $info = $Renderer->info;
    //    msg("Memory Usage p_render end: ". memory_get_usage(), -1);
    // Return the output
    return $Renderer->doc;
  }
  
  function getLatexEntities() {
    static $latex_ent = NULL;
    if ( !$latex_ent ) {
      $cfg = new texitConfig('entities');
      if ($cfg->is_exist())
	$latex_ent = $this->confToHash($cfg->get_filename());
    }
    return $latex_ent;
  }


/**
 * Builds a hash from a configfile
 *
 * If $lower is set to true all hash keys are converted to
 * lower case.
 *
 * @author Harry Fuecks <hfuecks@gmail.com>
 * @author Andreas Gohr <andi@splitbrain.org>
 */
  function confToHash($file,$lower=false) {
    $conf = array();
    $lines = @file( $file );
    if ( !$lines ) return $conf;
    
    foreach ( $lines as $line ) {
      //ignore comments
      //      $line = preg_replace('/(?<!&)#.+$/','',$line);
      // I need '#' characters
      $line = trim($line);
      if(empty($line)) continue;
      $line = preg_split('/\s+/',$line,2);
      // Build the associative array
      if($lower){
	$conf[strtolower($line[0])] = $line[1];
      }else{
	$conf[$line[0]] = $line[1];
      }
    }
    return $conf;
  }

  function buildfilelink($ext, $prefix = '') {
    $ret['id'] = $prefix . $this->_pageid . '.' . $ext;
    $ret['file'] = mediaFN($ret['id']);
    $ret['link'] = ml($ret['id']);
    return $ret;
  }
}

?>
