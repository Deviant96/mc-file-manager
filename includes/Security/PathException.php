<?php
/**
 * Raised when a path is invalid or escapes the root jail.
 *
 * @package MCFM
 */

namespace MCFM\Security;

defined( 'ABSPATH' ) || exit;

class PathException extends \Exception {}
