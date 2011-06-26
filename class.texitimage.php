<?php
/**
 * TexIt image convert 
 * For better rendering PDF's this class convert any image to Postscript
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
 * @version v0.1
 * @package texitconfig
 *
 */
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
if(!defined('DOKU_PLUGIN_TMP')) define('DOKU_PLUGIN_TMP',DOKU_PLUGIN.'tmp/');
if(!defined('DOKU_PLUGIN_TEXIT_TMP')) define('DOKU_PLUGIN_TEXIT_TMP',DOKU_PLUGIN_TMP.'texitimages/');

class TexItImage {
  // =======================================================================
  // Variable Definitions
  // =======================================================================
  var $_error = NULL;
  var $_inputfile = NULL;
  var $_outputfile = NULL;
  var $_outimg_info = NULL;
  var $_tmp_dir = NULL;
  var $_tmp_pattern = 'texit';
  var $_convert_bin = '/usr/bin/convert';
  var $_convert_cmd = '-scene 0 -density 126 -transparent white';
  var $_identify_bin = '/usr/bin/identify';
  // =======================================================================
  // constructor
  // =======================================================================
  
  /**
   * Initializes the class
   *
   * @param formnumber
   */
  function texitImage($inputfile = NULL, $tmp_dir = DOKU_PLUGIN_TEXIT_TMP) {
    global $conf;
    global $php_errormsg;
    if (isset($conf['im_convert']) && $conf['im_convert'] != '') {
      $this->_convert_bin = $conf['im_convert'];
      $this->_identify_bin = dirname($conf['im_convert']) . '/' . 'identify';
    }
    $this->_tmp_dir = $tmp_dir ;
    if (!is_dir($this->_tmp_dir)) {
      if (!io_mkdir_p($this->_tmp_dir))
	$this->_error = array("mkdir_err", $this->_tmp_dir, $php_errormsg);
    }
    if (!is_null($inputfile) && !$this->is_error) {
      $this->_inputfile = $inputfile;
      if (file_exists($this->_convert_bin)) 
	$this->convert();
    }
  }

  // ========================================================================
  // public functions
  // ========================================================================
  function in_is_readable() {
    return is_readable($this->_inputfile);
  }

  function in_is_exist() {
    return file_exists($this->_inputfile);
  }

  function in_is_writeable() {
    return is_writeable($this->_inputfile);
  }

  function out_is_readable() {
    return is_readable($this->_outputfile);
  }

  function out_is_exist() {
    return file_exists($this->_outputfile);
  }

  function out_is_writeable() {
    return is_writeable($this->_outputfile);
  }

  function get_error() {
    return $this->_error;
  }

  function is_error() {
    return !is_null($this->_error);
  }

  function read() {
    global $php_errormsg;
    if (!$this->is_readable()) {
      $this->_error = array("read_err", $this->_outputfile, $php_errormsg);
      return '';
    }
    return io_readfile($this->_outputfile);
  }

  function write($data) {
    global $php_errormsg;
    if ($this->is_exist() && !$this->is_writeable()) {
      $this->_error = array("write_err", $this->_outputfile, $php_errormsg);
      return;
    }
    io_savefile($this->_outputfile, $data);
  }

  function delete() {
    global $php_errormsg;
    if (@unlink($this->_outputfile)) {
      $this->_error = array("unlink_err", $this->_outputfile, $php_errormsg);
    }
  }

  function get_output_filename() {
    global $TeXitImage_glob;
    if (isset($TeXitImage_glob['list'][$this->_inputfile])
	&& file_exists($TeXitImage_glob['list'][$this->_inputfile]))
      return $TeXitImage_glob['list'][$this->_inputfile];
    if (file_exists($this->_convert_bin)) {
      
      return $this->convert();
    }
    return '';
  }
 
