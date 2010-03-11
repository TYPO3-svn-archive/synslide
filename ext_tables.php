<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}
$tempColumns = array (
	'tx_synslide_enabled' => array (		
		'exclude' => 0,		
		'label' => 'LLL:EXT:synslide/locallang_db.xml:tt_content.tx_synslide_enabled',		
		'config' => array (
			'type' => 'check',
		)
	),
);


t3lib_div::loadTCA('tt_content');
t3lib_extMgm::addTCAcolumns('tt_content',$tempColumns,1);
#t3lib_extMgm::addToAllTCAtypes('tt_content','tx_synslide_enabled;;;;1-1-1');

t3lib_extMgm::addStaticFile($_EXTKEY,'static/', 'Synslide');
$GLOBALS['TCA']['tt_content']['palettes']['2']['showitem'] .= ', tx_synslide_enabled';

?>