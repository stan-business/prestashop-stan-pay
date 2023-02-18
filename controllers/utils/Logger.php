<?php
/**
 *  2022 Brightweb SAS
 *
 *  @author Brightweb
 *  @copyright  2022 Brightweb SAS
 *  @license    http://www.gnu.org/licenses/gpl-3.0.txt  General Public License v3.0 (GPLv3)
 */

namespace Stanpayment\Utils;

class Logger
{
    /**
     * @param string $message
     * @param int $level
     * @param array $context
     */
    public static function write($message, $level = 1, $context = [])
    {
        $log = 'stanpayment - ' . $message;
        if (!empty($context)) {
            $log .= ': ' . json_encode($context);
        }
        \PrestaShopLogger::addLog($log, $level);
    }
}
