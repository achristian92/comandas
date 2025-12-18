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

        $ticketer = null;
        try {
            $ticketer = $this->createNetworkTicketer($detail['printer'] ?? null, $issueDate);
            $ticketer->setCliente($detail['client']['c_name']);
            $ticketer->setAmbiente($detail['table']['t_name'].' - '.$detail['table']['t_salon']);
            $ticketer->setMozo($detail['waiter']['u_name']);

            foreach ($detail['items'] as $item) {
                $ticketer->addItem($item['i_name'], $item['i_quantity'], null, false, false, false);
            }

            $ok = (bool) $ticketer->printCocina();
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
        } finally {
            if ($ticketer instanceof Ticketer) {
                $this->safeCloseTicketer($ticketer);
            }
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

        $ticketer = null;
        try {
            $ticketer = $this->createNetworkTicketer($detail['printer']);
            $ticketer->setFechaEmision($detail['order']['issue_date']);
            $ticketer->setCliente($detail['client']['c_name']);
            $ticketer->setAmbiente($detail['table']['t_name'].' - '.$detail['table']['t_salon']);
            foreach ($detail['items'] as $item) {
                $ticketer->addItem($item['i_name'], $item['i_quantity'], $item['i_price'], false, $item['i_free']);
            }

            $ticketer->setMozo($detail['waiter']['u_name']);

            $ok = (bool) $ticketer->printAvance();
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
        } finally {
            if ($ticketer instanceof Ticketer) {
                $this->safeCloseTicketer($ticketer);
            }
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
