<?php
/*
 * DokuTeXit plugin, Non-LaTeX configuration settings
 *
 * @author    Danjer <danjer@doudouke.org>
 */
$meta['zipsources'] = array('onoff'); // Show Download zip button
$meta['dnl_button'] = array('onoff'); // Show Download as PDF button
$meta['force_clean_up'] = array('onoff'); // Show Force Clean up button
$meta['latex_mode'] = array('multichoice','_choices' => array('latex', 'pdflatex'));
$meta['latex_path'] = array('string');