  function convert() {
    global $php_errormsg;
    global $TeXitImage_glob;
    if (isset($TeXitImage_glob['list'][$this->_inputfile])
	&& file_exists($TeXitImage_glob['list'][$this->_inputfile]))
      return $TeXitImage_glob['list'][$this->_inputfile];
    if ($this->is_error())
      return ;
    if (!$this->in_is_readable()) {
      $this->_error = array("read_err", $this->_inputfile, $php_errormsg);
      return;
    }
    $this->make_output_filename();
    if (file_exists($this->_convert_bin)) { //convert bin is prefered
      if ($this->exec_image_convert()) {
	return NULL;
      }
    } else {  
      if (function_exists('imagick_readimage')) { //imagick is not fully implemented
	if ($this->do_image_convert()) {
	  return NULL;
	}
      }
    }
    if (!$this->out_is_readable()) {
      $this->check_animated();
      $this->_error = array("read_err", $this->_outputfile, $php_errormsg);
      return NULL;
    }
    $this->add_garbage();
    return $_outputfile;
  }

  function make_output_filename() {
    $this->_outputfile = tempnam($this->_tmp_dir, $this->_tmp_pattern);
    @unlink($this->_outputfile); //Ugly, don't you think ?
    if ($this->latex_mode())
      $this->_outputfile .= '.png';
    else
      $this->_outputfile .= '.ps';
  }

  function exec_image_convert() {
    if ($this->latex_mode())
      $cmdline = implode(' ', array($this->_convert_bin, $this->_convert_cmd, 
				    $this->_inputfile, 'png:'.$this->_outputfile));
    else
      $cmdline = implode(' ', array($this->_convert_bin, $this->_convert_cmd, 
				    $this->_inputfile, 'ps:'.$this->_outputfile));
    $log = &exec($cmdline, $output, $ret); 
    if ($ret) {
      $this->_error = array("exec_err", implode('\n', $output), $php_errormsg);
      msg("ImageMagick convert error[$ret]: " . implode('\n', $output), -1);
      return 1;
    }
    return 0;
  }
  


  function do_image_convert() {
    $imgfh = imagick_readimage($this->_inputfile);
    if ( imagick_iserror($imgfh) ) {
      msg("Imagick read error: ". imagick_failedreason($imgfh) . " => " .
      imagick_faileddescription($imgfh), -1);
      return true;
    }
    if ($this->latex_mode())
      imagick_convert($imgfh, "PNG") ;
    else
      imagick_convert($imgfh, "PS3") ;
    if ( !imagick_writeimage($imgfh, $this->_outputfile) ) {
      msg("Imagick write: ". imagick_failedreason($imgfh) . " => " .
      imagick_faileddescription($imgfh), -1);
      return true;
    }
    return false;
  }

  
  function add_garbage() {
    global $TeXitImage_glob;
    $TeXitImage_glob['list'][$this->_inputfile] = $this->_outputfile;
  }

  function clean_up() {
    global $TeXitImage_glob;
    if (!isset($TeXitImage_glob['list']))
      return;
    foreach ($TeXitImage_glob['list'] as $file) {
      @unlink($file);
    }
    $TeXitImage_glob['list'] = array();
    return;
  }
  
  function get_list() {
    return $TeXitImage_glob['list'];
  }

  function latex_mode () {
    global $_dokutexit_conf;
    if (isset($_dokutexit_conf) && $_dokutexit_conf['mode'] == 'pdflatex')
      return true;
    return false;
  }

  function check_animated () {
    $outfile = $this->_outputfile;
    $ext = 'ps';
    if ($this->latex_mode())
      $ext = 'png';
    $outfile = str_replace('.'. $ext, '', $outfile);
    for ($i = 0 ; file_exists($outfile . '-'. $i . '.' . $ext); $i++) {
      if ($i == 0)
	$this->_outputfile = $outfile . '-'. $i . '.' . $ext;
      else
	@unlink($outfile . '-'. $i . '.' . $ext);
    }
  }

  function __destruct() {

  }

  function exec_image_identify() {
    $cmdline = implode(' ', array($this->_identify_bin, $this->_outputfile));
    $log = &exec($cmdline, $output, $ret); 
    if ($ret) {
      $this->_error = array("exec_err", implode('\n', $output), $php_errormsg);
      msg("ImageMagick convert error[$ret]: " . implode('\n', $output), -1);
      return 1;
    }
    $this->_outimg_info = explode(' ', $output[0]);
    return 0;
  }

  function get_width() {
    if (is_null($this->_outimg_info)) //need something more smart
      $this->exec_image_identify();
    list($x, $y) = explode('x', $this->_outimg_info[2]);
    if ($this->_outimg_info[1] == 'PS') {
      return $x;
    }
    return $x * 0.58; //why ? magic ?
  }

}

?>
