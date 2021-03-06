<?php
/**
 * @package   WPEmerge
 * @author    Atanas Angelov <atanas.angelov.dev@gmail.com>
 * @copyright 2018 Atanas Angelov
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0
 * @link      https://wpemerge.com/
 */

namespace WPEmerge\Facades;

use WPEmerge\Support\Facade;

/**
 * Provide access to session flashing service
 *
 * @codeCoverageIgnore
 * @see \WPEmerge\Flash\Flash
 */
class Flash extends Facade {
	protected static function getFacadeAccessor() {
		return WPEMERGE_FLASH_KEY;
	}
}
