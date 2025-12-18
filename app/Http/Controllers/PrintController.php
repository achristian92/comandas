<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Warrior\Ticketer\Store;
use Warrior\Ticketer\Ticketer;

class PrintController extends Controller
{
    public function print(Request $request)
    {
        $data = $request['data'];

        return match ($data['type']) {
            'Command' => $this->command($data),
            'Voucher' => $this->voucher($data),
            'PreAccount' => $this->preAccount($data),
            default   => response()->json(['status' => 'error'], 400),
        };
    }

    private function createNetworkTicketer($printer = null, $issue_date = null): Ticketer
    {
        if ($printer === null) {
            $printer = [
                'pr_ip' => (string) config('ticketer.conexion.connector_descriptor'),
                'pr_port' => (string) config('ticketer.conexion.connector_port', '9100'),
            ];
        }

        $ticketer = new Ticketer();
        $ticketer->init('network', $printer['pr_ip'], (string) ($printer['pr_port'] ?? '9100'));
        if($issue_date)
            $ticketer->setFechaEmision($issue_date);

        return $ticketer;
    }

    private function sendRawToPrinter(string $ip, int $port, string $payload): void
    {
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($ip, $port, $errno, $errstr, 3);
        if (!$fp) {
            throw new \RuntimeException("No se pudo conectar a impresora $ip:$port ($errno) $errstr");
        }

        stream_set_timeout($fp, 5);
        try {
            $written = fwrite($fp, $payload);
            if ($written === false) {
                throw new \RuntimeException("No se pudo escribir a impresora $ip:$port");
            }
            fflush($fp);
        } finally {
            fclose($fp);
        }
    }

    private function escposCut(): string
    {
        return pack('C*', 0x1D, 0x56, 0x00);
    }

    private function escposFeed(int $lines = 3): string
    {
        $lines = max(0, min(10, $lines));
        return pack('C*', 0x1B, 0x64, $lines);
    }

    private function safeCloseTicketer(Ticketer $ticketer): void
    {
        try {
            if (method_exists($ticketer, 'close')) {
                $ticketer->close();
                return;
            }

            if (method_exists($ticketer, 'finalize')) {
                $ticketer->finalize();
                return;
            }

            $visited = [];
            $this->deepCloseEscposObjects($ticketer, $visited, 0);
        } catch (\Throwable $e) {
            Log::warning('Error cerrando ticketer', ['error' => $e->getMessage()]);
        }
    }

