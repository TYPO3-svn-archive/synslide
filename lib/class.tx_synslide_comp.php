<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Roman Buechler <rb@synac.com>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */


/**
 * Synslide component for the 'synslide' extension.
 *
 * @author	Roman Buechler <rb@synac.com>
 * @package	TYPO3
 * @subpackage	synslide
 */
class tx_synslide_comp {

  /**
   * the following members might be accesed by other pi's
   *    
   */     
  var $aShowImage   = array();
  var $aThumbImage  = array();
  var $aCaption     = array();
  var $aLink        = array();
  var $aJsImageData = array();

	function render($content, $conf) {

    // preconditions
    if(!isset($conf['component.'])) return 'Config is misssing. Did you include static from Ext?';

    // blacklist
    require_once(t3lib_extMgm::extPath('synslide','res/var/blacklist.php'));
    $hostName = t3lib_div::getIndpEnv('TYPO3_HOST_ONLY');
    if($aBlacklist){
      foreach($aBlacklist as $blackName){
        if(strpos($hostName,$blackName)!==false) return sprintf(
          '<p style="color:#f00;font-weight:bold;border:#f00 2px solid;padding:5px;">%s<br /><br />%s</p>',
          'Functional restrictions apply for this host or domain. Unpaid invoices might be the reason! Please contact <a href="http://www.synac.com" target="_blank">Synac</a> to resolve the issue.',
          'WARNING: This extension is published under regulations as defined by typo3. DO NOT COPY OR ALTER THE SCRIPT AND/OR ITS CONTENT!'
        );
      };
    };

    // initialize component config
    $compConf = array();
    $this->evalStdWrapArray($conf['component.']['general.'],$compConf['general'],'jsFileToolsSlide,jsFileSlideshow,name,effectClass','isGlobal,width,height,showController,showThumbnails,showCaptions,showLoader,delay,ignoreT3mootools');
    $this->evalStdWrapArray($conf['component.']['image.'],$compConf['image'],'path,list,caption,link','compression,effects');
    $this->evalStdWrapArray($conf['component.']['mainPanel.'],$compConf['mainPanel'],'scaleFactor','useEffects,useCompression');
    $this->evalStdWrapArray($conf['component.']['thumbPanel.'],$compConf['thumbPanel'],'useEffects,useCompression,height');

    // initialize register parameters (provide greater flexibility)
    $GLOBALS['TSFE']->register['synslide_image_width'] = round($compConf['mainPanel']['scaleFactor'] * $compConf['general']['width']);
    $GLOBALS['TSFE']->register['synslide_image_height'] = round($compConf['mainPanel']['scaleFactor'] * $compConf['general']['height']);
    $GLOBALS['TSFE']->register['synslide_height'] = $compConf['general']['height'];
    if($compConf['general']['showThumbnails']) $GLOBALS['TSFE']->register['synslide_height'] += $compConf['thumbPanel']['height'];

    // unfortunatly params isn't stdWrap hence no flexible access through register parameters can be implemented
    $this->updateImageProcessingParams(
      $compConf['image'],
      $compConf['mainPanel'],
      $conf['component.']['mainPanel.']['file.']
    );
    $this->updateImageProcessingParams(
      $compConf['image'],
      $compConf['thumbPanel'],
      $conf['component.']['thumbPanel.']['file.']
    );

    // create images
    $aShowImage = array();
    $aThumbImage = array();
    $aImgList = t3lib_div::trimExplode(',',$compConf['image']['list']);
    $aCaption = t3lib_div::trimExplode(chr(10),$compConf['image']['caption']);
    $aLink = t3lib_div::trimExplode(',',$compConf['image']['link']);
    foreach($aImgList as $k => $imgFile){
      if(!isset($aCaption[$k])) $aCaption[$k] = '';
      if(!isset($aLink[$k])) $aLink[$k] = '';

      $GLOBALS['TSFE']->register['synslide_image_file'] = $compConf['image']['path'] . $imgFile;
      $GLOBALS['TSFE']->register['synslide_image_link'] = $aLink[$k];

      $aShowImage[] = $this->cObj->getImgResource(
        $conf['component.']['mainPanel.']['file'],
        $conf['component.']['mainPanel.']['file.']
      );
      $aThumbImage[] = $this->cObj->getImgResource(
        $conf['component.']['thumbPanel.']['file'],
        $conf['component.']['thumbPanel.']['file.']
      );

      if($aLink[$k]) $aLink[$k] = $this->cObj->typoLink_URL($conf['component.']['mainPanel.']['typolink.']);
    };

    // compile css header data
    $GLOBALS['TSFE']->additionalHeaderData['tx_synslide_css'] = sprintf('
      <link href="%sres/css/slideshow.css" rel="stylesheet" type="text/css" media="all" />
      ',
      $GLOBALS['TYPO3_LOADED_EXT']['synslide']['siteRelPath']
    );

    // compile js header data
    $aJsImageData = array();
    foreach($aShowImage as $k => $aImageInfo){
      $caption = str_replace(array('<br />',chr(10),chr(13)),'',$aCaption[$k]);
      $caption = str_replace(array("'",'"'),array("\'",'\"'),$aCaption[$k]); /* fix quotes - S.Delcroix (sdelcroix@rvvn.org) */
      $caption = $caption ? sprintf(', caption: \'%s\'',$caption) : '';
      $aJsImageData[] = sprintf(
        '\'%s\': { thumbnail: \'%s\' %s %s }',
        $aImageInfo[3],
        $aThumbImage[$k][3],
        $caption,
        $aLink[$k] ? sprintf(', href: \'%s\'',$aLink[$k]) : ''
      );
    };
    $js = sprintf('
      window.addEvent(\'domready\', function(){
  	    var data = {
  	      %s
  	    };
        %smy%s = new Slideshow%s(\'%s\', data, {width: %d, height: %d, hu: \'%s\', controller: %s, thumbnails: %s, captions: %s, loader: %s, delay: %d});
        $$(\'a\').addEvent(\'click\',function(){
          this.pause();
        }.bind(my%s));
  	  });
      ',
  	  implode(',' . chr(10),$aJsImageData),
  	  $compConf['general']['isGlobal'] ? '' : 'var ',
      $compConf['general']['name'],
  	  $compConf['general']['effectClass'] ? '.' . $compConf['general']['effectClass'] : '',
  	  $compConf['general']['name'],
  	  $compConf['general']['width'],
  	  $compConf['general']['height'],
  	  '',
  	  $compConf['general']['showController'] ? 'true' : 'false',
  	  $compConf['general']['showThumbnails'] ? 'true' : 'false',
  	  $compConf['general']['showCaptions'] ? 'true' : 'false',
  	  $compConf['general']['showLoader'] ? 'true' : 'false',
  	  $compConf['general']['delay'],
      $compConf['general']['name']
    );
    if(t3lib_extMgm::isLoaded('t3mootools') && !$compConf['general']['ignoreT3mootools']){
      require_once(t3lib_extMgm::extPath('t3mootools').'class.tx_t3mootools.php');
      $jsfile = $compConf['general']['jsFileSlideshow'];
      tx_t3mootools::addJS('',array('jsfile' => $jsfile,'jsfile.' => ''));
      tx_t3mootools::addJS('',array('jsdata' =>$js,'jsdata.' => ''));
    } else {
      $GLOBALS['TSFE']->additionalHeaderData['tx_synslide_js'] = sprintf('
        <script type="text/javascript" src="%s"></script>
        ',
        $compConf['general']['jsFileToolsSlide']
      );
      $GLOBALS['TSFE']->additionalJavaScript['tx_synslide'] = $js;
    };

