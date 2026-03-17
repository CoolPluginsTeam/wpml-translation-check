<?php
/**
 * Loads the setup wizard.
 *
 * @package WPML_Auto_Translate
 */

namespace AUTOMLP_WPML\Modules\Wizard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/wizard.php';

$automlp_ai_wizard = new AUTOMLP_Ai_Wizard();