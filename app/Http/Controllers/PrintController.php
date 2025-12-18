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
        } catch (\Throwable $e) {
            Log::warning('Error cerrando ticketer', ['error' => $e->getMessage()]);
        }
    }

    private function command($data)
    {
        $allOk = collect($data['details'])
            ->map(fn($detail) => $this->printCocinaDetail($this->createNetworkTicketer($detail['printer'],$data['created_at']), $detail))
            ->every(fn($ok) => $ok);

        if ($allOk) {
            return response()->json(['status' => 'ok']);
        } else {
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function printCocinaDetail(Ticketer $ticketer, array $detail): bool
    {
        try {
            $ticketer->setCliente($detail['client']['c_name']);
            $ticketer->setAmbiente($detail['table']['t_name'].' - '.$detail['table']['t_salon']);
            $ticketer->setMozo($detail['waiter']['u_name']);

            foreach ($detail['items'] as $item) {
                $ticketer->addItem($item['i_name'], $item['i_quantity'], null, false, false, false);
            }

            return (bool) $ticketer->printCocina();
        } catch (\Throwable $e) {
            Log::error('Error imprimiendo comanda de cocina', [
                'error' => $e->getMessage(),
                'printer' => $detail['printer']['pr_ip'] ?? null,
            ]);
            return false;
        } finally {
            $this->safeCloseTicketer($ticketer);
        }
    }

    private function preAccount($data)
    {
        $detail = $data['details'];
        $ticketer = $this->createNetworkTicketer($detail['printer']);
        try {
            $ticketer->setFechaEmision($detail['order']['issue_date']);
            $ticketer->setCliente($detail['client']['c_name']);
            $ticketer->setAmbiente($detail['table']['t_name'].' - '.$detail['table']['t_salon']);
            foreach ($detail['items'] as $item) {
                $ticketer->addItem($item['i_name'], $item['i_quantity'], $item['i_price'], false, $item['i_free']);
            }

            $ticketer->setMozo($detail['waiter']['u_name']);

            return $ticketer->printAvance()
                ? response()->json(['status' => 'ok'])
                : response()->json(['status' => 'error'], 500);
        } catch (\Throwable $e) {
            Log::error('Error imprimiendo pre-cuenta', [
                'error' => $e->getMessage(),
                'printer' => $detail['printer']['pr_ip'] ?? null,
            ]);
            return response()->json(['status' => 'error'], 500);
        } finally {
            $this->safeCloseTicketer($ticketer);
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