    // compile component
    $content = sprintf('
      <div id="%s" class="slideshow" style="width:%dpx;">
        <img src="%s" alt="%s" />
      </div>',
      $compConf['general']['name'],
      $compConf['general']['width'],
      $showImage[0][3],
      ''
    );
    if(isset($conf['component.']['stdWrap.'])) $content = $this->cObj->stdWrap($content,$conf['component.']['stdWrap.']);
    
    // provide available data to external modules
    $this->aShowImage = $aShowImage;
    $this->aThumbImage = $aThumbImage;
    $this->aCaption = $aCaption;
    $this->aLink = $aLink;
    $this->aJsImageData = $aJsImageData;
    
    return $content;
	}

	/**
	 * evaluate an array of stdWrap parameters
	 *
	 * @param    array       stdWrap source configuration
	 * @param    reference   the evaluated stdWrap parameter will be written to this array
	 * @param    string      parameter list
	 * @param    string      integer parameter list
	 * @return   mixed       the evaluated array
	 */
  function evalStdWrapArray($sourceCfg,&$targetCfg,$normParam='',$intParam=''){
    if($normParam){
      $aNormParam = t3lib_div::trimExplode(',',$normParam);
      foreach($aNormParam as $param){
        $targetCfg[$param] = $this->cObj->stdWrap($sourceCfg[$param],$sourceCfg[$param . '.']);
      };
    };
    if($intParam){
      $aIntParam = t3lib_div::trimExplode(',',$intParam);
      foreach($aIntParam as $param){
        $targetCfg[$param] = intval($this->cObj->stdWrap($sourceCfg[$param],$sourceCfg[$param . '.']));
      };
    };
  }

  /**
   * this method is needed due to the fact that the params-parameter isn't implemented
   * as a stdWrap yet.
   *
   * note: in case GIFBUILDER is needed to create showImages the targetCfg need to be
   * known (or stdWrap implemented for params). this isn't implemented yet.
   *
   * @param   ref   component configuration holding requested effect and compression data (from db)
   * @param   ref   source configuration holding selecting parameters useEffects and useCompression
   * @param   ref   target configuration will be completed with params and ext parameters
   * @return  void
   */
  function updateImageProcessingParams(&$compCfg,&$sourceCfg,&$targetCfg){
    // initialize image processing parameters
    $effects = $compCfg['effects'] ? $this->cObj->image_effects[$compCfg['effects']] : '';
    $aCompr = $compCfg['compression'] ? $this->cObj->image_compression[$compCfg['compression']] : array('params' => '','ext' => '');

    // update image processing parameters
    $aParams = array();
    $aParams[] = $sourceCfg['useEffects'] ? $effects : '';
    if($sourceCfg['useCompression']){
      $aParams[] = $aCompr['params'];
      if($aCompr['ext']) $targetCfg['ext'] = $aCompr['ext'];
    };
    $params = trim(implode(' ',$aParams));
    if($params) $targetCfg['params'] = $params;
  }


}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/synslide/lib/class.tx_synslide_comp.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/synslide/lib/class.tx_synslide_comp.php']);
}

?>