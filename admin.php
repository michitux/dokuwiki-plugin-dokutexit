<?php
/*
 *  DokuTeXit Admin Plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Danjer <danjer@doudouke.org>
 * @date       2006-09-08
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');
require_once(DOKU_PLUGIN.'dokutexit/class.texitconfig.php');
require_once(DOKU_PLUGIN.'dokutexit/texitrender.php');

/**
 * All Dokutexit plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_dokutexit extends Dokuwiki_Admin_Plugin {
  var $_changed = false;          // set to true if configuration has altered
  var $_error = NULL;
  var $_error_data = '';
  var $_error_php = '';
  var $_data = array(
		     'begin' => NULL,
		     'command' => NULL,
		     'document' => NULL,
		     'footer' => NULL,
		     'entities' => NULL
		     );
  /**
   * return some info
   */
  function getInfo(){
    $ld = '$LastChangedDate: 2007-11-27 10:42:17 +0100 (Tue, 27 Nov 2007) $';
    $date = substr($ld, 18, 10);
    return array(
		 'author' => 'Danjer',
		 'email'  => 'Danjer@doudouke.org',
		 'date'   => $date,
		 'name'   => 'TeXit Configuration Manager',
		 'desc'   => 'Manage dokuTeXit configuration ',
		 'url'    => 'http://danjer.doudouke.org/tech/dokutexit'
		 );
  }

  function admin_plugin_dokutexit() {
    $this->setupLocale(true);
  }

  /**
   * return sort order for position in admin menu
   */
  function getMenuSort() {
    return 999;
  }

  /**
   * handle user request
   */
  function handle() {
    if (isset($_REQUEST['subpage'])) {
      //save it later...
      return;
    }
    if (isset($_REQUEST['save'])) {
      $this->save_config();
      $this->write_config();
    }
    elseif (isset($_REQUEST['revert'])) 
      $this->default_config();
  }

  /**
   * output appropriate html
   */
  function latex($id) {
    $texit = new texitrender_plugin_dokutexit($id);
    io_saveFile('/tmp/test.tex', $texit->p_locale_latex());
  }

  function html() {
    //$this->latex('wiki:syntax'); //Debug Work Remove It
    $this->load_config();
    if (isset($_REQUEST['subpage'])) {
      $subpage = $_REQUEST['subpage'] . '_html';
      $this->$subpage();
      return;
    }

    $this->default_html();
  }

  function default_html() {
    global $lang;
    global $ID;

    print $this->locale_xhtml('intro');
    ptln('<div id="dokutexit__manager">');
    if ($this->_config->locked)
      ptln('<div class="info">'.$this->getLang('locked').'</div>');
    elseif (!is_null($this->_error)) {
      $err = $this->getLang($this->_error) . $this->_error_data;
      // Inefficient... $php_errormsg troubles
      // if (!is_null($this->_error_php)) {
      // 	$err .= "\n";
      // 	$err .= $this->_error_php;
      // }
      ptln('<div class="error"><pre>'. $err .'</pre></div><br>');
    }
    elseif ($this->_changed) {
      ptln('<div class="success">'.$this->getLang('updated') .'</div><br>');
    }
    ptln('<form action="'.wl($ID).'" method="post">');
    ptln('  <input type="submit" name="submit" class="button" value="'.$this->lang['texit_btn_save'].'" accesskey="s" />');
    ptln('  <table class="inline">');
    $this->show_config();
    ptln('  </table>');
    ptln('<p>');
    ptln('  <input type="hidden" name="do"     value="admin" />');
    ptln('  <input type="hidden" name="page"   value="dokutexit" />');
    ptln('  <input type="hidden" name="save"   value="1" />');
    ptln('  <input type="submit" name="submit" class="button" value="'.$this->lang['texit_btn_save'].'" accesskey="s" />');
    //    ptln('  <input type="reset" name="submit" class="button" value="'.$this->lang['texit_btn_revert'].'" />');
    ptln('</form>');
    ptln('<form action="'.wl($ID).'" method="post">');
    ptln('  <input type="hidden" name="do"     value="admin" />');
    ptln('  <input type="hidden" name="page"   value="dokutexit" />');
    ptln('  <input type="hidden" name="revert"   value="1" />');
    ptln('  <input type="submit" name="submit" class="button" value="'.$this->lang['texit_btn_revert'].'" accesskey="s" />');
    //    ptln('  <input type="reset" name="submit" class="button" value="'.$this->lang['texit_btn_revert'].'" />');
    ptln('</form>');
    
    print $this->locale_xhtml('test');
    if (!isset($this->_texit)) {
      $this->_texit = new texitrender_plugin_dokutexit('wiki:syntax');
      $this->_texit->add_inputs('<input type="hidden" name="do" value="admin" />');
      $this->_texit->add_inputs('<input type="hidden" name="page" value="dokutexit" />');
    }
    if ($this->_texit->add_data('info', "author=DokuTeXit\ntitle=Formating syntax\nrecurse=on")) {
	  print $this->_texit->render();
	  if ($this->_texit->pdf_exist()) {
	    print $this->_texit->render_cleanup(1);
	  }
    }
    print $this->locale_xhtml('ebook');
    ptln('</form>');
    ptln('<form action="'.wl($ID).'" method="post">');
    ptln('  <input type="hidden" name="do"     value="admin" />');
    ptln('  <input type="hidden" name="page"   value="dokutexit" />');
    ptln('  <input type="hidden" name="subpage"   value="ebook" />');
    ptln('  <input type="submit" name="submit" class="button" value="'.
	 $this->lang['ebook'].'" accesskey="e" />');
    ptln('</form>');

  }

  function ebook_html() {
    global $lang;
    global $ID;

    print $this->locale_xhtml('ebook');
    print $this->locale_xhtml('ebook_info');
    ptln('<form action="'.wl($ID).'" method="post">');
    $this->print_textarea('ebook_info', 
			  isset($_REQUEST['ebook_info']) 
			  ? $_REQUEST['ebook_info'] 
			  : "author=DokuTeXit\ntitle=My Ebook\nbackgroundtext=Ebook");
    ptln('  <input type="submit" name="submit" class="button" value="'.$this->lang['texit_btn_save'].'" accesskey="s" />');
    print $this->locale_xhtml('ebook_namespaces');
    $this->print_textarea('ebook_namespaces', 
			  isset($_REQUEST['ebook_namespaces']) 
			  ? $_REQUEST['ebook_namespaces'] 
			  : "$ID");
    print $this->show_ns_tree($ID);
    ptln('<p>');
    ptln('  <input type="hidden" name="do"     value="admin" />');
    ptln('  <input type="hidden" name="page"   value="dokutexit" />');
    ptln('  <input type="hidden" name="save"   value="1" />');
    ptln('  <input type="hidden" name="subpage" value="ebook" />');
    ptln('  <input type="submit" name="submit" class="button" value="'.$this->lang['texit_btn_save'].'" accesskey="s" />');
    ptln('</form>');
    ptln('<form action="'.wl($ID).'" method="post">');
    ptln('  <input type="hidden" name="do"     value="admin" />');
    ptln('  <input type="hidden" name="page"   value="dokutexit" />');
    ptln('  <input type="hidden" name="revert"   value="1" />');
    ptln('  <input type="hidden" name="subpage" value="ebook" />');
    ptln('  <input type="submit" name="submit" class="button" value="'.$this->lang['texit_btn_revert'].'" accesskey="s" />');
    ptln('</form>');
    if (!isset($this->_texit)) {
      $this->_texit = new texitrender_plugin_dokutexit($ID);
      $this->_texit->add_inputs('<input type="hidden" name="do" value="admin" />');
      $this->_texit->add_inputs('<input type="hidden" name="page" value="dokutexit" />');
      $this->_texit->add_inputs('<input type="hidden" name="subpage" value="ebook" />');
      $this->_texit->add_inputs('<input type="hidden" name="ebook" value="ebook" />');
      $this->_texit->add_inputs('<input type="hidden" name="ebook_namespaces" value="'.
				$_REQUEST['ebook_namespaces'] . '" />');
      $this->_texit->add_inputs('<input type="hidden" name="ebook_info" value="'.
				$_REQUEST['ebook_info'] . '" />');
    }
    if (isset($_REQUEST['subpage'])
	      && $_REQUEST['subpage'] == 'ebook' 
	      && isset($_REQUEST['ebook'])) {
      if ($this->_texit->add_data('info', $_REQUEST['ebook_info'])) {
	print $this->_texit->render('ebook');
	if ($this->_texit->pdf_exist()) {
	  print $this->_texit->render_cleanup(1);
	}
      }
    } else {  
      if ($this->_texit->add_data('info', "author=DokuTeXit\ntitle=Formating syntax\nrecurse=on")) {
	print $this->_texit->render();
	if ($this->_texit->pdf_exist()) {
	  print $this->_texit->render_cleanup(1);
	}
      }
    }
  }
  
  function locale_file($id) {
    $cfg = new texitConfig($id);
    if ($cfg->is_readable($id)) 
      return $cfg->read();
    else
      {
	$this->_error = true;
	$this->_error_data = $this->getLang('no_config');
	return io_readfile($this->localFN($id .'_config'));
      }
  }

  function write_locale_file($id, $data) {
    $cfg = new texitConfig($id);
    if ($this->check_cfg_error($cfg)) { return 0; };
    $cfg->write($data);
    if ($this->check_cfg_error($cfg)) { return 0; };
    return 1;
  }

  function delete_locale_file($id) {
    $cfg = new texitConfig($id);
    $cfg->delete();
    return $this->check_cfg_error($cfg);
  }
  
  function check_cfg_error($cfg) {
    if ($cfg->is_error()) {
      $err = $cfg->get_error();
      $this->_error = $err[0];
      $this->_error_data = $err[1];
      $this->_error_php = $err[2];
      return (1);
    }
    return (0);
  }

  /**
   * function from admin.php config manager plugin
   * Christopher Smith <chris@jalakai.co.uk>
   */  
  function setupLocale($prompts=false) {
    parent::setupLocale();
    if (!$prompts || $this->_localised_prompts) return;
    $this->_setup_localised_plugin_prompts();
    $this->_localised_prompts = true;
  }

  function _setup_localised_plugin_prompts() {
    global $conf;
    $langfile   = '/lang/'.$conf[lang].'/settings.php';
    $enlangfile = '/lang/en/settings.php';
    $lang = array();
    if ($dh = opendir(DOKU_PLUGIN)) {
      while (false !== ($plugin = readdir($dh))) {
	if ($plugin == '.' || $plugin == '..' || $plugin == 'tmp' || $plugin == 'config') continue;
	if (is_file(DOKU_PLUGIN.$plugin)) continue;
	if (@file_exists(DOKU_PLUGIN.$plugin.$enlangfile)){
	  @include(DOKU_PLUGIN.$plugin.$enlangfile);
	  if ($conf['lang'] != 'en') @include(DOKU_PLUGIN.$plugin.$langfile);
	}
      }
      closedir($dh);
      $this->lang = array_merge($lang, $this->lang);
    }
    return true;
  }

  function print_textarea($id, $data) {
    ptln('<textarea name="'.$id.'" id="wiki__text"  cols="60" rows="10" class="edit" tabindex="1">');
    print $data;
    ptln('</textarea>');      
  }

  function show_config() {
    foreach ($this->_data as $key => $value) {
      print $this->locale_xhtml($key);
      if (!is_null($this->_data[$key])) { 
	$this->print_textarea($key, $this->_data[$key]); 
      }
    }
  }

  function load_config() {
    foreach ($this->_data as $key => $value) {
      $this->_data[$key] = $this->locale_file($key);
    }
  }

  function save_config() {
    $this->_changed = true;
    foreach ($this->_data as $key => $value) {
      $this->_data[$key] = strtr($_REQUEST[$key], array("\x0D" => ""));
    }
  }

  function write_config() {
    $this->_changed = true;
    foreach ($this->_data as $key => $value) {
      if (!$this->write_locale_file($key, $this->_data[$key])) {
	return -1;
      }
    }
  }
  
  function revert_config() {
    $this->_changed = true;
    foreach ($this->_data as $key => $value) {
      $this->_data[$key] = $this->locale_file($key);
    }
  }

  function default_config() {
    $this->_changed = true;
    foreach ($this->_data as $key => $value) {
      $this->delete_locale_file($key);
      $this->_data[$key] = io_readfile($this->localFN($key .'_config'));
    }
  }
  function show_ns_tree($ns) {
    require_once(DOKU_INC.'inc/search.php');
    global $conf;
    global $ID;
    $dir = $conf['datadir'];
    $ns  = cleanID($ns);
#fixme use appropriate function
    if(empty($ns)){
      $ns = dirname(str_replace(':', '/', $ID));
      if($ns == '.') 
	$ns ='';
    }
    $ns  = utf8_encodeFN(str_replace(':', '/', $ns));
    
    $data = array();
    search($data, $conf['datadir'], 'search_index', array('ns' => $ns));
    print '<pre>';
    print_r($data);
    print '</pre>';
    return $this->buildlist($data, 'idx');
  }
  
  function buildlist($data, $class) {
    $level = 0;
    $opens = 0;
    $ret   = '';
    foreach ($data as $item) {
      if ($item['level'] > $level) {
	//open new list
	for ($i=0; $i<($item['level'] - $level); $i++) {
	  if ($i) 
	    $ret .= "<li class=\"clear\">\n";
	  $ret .= "\n<ul class=\"$class\">\n";
	}
      } elseif ( $item['level'] < $level ) {
	//close last item
	$ret .= "</li>\n";
	for ($i = 0; $i < ($level - $item['level']); $i++) {
	  //close higher lists
	  $ret .= "</ul>\n</li>\n";
	}
      } else {
	//close last item
	$ret .= "</li>\n";
      }
      //remember current level
      $level = $item['level'];
      //print item
      $ret .= $this->li_index($item); //user function
      $ret .= '<div class="li">';
      $ret .= $this->list_index($item); //user function
      $ret .= '</div>';
    }
    //close remaining items and lists
    for ($i = 0; $i < $level; $i++) {
      $ret .= "</li></ul>\n";
    }
    return $ret;
  }
  
  function list_index($item) {
    global $ID;
    $ret = '';
    $base = ':' . $item['id'];
    $base = substr($base, strrpos($base,':') + 1);
    if ($item['type'] == 'd') {
      $ret .= '<a href="' . wl($ID,'idx=' . $item['id']);
      $ret .= '" class="idx_dir">';
      $ret .= $base;
      $ret .= '</a>';
    } else {
      $ret .= html_wikilink(':' . $item['id']);
    }
    return $ret;
  }
  
  function li_index($item) {
    if ($item['type'] == "f") {
      return '<li class="level' . $item['level'] . '">';
    } elseif ($item['open']) {
      return '<li class="open">';
    } else {
      return '<li class="closed">';
    }
  }
}
