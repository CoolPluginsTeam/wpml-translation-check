<?php
/**
 * Loads the setup wizard.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOML_WPML\Modules\Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/wizard.php';

$automl_ai_wizard = new AUTOML_Ai_Wizard();