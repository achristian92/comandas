<?php

namespace App\Http\Controllers;

use App\Services\PrintService;
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
        $instanceId = $request->header('X-Frontend-Instance', 'unknown');
        $eventId = $request->header('X-Event-Id', 'unknown');
        
        Log::info('🔴 REQUEST RECIBIDO EN BACKEND', [
            'frontend_instance' => $instanceId,
            'event_id' => $eventId,
            'timestamp' => now()->toISOString(),
            'type' => $request['data']['type'] ?? 'unknown',
        ]);
        
        $data = $request['data'];

        return match ($data['type']) {
            'Command' => $this->command($data, $instanceId, $eventId),
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

    private function printRawWithRetry(string $type, string $ip, int $port, string $payload, array $metadata = []): bool
    {
        return PrintService::printRawWithRetry($type, $ip, $port, $payload, $metadata);
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

    private function command($data, $instanceId = 'unknown', $eventId = 'unknown')
    {
        Log::info('🟡 PROCESANDO COMMAND', [
            'frontend_instance' => $instanceId,
            'event_id' => $eventId,
            'type' => 'Command',
            'details_count' => is_array($data['details'] ?? null) ? count($data['details']) : null,
            'created_at' => $data['created_at'] ?? null,
        ]);

        $allOk = true;
        foreach ($data['details'] as $index => $detail) {
            Log::info("🖨️  IMPRIMIENDO DETAIL #{$index}", [
                'frontend_instance' => $instanceId,
                'event_id' => $eventId,
                'printer_ip' => $detail['printer']['pr_ip'] ?? null,
            ]);
            
            $ok = $this->printCocinaDetail($detail, $data['created_at'] ?? null);
            if (!$ok) {
                $allOk = false;
            }
        }
        
        Log::info('🏁 COMMAND FINALIZADO', [
            'frontend_instance' => $instanceId,
            'event_id' => $eventId,
            'success' => $allOk,
        ]);
        
        if ($allOk) {
            return response()->json(['status' => 'ok']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Algunas impresiones fallaron'], 500);
        }
    }

    private function printCocinaDetail(array $detail, $issueDate = null): bool
    {
        $printerIp = $detail['printer']['pr_ip'] ?? null;
        $printerPort = $detail['printer']['pr_port'] ?? '9100';
        
        if (!$printerIp) {
            Log::error('printer_ip vacío en comanda');
            return false;
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

        $payload = PrintService::buildEscposPayload($lines, 4, true);
        
        $metadata = [
            'table' => $detail['table']['t_name'] ?? null,
            'salon' => $detail['table']['t_salon'] ?? null,
            'client' => $detail['client']['c_name'] ?? null,
            'items_count' => is_array($detail['items'] ?? null) ? count($detail['items']) : null,
        ];

        return $this->printRawWithRetry('Command', (string) $printerIp, $port, $payload, $metadata);
    }

    private function preAccount($data)
    {
        $detail = $data['details'];
        $printerIp = $detail['printer']['pr_ip'] ?? null;
        $printerPort = $detail['printer']['pr_port'] ?? '9100';
        
        Log::info('Print preAccount received', [
            'type' => 'PreAccount',
            'printer_ip' => $printerIp,
            'printer_port' => $printerPort,
            'issue_date' => $detail['order']['issue_date'] ?? null,
            'table' => ($detail['table']['t_name'] ?? null),
            'salon' => ($detail['table']['t_salon'] ?? null),
            'client' => ($detail['client']['c_name'] ?? null),
            'items_count' => is_array($detail['items'] ?? null) ? count($detail['items']) : null,
        ]);

        if (!$printerIp) {
            Log::error('printer_ip vacío en pre-cuenta');
            return response()->json(['status' => 'error', 'message' => 'printer_ip vacío'], 400);
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

        $payload = PrintService::buildEscposPayload($lines, 4, true);
        
        $metadata = [
            'table' => $detail['table']['t_name'] ?? null,
            'salon' => $detail['table']['t_salon'] ?? null,
            'client' => $detail['client']['c_name'] ?? null,
            'total' => $total,
            'items_count' => is_array($detail['items'] ?? null) ? count($detail['items']) : null,
        ];

        $ok = $this->printRawWithRetry('PreAccount', (string) $printerIp, $port, $payload, $metadata);
        
        if ($ok) {
            return response()->json(['status' => 'ok']);
        } else {
            return response()->json(['status' => 'error', 'message' => 'Impresión falló después de reintentos'], 500);
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
