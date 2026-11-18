<?php

namespace App\Services;

use App\Models\ActivityLog;

class DrawerService
{
    /**
     * Send the ESC/POS "kick drawer" pulse to a network receipt printer.
     * Cash drawers are wired to the printer's RJ11 port and open on this pulse.
     *
     * Returns a status array so the POS UI can surface the result without failing checkout.
     */
    public function kick(): array
    {
        $host = env('POS_PRINTER_HOST');
        $port = (int) env('POS_PRINTER_PORT', 9100);

        if (empty($host)) {
            return [
                'ok' => false,
                'configured' => false,
                'message' => __('No cash-drawer printer configured (set POS_PRINTER_HOST in .env).'),
            ];
        }

        // ESC p m t1 t2 — standard drawer kick on pin 2.
        $pulse = "\x1B\x70\x00\x19\xFA";

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 2.0);

        if (! $socket) {
            ActivityLog::record('drawer', "Cash drawer kick failed: {$errstr}");

            return [
                'ok' => false,
                'configured' => true,
                'message' => __('Could not reach printer at :host::port (:error).', [
                    'host' => $host,
                    'port' => $port,
                    'error' => $errstr,
                ]),
            ];
        }

        fwrite($socket, $pulse);
        fclose($socket);

        ActivityLog::record('drawer', 'Cash drawer opened');

        return ['ok' => true, 'configured' => true, 'message' => __('Cash drawer opened.')];
    }
}