    private function deepCloseEscposObjects($value, array &$visited, int $depth): void
    {
        if ($depth > 3) {
            return;
        }

        if (!is_object($value) && !is_array($value)) {
            return;
        }

        if (is_object($value)) {
            $id = spl_object_id($value);
            if (isset($visited[$id])) {
                return;
            }
            $visited[$id] = true;

            $class = get_class($value);
            $isEscposRelated = (stripos($class, 'Mike42\\Escpos\\') === 0)
                || (stripos($class, 'PrintConnector') !== false)
                || (stripos($class, 'Printer') !== false);

            if ($isEscposRelated) {
                try {
                    if (method_exists($value, 'close')) {
                        $value->close();
                    }

                    if (method_exists($value, 'finalize')) {
                        $value->finalize();
                    }
                } catch (\Throwable $e) {
                    Log::warning('Error cerrando objeto escpos', [
                        'class' => $class,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            try {
                $ref = new \ReflectionObject($value);
                foreach ($ref->getProperties() as $prop) {
                    $prop->setAccessible(true);
                    $this->deepCloseEscposObjects($prop->getValue($value), $visited, $depth + 1);
                }
            } catch (\Throwable $e) {
                return;
            }

            return;
        }

        foreach ($value as $v) {
            $this->deepCloseEscposObjects($v, $visited, $depth + 1);
        }
    }

    private function command($data)
    {
        Log::info('Print command received', [
            'type' => 'Command',
            'details_count' => is_array($data['details'] ?? null) ? count($data['details']) : null,
            'created_at' => $data['created_at'] ?? null,
        ]);

        $allOk = collect($data['details'])
            ->map(fn($detail) => $this->printCocinaDetail($detail, $data['created_at'] ?? null))
            ->every(fn($ok) => $ok);

        if ($allOk) {
            return response()->json(['status' => 'ok']);
        } else {
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function printCocinaDetail(array $detail, $issueDate = null): bool
    {
        $printerIp = $detail['printer']['pr_ip'] ?? null;
        $printerPort = $detail['printer']['pr_port'] ?? '9100';
        Log::info('Print cocina start', [
            'type' => 'Command',
            'printer_ip' => $printerIp,
            'printer_port' => $printerPort,
            'table' => ($detail['table']['t_name'] ?? null),
            'salon' => ($detail['table']['t_salon'] ?? null),
            'client' => ($detail['client']['c_name'] ?? null),
            'items_count' => is_array($detail['items'] ?? null) ? count($detail['items']) : null,
        ]);

        try {
            if (!$printerIp) {
                throw new \RuntimeException('printer_ip vacío');
            }

            $port = (int) $printerPort;
            $lines = [];
            $lines[] = 'COMANDA';
            if ($issueDate) {
                $lines[] = 'FECHA: ' . (string) $issueDate;
            }
            $lines[] = 'MESA: ' . ($detail['table']['t_name'] ?? '-');
            $lines[] = 'SALON: ' . ($detail['table']['t_salon'] ?? '-');
            $lines[] = 'CLIENTE: ' . ($detail['client']['c_name'] ?? '-');
            $lines[] = 'MOZO: ' . ($detail['waiter']['u_name'] ?? '-');
            $lines[] = str_repeat('-', 32);

            foreach (($detail['items'] ?? []) as $item) {
                $qty = (string) ($item['i_quantity'] ?? '1');
                $name = (string) ($item['i_name'] ?? '');
                $lines[] = $qty . '  ' . $name;
            }

            $payload = implode("\n", $lines) . "\n";
            $payload .= $this->escposFeed(4);
            $payload .= $this->escposCut();

            $this->sendRawToPrinter((string) $printerIp, $port, $payload);
            $ok = true;
            Log::info('Print cocina done', [
                'type' => 'Command',
                'printer_ip' => $printerIp,
                'printer_port' => $printerPort,
                'ok' => $ok,
            ]);
            return $ok;
        } catch (\Throwable $e) {
            Log::error('Error imprimiendo comanda de cocina', [
                'error' => $e->getMessage(),
                'printer_ip' => $printerIp,
                'printer_port' => $printerPort,
            ]);
            return false;
        }
    }

    private function preAccount($data)
    {
        $detail = $data['details'];
        $printerIp = $detail['printer']['pr_ip'] ?? null;
        $printerPort = $detail['printer']['pr_port'] ?? '9100';
        Log::info('Print preAccount start', [
            'type' => 'PreAccount',
            'printer_ip' => $printerIp,
            'printer_port' => $printerPort,
            'issue_date' => $detail['order']['issue_date'] ?? null,
            'table' => ($detail['table']['t_name'] ?? null),
            'salon' => ($detail['table']['t_salon'] ?? null),
            'client' => ($detail['client']['c_name'] ?? null),
            'items_count' => is_array($detail['items'] ?? null) ? count($detail['items']) : null,
        ]);

        try {
            if (!$printerIp) {
                throw new \RuntimeException('printer_ip vacío');
            }

            $port = (int) $printerPort;
            $lines = [];
            $lines[] = 'PRE-CUENTA';
            $lines[] = 'FECHA: ' . (string) ($detail['order']['issue_date'] ?? '-');
            $lines[] = 'MESA: ' . ($detail['table']['t_name'] ?? '-');
            $lines[] = 'SALON: ' . ($detail['table']['t_salon'] ?? '-');
            $lines[] = 'CLIENTE: ' . ($detail['client']['c_name'] ?? '-');
            $lines[] = 'MOZO: ' . ($detail['waiter']['u_name'] ?? '-');
            $lines[] = str_repeat('-', 32);

            $total = 0.0;
            foreach (($detail['items'] ?? []) as $item) {
                $qty = (float) ($item['i_quantity'] ?? 1);
                $price = (float) ($item['i_price'] ?? 0);
                $name = (string) ($item['i_name'] ?? '');
                $free = (bool) ($item['i_free'] ?? false);
                $lineTotal = $free ? 0.0 : ($qty * $price);
                $total += $lineTotal;
                $lines[] = (string) ((int) $qty) . '  ' . $name;
                $lines[] = '    ' . ($free ? 'GRATIS' : number_format($lineTotal, 2, '.', ''));
            }

            $lines[] = str_repeat('-', 32);
            $lines[] = 'TOTAL: ' . number_format($total, 2, '.', '');

            $payload = implode("\n", $lines) . "\n";
            $payload .= $this->escposFeed(4);
            $payload .= $this->escposCut();

            $this->sendRawToPrinter((string) $printerIp, $port, $payload);
            $ok = true;
            Log::info('Print preAccount done', [
                'type' => 'PreAccount',
                'printer_ip' => $printerIp,
                'printer_port' => $printerPort,
                'ok' => $ok,
            ]);
            return $ok
                ? response()->json(['status' => 'ok'])
                : response()->json(['status' => 'error'], 500);
        } catch (\Throwable $e) {
            Log::error('Error imprimiendo pre-cuenta', [
                'error' => $e->getMessage(),
                'printer_ip' => $printerIp,
                'printer_port' => $printerPort,
            ]);
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function voucher($data)
    {
        $ticketer = $this->createNetworkTicketer();
        try {
            $ticketer->setStore($this->loadCompany($data['company']));
            $ticketer->setComprobante('BOLETA');
            $ticketer->setSerieComprobante('B001');
            $ticketer->setNumeroComprobante('000000100');
            $ticketer->setTipoComprobante('01');
            $ticketer->setCliente('Edwin Alexander Bautista Villegas');
            $ticketer->setTipoDocumento(1);
            $ticketer->setNumeroDocumento('72462226');
            $ticketer->setTipoDocumento('01');
            $ticketer->setDireccion('Jr. Enarte Torres 421 - Santa Lucia');
            $ticketer->setTipoDetalle('DETALLADO');

            foreach ($data['details']['items'] as $item) {
                $ticketer->addItem($item['i_name'], $item['i_quantity']);
            }

            return $ticketer->printComprobante()
                ? response()->json(['status' => 'ok'])
                : response()->json(['status' => 'error'], 500);
        } catch (\Throwable $e) {
            Log::error('Error imprimiendo voucher', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        } finally {
            $this->safeCloseTicketer($ticketer);
        }
    }

    private function loadCompany(array $company)
    {
        $store = new Store();
        $store->setRuc($company['ruc']);
        $store->setNombreComercial($company['name']);
        $store->setRazonSocial($company['name']);
        $store->setDireccion($company['address']);
        $store->setTelefono($company['phone']);
        $store->setEmail($company['email']);
        $store->setWebsite($company['website']);
        $store->setLogo(config('ticketer.store.logo'));

        return $store;
    }

}
