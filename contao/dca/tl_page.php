<?php

declare(strict_types=1);

use Contao\CoreBundle\DataContainer\PaletteManipulator;

// Web Content Access settings on the website root page (one set of settings per site).
$GLOBALS['TL_DCA']['tl_page']['palettes']['__selector__'][] = 'wcaEnabled';
$GLOBALS['TL_DCA']['tl_page']['subpalettes']['wcaEnabled'] = 'wcaStatementPage,wcaStatementUrl,wcaToolbar,wcaPosition,wcaColor,wcaSize,wcaShape,wcaDisabledFixes';

foreach (['root', 'rootfallback'] as $palette) {
    PaletteManipulator::create()
        ->addLegend('wca_legend', 'csp_legend', PaletteManipulator::POSITION_AFTER)
        ->addField('wcaEnabled', 'wca_legend', PaletteManipulator::POSITION_APPEND)
        ->applyToPalette($palette, 'tl_page');
}

$GLOBALS['TL_DCA']['tl_page']['fields'] += [
    'wcaEnabled' => [
        'inputType' => 'checkbox',
        'eval' => ['submitOnChange' => true, 'tl_class' => 'clr'],
        'sql' => ['type' => 'boolean', 'default' => true],
    ],
    'wcaStatementPage' => [
        'inputType' => 'pageTree',
        'foreignKey' => 'tl_page.title',
        'eval' => ['fieldType' => 'radio', 'tl_class' => 'clr'],
        'sql' => ['type' => 'integer', 'unsigned' => true, 'default' => 0],
        'relation' => ['type' => 'hasOne', 'load' => 'lazy'],
    ],
    'wcaStatementUrl' => [
        'inputType' => 'text',
        'eval' => ['rgxp' => 'url', 'decodeEntities' => true, 'maxlength' => 2048, 'tl_class' => 'w50'],
        'sql' => ['type' => 'string', 'length' => 2048, 'default' => ''],
    ],
    'wcaToolbar' => [
        'inputType' => 'checkbox',
        'eval' => ['tl_class' => 'w50 m12'],
        'sql' => ['type' => 'boolean', 'default' => true],
    ],
    'wcaPosition' => [
        'inputType' => 'select',
        'options' => ['bottom-right', 'bottom-left', 'middle-right', 'middle-left', 'top-right', 'top-left'],
        'reference' => &$GLOBALS['TL_LANG']['tl_page']['wcaPositions'],
        'eval' => ['tl_class' => 'w50'],
        'sql' => ['type' => 'string', 'length' => 16, 'default' => 'bottom-right'],
    ],
    'wcaColor' => [
        'inputType' => 'text',
        'eval' => ['maxlength' => 6, 'colorpicker' => true, 'isHexColor' => true, 'decodeEntities' => true, 'tl_class' => 'w50 wizard'],
        'sql' => ['type' => 'string', 'length' => 6, 'default' => '1a56db'],
    ],
    'wcaSize' => [
        'inputType' => 'select',
        'options' => ['s', 'm', 'l'],
        'reference' => &$GLOBALS['TL_LANG']['tl_page']['wcaSizes'],
        'eval' => ['tl_class' => 'w50'],
        'sql' => ['type' => 'string', 'length' => 1, 'default' => 'm'],
    ],
    'wcaShape' => [
        'inputType' => 'select',
        'options' => ['round', 'square'],
        'reference' => &$GLOBALS['TL_LANG']['tl_page']['wcaShapes'],
        'eval' => ['tl_class' => 'w50'],
        'sql' => ['type' => 'string', 'length' => 8, 'default' => 'round'],
    ],
    'wcaDisabledFixes' => [
        'inputType' => 'checkbox',
        'options' => ['lang', 'zoom', 'skip', 'focus', 'names', 'labels', 'tabindex', 'motion', 'statement'],
        'reference' => &$GLOBALS['TL_LANG']['tl_page']['wcaFixes'],
        'eval' => ['multiple' => true, 'tl_class' => 'clr'],
        'sql' => ['type' => 'blob', 'length' => 65535, 'notnull' => false],
    ],
];
