<?php
/**
 * texit Configing Class
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

class texitConfig {
  var $_error = NULL;
  // =======================================================================
  // Variable Definitions
  // =======================================================================
  
  // =======================================================================
  // constructor
  // =======================================================================
  
  /**
   * Initializes the class
   *
   * @param formnumber
   */
  function texitConfig($name) {
    global $conf;
    global $php_errormsg;
    $this->_name = $name;
    $this->_dokutexit_path = DOKU_PLUGIN.'dokutexit/';
    $this->_settings_path = $this->_dokutexit_path . 'settings/';
    $this->_configfile = $this->_settings_path . $name . '.cfg';
    if (!is_dir($this->_settings_path)) {
      if (!io_mkdir_p($this->_settings_path)) {
	$this->_error = array("mkdir_err", 
			      $this->_settings_path, 
			      $php_errormsg);
      }
    }
  }

  // ========================================================================
  // public functions
  // ========================================================================
  function is_readable() {
    return is_readable($this->_configfile);
  }

  function is_exist() {
    return file_exists($this->_configfile);
  }

  function is_writeable() {
    return is_writeable($this->_configfile);
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
      $this->_error = array("read_err", $this->_configfile, $php_errormsg);
      msg("No config file: " . $this->_configfile, -1);
      return "";
    }
    return io_readfile($this->_configfile);
  }

  function write($data) {
    global $php_errormsg;
    if ($this->is_exist() && !$this->is_writeable()) {
      $this->_error = array("write_err", $this->_configfile, $php_errormsg);
      return;
    }
    io_savefile($this->_configfile, $data);
  }

  function delete() {
    if (@unlink($this->_configfile)) {
      $this->_error = array("unlink_err", $this->_configfile, $php_errormsg);
    }
  }

  function get_filename() {
    return $this->_configfile;
  }
  
}

?>
